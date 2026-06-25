<?php

namespace App\Http\Controllers;

use App\Models\JobCard;
use App\Models\JobCardPayment;
use App\Models\JobCardPartMovement;
use App\Models\PieceRate;
use App\Models\ProductionStage;
use App\Models\Staff;
use App\Models\WorkOrder;
use App\Models\WorkOrderPart;
use App\Services\FinishedGoodsService;
use App\Services\PartStockService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use RuntimeException;

class JobCardController extends Controller
{
    public function store(Request $request, WorkOrder $workOrder, PartStockService $partStock)
    {
        if ($workOrder->status !== 'in_production') {
            return back()->with('error', 'Job cards can only be added while the order is in production.');
        }

        $data = $request->validate([
            'stage' => ['required', Rule::exists('production_stages', 'slug')->where('is_active', true)],
            'staff_id' => ['required', 'exists:staff,id'],
            'quantity_issued' => ['required', 'integer', 'min:1'],
            'piece_rate' => ['required', 'numeric', 'min:0'],
            'issued_on' => ['nullable', 'date'],
            'started_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:255'],
            'part_issue_lines' => ['nullable', 'array'],
            'part_issue_lines.*.part_id' => ['required_with:part_issue_lines', 'exists:parts,id'],
            'part_issue_lines.*.quantity' => ['nullable', 'integer', 'min:0'],
        ]);

        $remaining = $this->remainingStageQuantity($workOrder, $data['stage']);
        if (! $this->staffMatchesStage((int) $data['staff_id'], $data['stage'])) {
            return back()->withErrors([
                'staff_id' => 'Selected staff designation does not match this production stage.',
            ])->withInput();
        }

        if ((int) $data['quantity_issued'] > $remaining) {
            return back()->withErrors([
                'quantity_issued' => "Only {$remaining} pieces are remaining for this stage.",
            ])->withInput();
        }

        $data['piece_rate'] = $this->resolvedPieceRate($data, $workOrder);

        try {
            DB::transaction(function () use ($workOrder, $data, $partStock) {
                $jobCard = $workOrder->jobCards()->create([
                    ...collect($data)->except('part_issue_lines')->all(),
                    'status' => 'issued',
                    'issued_on' => $data['issued_on'] ?? now()->toDateString(),
                    'started_at' => $data['started_at'] ?? null,
                ]);

                foreach ($data['part_issue_lines'] ?? [] as $line) {
                    $quantity = (int) ($line['quantity'] ?? 0);
                    if ($quantity <= 0) {
                        continue;
                    }

                    $this->recordPartMovement(
                        $jobCard,
                        JobCardPartMovement::ISSUE,
                        (int) $line['part_id'],
                        $quantity,
                        'Initial parts issued with job card.',
                        $partStock,
                    );
                }
            });
        } catch (RuntimeException $exception) {
            return back()->withErrors([
                'part_issue_lines' => $exception->getMessage(),
            ])->withInput();
        }

