<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\FinishedGood;
use App\Models\PaymentMethod;
use App\Models\PosSession;
use App\Models\ProductVariant;
use App\Models\Sale;
use App\Models\SalePayment;
use App\Services\ProductCostingService;
use App\Services\SaleService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class SaleController extends Controller
{
    public function index(ProductCostingService $costing)
    {
        return Inertia::render('Sales/Index', [
            'sales' => Sale::query()
                ->with('customer')
                ->with('payments')
                ->withSum('returns as returned_amount', 'total_amount')
                ->withCount('items')
                ->latest('sold_at')
                ->latest('id')
                ->get()
                ->map(fn (Sale $sale) => $this->saleRow($sale)),
            'customers' => $this->customerOptions(),
            'products' => $this->productOptions($costing),
            'paymentMethods' => PaymentMethod::activeOptions(),
        ]);
    }

    public function pos(ProductCostingService $costing)
    {
        $session = $this->activePosSession();

        return Inertia::render('Sales/Pos', [
            'customers' => $this->customerOptions(),
            'products' => $this->productOptions($costing),
            'paymentMethods' => PaymentMethod::activeOptions(),
            'posSession' => $session ? $this->posSessionRow($session) : null,
            'posSummary' => $session ? $this->posSessionSummary($session) : null,
        ]);
    }

    public function posSessions()
    {
        return Inertia::render('Sales/PosSessions', [
            'sessions' => PosSession::query()
                ->with('openedBy:id,name', 'closedBy:id,name')
                ->withCount('sales')
                ->latest('opened_at')
                ->get()
                ->map(fn (PosSession $session) => [
                    'id' => $session->id,
                    'session_no' => $session->session_no,
                    'opened_by' => $session->openedBy?->name ?? '-',
                    'closed_by' => $session->closedBy?->name ?? '-',
                    'opened_at' => $session->opened_at?->format('d/m/Y h:i A'),
                    'closed_at' => $session->closed_at?->format('d/m/Y h:i A'),
                    'opening_amount' => (float) $session->opening_amount,
                    'expected_closing_amount' => (float) $session->expected_closing_amount,
                    'closing_amount' => (float) $session->closing_amount,
                    'difference_amount' => (float) $session->difference_amount,
                    'sales_count' => (int) $session->sales_count,
                    'status' => $session->closed_at ? 'closed' : 'open',
                ]),
        ]);
    }

    public function openPosSession(Request $request)
    {
        $data = $request->validate([
            'opening_amount' => ['required', 'numeric', 'min:0'],
            'opening_notes' => ['nullable', 'string'],
        ]);

        if ($this->activePosSession()) {
            throw ValidationException::withMessages([
                'opening_amount' => 'A POS register session is already open for this user.',
            ]);
        }

        PosSession::create([
            'session_no' => $this->nextPosSessionNo(),
            'opened_by' => Auth::id(),
            'opened_at' => now(),
            'opening_amount' => round((float) $data['opening_amount'], 2),
            'expected_closing_amount' => round((float) $data['opening_amount'], 2),
            'opening_notes' => $data['opening_notes'] ?? null,
        ]);

        return back()->with('success', 'POS register opened.');
    }

    public function closePosSession(Request $request, PosSession $posSession)
    {
        if ((int) $posSession->opened_by !== (int) Auth::id() || $posSession->closed_at) {
            throw ValidationException::withMessages([
                'closing_amount' => 'This POS register session cannot be closed.',
            ]);
        }

        $data = $request->validate([
            'closing_amount' => ['required', 'numeric', 'min:0'],
            'closing_notes' => ['nullable', 'string'],
        ]);

        $summary = $this->posSessionSummary($posSession);
        $closing = round((float) $data['closing_amount'], 2);

        $posSession->update([
            'closed_by' => Auth::id(),
            'closed_at' => now(),
            'expected_closing_amount' => $summary['expected_cash'],
            'closing_amount' => $closing,
            'difference_amount' => round($closing - $summary['expected_cash'], 2),
            'closing_notes' => $data['closing_notes'] ?? null,
        ]);

        return back()->with('success', 'POS register closed.');
    }

    public function show(Sale $sale)
    {
        $sale->load('customer', 'items.productVariant.product', 'payments');

        return Inertia::render('Sales/Show', [
            'sale' => $this->saleDetail($sale),
        ]);
    }

    public function print(Sale $sale)
    {
        $sale->load('customer', 'items.productVariant.product', 'payments');

        return Inertia::render('Sales/PrintInvoice', [
            'sale' => $this->saleDetail($sale),
        ]);
    }

    public function receipt(Sale $sale)
    {
        $sale->load('customer', 'items.productVariant.product', 'payments');

        return Inertia::render('Sales/ThermalReceipt', [
            'sale' => $this->saleDetail($sale),
        ]);
    }

    public function store(Request $request, SaleService $service)
    {
        $data = $this->validateSale($request);
        if (($data['print_mode'] ?? null) === 'receipt') {
            $session = $this->activePosSession();
            if (!$session) {
                throw ValidationException::withMessages([
                    'pos_session' => 'Open the POS register before completing a POS sale.',
                ]);
            }

            $data['pos_session_id'] = $session->id;
        }
        $this->assertStockAvailable($data);
        $sale = $service->create($data);

        if (($data['print_mode'] ?? null) === 'receipt') {
            return redirect()->route('sales.receipt', $sale);
        }

        if (($data['print_mode'] ?? null) === 'invoice') {
            return redirect()->route('sales.print', $sale);
        }

        return back()->with('success', 'Sale invoice recorded and finished-goods stock updated.');
    }

    public function payment(Request $request, Sale $sale, SaleService $service)
    {
        $data = $this->validatePayment($request);

        $service->addPayment($sale, $data);

        return back()->with('success', 'Sale payment recorded.');
    }

    public function updatePayment(Request $request, SalePayment $payment, SaleService $service)
    {
        $service->updatePayment($payment, $this->validatePayment($request));

        return back()->with('success', 'Sale payment updated.');
    }

    public function destroyPayment(SalePayment $payment, SaleService $service)
    {
        $service->deletePayment($payment);

        return back()->with('success', 'Sale payment deleted.');
    }

    public function void(Sale $sale, SaleService $service)
    {
        $service->void($sale);

        return back()->with('success', 'Sale voided and stock restored.');
    }

    private function validateSale(Request $request): array
    {
        return $request->validate([
            'customer_id' => ['nullable', 'exists:customers,id'],
            'sold_at' => ['required', 'date'],
            'payment_method' => ['nullable', 'string', 'max:40'],
            'payment_reference' => ['nullable', 'string', 'max:255'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'tax' => ['nullable', 'numeric', 'min:0'],
            'shipping' => ['nullable', 'numeric', 'min:0'],
            'paid' => ['nullable', 'numeric', 'min:0'],
            'print_mode' => ['nullable', 'in:invoice,receipt'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_variant_id' => ['required', 'exists:product_variants,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
        ]);
    }

    private function activePosSession(): ?PosSession
    {
        return PosSession::query()
            ->where('opened_by', Auth::id())
            ->whereNull('closed_at')
            ->latest('opened_at')
            ->first();
    }

    private function nextPosSessionNo(): string
    {
        $id = ((int) PosSession::query()->max('id')) + 1;

        do {
            $number = 'POS-'.str_pad((string) $id, 5, '0', STR_PAD_LEFT);
            $id++;
        } while (PosSession::where('session_no', $number)->exists());

        return $number;
    }

    private function posSessionSummary(PosSession $session): array
    {
        $session->loadMissing('sales.payments');
        $sales = $session->sales->where('status', Sale::STATUS_FINAL);
        $payments = $sales->flatMap->payments;
        $cashPayments = $payments->filter(fn (SalePayment $payment) => strtolower((string) $payment->method) === 'cash');

        $cashSales = round((float) $cashPayments->sum('amount'), 2);
        $otherPayments = round((float) $payments->sum('amount') - $cashSales, 2);
        $expectedCash = round((float) $session->opening_amount + $cashSales, 2);

        return [
            'sales_count' => $sales->count(),
            'sales_total' => round((float) $sales->sum('total'), 2),
            'cash_sales' => $cashSales,
            'other_payments' => $otherPayments,
            'expected_cash' => $expectedCash,
        ];
    }

    private function posSessionRow(PosSession $session): array
    {
        return [
            'id' => $session->id,
            'session_no' => $session->session_no,
            'opened_at' => $session->opened_at?->format('d/m/Y h:i A'),
            'opening_amount' => (float) $session->opening_amount,
        ];
    }

    private function validatePayment(Request $request): array
    {
        return $request->validate([
            'paid_on' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'method' => ['nullable', 'string', 'max:40'],
            'reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]);
    }

    private function assertStockAvailable(array $data): void
    {
        $needed = collect($data['items'])
            ->groupBy('product_variant_id')
            ->map(fn ($items) => $items->sum(fn ($item) => (int) $item['quantity']));

        foreach ($needed as $variantId => $quantity) {
            $available = (int) (FinishedGood::where('product_variant_id', $variantId)->value('quantity') ?? 0);
            if ($quantity > $available) {
                $label = ProductVariant::with('product:id,name')->find($variantId);
                $name = $label ? $label->product->name.' - '.$label->name : 'selected product';
                throw ValidationException::withMessages([
                    'items' => "Only {$available} finished goods available for {$name}.",
                ]);
            }
        }
    }

    private function customerOptions()
    {
        return Customer::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'phone', 'email'])
            ->map(fn (Customer $customer) => [
                'value' => $customer->id,
                'label' => $customer->name,
                'description' => $customer->phone ?: $customer->email,
            ]);
    }

    private function productOptions(ProductCostingService $costing)
    {
        return FinishedGood::query()
            ->with('productVariant.product:id,name')
            ->where('quantity', '>', 0)
            ->get()
            ->map(function (FinishedGood $stock) use ($costing) {
                $cost = $costing->costVariant($stock->productVariant);
                $purchaseCost = (float) ($stock->average_cost ?? 0);
                if ($purchaseCost > 0) {
                    $variant = $stock->productVariant;
                    $markupType = $variant->profit_markup_type ?? 'percent';
                    $markupPercent = (float) ($variant->profit_margin_percent ?? 0);
                    $markupAmount = (float) ($variant->profit_markup_amount ?? 0);
                    $sellingPrice = match ($markupType) {
                        'flat' => $markupAmount > 0 ? round($purchaseCost + $markupAmount, 2) : round((float) ($variant->selling_price ?? 0), 2),
                        default => $markupPercent > 0 ? round($purchaseCost * (1 + ($markupPercent / 100)), 2) : round((float) ($variant->selling_price ?? 0), 2),
                    };
                    $cost = array_merge($cost, [
                        'unit_cost' => round($purchaseCost, 2),
                        'selling_price' => $sellingPrice,
                        'profit_markup_type' => $markupType,
                        'profit_margin_percent' => $markupPercent,
                        'profit_markup_amount' => $markupAmount,
                    ]);
                }

                $price = (float) ($cost['selling_price'] ?? 0);
                $markupLabel = ($cost['profit_markup_type'] ?? 'percent') === 'flat'
                    ? 'Profit '.number_format((float) ($cost['profit_markup_amount'] ?? 0), 2)
                    : 'Profit '.number_format((float) ($cost['profit_margin_percent'] ?? 0), 2).'%';

                return [
                    'value' => $stock->product_variant_id,
                    'label' => $stock->productVariant->product->name.' - '.$stock->productVariant->name,
                    'description' => 'Stock '.$stock->quantity.' / Cost '.number_format((float) $cost['unit_cost'], 2).' / '.$markupLabel,
                    'sku' => $stock->productVariant->sku,
                    'cost' => (float) $cost['unit_cost'],
                    'profit_markup_type' => $cost['profit_markup_type'] ?? 'percent',
                    'profit_margin_percent' => (float) $cost['profit_margin_percent'],
                    'profit_markup_amount' => (float) ($cost['profit_markup_amount'] ?? 0),
                    'price' => $price,
                    'stock' => $stock->quantity,
                ];
            })
            ->sortBy('label')
            ->values();
    }

    private function saleRow(Sale $sale): array
    {
        return [
            'id' => $sale->id,
            'invoice_no' => $sale->invoice_no,
            'customer' => $sale->customer?->name ?? 'Walk-in',
            'sold_at' => $sale->sold_at?->format('d/m/Y h:i A'),
            'items_count' => $sale->items_count,
            'status' => $sale->status,
            'payment_status' => $sale->payment_status,
            'payment_method' => $sale->payment_method,
            'total' => (float) $sale->total,
            'returned' => (float) ($sale->returned_amount ?? 0),
            'net_total' => max(0, (float) $sale->total - (float) ($sale->returned_amount ?? 0)),
            'paid' => (float) $sale->paid,
            'due' => $sale->due,
            'payments' => $sale->payments
                ->sortByDesc('paid_on')
                ->values()
                ->map(fn (SalePayment $payment) => $this->paymentRow($payment)),
        ];
    }

    private function saleDetail(Sale $sale): array
    {
        return [
            ...$this->saleRow($sale->loadCount('items')),
            'subtotal' => (float) $sale->subtotal,
            'discount' => (float) $sale->discount,
            'tax' => (float) $sale->tax,
            'shipping' => (float) $sale->shipping,
            'notes' => $sale->notes,
            'items' => $sale->items->map(fn ($item) => [
                'product' => $item->productVariant->product->name.' - '.$item->productVariant->name,
                'quantity' => $item->quantity,
                'unit_price' => (float) $item->unit_price,
                'line_total' => (float) $item->line_total,
            ]),
            'payments' => $sale->payments->map(fn (SalePayment $payment) => $this->paymentRow($payment)),
        ];
    }

    private function paymentRow(SalePayment $payment): array
    {
        return [
            'id' => $payment->id,
            'paid_on' => $payment->paid_on?->format('d/m/Y'),
            'paid_on_input' => $payment->paid_on?->toDateString(),
            'amount' => (float) $payment->amount,
            'method' => $payment->method,
            'reference' => $payment->reference,
            'notes' => $payment->notes,
        ];
    }
}
