<?php

namespace App\Services;

use App\Models\WorkOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WorkOrderService
{
    public function __construct(private PartStockService $partStock) {}

    /**
     * Release a draft work order into production:
     *  - explode the recipe by the order quantity,
     *  - verify pre-cut part stock for every required part, then issue it,
     *  - snapshot required parts for stitching/packing.
     *
     * @throws ValidationException when the order isn't a draft or stock is short.
     */
    public function release(WorkOrder $workOrder): WorkOrder
    {
        if ($workOrder->status !== 'draft') {
            throw ValidationException::withMessages(['release' => 'Only draft work orders can be released.']);
        }

        return DB::transaction(function () use ($workOrder) {
            $this->allocateParts($workOrder);

            $workOrder->update([
                'status' => 'in_production',
                'released_at' => now(),
            ]);

            return $workOrder->refresh();
        });
    }

    public function update(WorkOrder $workOrder, array $data): WorkOrder
    {
        if ($workOrder->status === 'completed') {
            throw ValidationException::withMessages([
                'work_order' => 'Completed work orders cannot be edited.',
            ]);
        }

        return DB::transaction(function () use ($workOrder, $data) {
            $allocationChanged = $workOrder->status === 'in_production'
                && (
                    (int) $workOrder->product_variant_id !== (int) $data['product_variant_id']
                    || (int) $workOrder->quantity !== (int) $data['quantity']
                );

            if ($allocationChanged) {
                $this->ensureNoJobCards($workOrder, 'Product and quantity cannot be changed after job cards are issued.');
                $this->reverseAllocation($workOrder, 'Reversed part allocation before editing '.($workOrder->code ?? 'WO#'.$workOrder->id));
            }

            $workOrder->fill($data);
            $workOrder->save();

            if ($allocationChanged) {
                $this->allocateParts($workOrder, 'Edited part allocation '.($workOrder->code ?? 'WO#'.$workOrder->id));
            }

            return $workOrder->refresh();
        });
    }

    public function delete(WorkOrder $workOrder): void
    {
        if ($workOrder->status === 'completed') {
            throw ValidationException::withMessages([
                'work_order' => 'Completed work orders cannot be deleted.',
            ]);
        }

        DB::transaction(function () use ($workOrder) {
            $this->ensureNoJobCards($workOrder, 'Work orders with job cards cannot be deleted. Keep them for staff and stock history.');

            if ($workOrder->status === 'in_production') {
                $this->reverseAllocation($workOrder, 'Deleted work order '.($workOrder->code ?? 'WO#'.$workOrder->id));
            }

            $workOrder->delete();
        });
    }

    private function allocateParts(WorkOrder $workOrder, ?string $note = null): void
    {
        $variant = $workOrder->productVariant()->with(['recipeParts.part'])->first();
        $qty = (int) $workOrder->quantity;

        if ($variant->recipeParts->isEmpty()) {
            throw ValidationException::withMessages([
                'release' => 'This product variant has no required parts. Define the recipe first.',
            ]);
        }

        $shortfalls = [];
        foreach ($variant->recipeParts as $recipePart) {
            $needed = (int) $recipePart->quantity_per_garment * $qty;
            $available = $this->partStock->availableGood($variant->id, $recipePart->part_id);
            if ($available < $needed) {
                $shortfalls[] = "Need {$needed}, have {$available} of {$recipePart->part->name}.";
            }
        }
        if ($shortfalls) {
            throw ValidationException::withMessages(['release' => $shortfalls]);
        }

        $workOrder->materials()->delete();
        $workOrder->parts()->delete();

        foreach ($variant->recipeParts as $rp) {
            $needed = (int) $rp->quantity_per_garment * $qty;
            $movement = $this->partStock->issueGood(
                $variant->id,
                $rp->part_id,
                $needed,
                WorkOrder::class,
                $workOrder->id,
                $note ?? 'Issued parts to '.($workOrder->code ?? 'WO#'.$workOrder->id),
            );
            $unitCost = (float) ($movement?->unit_cost ?? 0);
            $totalCost = round($needed * $unitCost, 2);

            $workOrder->parts()->create([
                'part_id' => $rp->part_id,
                'quantity' => $needed,
                'unit_cost' => $unitCost,
                'total_cost' => $totalCost,
            ]);
        }

        $workOrder->update(['material_cost' => round((float) $workOrder->parts()->sum('total_cost'), 2)]);
    }

    private function reverseAllocation(WorkOrder $workOrder, string $note): void
    {
        $workOrder->loadMissing('materials', 'parts');

        $variantId = (int) $workOrder->product_variant_id;
        foreach ($workOrder->parts as $part) {
            $this->partStock->receiveGood(
                $variantId,
                $part->part_id,
                (int) $part->quantity,
                (float) ($part->unit_cost ?? 0),
                WorkOrder::class,
                $workOrder->id,
                $note,
            );
        }

        $workOrder->materials()->delete();
        $workOrder->parts()->delete();
        $workOrder->update(['material_cost' => 0]);
    }

    private function ensureNoJobCards(WorkOrder $workOrder, string $message): void
    {
        if ($workOrder->jobCards()->exists()) {
            throw ValidationException::withMessages([
                'work_order' => $message,
            ]);
        }
    }
}
