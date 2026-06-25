<?php

namespace App\Http\Controllers;

use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\RawMaterialVariant;
use App\Models\Supplier;
use App\Services\FinishedGoodsService;
use App\Services\StockService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class PurchaseController extends Controller
{
    private const PURCHASE_STATUSES = ['placed', 'partially_received', 'received', 'cancelled'];

    public function index()
    {
        return Inertia::render('Purchases/Index', [
            'purchases' => Purchase::with([
                    'items.rawMaterialVariant.rawMaterial',
                    'items.productVariant.product',
                    'creator',
                    'payments:id,purchase_id,paid_on,amount,method,reference',
                ])
                ->withCount('items')
                ->withSum('payments', 'amount')
                ->withSum('returns', 'total_amount')
                ->latest('purchased_on')
                ->latest('id')
                ->get(['id', 'reference', 'supplier_name', 'purchased_on', 'status', 'transport_charge', 'allocation_method', 'items_total', 'grand_total', 'notes', 'created_by'])
                ->map(function (Purchase $purchase): array {
                    $paid = (float) ($purchase->payments_sum_amount ?? 0);
                    $returned = (float) ($purchase->returns_sum_total_amount ?? 0);
                    $netTotal = max((float) $purchase->grand_total - $returned, 0);
                    $due = max($netTotal - $paid, 0);

                    return [
                        'id' => $purchase->id,
                        'reference' => $purchase->reference,
                        'supplier_name' => $purchase->supplier_name,
                        'purchased_on' => $purchase->purchased_on->format('d/m/Y'),
                        'purchased_on_input' => $purchase->purchased_on->toDateString(),
                        'status' => $purchase->status,
                        'status_label' => $this->statusLabel($purchase->status),
                        'allocation_method' => $purchase->allocation_method,
                        'items_total' => $purchase->items_total,
                        'items_count' => $purchase->items_count,
                        'transport_charge' => $purchase->transport_charge,
                        'grand_total' => $purchase->grand_total,
                        'returned_total' => $returned,
                        'has_return' => $returned > 0.005,
                        'net_total' => $netTotal,
                        'notes' => $purchase->notes,
                        'created_by' => $purchase->creator?->name,
                        'paid_total' => $paid,
                        'due_amount' => $due,
                        'is_due' => $due > 0.005,
                        'items' => $purchase->items->map(fn ($item): array => [
                            'id' => $item->id,
                            'item_type' => $item->item_type ?? 'raw_material',
                            'raw_material_variant_id' => $item->raw_material_variant_id,
                            'product_variant_id' => $item->product_variant_id,
                            'label' => $this->purchaseItemLabel($item),
                            'quantity' => $item->quantity,
                            'unit_price' => $item->unit_price,
                            'line_total' => $item->line_total,
                            'allocated_transport' => $item->allocated_transport,
                            'landed_unit_cost' => $item->landed_unit_cost,
                        ]),
                        'payments' => $purchase->payments->map(fn ($payment): array => [
                            'id' => $payment->id,
                            'paid_on' => $payment->paid_on->format('d/m/Y'),
                            'amount' => $payment->amount,
                            'method' => $payment->method,
                            'reference' => $payment->reference,
                        ]),
                    ];
                }),
            'materialOptions' => $this->materialOptions(),
            'finishedProductOptions' => $this->finishedProductOptions(),
            'supplierOptions' => $this->supplierOptions(),
            'statusOptions' => $this->statusOptions(),
            'paymentMethods' => PaymentMethod::activeOptions(),
            'today' => now()->toDateString(),
        ]);
    }

    public function create()
    {
        return Inertia::render('Purchases/Create', [
            'materialOptions' => $this->materialOptions(),
            'finishedProductOptions' => $this->finishedProductOptions(),
            'supplierOptions' => $this->supplierOptions(),
            'statusOptions' => $this->statusOptions(),
            'today' => now()->toDateString(),
        ]);
    }

    public function store(Request $request, StockService $stock, FinishedGoodsService $finishedGoods)
    {
        $data = $this->validatePurchase($request);

        DB::transaction(function () use ($data, $stock, $finishedGoods) {
            $lines = collect($data['items'])->map(fn ($i) => [
                'item_type' => $i['item_type'] ?? 'raw_material',
                'raw_material_variant_id' => ($i['item_type'] ?? 'raw_material') === 'raw_material' ? (int) $i['raw_material_variant_id'] : null,
                'product_variant_id' => ($i['item_type'] ?? 'raw_material') === 'finished_good' ? (int) $i['product_variant_id'] : null,
                'quantity' => ($i['item_type'] ?? 'raw_material') === 'finished_good' ? (int) $i['quantity'] : (float) $i['quantity'],
                'unit_price' => (float) $i['unit_price'],
                'line_total' => round((float) $i['quantity'] * (float) $i['unit_price'], 2),
            ]);

            $itemsTotal = round($lines->sum('line_total'), 2);
            $transport = (float) $data['transport_charge'];

            // Choose the basis for spreading transport across lines.
            $byValue = $data['allocation_method'] === 'value' && $itemsTotal > 0;
            $basisTotal = $byValue ? $itemsTotal : $lines->sum('quantity');

            $purchase = Purchase::create([
                'reference' => $data['reference'] ?? null,
                'supplier_name' => $data['supplier_name'] ?? null,
                'purchased_on' => $data['purchased_on'],
                'status' => $data['status'] ?? 'received',
                'transport_charge' => $transport,
                'allocation_method' => $byValue ? 'value' : 'quantity',
                'items_total' => $itemsTotal,
                'grand_total' => round($itemsTotal + $transport, 2),
                'notes' => $data['notes'] ?? null,
                'created_by' => Auth::id(),
            ]);

            foreach ($lines as $line) {
                $basis = $byValue ? $line['line_total'] : $line['quantity'];
                $allocated = $basisTotal > 0 ? round($transport * ($basis / $basisTotal), 2) : 0;
                $landedUnitCost = round(($line['line_total'] + $allocated) / $line['quantity'], 4);

                $purchase->items()->create([
                    'item_type' => $line['item_type'],
                    'raw_material_variant_id' => $line['raw_material_variant_id'],
                    'product_variant_id' => $line['product_variant_id'],
                    'quantity' => $line['quantity'],
                    'received_quantity' => ($purchase->status === 'received') ? $line['quantity'] : 0,
                    'unit_price' => $line['unit_price'],
                    'line_total' => $line['line_total'],
                    'allocated_transport' => $allocated,
                    'landed_unit_cost' => $landedUnitCost,
                ]);

                if ($purchase->status === 'received') {
                    $this->receivePurchasedItem($stock, $finishedGoods, $line, $landedUnitCost, $purchase, 'Purchase '.($data['reference'] ?? '#'.$purchase->id));
                }
            }
        });

        return redirect()->route('purchases.index')->with('success', 'Purchase recorded and stock updated.');
    }

    public function update(Request $request, Purchase $purchase, StockService $stock, FinishedGoodsService $finishedGoods)
    {
        $data = $this->validatePurchase($request);

        DB::transaction(function () use ($data, $purchase, $stock, $finishedGoods) {
            $oldItems = $purchase->items()->get()->keyBy('id');
            $lines = $this->buildLines($data);
            $itemsTotal = round($lines->sum('line_total'), 2);
            $transport = (float) $data['transport_charge'];
            $byValue = $data['allocation_method'] === 'value' && $itemsTotal > 0;
            $basisTotal = $byValue ? $itemsTotal : $lines->sum('quantity');

            $purchase->update([
                'reference' => $data['reference'] ?? null,
                'supplier_name' => $data['supplier_name'] ?? null,
                'purchased_on' => $data['purchased_on'],
                'status' => $data['status'] ?? 'received',
                'transport_charge' => $transport,
                'allocation_method' => $byValue ? 'value' : 'quantity',
                'items_total' => $itemsTotal,
                'grand_total' => round($itemsTotal + $transport, 2),
                'notes' => $data['notes'] ?? null,
            ]);

            $keptIds = [];

            foreach ($lines as $line) {
                $basis = $byValue ? $line['line_total'] : $line['quantity'];
                $allocated = $basisTotal > 0 ? round($transport * ($basis / $basisTotal), 2) : 0;
                $landedUnitCost = round(($line['line_total'] + $allocated) / $line['quantity'], 4);

                $item = !empty($line['id']) ? $oldItems->get((int) $line['id']) : null;

                if ($item) {
                    $keptIds[] = $item->id;
                    $this->syncStockForItemUpdate($stock, $finishedGoods, $item, $line, $landedUnitCost, $purchase);
                    $item->update([
                        'item_type' => $line['item_type'],
                        'raw_material_variant_id' => $line['raw_material_variant_id'],
                        'product_variant_id' => $line['product_variant_id'],
                        'quantity' => $line['quantity'],
                        'unit_price' => $line['unit_price'],
                        'line_total' => $line['line_total'],
                        'allocated_transport' => $allocated,
                        'landed_unit_cost' => $landedUnitCost,
                    ]);
                } else {
                    $newItem = $purchase->items()->create([
                        'item_type' => $line['item_type'],
                        'raw_material_variant_id' => $line['raw_material_variant_id'],
                        'product_variant_id' => $line['product_variant_id'],
                        'quantity' => $line['quantity'],
                        'received_quantity' => $purchase->status === 'received' ? $line['quantity'] : 0,
                        'unit_price' => $line['unit_price'],
                        'line_total' => $line['line_total'],
                        'allocated_transport' => $allocated,
                        'landed_unit_cost' => $landedUnitCost,
                    ]);
                    $keptIds[] = $newItem->id;
                    if ($purchase->status === 'received') {
                        $this->receivePurchasedItem($stock, $finishedGoods, $line, $landedUnitCost, $purchase, 'Purchase edit add '.($purchase->reference ?? '#'.$purchase->id));
                    }
                }
            }

            $oldItems->whereNotIn('id', $keptIds)->each(function ($item) use ($stock, $finishedGoods, $purchase): void {
                $this->reversePurchasedItem($stock, $finishedGoods, $item, $purchase, 'Purchase edit remove '.($purchase->reference ?? '#'.$purchase->id), (float) $item->received_quantity);
                $item->delete();
            });
        });

        return back()->with('success', 'Purchase updated.');
    }

    public function updateStatus(Request $request, Purchase $purchase)
    {
        $data = $request->validate([
            'status' => ['required', 'in:'.implode(',', self::PURCHASE_STATUSES)],
        ]);

        DB::transaction(function () use ($purchase, $data): void {
            $stock = app(StockService::class);
            $finishedGoods = app(FinishedGoodsService::class);
            $purchase->load('items');

            if ($data['status'] === 'received') {
                foreach ($purchase->items as $item) {
                    $remaining = (float) $item->quantity - (float) $item->received_quantity;
                    if ($remaining <= 0) {
                        continue;
                    }

                    $line = [
                        'item_type' => $item->item_type ?? 'raw_material',
                        'raw_material_variant_id' => $item->raw_material_variant_id,
                        'product_variant_id' => $item->product_variant_id,
                        'quantity' => $remaining,
                    ];
                    $this->receivePurchasedItem($stock, $finishedGoods, $line, (float) $item->landed_unit_cost, $purchase, 'Purchase received '.($purchase->reference ?? '#'.$purchase->id));
                    $item->increment('received_quantity', $remaining);
                }
            }

            if ($data['status'] === 'cancelled') {
                foreach ($purchase->items as $item) {
                    if ((float) $item->received_quantity <= 0) {
                        continue;
                    }

                    $this->reversePurchasedItem($stock, $finishedGoods, $item, $purchase, 'Purchase cancelled '.($purchase->reference ?? '#'.$purchase->id), (float) $item->received_quantity);
                    $item->update(['received_quantity' => 0]);
                }
            }

            $purchase->update(['status' => $data['status']]);
        });

        return back()->with('success', 'Purchase status updated.');
    }

    public function show(Purchase $purchase)
    {
        $purchase->load('items.rawMaterialVariant.rawMaterial', 'items.productVariant.product', 'creator');

        return Inertia::render('Purchases/Show', [
            'purchase' => [
                'id' => $purchase->id,
                'reference' => $purchase->reference,
                'supplier_name' => $purchase->supplier_name,
                'purchased_on' => $purchase->purchased_on->format('d/m/Y'),
                'status' => $purchase->status,
                'status_label' => $this->statusLabel($purchase->status),
                'transport_charge' => $purchase->transport_charge,
                'allocation_method' => $purchase->allocation_method,
                'items_total' => $purchase->items_total,
                'grand_total' => $purchase->grand_total,
                'notes' => $purchase->notes,
                'created_by' => $purchase->creator?->name,
                'items' => $purchase->items->map(fn ($i) => [
                    'label' => $this->purchaseItemLabel($i),
                    'item_type' => $i->item_type ?? 'raw_material',
                    'quantity' => $i->quantity,
                    'unit_price' => $i->unit_price,
                    'line_total' => $i->line_total,
                    'allocated_transport' => $i->allocated_transport,
                    'landed_unit_cost' => $i->landed_unit_cost,
                ]),
            ],
        ]);
    }

    private function materialOptions()
    {
        return RawMaterialVariant::query()
            ->where('is_active', true)
            ->with('rawMaterial:id,name,unit')
            ->get()
            ->map(fn ($v) => [
                'id' => $v->id,
                'label' => $v->rawMaterial->name.' — '.$v->name,
                'unit' => $v->rawMaterial->unit,
            ])
            ->sortBy('label')
            ->values();
    }

    private function finishedProductOptions()
    {
        return ProductVariant::query()
            ->where('is_active', true)
            ->whereHas('product', fn ($query) => $query->whereIn('source_type', [Product::SOURCE_OUTSOURCED, Product::SOURCE_BOTH]))
            ->with('product:id,name,source_type')
            ->get(['id', 'product_id', 'name', 'sku'])
            ->map(fn (ProductVariant $variant) => [
                'id' => $variant->id,
                'label' => $variant->product->name.' - '.$variant->name,
                'unit' => 'piece',
                'description' => collect([$variant->product->sourceLabel(), $variant->sku ? 'SKU '.$variant->sku : null])->filter()->join(' / '),
            ])
            ->sortBy('label')
            ->values();
    }

    private function purchaseItemLabel(PurchaseItem $item): string
    {
        if (($item->item_type ?? 'raw_material') === 'finished_good') {
            return $item->productVariant
                ? $item->productVariant->product->name.' - '.$item->productVariant->name
                : 'Finished product #'.$item->product_variant_id;
        }

        return $item->rawMaterialVariant
            ? $item->rawMaterialVariant->rawMaterial->name.' - '.$item->rawMaterialVariant->name
            : 'Raw material #'.$item->raw_material_variant_id;
    }

    private function validatePurchase(Request $request): array
    {
        $data = $request->validate([
            'reference' => ['nullable', 'string', 'max:255'],
            'supplier_name' => ['nullable', 'string', 'max:255'],
            'purchased_on' => ['required', 'date'],
            'status' => ['nullable', 'in:'.implode(',', self::PURCHASE_STATUSES)],
            'transport_charge' => ['required', 'numeric', 'min:0'],
            'allocation_method' => ['required', 'in:value,quantity'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['nullable', 'integer', 'exists:purchase_items,id'],
            'items.*.item_type' => ['nullable', 'in:raw_material,finished_good'],
            'items.*.raw_material_variant_id' => ['nullable', 'exists:raw_material_variants,id'],
            'items.*.product_variant_id' => ['nullable', 'exists:product_variants,id'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.001'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
        ]);

        foreach ($data['items'] as $index => $item) {
            $type = $item['item_type'] ?? 'raw_material';
            if ($type === 'raw_material' && empty($item['raw_material_variant_id'])) {
                throw ValidationException::withMessages(["items.{$index}.raw_material_variant_id" => 'Select a raw material.']);
            }

            if ($type === 'finished_good' && empty($item['product_variant_id'])) {
                throw ValidationException::withMessages(["items.{$index}.product_variant_id" => 'Select a finished product.']);
            }

            if ($type === 'finished_good' && (int) $item['quantity'] != (float) $item['quantity']) {
                throw ValidationException::withMessages(["items.{$index}.quantity" => 'Finished product quantity must be a whole number.']);
            }
        }

        return $data;
    }

    private function buildLines(array $data)
    {
        return collect($data['items'])->map(fn ($item) => [
            'id' => $item['id'] ?? null,
            'item_type' => $item['item_type'] ?? 'raw_material',
            'raw_material_variant_id' => ($item['item_type'] ?? 'raw_material') === 'raw_material' ? (int) $item['raw_material_variant_id'] : null,
            'product_variant_id' => ($item['item_type'] ?? 'raw_material') === 'finished_good' ? (int) $item['product_variant_id'] : null,
            'quantity' => ($item['item_type'] ?? 'raw_material') === 'finished_good' ? (int) $item['quantity'] : (float) $item['quantity'],
            'unit_price' => (float) $item['unit_price'],
            'line_total' => round((float) $item['quantity'] * (float) $item['unit_price'], 2),
        ]);
    }

    private function syncStockForItemUpdate(StockService $stock, FinishedGoodsService $finishedGoods, $item, array $line, float $landedUnitCost, Purchase $purchase): void
    {
        $oldReceived = (float) ($item->received_quantity ?? 0);
        $targetReceived = $purchase->status === 'received' ? (float) $line['quantity'] : min($oldReceived, (float) $line['quantity']);

        if (($item->item_type ?? 'raw_material') !== $line['item_type']) {
            $this->reversePurchasedItem($stock, $finishedGoods, $item, $purchase, 'Purchase edit remove old item '.($purchase->reference ?? '#'.$purchase->id), $oldReceived);
            if ($targetReceived > 0) {
                $receiveLine = [...$line, 'quantity' => $targetReceived];
                $this->receivePurchasedItem($stock, $finishedGoods, $receiveLine, $landedUnitCost, $purchase, 'Purchase edit receive new item '.($purchase->reference ?? '#'.$purchase->id));
            }
            $item->received_quantity = $targetReceived;

            return;
        }

        if ($line['item_type'] === 'finished_good') {
            $oldVariantId = (int) $item->product_variant_id;
            $newVariantId = (int) $line['product_variant_id'];
            $note = 'Purchase edit '.($purchase->reference ?? '#'.$purchase->id);

            if ($oldVariantId !== $newVariantId) {
                if ($oldReceived > 0) {
                    $finishedGoods->remove($oldVariantId, (int) $oldReceived, Purchase::class, $purchase->id, $note.' remove old finished product');
                }
                if ($targetReceived > 0) {
                    $finishedGoods->add($newVariantId, (int) $targetReceived, Purchase::class, $purchase->id, $note.' receive new finished product', $landedUnitCost);
                }
                $item->received_quantity = $targetReceived;

                return;
            }

            $difference = $targetReceived - $oldReceived;
            if ($difference > 0) {
                $finishedGoods->add($newVariantId, (int) $difference, Purchase::class, $purchase->id, $note.' quantity increase', $landedUnitCost);
            } elseif ($difference < 0) {
                $finishedGoods->remove($newVariantId, (int) abs($difference), Purchase::class, $purchase->id, $note.' quantity decrease');
            }
            $item->received_quantity = $targetReceived;

            return;
        }

        $oldVariantId = (int) $item->raw_material_variant_id;
        $newVariantId = (int) $line['raw_material_variant_id'];
        $note = 'Purchase edit '.($purchase->reference ?? '#'.$purchase->id);

        if ($oldVariantId !== $newVariantId) {
            if ($oldReceived > 0) {
                $stock->issue($oldVariantId, $oldReceived, Purchase::class, $purchase->id, $note.' remove old material');
            }
            if ($targetReceived > 0) {
                $stock->receive($newVariantId, $targetReceived, $landedUnitCost, Purchase::class, $purchase->id, $note.' receive new material');
            }
            $item->received_quantity = $targetReceived;

            return;
        }

        $difference = $targetReceived - $oldReceived;
        if ($difference > 1e-6) {
            $stock->receive($newVariantId, $difference, $landedUnitCost, Purchase::class, $purchase->id, $note.' quantity increase');
        } elseif ($difference < -1e-6) {
            $stock->issue($newVariantId, abs($difference), Purchase::class, $purchase->id, $note.' quantity decrease');
        }
        $item->received_quantity = $targetReceived;
    }

    private function receivePurchasedItem(StockService $stock, FinishedGoodsService $finishedGoods, array $line, float $landedUnitCost, Purchase $purchase, string $note): void
    {
        if ($line['item_type'] === 'finished_good') {
            $finishedGoods->add((int) $line['product_variant_id'], (int) $line['quantity'], Purchase::class, $purchase->id, $note, $landedUnitCost);

            return;
        }

        $stock->receive((int) $line['raw_material_variant_id'], (float) $line['quantity'], $landedUnitCost, Purchase::class, $purchase->id, $note);
    }

    private function reversePurchasedItem(StockService $stock, FinishedGoodsService $finishedGoods, PurchaseItem $item, Purchase $purchase, string $note, ?float $quantity = null): void
    {
        $quantity ??= (float) $item->quantity;
        if ($quantity <= 0) {
            return;
        }

        if (($item->item_type ?? 'raw_material') === 'finished_good') {
            $finishedGoods->remove((int) $item->product_variant_id, (int) $quantity, Purchase::class, $purchase->id, $note);

            return;
        }

        $stock->issue((int) $item->raw_material_variant_id, $quantity, Purchase::class, $purchase->id, $note);
    }

    private function supplierOptions()
    {
        return Supplier::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'phone', 'email'])
            ->map(fn (Supplier $supplier): array => [
                'value' => $supplier->name,
                'label' => $supplier->name,
                'description' => collect([$this->formatPhone($supplier->phone), $supplier->email])
                    ->filter()
                    ->join(' | '),
            ]);
    }

    private function statusOptions(): array
    {
        return [
            ['value' => 'placed', 'label' => 'Placed'],
            ['value' => 'partially_received', 'label' => 'Partially received'],
            ['value' => 'received', 'label' => 'Received'],
            ['value' => 'cancelled', 'label' => 'Cancelled'],
        ];
    }

    private function statusLabel(?string $status): string
    {
        return collect($this->statusOptions())->firstWhere('value', $status)['label'] ?? 'Received';
    }

    private function formatPhone(?string $phone): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $phone);

        if (strlen($digits) !== 10) {
            return $phone;
        }

        return substr($digits, 0, 3).' '.substr($digits, 3, 3).' '.substr($digits, 6);
    }
}
