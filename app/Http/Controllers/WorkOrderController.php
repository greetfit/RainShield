<?php

namespace App\Http\Controllers;

use App\Models\PieceRate;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ProductionStage;
use App\Models\Staff;
use App\Models\WorkOrder;
use App\Services\FinishedGoodsService;
use App\Services\PartStockService;
use App\Services\WorkOrderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class WorkOrderController extends Controller
{
    public function index()
    {
        return Inertia::render('WorkOrders/Index', [
            'workOrders' => $this->workOrderRows(),
            'variantOptions' => $this->variantOptions(),
            'today' => now()->toDateString(),
            'openCreate' => false,
        ]);
    }

    public function create()
    {
        return Inertia::render('WorkOrders/Index', [
            'workOrders' => $this->workOrderRows(),
            'variantOptions' => $this->variantOptions(),
            'today' => now()->toDateString(),
            'openCreate' => true,
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validatedData($request);

        $workOrder = WorkOrder::create([
            ...$data,
            'status' => 'draft',
            'created_by' => Auth::id(),
        ]);
        $workOrder->update(['code' => 'WO-'.str_pad((string) $workOrder->id, 5, '0', STR_PAD_LEFT)]);

        return redirect()->route('work-orders.index')->with('success', 'Work order created.');
    }

    public function update(Request $request, WorkOrder $workOrder, WorkOrderService $service)
    {
        $service->update($workOrder, $this->validatedData($request));

        return back()->with('success', 'Work order updated.');
    }

    public function destroy(WorkOrder $workOrder, WorkOrderService $service)
    {
        $service->delete($workOrder);

        return redirect()->route('work-orders.index')->with('success', 'Work order deleted and stock allocation reversed.');
    }

    public function show(WorkOrder $workOrder, PartStockService $partStock)
    {
        $workOrder->load([
            'productVariant.product',
            'materials.rawMaterialVariant.rawMaterial',
            'parts.part',
            'jobCards.staff',
            'jobCards.receipts',
            'jobCards.payments',
            'jobCards.partMovements.part',
        ]);

        $stockPreview = [];
        if ($workOrder->status === 'draft') {
            $variant = $workOrder->productVariant()->with('recipeParts.part')->first();
            foreach ($variant->recipeParts as $recipePart) {
                $needed = (int) $recipePart->quantity_per_garment * $workOrder->quantity;
                $available = $partStock->availableGood($variant->id, $recipePart->part_id);
                $stockPreview[] = [
                    'label' => $recipePart->part->name,
                    'needed' => $needed,
                    'available' => $available,
                    'ok' => $available >= $needed,
                ];
            }
        }

        $stages = ProductionStage::options()
            ->reject(fn (array $stage) => $stage['value'] === 'cutting')
            ->values();
        $rates = [];
        foreach ($stages as $stage) {
            $rates[$stage['value']] = PieceRate::resolve($stage['value'], $workOrder->product_variant_id);
        }
        $stageProgress = $this->stageProgress($workOrder, $stages);

        return Inertia::render('WorkOrders/Show', [
            'workOrder' => [
                'id' => $workOrder->id,
                'code' => $workOrder->code,
                'product_variant_id' => $workOrder->product_variant_id,
                'product' => $this->variantLabel($workOrder->productVariant),
                'quantity' => $workOrder->quantity,
                'status' => $workOrder->status,
                'target_delivery_date' => $workOrder->target_delivery_date?->toDateString(),
                'released_at' => $workOrder->released_at?->format('Y-m-d H:i'),
                'completed_at' => $workOrder->completed_at?->format('Y-m-d H:i'),
                'completed_quantity' => $workOrder->completed_quantity,
                'rejected_quantity' => $workOrder->rejected_quantity,
                'shortfall_quantity' => $workOrder->status === 'completed'
                    ? max(0, $workOrder->quantity - (int) $workOrder->completed_quantity - (int) $workOrder->rejected_quantity)
                    : null,
                'material_cost' => $workOrder->material_cost,
                'completion_notes' => $workOrder->completion_notes,
                'notes' => $workOrder->notes,
            ],
            'materials' => $workOrder->materials->map(fn ($material) => [
                'label' => $material->rawMaterialVariant->rawMaterial->name.' - '.$material->rawMaterialVariant->name,
                'quantity' => $material->quantity,
                'unit_cost' => $material->unit_cost,
                'total_cost' => $material->total_cost,
            ]),
            'parts' => $workOrder->parts->map(function ($part) use ($workOrder) {
                $movements = $workOrder->jobCards->flatMap->partMovements
                    ->where('part_id', $part->part_id);
                $issued = (int) $movements->where('type', 'issue')->sum('quantity');
                $damaged = (int) $movements
                    ->whereIn('type', ['return_recoverable', 'scrap'])
                    ->sum('quantity');

                return [
                    'part_id' => $part->part_id,
                    'name' => $part->part->name,
                    'quantity' => $part->quantity,
                    'quantity_cut' => $part->quantity,
                    'quantity_issued' => $issued,
                    'quantity_damaged' => $damaged,
                    'quantity_pending' => max(0, (int) $part->quantity - $issued),
                ];
            }),
            'jobCards' => $workOrder->jobCards->map(fn ($jobCard) => [
                'id' => $jobCard->id,
                'stage' => $jobCard->stage,
                'staff_id' => $jobCard->staff_id,
                'staff' => $jobCard->staff?->name,
                'staff_salary_type' => $jobCard->staff?->salary_type ?? 'piece_rate',
                'quantity_issued' => $jobCard->quantity_issued,
                'quantity_received' => $jobCard->quantity_received,
                'quantity_damaged' => $jobCard->quantity_damaged,
                'pending_quantity' => $jobCard->pending_quantity,
                'shortfall' => $jobCard->shortfall,
                'piece_rate' => $jobCard->piece_rate,
                'wage_amount' => $jobCard->wage_amount,
                'wage_paid_amount' => $jobCard->wage_paid_amount,
                'wage_balance' => $jobCard->wage_balance,
                'wage_status' => $jobCard->wage_status,
                'status' => $jobCard->status,
                'started_at' => $jobCard->started_at?->format('Y-m-d H:i'),
                'started_at_input' => $jobCard->started_at?->format('Y-m-d\TH:i'),
                'completed_at' => $jobCard->completed_at?->format('Y-m-d H:i'),
                'duration_minutes' => $jobCard->duration_minutes,
                'notes' => $jobCard->notes,
                'payments' => $jobCard->payments
                    ->sortByDesc('paid_on')
                    ->values()
                    ->map(fn ($payment) => [
                        'id' => $payment->id,
                        'paid_on' => $payment->paid_on?->format('d/m/Y'),
                        'paid_on_input' => $payment->paid_on?->toDateString(),
                        'amount' => $payment->amount,
                        'method' => $payment->method,
                        'reference' => $payment->reference,
                        'source' => $payment->source,
                        'notes' => $payment->notes,
                    ]),
                'part_movements' => $jobCard->partMovements
                    ->sortByDesc('created_at')
                    ->values()
                    ->map(fn ($movement) => [
                        'id' => $movement->id,
                        'type' => $movement->type,
                        'part_id' => $movement->part_id,
                        'part' => $movement->part?->name,
                        'quantity' => $movement->quantity,
                        'notes' => $movement->notes,
                        'created_at' => $movement->created_at?->format('Y-m-d H:i'),
                    ]),
                'receipts' => $jobCard->receipts
                    ->sortBy('received_at')
                    ->values()
                    ->map(fn ($receipt) => [
                        'id' => $receipt->id,
                        'received_on' => $receipt->received_on?->format('d/m/Y'),
                        'quantity_received' => $receipt->quantity_received,
                        'quantity_damaged' => $receipt->quantity_damaged,
                        'started_at' => $receipt->started_at?->format('Y-m-d H:i'),
                        'received_at' => $receipt->received_at?->format('Y-m-d H:i'),
                        'duration_minutes' => $receipt->duration_minutes,
                        'wage_amount' => $receipt->wage_amount,
                        'wage_paid_amount' => $receipt->wage_paid_amount,
                        'wage_balance' => round((float) $receipt->wage_amount - (float) $receipt->wage_paid_amount, 2),
                        'notes' => $receipt->notes,
                    ]),
            ]),
            'stageProgress' => $stageProgress,
            'laborCost' => round((float) $workOrder->jobCards->sum('wage_amount'), 2),
            'stockPreview' => $stockPreview,
            'staffOptions' => Staff::query()
                ->leftJoin('designations', 'designations.id', '=', 'staff.designation_id')
                ->where('staff.is_active', true)
                ->orderByRaw('designations.priority_level is null')
                ->orderBy('designations.priority_level')
                ->orderBy('staff.name')
                ->get([
                    'staff.id',
                    'staff.name',
                    'staff.salary_type',
                    'staff.monthly_salary',
                    'staff.designation_id',
                    'designations.name as designation',
                    'designations.priority_level as designation_priority_level',
                ]),
            'stageOptions' => $stages,
            'stageRates' => $rates,
            'paymentMethods' => PaymentMethod::activeOptions(),
            'pieceRateOptions' => PieceRate::query()
                ->whereIn('stage', $stages->pluck('value'))
                ->get(['stage', 'staff_id', 'product_variant_id', 'rate']),
        ]);
    }

    public function release(WorkOrder $workOrder, WorkOrderService $service)
    {
        $service->release($workOrder);

        return back()->with('success', 'Work order released - materials issued and parts cut.');
    }

    public function complete(Request $request, WorkOrder $workOrder, FinishedGoodsService $finishedGoods)
    {
        if ($workOrder->status !== 'in_production') {
            return back()->with('error', 'Only in-production work orders can be completed.');
        }

        if (! $workOrder->jobCards()->exists()) {
            return back()->withErrors([
                'completed_quantity' => 'Issue and complete at least one job card before completing the work order.',
            ]);
        }

        if ($workOrder->jobCards()->where('status', '!=', 'completed')->exists()) {
            return back()->withErrors([
                'completed_quantity' => 'Complete all job cards before completing the work order.',
            ]);
        }

        $data = $request->validate([
            'completed_quantity' => ['required', 'integer', 'min:0', 'max:'.$workOrder->quantity],
            'rejected_quantity' => ['nullable', 'integer', 'min:0', 'max:'.$workOrder->quantity],
            'completion_notes' => ['nullable', 'string'],
        ]);
        $rejectedQuantity = (int) ($data['rejected_quantity'] ?? 0);

        if ((int) $data['completed_quantity'] + $rejectedQuantity > $workOrder->quantity) {
            return back()->withErrors([
                'completed_quantity' => 'Finished plus rejected quantity cannot exceed the work order quantity.',
            ]);
        }

        $workOrder->update([
            'status' => 'completed',
            'completed_at' => now(),
            'completed_quantity' => $data['completed_quantity'],
            'rejected_quantity' => $rejectedQuantity,
            'completion_notes' => $data['completion_notes'] ?? null,
        ]);

        if ($data['completed_quantity'] > 0) {
            $finishedGoods->add(
                $workOrder->product_variant_id,
                $data['completed_quantity'],
                WorkOrder::class,
                $workOrder->id,
                'Completed '.($workOrder->code ?? 'WO#'.$workOrder->id),
            );
        }

        return back()->with('success', 'Work order completed and added to finished goods.');
    }

    private function variantOptions()
    {
        return ProductVariant::query()
            ->where('is_active', true)
            ->whereHas('product', fn ($query) => $query->whereIn('source_type', [Product::SOURCE_IN_HOUSE, Product::SOURCE_BOTH]))
            ->whereHas('recipeParts')
            ->with('product:id,name')
            ->get()
            ->map(fn (ProductVariant $variant) => [
                'id' => $variant->id,
                'value' => $variant->id,
                'label' => $this->variantLabel($variant),
                'description' => collect([$variant->size, $variant->layer, $variant->grade])->filter()->join(' / '),
            ])
            ->sortBy('label')
            ->values();
    }

    private function workOrderRows()
    {
        return WorkOrder::with('productVariant.product')
            ->withExists('jobCards')
            ->latest('id')
            ->get()
            ->map(fn ($workOrder) => [
                'id' => $workOrder->id,
                'code' => $workOrder->code,
                'product_variant_id' => $workOrder->product_variant_id,
                'product' => $this->variantLabel($workOrder->productVariant),
                'quantity' => $workOrder->quantity,
                'status' => $workOrder->status,
                'target_delivery_date' => $workOrder->target_delivery_date?->toDateString(),
                'notes' => $workOrder->notes,
                'has_job_cards' => (bool) $workOrder->job_cards_exists,
                'allocation_locked' => $workOrder->status === 'in_production' && (bool) $workOrder->job_cards_exists,
                'can_edit' => $workOrder->status !== 'completed',
                'can_delete' => $workOrder->status !== 'completed' && ! $workOrder->job_cards_exists,
            ]);
    }

    private function validatedData(Request $request): array
    {
        return $request->validate([
            'product_variant_id' => ['required', 'exists:product_variants,id'],
            'quantity' => ['required', 'integer', 'min:1'],
            'target_delivery_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ]);
    }

    private function variantLabel(ProductVariant $variant): string
    {
        return $variant->product->name.' - '.$variant->name;
    }

    private function stageProgress(WorkOrder $workOrder, $stages)
    {
        $cardsByStage = $workOrder->jobCards->groupBy('stage');

        return collect($stages)->values()->map(function (array $stage, int $index) use ($workOrder, $stages, $cardsByStage) {
            $cards = $cardsByStage->get($stage['value'], collect());
            $previousStage = $index > 0 ? $stages[$index - 1] : null;
            $nextStage = $stages[$index + 1] ?? null;
            $previousCards = $previousStage ? $cardsByStage->get($previousStage['value'], collect()) : collect();
            $nextCards = $nextStage ? $cardsByStage->get($nextStage['value'], collect()) : collect();
            $available = $previousStage
                ? (int) $previousCards->sum('quantity_received')
                : (int) $workOrder->quantity;
            $issued = (int) $cards->sum('quantity_issued');
            $good = (int) $cards->sum('quantity_received');
            $damaged = (int) $cards->sum('quantity_damaged');
            $waitingToReceive = (int) $cards->sum(fn ($card) => $card->pending_quantity);
            $sentToNext = $nextStage ? (int) $nextCards->sum('quantity_issued') : 0;

            return [
                'stage' => $stage['value'],
                'label' => $stage['label'],
                'priority' => $stage['priority'],
                'is_first' => $index === 0,
                'is_final' => $index === count($stages) - 1,
                'available' => $available,
                'issued' => $issued,
                'good' => $good,
                'damaged' => $damaged,
                'waiting_to_receive' => $waitingToReceive,
                'available_to_issue' => max(0, $available - $issued),
                'ready_for_next' => max(0, $good - $sentToNext),
            ];
        })->values();
    }
}
