<?php

namespace App\Http\Controllers;

use App\Models\Part;
use App\Models\PartStockBalance;
use App\Models\PartStockMovement;
use App\Models\ProductVariant;
use App\Models\RecoverablePartBalance;
use App\Services\PartStockService;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Model;
use Inertia\Inertia;

class PartStockController extends Controller
{
    public function index()
    {
        return Inertia::render('PartStock/Index', [
            'rows' => $this->stockRows(),
            'partOptions' => Part::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'variantOptions' => $this->variantOptions(),
        ]);
    }

    public function adjust(Request $request, PartStockService $partStock)
    {
        $data = $request->validate([
            'product_variant_id' => ['required', 'exists:product_variants,id'],
            'part_id' => ['required', 'exists:parts,id'],
            'stock_type' => ['required', 'in:good,recoverable'],
            'counted_quantity' => ['required', 'integer', 'min:0'],
            'alert_quantity' => ['nullable', 'integer', 'min:0'],
            'note' => ['nullable', 'string'],
        ]);

        if ($data['stock_type'] === 'recoverable') {
            $partStock->adjustRecoverable((int) $data['product_variant_id'], (int) $data['part_id'], (int) $data['counted_quantity'], $data['note'] ?? null);
        } else {
            $partStock->adjustGood((int) $data['product_variant_id'], (int) $data['part_id'], (int) $data['counted_quantity'], $data['note'] ?? null);
        }

        $this->setAlertQuantity(
            (int) $data['product_variant_id'],
            (int) $data['part_id'],
            $data['stock_type'],
            (int) ($data['alert_quantity'] ?? 0),
        );

        return back()->with('success', 'Part stock adjusted.');
    }

    public function opening(Request $request, PartStockService $partStock)
    {
        $data = $request->validate([
            'product_variant_id' => ['required', 'exists:product_variants,id'],
            'part_id' => ['required', 'exists:parts,id'],
            'stock_type' => ['required', 'in:good,recoverable'],
            'quantity' => ['required', 'integer', 'min:0'],
            'unit_cost' => ['nullable', 'numeric', 'min:0'],
            'alert_quantity' => ['nullable', 'integer', 'min:0'],
            'note' => ['nullable', 'string'],
        ]);

        $partStock->openingStock(
            (int) $data['product_variant_id'],
            (int) $data['part_id'],
            $data['stock_type'],
            (int) $data['quantity'],
            (float) ($data['unit_cost'] ?? 0),
            (int) ($data['alert_quantity'] ?? 0),
            $data['note'] ?? null,
        );

        return back()->with('success', 'Part opening stock saved.');
    }

    public function movements()
    {
        return Inertia::render('PartStock/Movements', [
            'movements' => PartStockMovement::query()
                ->with(['productVariant.product', 'part'])
                ->latest()
                ->limit(500)
                ->get()
                ->map(fn (PartStockMovement $movement) => [
                    'id' => $movement->id,
                    'date' => $movement->created_at?->format('d/m/Y H:i'),
                    'item' => $this->variantLabel($movement->productVariant).' - '.$movement->part->name,
                    'stock_type' => ucfirst($movement->stock_type),
                    'direction' => ucfirst($movement->direction),
                    'quantity' => $movement->quantity,
                    'unit_cost' => round((float) $movement->unit_cost, 4),
                    'balance_quantity' => $movement->balance_quantity,
                    'balance_average_cost' => round((float) $movement->balance_average_cost, 4),
                    'note' => $movement->note,
                ]),
        ]);
    }

    private function stockRows()
    {
        $good = PartStockBalance::with(['productVariant.product', 'part'])
            ->get()
            ->map(fn (PartStockBalance $balance) => [
                'key' => 'good-'.$balance->product_variant_id.'-'.$balance->part_id,
                'product_variant_id' => $balance->product_variant_id,
                'part_id' => $balance->part_id,
                'label' => $this->variantLabel($balance->productVariant),
                'part' => $balance->part->name,
                'stock_type' => 'good',
                'stock_type_label' => 'Good',
                'quantity' => $balance->quantity,
                'average_cost' => round((float) $balance->average_cost, 4),
                'value' => round((float) $balance->quantity * (float) $balance->average_cost, 2),
                'alert_quantity' => $balance->alert_quantity,
            ]);

        $recoverable = RecoverablePartBalance::with(['productVariant.product', 'part'])
            ->get()
            ->map(fn (RecoverablePartBalance $balance) => [
                'key' => 'recoverable-'.$balance->product_variant_id.'-'.$balance->part_id,
                'product_variant_id' => $balance->product_variant_id,
                'part_id' => $balance->part_id,
                'label' => $this->variantLabel($balance->productVariant),
                'part' => $balance->part->name,
                'stock_type' => 'recoverable',
                'stock_type_label' => 'Recoverable',
                'quantity' => $balance->quantity,
                'average_cost' => round((float) $balance->average_cost, 4),
                'value' => round((float) $balance->quantity * (float) $balance->average_cost, 2),
                'alert_quantity' => $balance->alert_quantity,
            ]);

        return $good->merge($recoverable)
            ->sortBy([['label', 'asc'], ['part', 'asc'], ['stock_type', 'asc']])
            ->values();
    }

    private function variantOptions()
    {
        return ProductVariant::query()
            ->with('product:id,name')
            ->where('is_active', true)
            ->get()
            ->map(fn (ProductVariant $variant) => [
                'id' => $variant->id,
                'label' => $this->variantLabel($variant),
            ])
            ->sortBy('label')
            ->values();
    }

    private function variantLabel(ProductVariant $variant): string
    {
        return $variant->product->name.' - '.$variant->name;
    }

    private function setAlertQuantity(int $productVariantId, int $partId, string $stockType, int $alertQuantity): Model
    {
        $model = $stockType === 'recoverable'
            ? RecoverablePartBalance::class
            : PartStockBalance::class;

        return $model::query()->updateOrCreate(
            ['product_variant_id' => $productVariantId, 'part_id' => $partId],
            ['alert_quantity' => $alertQuantity],
        );
    }
}
