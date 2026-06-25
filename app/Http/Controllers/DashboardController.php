<?php

namespace App\Http\Controllers;

use App\Models\Delivery;
use App\Models\FinishedGood;
use App\Models\JobCard;
use App\Models\JobCardPartMovement;
use App\Models\PartStockBalance;
use App\Models\PaymentMethod;
use App\Models\RawMaterial;
use App\Models\Staff;
use App\Models\StockBalance;
use App\Models\WorkOrder;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index()
    {
        $stockValue = StockBalance::select(DB::raw('COALESCE(SUM(quantity * average_cost), 0) as v'))->value('v');
        $stockByMaterial = StockBalance::query()
            ->join('raw_material_variants', 'raw_material_variants.id', '=', 'stock_balances.raw_material_variant_id')
            ->select('raw_material_variants.raw_material_id', DB::raw('COALESCE(SUM(stock_balances.quantity), 0) as quantity'))
            ->groupBy('raw_material_variants.raw_material_id')
            ->pluck('quantity', 'raw_material_variants.raw_material_id');

        $lowStockMaterials = RawMaterial::query()
            ->where('is_active', true)
            ->where('alert_quantity', '>', 0)
            ->orderBy('name')
            ->get(['id', 'name', 'unit', 'alert_quantity'])
            ->map(function (RawMaterial $material) use ($stockByMaterial) {
                $currentQuantity = round((float) ($stockByMaterial[$material->id] ?? 0), 3);
                $alertQuantity = round((float) $material->alert_quantity, 3);

                return [
                    'id' => $material->id,
                    'name' => $material->name,
                    'unit' => $material->unit,
                    'current_quantity' => $currentQuantity,
                    'alert_quantity' => $alertQuantity,
                    'short_by' => max(0, round($alertQuantity - $currentQuantity, 3)),
                ];
            })
            ->filter(fn (array $material) => $material['current_quantity'] <= $material['alert_quantity'])
            ->values();

        $woByStatus = WorkOrder::select('status', DB::raw('count(*) as c'))
            ->groupBy('status')->pluck('c', 'status');

        $partStock = PartStockBalance::query()
            ->get(['product_variant_id', 'part_id', 'quantity'])
            ->groupBy('product_variant_id')
            ->map(fn ($rows) => $rows->pluck('quantity', 'part_id'));

        $partRequirements = collect();
        WorkOrder::query()
            ->where('status', 'draft')
            ->with(['productVariant.product', 'productVariant.recipeParts.part'])
            ->get()
            ->each(function (WorkOrder $workOrder) use (&$partRequirements): void {
                foreach ($workOrder->productVariant->recipeParts as $recipePart) {
                    $key = $workOrder->product_variant_id.'-'.$recipePart->part_id;
                    $existing = $partRequirements->get($key, [
                        'product_variant_id' => $workOrder->product_variant_id,
                        'part_id' => $recipePart->part_id,
                        'label' => $workOrder->productVariant->product->name.' - '.$workOrder->productVariant->name,
                        'part' => $recipePart->part->name,
                        'needed' => 0,
                    ]);
                    $existing['needed'] += (int) $recipePart->quantity_per_garment * (int) $workOrder->quantity;
                    $partRequirements->put($key, $existing);
                }
            });

        $lowPartStock = $partRequirements
            ->map(function (array $row) use ($partStock) {
                $available = (int) ($partStock[$row['product_variant_id']][$row['part_id']] ?? 0);
                $row['available'] = $available;
                $row['short_by'] = max(0, $row['needed'] - $available);

                return $row;
            })
            ->filter(fn (array $row) => $row['short_by'] > 0)
            ->values();

        $minimumPartAlerts = PartStockBalance::query()
            ->with(['productVariant.product', 'part'])
            ->where('alert_quantity', '>', 0)
            ->whereColumn('quantity', '<=', 'alert_quantity')
            ->get()
            ->map(fn (PartStockBalance $balance) => [
                'product_variant_id' => $balance->product_variant_id,
                'part_id' => $balance->part_id,
                'label' => $balance->productVariant->product->name.' - '.$balance->productVariant->name,
                'part' => $balance->part->name,
                'needed' => (int) $balance->alert_quantity,
                'available' => (int) $balance->quantity,
                'short_by' => max(0, (int) $balance->alert_quantity - (int) $balance->quantity),
                'source' => 'Minimum alert',
            ]);

        $lowPartStock = $lowPartStock
            ->map(fn (array $row) => [...$row, 'source' => 'Draft work orders'])
            ->merge($minimumPartAlerts)
            ->unique(fn (array $row) => $row['source'].'-'.$row['product_variant_id'].'-'.$row['part_id'])
            ->values();

        $productStockAlerts = FinishedGood::query()
            ->with('productVariant.product')
            ->where('alert_quantity', '>', 0)
            ->whereColumn('quantity', '<=', 'alert_quantity')
            ->orderBy('quantity')
            ->get()
            ->map(fn (FinishedGood $finishedGood) => [
                'id' => $finishedGood->id,
                'label' => $finishedGood->productVariant->product->name.' - '.$finishedGood->productVariant->name,
                'current' => (int) $finishedGood->quantity,
                'alert' => (int) $finishedGood->alert_quantity,
                'short_by' => max(0, (int) $finishedGood->alert_quantity - (int) $finishedGood->quantity),
            ]);

        $partStockSummary = PartStockBalance::query()
            ->with(['productVariant.product', 'part'])
            ->orderBy('quantity')
            ->get()
            ->map(fn (PartStockBalance $balance) => [
                'label' => $balance->productVariant->product->name.' - '.$balance->productVariant->name,
                'part' => $balance->part->name,
                'quantity' => (int) $balance->quantity,
                'alert_quantity' => (int) $balance->alert_quantity,
                'is_alert' => (int) $balance->alert_quantity > 0 && (int) $balance->quantity <= (int) $balance->alert_quantity,
            ]);

        $wagesThisMonth = JobCard::where('status', 'completed')
            ->whereBetween('completed_on', [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()])
            ->sum('wage_amount');

        return Inertia::render('Dashboard', [
            'stats' => [
                'stock_value' => round((float) $stockValue, 2),
                'low_stock' => $lowStockMaterials->count(),
                'part_stock_alerts' => $lowPartStock->count(),
                'product_stock_alerts' => $productStockAlerts->count(),
                'wo_draft' => (int) ($woByStatus['draft'] ?? 0),
                'wo_in_production' => (int) ($woByStatus['in_production'] ?? 0),
                'wo_completed' => (int) ($woByStatus['completed'] ?? 0),
                'finished_units' => (int) FinishedGood::sum('quantity'),
                'pending_deliveries' => Delivery::where('status', 'dispatched')->count(),
                'wages_month' => round((float) $wagesThisMonth, 2),
            ],
            'lowStockMaterials' => $lowStockMaterials,
            'lowPartStock' => $lowPartStock,
            'productStockAlerts' => $productStockAlerts,
            'partStockSummary' => $partStockSummary,
            'staffWorkflows' => $this->staffWorkflows(),
            'paymentMethods' => PaymentMethod::activeOptions(),
        ]);
    }

    private function staffWorkflows()
    {
        $jobCards = JobCard::query()
            ->with([
                'staff.designationRecord',
                'workOrder.productVariant.product',
                'workOrder.parts.part',
                'payments',
                'receipts',
                'partMovements.part',
            ])
            ->whereHas('workOrder', fn ($query) => $query->where('status', 'in_production'))
            ->whereNotNull('staff_id')
            ->latest('id')
            ->get();

        $cardsByStaff = $jobCards->groupBy('staff_id');

        return Staff::query()
            ->with('designationRecord')
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(function (Staff $staff) use ($cardsByStaff) {
                $cards = $cardsByStaff->get($staff->id, collect());
                $activeCards = $cards
                    ->filter(fn (JobCard $card) => $card->status !== 'completed' || abs($card->wage_balance) > 0.005)
                    ->values();

                return [
                    'id' => $staff->id,
                    'name' => $staff->name,
                    'designation' => $staff->designationRecord?->name ?? $staff->designation,
                    'salary_type' => $staff->salary_type,
                    'open_cards_count' => $activeCards->where('status', '!=', 'completed')->count(),
                    'pending_wage' => round((float) $cards->sum(fn (JobCard $card) => max(0, $card->wage_balance)), 2),
                    'overpaid_wage' => round(abs((float) $cards->sum(fn (JobCard $card) => min(0, $card->wage_balance))), 2),
                    'cards' => $activeCards->map(fn (JobCard $card) => $this->dashboardJobCard($card))->values(),
                ];
            })
            ->filter(fn (array $staff) => $staff['open_cards_count'] > 0 || $staff['pending_wage'] > 0 || $staff['overpaid_wage'] > 0)
            ->values();
    }

    private function dashboardJobCard(JobCard $card): array
    {
        $workOrder = $card->workOrder;

        return [
            'id' => $card->id,
            'stage' => $card->stage,
            'stage_label' => ucfirst(str_replace('_', ' ', $card->stage)),
            'work_order_id' => $workOrder?->id,
            'work_order_code' => $workOrder?->code ?? 'WO#'.$workOrder?->id,
            'product' => $workOrder?->productVariant
                ? $workOrder->productVariant->product->name.' - '.$workOrder->productVariant->name
                : '-',
            'status' => $card->status,
            'quantity_issued' => (int) $card->quantity_issued,
            'quantity_received' => (int) ($card->quantity_received ?? 0),
            'quantity_damaged' => (int) ($card->quantity_damaged ?? 0),
            'pending_quantity' => $card->pending_quantity,
            'wage_amount' => (float) $card->wage_amount,
            'wage_paid_amount' => (float) $card->wage_paid_amount,
            'wage_balance' => $card->wage_balance,
            'started_at_input' => $card->started_at?->format('Y-m-d\TH:i'),
            'parts' => $workOrder?->parts
                ? $workOrder->parts->map(fn ($part) => [
                    'part_id' => $part->part_id,
                    'name' => $part->part->name,
                    'required' => (int) $part->quantity,
                    'issued' => (int) $card->partMovements
                        ->where('part_id', $part->part_id)
                        ->where('type', JobCardPartMovement::ISSUE)
                        ->sum('quantity'),
                ])->values()
                : [],
            'payments' => $card->payments
                ->sortByDesc('paid_on')
                ->values()
                ->map(fn ($payment) => [
                    'id' => $payment->id,
                    'paid_on' => $payment->paid_on?->format('d/m/Y'),
                    'amount' => (float) $payment->amount,
                    'method' => $payment->method,
                    'reference' => $payment->reference,
                    'source' => $payment->source,
                ]),
            'receipts' => $card->receipts
                ->sortByDesc('received_at')
                ->values()
                ->map(fn ($receipt) => [
                    'id' => $receipt->id,
                    'received_at' => $receipt->received_at?->format('Y-m-d H:i'),
                    'quantity_received' => (int) $receipt->quantity_received,
                    'quantity_damaged' => (int) $receipt->quantity_damaged,
                    'wage_amount' => (float) $receipt->wage_amount,
                    'wage_paid_amount' => (float) $receipt->wage_paid_amount,
                    'duration_minutes' => $receipt->duration_minutes,
                ]),
            'part_movements' => $card->partMovements
                ->sortByDesc('created_at')
                ->values()
                ->map(fn ($movement) => [
                    'id' => $movement->id,
                    'type' => $movement->type,
                    'part' => $movement->part?->name,
                    'part_id' => $movement->part_id,
                    'quantity' => (int) $movement->quantity,
                    'created_at' => $movement->created_at?->format('Y-m-d H:i'),
                    'notes' => $movement->notes,
                ]),
        ];
    }
}