        return back()->with('success', 'Job card issued.');
    }

    public function complete(Request $request, JobCard $jobCard, FinishedGoodsService $finishedGoods)
    {
        $remaining = $jobCard->pending_quantity;

        $data = $request->validate([
            'quantity_received' => ['required', 'integer', 'min:0', 'max:'.$remaining],
            'quantity_damaged' => ['nullable', 'integer', 'min:0', 'max:'.$remaining],
            'wage_paid_amount' => ['nullable', 'numeric', 'min:0'],
            'completed_on' => ['nullable', 'date'],
            'started_at' => ['nullable', 'date'],
            'completed_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        $receivedNow = (int) $data['quantity_received'];
        $damagedNow = (int) ($data['quantity_damaged'] ?? 0);

        if ($receivedNow + $damagedNow <= 0) {
            return back()->withErrors([
                'quantity_received' => 'Enter returned or damaged pieces.',
            ]);
        }

        if ($receivedNow + $damagedNow > $remaining) {
            return back()->withErrors([
                'quantity_received' => 'Returned plus damaged pieces cannot exceed the pending quantity.',
            ]);
        }

        if (! empty($data['started_at']) && ! empty($data['completed_at']) && strtotime($data['completed_at']) < strtotime($data['started_at'])) {
            return back()->withErrors([
                'completed_at' => 'Finished time cannot be before started time.',
            ]);
        }

        $totalReceived = (int) ($jobCard->quantity_received ?? 0) + $receivedNow;
        $totalDamaged = (int) ($jobCard->quantity_damaged ?? 0) + $damagedNow;
        $wageNow = $jobCard->staff?->salary_type === 'monthly'
            ? 0
            : round($receivedNow * (float) $jobCard->piece_rate, 2);
        $wagePaidNow = round((float) ($data['wage_paid_amount'] ?? 0), 2);
        $periodStartedAt = $jobCard->receipts()->latest('received_at')->value('received_at') ?? ($data['started_at'] ?? $jobCard->started_at);
        $pending = max(0, (int) $jobCard->quantity_issued - $totalReceived - $totalDamaged);
        $completed = $pending === 0;
        $startedAt = $data['started_at'] ?? $jobCard->started_at;
        $receivedAt = $data['completed_at'] ?? now();
        $completedAt = $completed ? $receivedAt : null;
        $receiptDurationMinutes = null;
        $totalDurationMinutes = null;

        if ($periodStartedAt && $receivedAt) {
            $receiptDurationMinutes = max(0, Carbon::parse($periodStartedAt)->diffInMinutes(Carbon::parse($receivedAt)));
        }

        if ($startedAt && $completedAt) {
            $totalDurationMinutes = max(0, Carbon::parse($startedAt)->diffInMinutes(Carbon::parse($completedAt)));
        }

        $receipt = $jobCard->receipts()->create([
            'received_on' => $data['completed_on'] ?? now()->toDateString(),
            'quantity_received' => $receivedNow,
            'quantity_damaged' => $damagedNow,
            'started_at' => $periodStartedAt,
            'received_at' => $receivedAt,
            'duration_minutes' => $receiptDurationMinutes,
            'wage_amount' => $wageNow,
            'wage_paid_amount' => $wagePaidNow,
            'notes' => $data['notes'] ?? null,
        ]);

        if ($wagePaidNow > 0) {
            $jobCard->payments()->create([
                'job_card_receipt_id' => $receipt->id,
                'paid_on' => $data['completed_on'] ?? now()->toDateString(),
                'amount' => $wagePaidNow,
                'source' => 'receipt',
                'notes' => $data['notes'] ?? null,
                'created_by' => Auth::id(),
            ]);
        }

        $jobCard->update([
            'quantity_received' => $totalReceived,
            'quantity_damaged' => $totalDamaged,
            'wage_amount' => round((float) ($jobCard->wage_amount ?? 0) + $wageNow, 2),
            'wage_paid_amount' => round((float) ($jobCard->wage_paid_amount ?? 0) + $wagePaidNow, 2),
            'status' => $completed ? 'completed' : 'partial',
            'started_at' => $startedAt,
            'completed_on' => $completed ? ($data['completed_on'] ?? now()->toDateString()) : null,
            'completed_at' => $completedAt,
            'duration_minutes' => $totalDurationMinutes,
        ]);

        $autoCompleted = $this->completeWorkOrderIfFinalStageIsDone($jobCard->fresh('workOrder'), $finishedGoods);

        if ($autoCompleted) {
            return back()->with('success', 'Final stage completed. Work order moved to finished goods.');
        }

        return back()->with('success', $completed ? 'Job card completed and wage recorded.' : 'Job card receipt recorded. Pending balance remains.');
    }

    public function storePayment(Request $request, JobCard $jobCard)
    {
        $data = $request->validate([
            'paid_on' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'method' => ['nullable', 'string', 'max:255'],
            'reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]);

        $amount = round((float) $data['amount'], 2);

        $jobCard->payments()->create([
            ...$data,
            'amount' => $amount,
            'source' => 'manual',
            'created_by' => Auth::id(),
        ]);

        $jobCard->increment('wage_paid_amount', $amount);

        return back()->with('success', 'Job card payment recorded.');
    }

    public function updatePayment(Request $request, JobCardPayment $payment)
    {
        if ($payment->source !== 'manual') {
            return back()->withErrors([
                'amount' => 'Receipt-linked payments must be edited from the receipt.',
            ]);
        }

        $data = $request->validate([
            'paid_on' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'method' => ['nullable', 'string', 'max:255'],
            'reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]);

        DB::transaction(function () use ($payment, $data) {
            $oldAmount = (float) $payment->amount;
            $newAmount = round((float) $data['amount'], 2);

            $payment->update([
                ...$data,
                'amount' => $newAmount,
            ]);

            $payment->jobCard()->increment('wage_paid_amount', round($newAmount - $oldAmount, 2));
        });

        return back()->with('success', 'Job card payment updated.');
    }

    public function destroyPayment(JobCardPayment $payment)
    {
        if ($payment->source !== 'manual') {
            return back()->withErrors([
                'amount' => 'Receipt-linked payments must be deleted from the receipt.',
            ]);
        }

        DB::transaction(function () use ($payment) {
            $amount = (float) $payment->amount;
            $jobCard = $payment->jobCard;
            $payment->delete();
            $jobCard->decrement('wage_paid_amount', $amount);
        });

        return back()->with('success', 'Job card payment deleted.');
    }

    public function storePartMovement(Request $request, JobCard $jobCard, PartStockService $partStock)
    {
        $data = $request->validate([
            'type' => ['required', Rule::in([
                JobCardPartMovement::ISSUE,
                JobCardPartMovement::RETURN_GOOD,
                JobCardPartMovement::RETURN_RECOVERABLE,
                JobCardPartMovement::SCRAP,
            ])],
            'part_id' => ['required', 'exists:parts,id'],
            'quantity' => ['required', 'integer', 'min:1'],
            'notes' => ['nullable', 'string'],
        ]);

        try {
            DB::transaction(function () use ($data, $jobCard, $partStock) {
                $this->recordPartMovement(
                    $jobCard,
                    $data['type'],
                    (int) $data['part_id'],
                    (int) $data['quantity'],
                    $data['notes'] ?? null,
                    $partStock,
                );
            });
        } catch (RuntimeException $exception) {
            return back()->withErrors([
                'quantity' => $exception->getMessage(),
            ])->withInput();
        }

        return back()->with('success', 'Job card part movement recorded.');
    }

    public function update(Request $request, JobCard $jobCard)
    {
        if ($jobCard->workOrder->status !== 'in_production') {
            return back()->with('error', 'Job cards can only be edited while the order is in production.');
        }

        $alreadyReturned = (int) ($jobCard->quantity_received ?? 0) + (int) ($jobCard->quantity_damaged ?? 0);

        $data = $request->validate([
            'stage' => ['required', Rule::exists('production_stages', 'slug')->where('is_active', true)],
            'staff_id' => ['required', 'exists:staff,id'],
            'quantity_issued' => ['required', 'integer', 'min:'.$alreadyReturned],
            'piece_rate' => ['required', 'numeric', 'min:0'],
            'started_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:255'],
        ], [
            'quantity_issued.min' => 'Quantity issued cannot be less than pieces already received or damaged.',
        ]);

        $remaining = $this->remainingStageQuantity($jobCard->workOrder, $data['stage'], $jobCard->id);
        if (! $this->staffMatchesStage((int) $data['staff_id'], $data['stage'])) {
            return back()->withErrors([
                'staff_id' => 'Selected staff designation does not match this production stage.',
            ])->withInput();
        }

        if ((int) $data['quantity_issued'] > $remaining) {
            return back()->withErrors([
                'quantity_issued' => "Only {$remaining} pieces are available for this stage.",
            ])->withInput();
        }

        $data['piece_rate'] = $this->resolvedPieceRate($data, $jobCard->workOrder);

        $jobCard->fill($data);
        $jobCard->load('staff', 'receipts');

        $wageAmount = 0;
        foreach ($jobCard->receipts as $receipt) {
            $receiptWage = $jobCard->staff?->salary_type === 'monthly'
                ? 0
                : round((float) $receipt->quantity_received * (float) $jobCard->piece_rate, 2);
            $receipt->update(['wage_amount' => $receiptWage]);
            $wageAmount += $receiptWage;
        }

        $pending = max(0, (int) $jobCard->quantity_issued - $alreadyReturned);
        $jobCard->wage_amount = round($wageAmount, 2);
        $jobCard->status = $pending === 0 && $alreadyReturned > 0
            ? 'completed'
            : ($alreadyReturned > 0 ? 'partial' : 'issued');
        $lastReceipt = $jobCard->receipts->sortByDesc('received_at')->first();
        $jobCard->completed_on = $jobCard->status === 'completed'
            ? ($jobCard->completed_on ?? $lastReceipt?->received_on ?? now()->toDateString())
            : null;
        $jobCard->completed_at = $jobCard->status === 'completed'
            ? ($jobCard->completed_at ?? $lastReceipt?->received_at)
            : null;
        $jobCard->duration_minutes = $jobCard->status === 'completed' && $jobCard->started_at && $jobCard->completed_at
            ? max(0, Carbon::parse($jobCard->started_at)->diffInMinutes(Carbon::parse($jobCard->completed_at)))
            : null;
        $jobCard->save();

        return back()->with('success', 'Job card updated.');
    }

    public function destroy(JobCard $jobCard)
    {
        if ($jobCard->partMovements()->exists()) {
            return back()->withErrors([
                'job_card' => 'This job card has part movement records. Keep it for stock/accountability history.',
            ]);
        }

        $jobCard->delete();

        return back()->with('success', 'Job card removed.');
    }

    private function remainingStageQuantity(WorkOrder $workOrder, string $stage, ?int $excludingJobCardId = null): int
    {
        $available = $this->availableStageQuantity($workOrder, $stage);
        $issued = $workOrder->jobCards()
            ->where('stage', $stage)
            ->when($excludingJobCardId, fn ($query) => $query->whereKeyNot($excludingJobCardId))
            ->sum('quantity_issued');

        return max(0, $available - (int) $issued);
    }

    private function resolvedPieceRate(array $data, WorkOrder $workOrder): float
    {
        $staff = Staff::find($data['staff_id']);
        if ($staff?->salary_type === 'monthly') {
            return 0;
        }

        $pieceRate = (float) ($data['piece_rate'] ?? 0);
        if ($pieceRate > 0) {
            return $pieceRate;
        }

        return PieceRate::resolve($data['stage'], (int) $workOrder->product_variant_id, (int) $data['staff_id']);
    }

    private function partMovementLabel(string $type): string
    {
        return [
            JobCardPartMovement::ISSUE => 'Parts issued',
            JobCardPartMovement::RETURN_GOOD => 'Unused parts returned',
            JobCardPartMovement::RETURN_RECOVERABLE => 'Damaged recoverable parts returned',
            JobCardPartMovement::SCRAP => 'Scrap parts recorded',
        ][$type] ?? 'Part movement';
    }

    private function recordPartMovement(
        JobCard $jobCard,
        string $type,
        int $partId,
        int $quantity,
        ?string $notes,
        PartStockService $partStock,
    ): void {
        $jobCard->loadMissing('workOrder.parts.part', 'staff');
        $workOrder = $jobCard->workOrder;

        $workOrderPart = WorkOrderPart::query()
            ->where('work_order_id', $workOrder->id)
            ->where('part_id', $partId)
            ->lockForUpdate()
            ->firstOrFail();

        $note = $notes ?: $this->partMovementLabel($type).' for '.$jobCard->staff?->name.' on '.($workOrder->code ?? 'WO#'.$workOrder->id);

        if ($type === JobCardPartMovement::ISSUE) {
            $alreadyIssued = JobCardPartMovement::query()
                ->where('work_order_id', $workOrder->id)
                ->where('part_id', $partId)
                ->where('type', JobCardPartMovement::ISSUE)
                ->sum('quantity');

            $extraNeeded = max(0, ($alreadyIssued + $quantity) - (int) $workOrderPart->quantity);
            if ($extraNeeded > 0) {
                $movement = $partStock->issueGood(
                    (int) $workOrder->product_variant_id,
                    $partId,
                    $extraNeeded,
                    JobCardPartMovement::class,
                    $jobCard->id,
                    'Extra part issue for '.($workOrder->code ?? 'WO#'.$workOrder->id),
                );

                $unitCost = (float) ($movement?->unit_cost ?? $workOrderPart->unit_cost ?? 0);
                $newQuantity = (int) $workOrderPart->quantity + $extraNeeded;
                $newTotal = (float) $workOrderPart->total_cost + ($extraNeeded * $unitCost);
                $workOrderPart->update([
                    'quantity' => $newQuantity,
                    'unit_cost' => $newQuantity > 0 ? $newTotal / $newQuantity : 0,
                    'total_cost' => round($newTotal, 2),
                ]);
                $workOrder->update(['material_cost' => round((float) $workOrder->parts()->sum('total_cost'), 2)]);
            }
        }

        if ($type === JobCardPartMovement::RETURN_GOOD) {
            $partStock->receiveGood(
                (int) $workOrder->product_variant_id,
                $partId,
                $quantity,
                (float) ($workOrderPart->unit_cost ?? 0),
                JobCardPartMovement::class,
                $jobCard->id,
                $note,
            );
        }

        if ($type === JobCardPartMovement::RETURN_RECOVERABLE) {
            $partStock->receiveRecoverable(
                (int) $workOrder->product_variant_id,
                $partId,
                $quantity,
                (float) ($workOrderPart->unit_cost ?? 0),
                JobCardPartMovement::class,
                $jobCard->id,
                $note,
            );
            $workOrderPart->increment('quantity_damaged', $quantity);
        }

        if ($type === JobCardPartMovement::SCRAP) {
            $partStock->recordScrap(
                (int) $workOrder->product_variant_id,
                $partId,
                $quantity,
                (float) ($workOrderPart->unit_cost ?? 0),
                JobCardPartMovement::class,
                $jobCard->id,
                $note,
            );
            $workOrderPart->increment('quantity_damaged', $quantity);
        }

        JobCardPartMovement::create([
            'job_card_id' => $jobCard->id,
            'work_order_id' => $workOrder->id,
            'product_variant_id' => $workOrder->product_variant_id,
            'part_id' => $partId,
            'type' => $type,
            'quantity' => $quantity,
            'notes' => $notes,
            'created_by' => Auth::id(),
        ]);
    }

    private function availableStageQuantity(WorkOrder $workOrder, string $stage): int
    {
        $stages = ProductionStage::query()
            ->active()
            ->where('slug', '!=', 'cutting')
            ->orderBy('priority_level')
            ->orderBy('id')
            ->pluck('slug')
            ->values();

        $index = $stages->search($stage);

        if ($index === false) {
            return 0;
        }

        if ($index === 0) {
            return (int) $workOrder->quantity;
        }

        $previousStage = $stages[$index - 1];

        return (int) $workOrder->jobCards()
            ->where('stage', $previousStage)
            ->sum('quantity_received');
    }

    private function staffMatchesStage(int $staffId, string $stage): bool
    {
        $stagePriority = ProductionStage::query()
            ->active()
            ->where('slug', $stage)
            ->value('priority_level');

        if (! $stagePriority) {
            return false;
        }

        return Staff::query()
            ->leftJoin('designations', 'designations.id', '=', 'staff.designation_id')
            ->where('staff.id', $staffId)
            ->where('staff.is_active', true)
            ->where('designations.priority_level', $stagePriority)
            ->exists();
    }

    private function completeWorkOrderIfFinalStageIsDone(JobCard $jobCard, FinishedGoodsService $finishedGoods): bool
    {
        if ($jobCard->status !== 'completed') {
            return false;
        }

        $finalStage = ProductionStage::query()
            ->active()
            ->where('slug', '!=', 'cutting')
            ->orderByDesc('priority_level')
            ->orderByDesc('id')
            ->value('slug');

        if (! $finalStage || $jobCard->stage !== $finalStage) {
            return false;
        }

        $workOrder = $jobCard->workOrder()->with('jobCards')->first();

        if (! $workOrder || $workOrder->status !== 'in_production') {
            return false;
        }

        if ($workOrder->jobCards->contains(fn (JobCard $card) => $card->status !== 'completed')) {
            return false;
        }

        $finalStageCards = $workOrder->jobCards->where('stage', $finalStage);
        $completedQuantity = min((int) $workOrder->quantity, (int) $finalStageCards->sum('quantity_received'));
        $rejectedQuantity = min(
            max(0, (int) $workOrder->quantity - $completedQuantity),
            (int) $finalStageCards->sum('quantity_damaged'),
        );

        $workOrder->update([
            'status' => 'completed',
            'completed_at' => now(),
            'completed_quantity' => $completedQuantity,
            'rejected_quantity' => $rejectedQuantity,
            'completion_notes' => 'Auto-completed when final production stage was received.',
        ]);

        if ($completedQuantity > 0) {
            $finishedGoods->add(
                $workOrder->product_variant_id,
                $completedQuantity,
                WorkOrder::class,
                $workOrder->id,
                'Completed '.($workOrder->code ?? 'WO#'.$workOrder->id).' from final stage',
            );
        }

        return true;
    }

}
