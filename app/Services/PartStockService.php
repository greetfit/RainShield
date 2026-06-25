<?php

namespace App\Services;

use App\Models\PartStockBalance;
use App\Models\PartStockMovement;
use App\Models\RecoverablePartBalance;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PartStockService
{
    public function receiveGood(
        int $productVariantId,
        int $partId,
        int $quantity,
        float $unitCost = 0,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?string $note = null,
    ): ?PartStockMovement {
        return $this->receive($productVariantId, $partId, $quantity, $unitCost, 'good', $referenceType, $referenceId, $note);
    }

    public function issueGood(
        int $productVariantId,
        int $partId,
        int $quantity,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?string $note = null,
    ): ?PartStockMovement {
        return $this->issue($productVariantId, $partId, $quantity, 'good', $referenceType, $referenceId, $note);
    }

    public function receiveRecoverable(
        int $productVariantId,
        int $partId,
        int $quantity,
        float $unitCost = 0,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?string $note = null,
    ): ?PartStockMovement {
        return $this->receive($productVariantId, $partId, $quantity, $unitCost, 'recoverable', $referenceType, $referenceId, $note);
    }

    public function issueRecoverable(
        int $productVariantId,
        int $partId,
        int $quantity,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?string $note = null,
    ): ?PartStockMovement {
        return $this->issue($productVariantId, $partId, $quantity, 'recoverable', $referenceType, $referenceId, $note);
    }

    public function recordScrap(
        int $productVariantId,
        int $partId,
        int $quantity,
        float $unitCost = 0,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?string $note = null,
    ): void {
        if ($quantity <= 0) {
            return;
        }

        PartStockMovement::create([
            'product_variant_id' => $productVariantId,
            'part_id' => $partId,
            'stock_type' => 'scrap',
            'direction' => 'scrap',
            'quantity' => $quantity,
            'unit_cost' => $unitCost,
            'balance_quantity' => 0,
            'balance_average_cost' => 0,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'note' => $note,
            'created_by' => Auth::id(),
        ]);
    }

    public function recordScrapReversal(
        int $productVariantId,
        int $partId,
        int $quantity,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?string $note = null,
    ): void {
        if ($quantity <= 0) {
            return;
        }

        PartStockMovement::create([
            'product_variant_id' => $productVariantId,
            'part_id' => $partId,
            'stock_type' => 'scrap',
            'direction' => 'scrap_reversal',
            'quantity' => $quantity,
            'unit_cost' => 0,
            'balance_quantity' => 0,
            'balance_average_cost' => 0,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'note' => $note,
            'created_by' => Auth::id(),
        ]);
    }

    public function availableGood(int $productVariantId, int $partId): int
    {
        return (int) (PartStockBalance::where('product_variant_id', $productVariantId)
            ->where('part_id', $partId)
            ->value('quantity') ?? 0);
    }

    public function availableRecoverable(int $productVariantId, int $partId): int
    {
        return (int) (RecoverablePartBalance::where('product_variant_id', $productVariantId)
            ->where('part_id', $partId)
            ->value('quantity') ?? 0);
    }

    public function adjustGood(int $productVariantId, int $partId, int $countedQuantity, ?string $note = null): void
    {
        $this->adjust($productVariantId, $partId, $countedQuantity, 'good', $note);
    }

    public function adjustRecoverable(int $productVariantId, int $partId, int $countedQuantity, ?string $note = null): void
    {
        $this->adjust($productVariantId, $partId, $countedQuantity, 'recoverable', $note);
    }

    public function openingStock(
        int $productVariantId,
        int $partId,
        string $stockType,
        int $quantity,
        float $unitCost = 0,
        ?int $alertQuantity = null,
        ?string $note = null,
    ): Model {
        return DB::transaction(function () use ($productVariantId, $partId, $stockType, $quantity, $unitCost, $alertQuantity, $note) {
            $balance = $this->lockBalance($productVariantId, $partId, $stockType);
            $balance->quantity = $quantity;
            $balance->average_cost = $quantity > 0 ? $unitCost : 0;
            if ($alertQuantity !== null) {
                $balance->alert_quantity = $alertQuantity;
            }
            $balance->save();

            $this->writeMovement(
                $productVariantId,
                $partId,
                $stockType,
                'opening',
                $quantity,
                $unitCost,
                (int) $balance->quantity,
                (float) $balance->average_cost,
                null,
                null,
                $note ?: 'Opening stock',
            );

            return $balance->refresh();
        });
    }

    private function receive(
        int $productVariantId,
        int $partId,
        int $quantity,
        float $unitCost,
        string $stockType,
        ?string $referenceType,
        ?int $referenceId,
        ?string $note,
    ): ?PartStockMovement {
        if ($quantity <= 0) {
            return null;
        }

        return DB::transaction(function () use ($productVariantId, $partId, $quantity, $unitCost, $stockType, $referenceType, $referenceId, $note) {
            $balance = $this->lockBalance($productVariantId, $partId, $stockType);
            $oldQuantity = (int) $balance->quantity;
            $oldValue = $oldQuantity * (float) ($balance->average_cost ?? 0);
            $newQuantity = $oldQuantity + $quantity;
            $newAverage = $newQuantity > 0
                ? ($oldValue + ($quantity * $unitCost)) / $newQuantity
                : 0;

            $balance->quantity = $newQuantity;
            $balance->average_cost = $newAverage;
            $balance->save();

            return $this->writeMovement($productVariantId, $partId, $stockType, 'in', $quantity, $unitCost, (int) $balance->quantity, (float) $balance->average_cost, $referenceType, $referenceId, $note);
        });
    }

    private function issue(
        int $productVariantId,
        int $partId,
        int $quantity,
        string $stockType,
        ?string $referenceType,
        ?int $referenceId,
        ?string $note,
    ): ?PartStockMovement {
        if ($quantity <= 0) {
            return null;
        }

        return DB::transaction(function () use ($productVariantId, $partId, $quantity, $stockType, $referenceType, $referenceId, $note) {
            $balance = $this->lockBalance($productVariantId, $partId, $stockType);

            if ((int) $balance->quantity < $quantity) {
                throw new RuntimeException("Insufficient {$stockType} part stock.");
            }

            $unitCost = (float) ($balance->average_cost ?? 0);
            $balance->decrement('quantity', $quantity);
            $balance->refresh();
            return $this->writeMovement($productVariantId, $partId, $stockType, 'out', $quantity, $unitCost, (int) $balance->quantity, (float) ($balance->average_cost ?? 0), $referenceType, $referenceId, $note);
        });
    }

    private function adjust(int $productVariantId, int $partId, int $countedQuantity, string $stockType, ?string $note): void
    {
        DB::transaction(function () use ($productVariantId, $partId, $countedQuantity, $stockType, $note) {
            $balance = $this->lockBalance($productVariantId, $partId, $stockType);
            $oldQuantity = (int) $balance->quantity;
            $difference = $countedQuantity - $oldQuantity;
            $balance->quantity = $countedQuantity;
            $balance->save();

            $this->writeMovement(
                $productVariantId,
                $partId,
                $stockType,
                'adjustment',
                abs($difference),
                (float) ($balance->average_cost ?? 0),
                $countedQuantity,
                (float) ($balance->average_cost ?? 0),
                null,
                null,
                $note ?: "Physical count adjustment from {$oldQuantity} to {$countedQuantity}",
            );
        });
    }

    private function lockBalance(int $productVariantId, int $partId, string $stockType): Model
    {
        $model = $stockType === 'recoverable'
            ? RecoverablePartBalance::class
            : PartStockBalance::class;

        return $model::query()
            ->lockForUpdate()
            ->firstOrCreate(
                ['product_variant_id' => $productVariantId, 'part_id' => $partId],
                ['quantity' => 0, 'average_cost' => 0],
            );
    }

    private function writeMovement(
        int $productVariantId,
        int $partId,
        string $stockType,
        string $direction,
        int $quantity,
        float $unitCost,
        int $balanceQuantity,
        float $balanceAverageCost,
        ?string $referenceType,
        ?int $referenceId,
        ?string $note,
    ): PartStockMovement {
        return PartStockMovement::create([
            'product_variant_id' => $productVariantId,
            'part_id' => $partId,
            'stock_type' => $stockType,
            'direction' => $direction,
            'quantity' => $quantity,
            'unit_cost' => $unitCost,
            'balance_quantity' => $balanceQuantity,
            'balance_average_cost' => $balanceAverageCost,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'note' => $note,
            'created_by' => Auth::id(),
        ]);
    }
}
