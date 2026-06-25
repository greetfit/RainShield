<?php

namespace App\Http\Controllers;

use App\Models\PaymentMethod;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SaleReturn;
use App\Services\FinishedGoodsService;
use App\Services\SaleService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class SaleReturnController extends Controller
{
    public function index()
    {
        return Inertia::render('Sales/Returns', [
            'returns' => SaleReturn::query()
                ->with('sale.customer')
                ->withCount('items')
                ->latest('returned_on')
                ->latest('id')
                ->get()
                ->map(fn (SaleReturn $return) => [
                    'id' => $return->id,
                    'return_no' => $return->return_no,
                    'invoice_no' => $return->sale?->invoice_no,
                    'customer' => $return->sale?->customer?->name ?? 'Walk-in customer',
                    'returned_on' => $return->returned_on?->format('d/m/Y'),
                    'items_count' => $return->items_count,
                    'total_amount' => (float) $return->total_amount,
                    'notes' => $return->notes,
                ]),
        ]);
    }

    public function create(Sale $sale)
    {
        $sale->load('customer', 'items.productVariant.product', 'items.saleReturnItems');

        return Inertia::render('Sales/ReturnCreate', [
            'sale' => [
                'id' => $sale->id,
                'invoice_no' => $sale->invoice_no,
                'customer' => $sale->customer?->name ?? 'Walk-in customer',
                'sold_at' => $sale->sold_at?->format('d/m/Y'),
                'items' => $sale->items->map(fn (SaleItem $item) => [
                    'id' => $item->id,
                    'label' => $item->productVariant->product->name.' - '.$item->productVariant->name,
                    'sold_quantity' => (int) $item->quantity,
                    'returned_quantity' => (int) $item->saleReturnItems->sum('quantity'),
                    'available_to_return' => max(0, (int) $item->quantity - (int) $item->saleReturnItems->sum('quantity')),
                    'unit_price' => (float) $item->unit_price,
                ]),
            ],
        ]);
    }

    public function store(Request $request, Sale $sale, FinishedGoodsService $finishedGoods, SaleService $saleService)
    {
        $data = $request->validate([
            'returned_on' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.sale_item_id' => ['required', 'exists:sale_items,id'],
            'items.*.quantity' => ['required', 'integer', 'min:0'],
        ]);

        DB::transaction(function () use ($sale, $data, $finishedGoods, $saleService): void {
            $sale->load('items.saleReturnItems');
            $items = collect($data['items'])
                ->map(function (array $row) use ($sale) {
                    $saleItem = $sale->items->firstWhere('id', (int) $row['sale_item_id']);
                    if (! $saleItem) {
                        throw ValidationException::withMessages(['items' => 'Return item does not belong to this sale.']);
                    }

                    $alreadyReturned = (int) $saleItem->saleReturnItems->sum('quantity');
                    $available = max(0, (int) $saleItem->quantity - $alreadyReturned);
                    $quantity = (int) $row['quantity'];
                    if ($quantity > $available) {
                        throw ValidationException::withMessages(['items' => "Only {$available} piece(s) can be returned for this line."]);
                    }

                    return compact('saleItem', 'quantity');
                })
                ->filter(fn (array $row) => $row['quantity'] > 0)
                ->values();

            if ($items->isEmpty()) {
                throw ValidationException::withMessages(['items' => 'Enter at least one return quantity.']);
            }

            $return = $sale->returns()->create([
                'return_no' => $this->nextReturnNo(),
                'returned_on' => $data['returned_on'],
                'notes' => $data['notes'] ?? null,
                'created_by' => Auth::id(),
            ]);

            $total = 0;
            foreach ($items as $row) {
                /** @var SaleItem $saleItem */
                $saleItem = $row['saleItem'];
                $quantity = $row['quantity'];
                $lineTotal = round($quantity * (float) $saleItem->unit_price, 2);
                $total += $lineTotal;

                $return->items()->create([
                    'sale_item_id' => $saleItem->id,
                    'product_variant_id' => $saleItem->product_variant_id,
                    'quantity' => $quantity,
                    'unit_price' => $saleItem->unit_price,
                    'line_total' => $lineTotal,
                ]);

                $finishedGoods->add($saleItem->product_variant_id, $quantity, SaleReturn::class, $return->id, 'Sales return '.$return->return_no);
            }

            $return->update(['total_amount' => round($total, 2)]);
            $saleService->syncPaymentStatus($sale->refresh());
        });

        return redirect()->route('sales.index')->with('success', 'Sales return recorded and stock restored.');
    }

    private function nextReturnNo(): string
    {
        $id = ((int) SaleReturn::query()->max('id')) + 1;

        do {
            $number = 'SR-'.str_pad((string) $id, 5, '0', STR_PAD_LEFT);
            $id++;
        } while (SaleReturn::where('return_no', $number)->exists());

        return $number;
    }
}
