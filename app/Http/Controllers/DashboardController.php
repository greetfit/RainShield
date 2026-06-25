<?php

namespace App\Http\Controllers;

use App\Models\Delivery;
use App\Models\FinishedGood;
use App\Models\JobCard;
use App\Models\PartStockBalance;
use App\Models\RawMaterial;
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
            ->limit(8)
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
        ]);
    }
}
