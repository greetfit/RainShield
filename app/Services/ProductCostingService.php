<?php

namespace App\Services;

use App\Models\PartStockBalance;
use App\Models\PieceRate;
use App\Models\ProductVariant;

class ProductCostingService
{
    public function rows()
    {
        $avgPartCost = PartStockBalance::query()
            ->get(['product_variant_id', 'part_id', 'average_cost'])
            ->groupBy('product_variant_id')
            ->map(fn ($rows) => $rows->pluck('average_cost', 'part_id'));

        return ProductVariant::query()
            ->whereHas('recipeParts')
            ->with(['product:id,name', 'recipeParts'])
            ->get()
            ->map(fn (ProductVariant $variant) => $this->costVariant($variant, $avgPartCost));
    }

    public function costVariant(ProductVariant $variant, $avgPartCost = null): array
    {
        $variant->loadMissing(['product:id,name', 'recipeParts']);

        if ($avgPartCost === null) {
            $avgPartCost = PartStockBalance::query()
                ->where('product_variant_id', $variant->id)
                ->pluck('average_cost', 'part_id');
        }

        $partCost = function (int $partId) use ($variant, $avgPartCost): float {
            $variantCosts = $avgPartCost[$variant->id] ?? null;

            if (is_iterable($variantCosts) && isset($variantCosts[$partId])) {
                return (float) $variantCosts[$partId];
            }

            return (float) ($avgPartCost[$partId] ?? 0);
        };

        $materialCost = $variant->recipeParts->sum(
            fn ($part) => (float) $part->quantity_per_garment * $partCost((int) $part->part_id),
        );

        $stageCodes = \App\Models\ProductionStage::options()
            ->reject(fn (array $stage) => $stage['value'] === 'cutting')
            ->pluck('value');

        $laborByStage = [];
        $laborCost = 0;
        foreach ($stageCodes as $stage) {
            $rate = PieceRate::estimateForCosting($stage, $variant->id);
            $laborByStage[$stage] = $rate;
            $laborCost += $rate;
        }

        $unitCost = round($materialCost + $laborCost, 2);
        $markupType = $variant->profit_markup_type ?? 'percent';
        $markupPercent = (float) ($variant->profit_margin_percent ?? 0);
        $markupAmount = (float) ($variant->profit_markup_amount ?? 0);
        $computedSellingPrice = match ($markupType) {
            'flat' => $markupAmount > 0 ? round($unitCost + $markupAmount, 2) : round((float) ($variant->selling_price ?? 0), 2),
            default => $markupPercent > 0 ? round($unitCost * (1 + ($markupPercent / 100)), 2) : round((float) ($variant->selling_price ?? 0), 2),
        };

        return [
            'id' => $variant->id,
            'label' => $variant->product->name.' - '.$variant->name,
            'material_cost' => round($materialCost, 2),
            'labor' => $laborByStage,
            'labor_cost' => round($laborCost, 2),
            'unit_cost' => $unitCost,
            'profit_markup_type' => $markupType,
            'profit_margin_percent' => $markupPercent,
            'profit_markup_amount' => $markupAmount,
            'selling_price' => $computedSellingPrice,
        ];
    }
}
