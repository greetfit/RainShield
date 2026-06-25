<?php

namespace App\Http\Controllers;

use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\PurchaseReturn;
use App\Services\FinishedGoodsService;
use App\Services\StockService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class PurchaseReturnController extends Controller
{
    public function index()
    {
        return Inertia::render('PurchaseReturns/Index', [
            'returns' => PurchaseReturn::with('purchase:id,reference,supplier_name')
                ->withCount('items')
                ->latest('returned_on')
                ->latest('id')
                ->get()
                ->map(fn (PurchaseReturn $return): array => [
                    'id' => $return->id,
                    'return_no' => $return->return_no,
                    'returned_on' => $return->returned_on->format('d/m/Y'),
                    'purchase_reference' => $return->purchase?->reference,
                    'supplier_name' => $return->purchase?->supplier_name,
                    'items_count' => $return->items_count,
                    'total_amount' => $return->total_amount,
                    'status' => $return->status,
                ]),
        ]);
    }

    public function create(Purchase $purchase)
    {
        $purchase->load('items.rawMaterialVariant.rawMaterial', 'items.productVariant.product', 'items.returnItems');

        return Inertia::render('PurchaseReturns/Create', [
            'purchase' => [
                'id' => $purchase->id,
                'reference' => $purchase->reference,
                'supplier_name' => $purchase->supplier_name,
                'purchased_on' => $purchase->purchased_on->format('d/m/Y'),
                'items' => $purchase->items->map(function (PurchaseItem $item): array {
                    $returned = (float) $item->returnItems->sum('quantity');
                    $available = max((float) $item->quantity - $returned, 0);

                    return [
                        'id' => $item->id,
                        'item_type' => $item->item_type ?? 'raw_material',
                        'label' => $this->itemLabel($item),
                        'raw_material_variant_id' => $item->raw_material_variant_id,
                        'product_variant_id' => $item->product_variant_id,
                        'purchased_quantity' => $item->quantity,
                        'returned_quantity' => $returned,
                        'returnable_quantity' => $available,
                        'landed_unit_cost' => $item->landed_unit_cost,
                    ];
                })->filter(fn (array $item): bool => $item['returnable_quantity'] > 0)->values(),
            ],
            'today' => now()->toDateString(),
        ]);
    }

    public function store(Request $request, Purchase $purchase, StockService $stock, FinishedGoodsService $finishedGoods)
    {
        $data = $request->validate([
            'return_no' => ['nullable', 'string', 'max:255'],
            'returned_on' => ['required', 'date'],
            'reason' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.purchase_item_id' => ['required', 'exists:purchase_items,id'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.001'],
        ]);

        DB::transaction(function () use ($data, $purchase, $stock, $finishedGoods): void {
            $return = $purchase->returns()->create([
                'return_no' => $data['return_no'] ?? null,
                'returned_on' => $data['returned_on'],
                'status' => 'completed',
                'reason' => $data['reason'] ?? null,
                'total_amount' => 0,
                'created_by' => Auth::id(),
            ]);

            $total = 0;

            foreach ($data['items'] as $line) {
                $item = $purchase->items()
                    ->withSum('returnItems', 'quantity')
                    ->whereKey($line['purchase_item_id'])
                    ->firstOrFail();

                $alreadyReturned = (float) ($item->return_items_sum_quantity ?? 0);
                $returnable = (float) $item->quantity - $alreadyReturned;
                $quantity = (float) $line['quantity'];

                if ($quantity > $returnable + 1e-6) {
                    throw ValidationException::withMessages([
                        'items' => 'Return quantity cannot exceed purchased quantity.',
                    ]);
                }

                $lineTotal = round($quantity * (float) $item->landed_unit_cost, 2);
                $return->items()->create([
                    'purchase_item_id' => $item->id,
                    'item_type' => $item->item_type ?? 'raw_material',
                    'raw_material_variant_id' => $item->raw_material_variant_id,
                    'product_variant_id' => $item->product_variant_id,
                    'quantity' => $quantity,
                    'unit_cost' => $item->landed_unit_cost,
                    'line_total' => $lineTotal,
                ]);

                $note = 'Purchase return '.($data['return_no'] ?? '#'.$return->id);
                if (($item->item_type ?? 'raw_material') === 'finished_good') {
                    $finishedGoods->remove(
                        (int) $item->product_variant_id,
                        (int) $quantity,
                        PurchaseReturn::class,
                        $return->id,
                        $note,
                    );
                } else {
                    $stock->issue(
                        $item->raw_material_variant_id,
                        $quantity,
                        PurchaseReturn::class,
                        $return->id,
                        $note,
                    );
                }

                $total += $lineTotal;
            }

            $return->update(['total_amount' => round($total, 2)]);
        });

        return redirect()->route('purchase-returns.index')->with('success', 'Purchase return recorded.');
    }

    public function show(PurchaseReturn $purchaseReturn)
    {
        $purchaseReturn->load('purchase', 'items.purchaseItem.rawMaterialVariant.rawMaterial', 'items.purchaseItem.productVariant.product');

        return Inertia::render('PurchaseReturns/Show', [
            'returnRecord' => [
                'id' => $purchaseReturn->id,
                'return_no' => $purchaseReturn->return_no,
                'returned_on' => $purchaseReturn->returned_on->format('d/m/Y'),
                'supplier_name' => $purchaseReturn->purchase?->supplier_name,
                'purchase_reference' => $purchaseReturn->purchase?->reference,
                'total_amount' => $purchaseReturn->total_amount,
                'reason' => $purchaseReturn->reason,
                'items' => $purchaseReturn->items->map(fn ($item): array => [
                    'label' => $this->itemLabel($item->purchaseItem),
                    'quantity' => $item->quantity,
                    'unit_cost' => $item->unit_cost,
                    'line_total' => $item->line_total,
                ]),
            ],
        ]);
    }

    private function itemLabel(PurchaseItem $item): string
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
}
