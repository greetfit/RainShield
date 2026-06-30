<?php

namespace App\Services;

use App\Models\StockBalance;
use App\Models\StockMovement;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class StockService
{
    /**
     * Receive stock at a known landed unit cost and recompute the weighted average.
     */
    public function receive(
        int $rawMaterialVariantId,
        float $quantity,
        float $unitCost,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?string $note = null,
    ): StockMovement {
        return DB::transaction(function () use ($rawMaterialVariantId, $quantity, $unitCost, $referenceType, $referenceId, $note) {
            $balance = $this->lockBalance($rawMaterialVariantId);

            $oldQty = (float) $balance->quantity;
            $oldValue = $oldQty * (float) $balance->average_cost;
            $newQty = $oldQty + $quantity;

            // Weighted-average: blend old value with incoming value.
            $newAvg = $newQty > 0
                ? ($oldValue + $quantity * $unitCost) / $newQty
                : 0;

            $balance->quantity = $newQty;
            $balance->average_cost = $newAvg;
            $balance->save();

            return $this->writeMovement($rawMaterialVariantId, 'in', $quantity, $unitCost, $balance, $referenceType, $referenceId, $note);
        });
    }

    /**
     * Issue stock out at the current average cost. Throws if insufficient.
     */
    public function issue(
        int $rawMaterialVariantId,
        float $quantity,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?string $note = null,
    ): StockMovement {
        return DB::transaction(function () use ($rawMaterialVariantId, $quantity, $referenceType, $referenceId, $note) {
            $balance = $this->lockBalance($rawMaterialVariantId);

            if ((float) $balance->quantity + 1e-6 < $quantity) {
                throw new RuntimeException("Insufficient stock for material variant #{$rawMaterialVariantId}.");
            }

            $unitCost = (float) $balance->average_cost; // average unchanged on issue
            $balance->quantity = (float) $balance->quantity - $quantity;
            $balance->save();

            return $this->writeMovement($rawMaterialVariantId, 'out', $quantity, $unitCost, $balance, $referenceType, $referenceId, $note);
        });
    }

    /**
     * Quantity available right now (0 if never stocked).
     */
    public function available(int $rawMaterialVariantId): float
    {
        return (float) (StockBalance::where('raw_material_variant_id', $rawMaterialVariantId)->value('quantity') ?? 0);
    }

    /**
     * Set stock to a physical counted quantity while preserving current average cost.
     */
    public function adjust(
        int $rawMaterialVariantId,
        float $countedQuantity,
        ?string $note = null,
    ): StockMovement {
        return DB::transaction(function () use ($rawMaterialVariantId, $countedQuantity, $note) {
            $balance = $this->lockBalance($rawMaterialVariantId);
            $oldQty = (float) $balance->quantity;
            $difference = $countedQuantity - $oldQty;

            $balance->quantity = $countedQuantity;
            $balance->save();

            return $this->writeMovement(
                $rawMaterialVariantId,
                'adjustment',
                abs($difference),
                (float) $balance->average_cost,
                $balance,
                null,
                null,
                $note ?: "Physical count adjustment from {$oldQty} to {$countedQuantity}",
            );
        });
    }

    /**
     * Set the starting balance and cost for a raw material variant.
     */
    public function openingStock(
        int $rawMaterialVariantId,
        float $quantity,
        float $unitCost = 0,
        ?string $note = null,
    ): StockMovement {
        return DB::transaction(function () use ($rawMaterialVariantId, $quantity, $unitCost, $note) {
            $balance = $this->lockBalance($rawMaterialVariantId);

            $balance->quantity = $quantity;
            $balance->average_cost = $quantity > 0 ? $unitCost : 0;
            $balance->save();

            return $this->writeMovement(
                $rawMaterialVariantId,
                'opening',
                $quantity,
                $unitCost,
                $balance,
                null,
                null,
                $note ?: 'Opening stock',
            );
        });
    }

    private function lockBalance(int $rawMaterialVariantId): StockBalance
    {
        return StockBalance::query()
            ->lockForUpdate()
            ->firstOrCreate(
                ['raw_material_variant_id' => $rawMaterialVariantId],
                ['quantity' => 0, 'average_cost' => 0],
            );
    }

    private function writeMovement(
        int $rmvId,
        string $direction,
        float $quantity,
        float $unitCost,
        StockBalance $balance,
        ?string $referenceType,
        ?int $referenceId,
        ?string $note,
    ): StockMovement {
        return StockMovement::create([
            'raw_material_variant_id' => $rmvId,
            'direction' => $direction,
            'quantity' => $quantity,
            'unit_cost' => $unitCost,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'note' => $note,
            'balance_quantity' => $balance->quantity,
            'balance_average_cost' => $balance->average_cost,
            'created_by' => Auth::id(),
        ]);
    }
}
