<?php

namespace App\Http\Controllers;

use App\Models\CuttingBatch;
use App\Models\FinishedGood;
use App\Models\FinishedGoodMovement;
use App\Models\JobCard;
use App\Models\PartStockMovement;
use App\Models\PartStockBalance;
use App\Models\Purchase;
use App\Models\PurchaseReturn;
use App\Models\RawMaterial;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\StockBalance;
use App\Models\WorkOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        return Inertia::render('Reports/Index', [
            'summary' => $this->summary(),
            'production' => $this->productionStats(),
            'reportLinks' => [
                ['label' => 'Production Flow', 'route' => 'reports.production-flow', 'description' => 'Stage-wise issued, good, damaged, pending and wage movement.'],
                ['label' => 'Part In / Out', 'route' => 'reports.part-flow', 'description' => 'Good, recoverable and scrap part stock movements.'],
                ['label' => 'Finished Goods Flow', 'route' => 'reports.finished-good-flow', 'description' => 'Finished product stock in and out movement.'],
                ['label' => 'Sales By Product', 'route' => 'reports.sales-by-product', 'description' => 'Top selling products and revenue.'],
                ['label' => 'Work Order Status', 'route' => 'reports.work-order-status', 'description' => 'Work orders grouped by current status.'],
                ['label' => 'Stock Alerts', 'route' => 'reports.stock-alerts', 'description' => 'Raw material and part stock under alert levels.'],
            ],
        ]);
    }

    public function productionFlowPage(Request $request)
    {
        $from = ($request->date('from') ?? now()->startOfMonth())->startOfDay();
        $to = ($request->date('to') ?? now())->endOfDay();

        return Inertia::render('Reports/ProductionFlow', [
            'filters' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
            ],
            'productionFlow' => $this->productionFlow($from, $to),
        ]);
    }

    public function partFlowPage(Request $request)
    {
        $from = ($request->date('from') ?? now()->startOfMonth())->startOfDay();
        $to = ($request->date('to') ?? now())->endOfDay();

        return Inertia::render('Reports/PartFlow', [
            'filters' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'partFlow' => $this->partFlow($from, $to),
        ]);
    }

    public function finishedGoodFlowPage(Request $request)
    {
        $from = ($request->date('from') ?? now()->startOfMonth())->startOfDay();
        $to = ($request->date('to') ?? now())->endOfDay();

        return Inertia::render('Reports/FinishedGoodFlow', [
            'filters' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'finishedGoodFlow' => $this->finishedGoodFlow($from, $to),
        ]);
    }

    public function salesByProductPage()
    {
        return Inertia::render('Reports/SalesByProduct', [
            'salesByProduct' => $this->salesByProduct(),
        ]);
    }

    public function workOrderStatusPage()
    {
        return Inertia::render('Reports/WorkOrderStatus', [
            'production' => $this->productionStats(),
            'workOrdersByStatus' => $this->workOrdersByStatus(),
        ]);
    }

    public function stockAlertsPage()
    {
        return Inertia::render('Reports/StockAlerts', [
            'lowRawStock' => $this->lowRawStock(),
            'lowPartStock' => $this->lowPartStock(),
        ]);
    }

    private function summary(): array
    {
        $salesTotal = (float) Sale::where('status', Sale::STATUS_FINAL)->sum('total');
        $salesPaid = (float) Sale::where('status', Sale::STATUS_FINAL)->sum('paid');
        $purchaseTotal = max((float) Purchase::sum('grand_total') - (float) PurchaseReturn::sum('total_amount'), 0);
        $rawStockValue = (float) StockBalance::select(DB::raw('COALESCE(SUM(quantity * average_cost), 0) as value'))->value('value');
        $partStockValue = (float) PartStockBalance::select(DB::raw('COALESCE(SUM(quantity * average_cost), 0) as value'))->value('value');

        return [
            'sales_total' => round($salesTotal, 2),
            'sales_due' => round(max(0, $salesTotal - $salesPaid), 2),
            'purchase_total' => round($purchaseTotal, 2),
            'raw_stock_value' => round($rawStockValue, 2),
            'part_stock_value' => round($partStockValue, 2),
            'finished_units' => (int) FinishedGood::sum('quantity'),
            'open_work_orders' => WorkOrder::whereIn('status', ['draft', 'in_production'])->count(),
            'wage_balance' => round((float) JobCard::sum('wage_amount') - (float) JobCard::sum('wage_paid_amount'), 2),
        ];
    }

    private function productionFlow($from, $to)
    {
        return JobCard::query()
            ->whereBetween('created_at', [$from, $to])
            ->select(
                'stage',
                DB::raw('COUNT(*) as cards'),
                DB::raw('COALESCE(SUM(quantity_issued), 0) as issued'),
                DB::raw('COALESCE(SUM(quantity_received), 0) as good'),
                DB::raw('COALESCE(SUM(quantity_damaged), 0) as damaged'),
                DB::raw('COALESCE(SUM(wage_amount), 0) as wage'),
                DB::raw('COALESCE(SUM(wage_paid_amount), 0) as paid')
            )
            ->groupBy('stage')
            ->orderBy('stage')
            ->get()
            ->map(fn ($row) => [
                'stage' => $row->stage,
                'cards' => (int) $row->cards,
                'issued' => (int) $row->issued,
                'good' => (int) $row->good,
                'damaged' => (int) $row->damaged,
                'pending' => max(0, (int) $row->issued - (int) $row->good - (int) $row->damaged),
                'wage' => round((float) $row->wage, 2),
                'paid' => round((float) $row->paid, 2),
            ]);
    }

    private function partFlow($from, $to)
    {
        $summary = PartStockMovement::query()
            ->whereBetween('created_at', [$from, $to])
            ->select(
                'direction',
                'stock_type',
                DB::raw('COUNT(*) as movement_count'),
                DB::raw('COALESCE(SUM(quantity), 0) as quantity')
            )
            ->groupBy('direction', 'stock_type')
            ->orderBy('stock_type')
            ->orderBy('direction')
            ->get()
            ->map(fn ($row) => [
                'direction' => $row->direction,
                'stock_type' => $row->stock_type,
                'movement_count' => (int) $row->movement_count,
                'quantity' => (int) $row->quantity,
            ]);

        $movements = PartStockMovement::query()
            ->with(['productVariant.product:id,name', 'part:id,name'])
            ->whereBetween('created_at', [$from, $to])
            ->latest('created_at')
            ->limit(50)
            ->get()
            ->map(fn (PartStockMovement $movement) => [
                'at' => $movement->created_at->format('d/m/Y h:i A'),
                'product' => $movement->productVariant->product->name.' - '.$movement->productVariant->name,
                'part' => $movement->part->name,
                'stock_type' => $movement->stock_type,
                'direction' => $movement->direction,
                'quantity' => (int) $movement->quantity,
                'balance' => (int) $movement->balance_quantity,
                'note' => $movement->note,
            ]);

        return ['summary' => $summary, 'movements' => $movements];
    }

    private function finishedGoodFlow($from, $to)
    {
        $summary = FinishedGoodMovement::query()
            ->whereBetween('created_at', [$from, $to])
            ->select(
                'direction',
                DB::raw('COUNT(*) as movement_count'),
                DB::raw('COALESCE(SUM(quantity), 0) as quantity')
            )
            ->groupBy('direction')
            ->orderBy('direction')
            ->get()
            ->map(fn ($row) => [
                'direction' => $row->direction,
                'movement_count' => (int) $row->movement_count,
                'quantity' => (int) $row->quantity,
            ]);

        $movements = FinishedGoodMovement::query()
            ->with('productVariant.product:id,name')
            ->whereBetween('created_at', [$from, $to])
            ->latest('created_at')
            ->limit(50)
            ->get()
            ->map(fn (FinishedGoodMovement $movement) => [
                'at' => $movement->created_at->format('d/m/Y h:i A'),
                'product' => $movement->productVariant->product->name.' - '.$movement->productVariant->name,
                'direction' => $movement->direction,
                'quantity' => (int) $movement->quantity,
                'balance' => (int) $movement->balance_quantity,
                'note' => $movement->note,
            ]);

        return ['summary' => $summary, 'movements' => $movements];
    }

    private function salesByProduct()
    {
        return SaleItem::query()
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->join('product_variants', 'product_variants.id', '=', 'sale_items.product_variant_id')
            ->join('products', 'products.id', '=', 'product_variants.product_id')
            ->where('sales.status', Sale::STATUS_FINAL)
            ->select(
                DB::raw("CONCAT(products.name, ' - ', product_variants.name) as product"),
                DB::raw('SUM(sale_items.quantity) as quantity'),
                DB::raw('SUM(sale_items.line_total) as total')
            )
            ->groupBy('products.name', 'product_variants.name')
            ->orderByDesc('total')
            ->limit(50)
            ->get()
            ->map(fn ($row) => [
                'product' => $row->product,
                'quantity' => (int) $row->quantity,
                'total' => round((float) $row->total, 2),
            ]);
    }

    private function workOrdersByStatus()
    {
        return WorkOrder::query()
            ->select('status', DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->orderBy('status')
            ->get()
            ->map(fn ($row) => [
                'status' => str_replace('_', ' ', $row->status),
                'count' => (int) $row->count,
            ]);
    }

    private function productionStats(): array
    {
        return [
            'cutting_batches' => CuttingBatch::count(),
            'completed_job_cards' => JobCard::where('status', 'completed')->count(),
            'open_job_cards' => JobCard::where('status', 'issued')->count(),
            'completed_work_orders' => WorkOrder::where('status', 'completed')->count(),
        ];
    }

    private function lowRawStock()
    {
        $stockByMaterial = StockBalance::query()
            ->join('raw_material_variants', 'raw_material_variants.id', '=', 'stock_balances.raw_material_variant_id')
            ->select('raw_material_variants.raw_material_id', DB::raw('COALESCE(SUM(stock_balances.quantity), 0) as quantity'))
            ->groupBy('raw_material_variants.raw_material_id')
            ->pluck('quantity', 'raw_material_variants.raw_material_id');

        return RawMaterial::query()
            ->where('is_active', true)
            ->where('alert_quantity', '>', 0)
            ->orderBy('name')
            ->get(['id', 'name', 'unit', 'alert_quantity'])
            ->map(function (RawMaterial $material) use ($stockByMaterial) {
                $current = round((float) ($stockByMaterial[$material->id] ?? 0), 3);

                return [
                    'item' => $material->name,
                    'unit' => $material->unit,
                    'current' => $current,
                    'alert' => round((float) $material->alert_quantity, 3),
                ];
            })
            ->filter(fn (array $row) => $row['current'] <= $row['alert'])
            ->take(10)
            ->values();
    }

    private function lowPartStock()
    {
        return PartStockBalance::query()
            ->with(['productVariant.product', 'part'])
            ->where('alert_quantity', '>', 0)
            ->whereColumn('quantity', '<=', 'alert_quantity')
            ->orderBy('quantity')
            ->limit(50)
            ->get()
            ->map(fn (PartStockBalance $balance) => [
                'item' => $balance->productVariant->product->name.' - '.$balance->productVariant->name,
                'part' => $balance->part->name,
                'current' => (int) $balance->quantity,
                'alert' => (int) $balance->alert_quantity,
            ]);
    }
}
