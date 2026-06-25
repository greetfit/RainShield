<?php

namespace App\Http\Controllers;

use App\Models\JobCard;
use App\Models\JobCardPayment;
use App\Models\CuttingBatch;
use App\Models\Expense;
use App\Models\Purchase;
use App\Models\PurchasePayment;
use App\Models\PurchaseReturn;
use App\Models\Sale;
use App\Models\SalePayment;
use App\Models\SaleReturn;
use App\Models\RecoveryCutting;
use App\Services\ProductCostingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class AccountingController extends Controller
{
    public function index(Request $request, ProductCostingService $costing)
    {
        $salesTotal = (float) Sale::where('status', Sale::STATUS_FINAL)->sum('total');
        $salesReturned = (float) SaleReturn::sum('total_amount');
        $salesPaid = (float) Sale::where('status', Sale::STATUS_FINAL)->sum('paid');
        $purchaseTotal = max((float) Purchase::sum('grand_total') - (float) PurchaseReturn::sum('total_amount'), 0);
        $purchasePaid = (float) PurchasePayment::sum('amount');
        $wageTotal = (float) JobCard::sum('wage_amount') + (float) CuttingBatch::sum('wage_amount') + (float) RecoveryCutting::sum('wage_amount');
        $wagePaid = (float) JobCard::sum('wage_paid_amount') + (float) CuttingBatch::sum('wage_paid_amount') + (float) RecoveryCutting::sum('wage_paid_amount');
        $expenseTotal = (float) Expense::sum('amount');

        return Inertia::render('Accounting/Index', [
            'summary' => [
                'receivables' => round(max(0, $salesTotal - $salesReturned - $salesPaid), 2),
                'payables' => round(max(0, $purchaseTotal - $purchasePaid), 2),
                'wage_balance' => round($wageTotal - $wagePaid, 2),
                'net_cash' => round($salesPaid - $purchasePaid - $wagePaid - $expenseTotal, 2),
                'sales_total' => round($salesTotal - $salesReturned, 2),
                'sales_returns' => round($salesReturned, 2),
                'sales_paid' => round($salesPaid, 2),
                'purchase_total' => round($purchaseTotal, 2),
                'purchase_paid' => round($purchasePaid, 2),
                'wage_total' => round($wageTotal, 2),
                'wage_paid' => round($wagePaid, 2),
                'expense_total' => round($expenseTotal, 2),
            ],
            'cashByMethod' => [
                'in' => $this->paymentTotalsByMethod(SalePayment::query()),
                'out' => $this->paymentTotalsByMethod(PurchasePayment::query()),
            'wages' => $this->wagePaymentTotals(),
                'expenses' => $this->expenseTotalsByMethod(),
            ],
            'counts' => [
                'receivables' => $this->receivables()->count(),
                'payables' => $this->payables()->count(),
                'wage_balances' => $this->wageBalanceRows()->count(),
                'expenses' => Expense::count(),
                'transactions' => $this->recentTransactions()->count(),
            ],
        ]);
    }

    public function dailyProfitLossPage(Request $request, ProductCostingService $costing)
    {
        $profitDate = $request->date('profit_date') ?? now();

        return Inertia::render('Accounting/DailyProfitLoss', [
            'dailyProfitLoss' => $this->dailyProfitLoss($profitDate->toDateString(), $costing),
        ]);
    }

    public function customerDueInvoices()
    {
        return Inertia::render('Accounting/CustomerDueInvoices', [
            'receivables' => $this->receivables(),
        ]);
    }

    public function supplierPayables()
    {
        return Inertia::render('Accounting/SupplierPayables', [
            'payables' => $this->payables(),
        ]);
    }

    public function wageBalances()
    {
        return Inertia::render('Accounting/WageBalances', [
            'wageBalances' => $this->wageBalanceRows(),
        ]);
    }

    public function moneyMovement()
    {
        return Inertia::render('Accounting/MoneyMovement', [
            'transactions' => $this->recentTransactions(),
        ]);
    }

    private function dailyProfitLoss(string $date, ProductCostingService $costing): array
    {
        $sales = Sale::query()
            ->with(['customer', 'items.productVariant.product'])
            ->where('status', Sale::STATUS_FINAL)
            ->whereDate('sold_at', $date)
            ->orderBy('sold_at')
            ->get();

        $costs = [];
        $salesCost = 0;
        $itemsCount = 0;

        foreach ($sales as $sale) {
            foreach ($sale->items as $item) {
                $variantId = (int) $item->product_variant_id;
                if (! array_key_exists($variantId, $costs)) {
                    $costs[$variantId] = (float) $item->unit_cost > 0
                        ? (float) $item->unit_cost
                        : ($item->productVariant
                        ? (float) $costing->costVariant($item->productVariant)['unit_cost']
                        : 0);
                }

                $quantity = (int) $item->quantity;
                $itemsCount += $quantity;
                $salesCost += $quantity * $costs[$variantId];
            }
        }

        $subtotal = (float) $sales->sum('subtotal');
        $returnsTotal = (float) SaleReturn::query()
            ->whereDate('returned_on', $date)
            ->sum('total_amount');
        $discount = (float) $sales->sum('discount');
        $tax = (float) $sales->sum('tax');
        $shipping = (float) $sales->sum('shipping');
        $total = max((float) $sales->sum('total') - $returnsTotal, 0);
        $paid = (float) $sales->sum('paid');
        $grossProfit = $total - $salesCost;
        $expenses = Expense::query()
            ->with('category:id,name')
            ->whereDate('expense_on', $date)
            ->orderBy('expense_on')
            ->get();
        $expenseTotal = (float) $expenses->sum('amount');
        $netProfit = $grossProfit - $expenseTotal;

        return [
            'date' => $date,
            'summary' => [
                'invoice_count' => $sales->count(),
                'items_count' => $itemsCount,
                'subtotal' => round($subtotal, 2),
                'discount' => round($discount, 2),
                'tax' => round($tax, 2),
                'shipping' => round($shipping, 2),
                'sales_total' => round($total, 2),
                'sales_returns' => round($returnsTotal, 2),
                'estimated_cost' => round($salesCost, 2),
                'gross_profit' => round($grossProfit, 2),
                'gross_margin_percent' => $total > 0 ? round(($grossProfit / $total) * 100, 2) : 0,
                'expenses' => round($expenseTotal, 2),
                'net_profit' => round($netProfit, 2),
                'net_margin_percent' => $total > 0 ? round(($netProfit / $total) * 100, 2) : 0,
                'collected' => round($paid, 2),
                'due' => round(max(0, $total - $paid), 2),
            ],
            'sales' => $sales->map(fn (Sale $sale) => [
                'id' => $sale->id,
                'invoice_no' => $sale->invoice_no,
                'time' => optional($sale->sold_at)->format('h:i A'),
                'customer' => $sale->customer?->name ?? 'Walk-in customer',
                'items_count' => $sale->items->sum('quantity'),
                'total' => round((float) $sale->total, 2),
                'paid' => round((float) $sale->paid, 2),
                'due' => round((float) $sale->due, 2),
            ]),
            'expenses' => $expenses->map(fn (Expense $expense) => [
                'id' => $expense->id,
                'category' => $expense->category?->name ?? '-',
                'amount' => round((float) $expense->amount, 2),
                'method' => $expense->payment_method ?: '-',
                'reference' => $expense->reference ?: '-',
                'notes' => $expense->notes,
            ]),
        ];
    }

    private function paymentTotalsByMethod($query): array
    {
        return $query
            ->select('method', DB::raw('COALESCE(SUM(amount), 0) as total'))
            ->groupBy('method')
            ->orderBy('method')
            ->get()
            ->map(fn ($row) => [
                'method' => $row->method ?: 'Unspecified',
                'total' => round((float) $row->total, 2),
            ])
            ->all();
    }

    private function expenseTotalsByMethod(): array
    {
        return Expense::query()
            ->select('payment_method', DB::raw('COALESCE(SUM(amount), 0) as total'))
            ->groupBy('payment_method')
            ->orderBy('payment_method')
            ->get()
            ->map(fn ($row) => [
                'method' => $row->payment_method ?: 'Unspecified',
                'total' => round((float) $row->total, 2),
            ])
            ->all();
    }

    private function receivables()
    {
        return Sale::query()
            ->with('customer')
            ->where('status', Sale::STATUS_FINAL)
            ->withSum('returns as returned_amount', 'total_amount')
            ->latest('sold_at')
            ->get()
            ->map(function (Sale $sale) {
                $netTotal = max((float) $sale->total - (float) ($sale->returned_amount ?? 0), 0);

                return [
                    'id' => $sale->id,
                    'date' => optional($sale->sold_at)->format('d/m/Y'),
                    'reference' => $sale->invoice_no,
                    'party' => $sale->customer?->name ?? 'Walk-in customer',
                    'total' => round($netTotal, 2),
                    'paid' => round((float) $sale->paid, 2),
                    'due' => round(max($netTotal - (float) $sale->paid, 0), 2),
                ];
            })
            ->filter(fn (array $sale) => $sale['due'] > 0)
            ->values();
    }

    private function payables()
    {
        return Purchase::query()
            ->withSum('payments as paid_amount', 'amount')
            ->withSum('returns as returned_amount', 'total_amount')
            ->latest('purchased_on')
            ->get()
            ->map(function (Purchase $purchase) {
                $returned = (float) ($purchase->returned_amount ?? 0);
                $netTotal = max((float) $purchase->grand_total - $returned, 0);
                $paid = (float) ($purchase->paid_amount ?? 0);

                return [
                    'id' => $purchase->id,
                    'date' => optional($purchase->purchased_on)->format('d/m/Y'),
                    'reference' => $purchase->reference ?: '-',
                    'party' => $purchase->supplier_name,
                    'total' => round($netTotal, 2),
                    'paid' => round($paid, 2),
                    'due' => round(max($netTotal - $paid, 0), 2),
                ];
            })
            ->filter(fn (array $purchase) => $purchase['due'] > 0)
            ->values();
    }

    private function wageBalanceRows()
    {
        $jobCards = JobCard::query()
            ->with(['staff', 'workOrder'])
            ->whereRaw('wage_amount <> wage_paid_amount')
            ->latest('updated_at')
            ->get()
            ->map(fn (JobCard $jobCard) => [
                'id' => $jobCard->id,
                'reference' => $jobCard->workOrder?->code ?? 'Job card #'.$jobCard->id,
                'staff' => $jobCard->staff?->name ?? '-',
                'stage' => $jobCard->stage,
                'wage' => round((float) $jobCard->wage_amount, 2),
                'paid' => round((float) $jobCard->wage_paid_amount, 2),
                'balance' => round((float) $jobCard->wage_balance, 2),
            ]);

        $cutting = CuttingBatch::query()
            ->with('staff')
            ->whereRaw('wage_amount <> wage_paid_amount')
            ->latest('updated_at')
            ->get()
            ->map(fn (CuttingBatch $batch) => [
                'id' => 'cutting-'.$batch->id,
                'reference' => $batch->code ?? 'Cutting batch #'.$batch->id,
                'staff' => $batch->staff?->name ?? '-',
                'stage' => 'cutting',
                'wage' => round((float) $batch->wage_amount, 2),
                'paid' => round((float) $batch->wage_paid_amount, 2),
                'balance' => round((float) $batch->wage_amount - (float) $batch->wage_paid_amount, 2),
            ]);

        $recovery = RecoveryCutting::query()
            ->with('staff')
            ->whereRaw('wage_amount <> wage_paid_amount')
            ->latest('updated_at')
            ->get()
            ->map(fn (RecoveryCutting $cutting) => [
                'id' => 'recovery-'.$cutting->id,
                'reference' => $cutting->code ?? 'Recovery cutting #'.$cutting->id,
                'staff' => $cutting->staff?->name ?? '-',
                'stage' => 'recovery cutting',
                'wage' => round((float) $cutting->wage_amount, 2),
                'paid' => round((float) $cutting->wage_paid_amount, 2),
                'balance' => round((float) $cutting->wage_amount - (float) $cutting->wage_paid_amount, 2),
            ]);

        return $jobCards->merge($cutting)->merge($recovery)->values();
    }

    private function wagePaymentTotals(): array
    {
        $manual = collect($this->paymentTotalsByMethod(JobCardPayment::query()));
        $cuttingPaid = (float) CuttingBatch::sum('wage_paid_amount') + (float) RecoveryCutting::sum('wage_paid_amount');

        if ($cuttingPaid > 0) {
            $manual->push(['method' => 'Cutting batches', 'total' => round($cuttingPaid, 2)]);
        }

        return $manual->values()->all();
    }

    private function recentTransactions()
    {
        $sales = SalePayment::query()
            ->with('sale.customer')
            ->latest('paid_on')
            ->limit(8)
            ->get()
            ->map(fn (SalePayment $payment) => [
                'date' => optional($payment->paid_on)->format('d/m/Y'),
                'type' => 'Sale receipt',
                'reference' => $payment->sale?->invoice_no ?? '-',
                'party' => $payment->sale?->customer?->name ?? 'Walk-in customer',
                'method' => $payment->method ?: '-',
                'amount' => round((float) $payment->amount, 2),
                'direction' => 'in',
            ]);

        $purchases = PurchasePayment::query()
            ->with('purchase')
            ->latest('paid_on')
            ->limit(8)
            ->get()
            ->map(fn (PurchasePayment $payment) => [
                'date' => optional($payment->paid_on)->format('d/m/Y'),
                'type' => 'Supplier payment',
                'reference' => $payment->purchase?->reference ?: '-',
                'party' => $payment->purchase?->supplier_name ?? '-',
                'method' => $payment->method ?: '-',
                'amount' => round((float) $payment->amount, 2),
                'direction' => 'out',
            ]);

        $wages = JobCardPayment::query()
            ->with('jobCard.staff')
            ->latest('paid_on')
            ->limit(8)
            ->get()
            ->map(fn (JobCardPayment $payment) => [
                'date' => optional($payment->paid_on)->format('d/m/Y'),
                'type' => 'Wage payment',
                'reference' => 'Job card #'.$payment->job_card_id,
                'party' => $payment->jobCard?->staff?->name ?? '-',
                'method' => $payment->method ?: '-',
                'amount' => round((float) $payment->amount, 2),
                'direction' => 'out',
            ]);

        $expenses = Expense::query()
            ->with('category')
            ->latest('expense_on')
            ->limit(8)
            ->get()
            ->map(fn (Expense $expense) => [
                'date' => optional($expense->expense_on)->format('d/m/Y'),
                'type' => 'Expense',
                'reference' => $expense->reference ?: '-',
                'party' => $expense->category?->name ?? '-',
                'method' => $expense->payment_method ?: '-',
                'amount' => round((float) $expense->amount, 2),
                'direction' => 'out',
            ]);

        $cuttingWages = CuttingBatch::query()
            ->with('staff')
            ->where('wage_paid_amount', '>', 0)
            ->latest('completed_at')
            ->limit(8)
            ->get()
            ->map(fn (CuttingBatch $batch) => [
                'date' => optional($batch->completed_at ?? $batch->cut_on)->format('d/m/Y'),
                'type' => 'Cutting wage',
                'reference' => $batch->code ?? 'Cutting batch #'.$batch->id,
                'party' => $batch->staff?->name ?? '-',
                'method' => '-',
                'amount' => round((float) $batch->wage_paid_amount, 2),
                'direction' => 'out',
            ]);

        $recoveryWages = RecoveryCutting::query()
            ->with('staff')
            ->where('wage_paid_amount', '>', 0)
            ->latest('completed_at')
            ->limit(8)
            ->get()
            ->map(fn (RecoveryCutting $cutting) => [
                'date' => optional($cutting->completed_at ?? $cutting->cut_on)->format('d/m/Y'),
                'type' => 'Recovery cutting wage',
                'reference' => $cutting->code ?? 'Recovery cutting #'.$cutting->id,
                'party' => $cutting->staff?->name ?? '-',
                'method' => '-',
                'amount' => round((float) $cutting->wage_paid_amount, 2),
                'direction' => 'out',
            ]);

        return $sales
            ->merge($purchases)
            ->merge($wages)
            ->merge($expenses)
            ->merge($cuttingWages)
            ->merge($recoveryWages)
            ->sortByDesc('date')
            ->take(12)
            ->values();
    }
}
