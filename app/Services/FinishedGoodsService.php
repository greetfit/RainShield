<?php

namespace App\Services;

use App\Models\FinishedGood;
use App\Models\FinishedGoodMovement;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class FinishedGoodsService
{
    public function add(
        int $productVariantId,
        int $quantity,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?string $note = null,
        ?float $unitCost = null,
    ): FinishedGood
    {
        return DB::transaction(function () use ($productVariantId, $quantity, $referenceType, $referenceId, $note, $unitCost) {
            $fg = FinishedGood::lockForUpdate()->firstOrCreate(
                ['product_variant_id' => $productVariantId],
                ['quantity' => 0, 'average_cost' => 0],
            );

            $movementCost = $unitCost ?? (float) $fg->average_cost;
            $oldQty = (int) $fg->quantity;
            $newQty = $oldQty + $quantity;
            if ($unitCost !== null) {
                $oldValue = $oldQty * (float) $fg->average_cost;
                $fg->average_cost = $newQty > 0 ? ($oldValue + ($quantity * $unitCost)) / $newQty : 0;
            }

            $fg->quantity = $newQty;
            $fg->save();
            $fg->refresh();
            $this->writeMovement($productVariantId, 'in', $quantity, $movementCost, $fg->quantity, $referenceType, $referenceId, $note);

            return $fg;
        });
    }

    public function remove(
        int $productVariantId,
        int $quantity,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?string $note = null,
    ): FinishedGood
    {
        return DB::transaction(function () use ($productVariantId, $quantity, $referenceType, $referenceId, $note) {
            $fg = FinishedGood::lockForUpdate()->firstOrCreate(
                ['product_variant_id' => $productVariantId],
                ['quantity' => 0, 'average_cost' => 0],
            );

            if ($fg->quantity < $quantity) {
                throw new RuntimeException('Insufficient finished-goods stock to dispatch.');
            }

            $fg->decrement('quantity', $quantity);
            $fg->refresh();
            $this->writeMovement($productVariantId, 'out', $quantity, (float) $fg->average_cost, $fg->quantity, $referenceType, $referenceId, $note);

            return $fg;
        });
    }

    public function adjust(int $productVariantId, int $countedQuantity, ?string $note = null, ?int $alertQuantity = null): FinishedGood
    {
        return DB::transaction(function () use ($productVariantId, $countedQuantity, $note, $alertQuantity) {
            $fg = FinishedGood::lockForUpdate()->firstOrCreate(
                ['product_variant_id' => $productVariantId],
                ['quantity' => 0, 'average_cost' => 0, 'alert_quantity' => 0],
            );
            $oldQty = (int) $fg->quantity;
            $difference = $countedQuantity - $oldQty;

            $fg->quantity = $countedQuantity;
            if ($alertQuantity !== null) {
                $fg->alert_quantity = $alertQuantity;
            }
            $fg->save();

            $this->writeMovement(
                $productVariantId,
                'adjustment',
                abs($difference),
                (float) $fg->average_cost,
                $fg->quantity,
                null,
                null,
                $note ?: "Physical count adjustment from {$oldQty} to {$countedQuantity}",
            );

            return $fg->refresh();
        });
    }

    public function openingStock(
        int $productVariantId,
        int $quantity,
        float $unitCost = 0,
        ?int $alertQuantity = null,
        ?string $note = null,
    ): FinishedGood {
        return DB::transaction(function () use ($productVariantId, $quantity, $unitCost, $alertQuantity, $note) {
            $fg = FinishedGood::lockForUpdate()->firstOrCreate(
                ['product_variant_id' => $productVariantId],
                ['quantity' => 0, 'average_cost' => 0, 'alert_quantity' => 0],
            );

            $fg->quantity = $quantity;
            $fg->average_cost = $quantity > 0 ? $unitCost : 0;
            if ($alertQuantity !== null) {
                $fg->alert_quantity = $alertQuantity;
            }
            $fg->save();

            $this->writeMovement(
                $productVariantId,
                'opening',
                $quantity,
                $unitCost,
                $fg->quantity,
                null,
                null,
                $note ?: 'Opening stock',
            );

            return $fg->refresh();
        });
    }

    public function available(int $productVariantId): int
    {
        return (int) (FinishedGood::where('product_variant_id', $productVariantId)->value('quantity') ?? 0);
    }

    private function writeMovement(
        int $productVariantId,
        string $direction,
        int $quantity,
        float $unitCost,
        int $balanceQuantity,
        ?string $referenceType,
        ?int $referenceId,
        ?string $note,
    ): FinishedGoodMovement {
        return FinishedGoodMovement::create([
            'product_variant_id' => $productVariantId,
            'direction' => $direction,
            'quantity' => $quantity,
            'unit_cost' => $unitCost,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'note' => $note,
            'balance_quantity' => $balanceQuantity,
            'created_by' => Auth::id(),
        ]);
    }
}
