<?php

namespace App\Services;

use App\Models\FinishedGood;
use App\Models\Sale;
use App\Models\SalePayment;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaleService
{
    public function __construct(private FinishedGoodsService $finishedGoods) {}

    public function create(array $data): Sale
    {
        return DB::transaction(function () use ($data) {
            $subtotal = 0.0;
            $lines = [];

            foreach ($data['items'] as $item) {
                $quantity = (int) $item['quantity'];
                $unitPrice = round((float) $item['unit_price'], 2);
                $unitCost = (float) (FinishedGood::where('product_variant_id', (int) $item['product_variant_id'])->value('average_cost') ?? 0);
                $lineTotal = round($quantity * $unitPrice, 2);
                $subtotal += $lineTotal;
                $lines[] = [
                    'product_variant_id' => (int) $item['product_variant_id'],
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'unit_cost' => round($unitCost, 4),
                    'line_total' => $lineTotal,
                ];
            }

            $discount = round((float) ($data['discount'] ?? 0), 2);
            $tax = round((float) ($data['tax'] ?? 0), 2);
            $shipping = round((float) ($data['shipping'] ?? 0), 2);
            $total = max(0, round($subtotal - $discount + $tax + $shipping, 2));
            $paid = min(round((float) ($data['paid'] ?? 0), 2), $total);

            $sale = Sale::create([
                'invoice_no' => $this->nextInvoiceNo(),
                'customer_id' => $data['customer_id'] ?? null,
                'pos_session_id' => $data['pos_session_id'] ?? null,
                'sold_at' => $data['sold_at'] ?? now(),
                'status' => Sale::STATUS_FINAL,
                'payment_status' => $this->paymentStatus($total, $paid),
                'payment_method' => $data['payment_method'] ?? null,
                'subtotal' => round($subtotal, 2),
                'discount' => $discount,
                'tax' => $tax,
                'shipping' => $shipping,
                'total' => $total,
                'paid' => $paid,
                'notes' => $data['notes'] ?? null,
                'created_by' => Auth::id(),
            ]);

            $sale->items()->createMany($lines);

            if ($paid > 0) {
                $sale->payments()->create([
                    'paid_on' => now()->toDateString(),
                    'amount' => $paid,
                    'method' => $data['payment_method'] ?? null,
                    'reference' => $data['payment_reference'] ?? null,
                    'created_by' => Auth::id(),
                ]);
            }

            foreach ($lines as $line) {
                $this->finishedGoods->remove(
                    (int) $line['product_variant_id'],
                    (int) $line['quantity'],
                    Sale::class,
                    $sale->id,
                    'Sale '.$sale->invoice_no,
                );
            }

            return $sale->load('items.productVariant.product', 'customer', 'payments');
        });
    }

    public function addPayment(Sale $sale, array $data): Sale
    {
        return DB::transaction(function () use ($sale, $data) {
            if ($sale->status !== Sale::STATUS_FINAL) {
                throw ValidationException::withMessages(['amount' => 'Payments cannot be added to a void sale.']);
            }

            $amount = round((float) $data['amount'], 2);
            $sale->payments()->create([
                'paid_on' => $data['paid_on'] ?? now()->toDateString(),
                'amount' => $amount,
                'method' => $data['method'] ?? null,
                'reference' => $data['reference'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by' => Auth::id(),
            ]);

            $newPaid = round((float) $sale->paid + $amount, 2);
            $sale->update([
                'paid' => $newPaid,
                'payment_status' => $this->paymentStatus($this->netTotal($sale), $newPaid),
            ]);

            return $sale->refresh();
        });
    }

    public function updatePayment(SalePayment $payment, array $data): Sale
    {
        return DB::transaction(function () use ($payment, $data) {
            $sale = $payment->sale;

            if ($sale->status !== Sale::STATUS_FINAL) {
                throw ValidationException::withMessages(['amount' => 'Payments cannot be updated on a void sale.']);
            }

            $payment->update([
                'paid_on' => $data['paid_on'] ?? now()->toDateString(),
                'amount' => round((float) $data['amount'], 2),
                'method' => $data['method'] ?? null,
                'reference' => $data['reference'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            return $this->refreshPaidTotals($sale);
        });
    }

    public function deletePayment(SalePayment $payment): Sale
    {
        return DB::transaction(function () use ($payment) {
            $sale = $payment->sale;

            if ($sale->status !== Sale::STATUS_FINAL) {
                throw ValidationException::withMessages(['amount' => 'Payments cannot be deleted from a void sale.']);
            }

            $payment->delete();

            return $this->refreshPaidTotals($sale);
        });
    }

    public function void(Sale $sale): void
    {
        DB::transaction(function () use ($sale) {
            if ($sale->status === Sale::STATUS_VOID) {
                return;
            }

            $sale->loadMissing('items');
            foreach ($sale->items as $item) {
                $this->finishedGoods->add(
                    (int) $item->product_variant_id,
                    (int) $item->quantity,
                    Sale::class,
                    $sale->id,
                    'Void sale '.$sale->invoice_no,
                );
            }

            $sale->update(['status' => Sale::STATUS_VOID]);
        });
    }

    public function available(int $productVariantId): int
    {
        return (int) (FinishedGood::where('product_variant_id', $productVariantId)->value('quantity') ?? 0);
    }

    public function syncPaymentStatus(Sale $sale): Sale
    {
        return $this->refreshPaidTotals($sale);
    }

    private function paymentStatus(float $total, float $paid): string
    {
        if ($paid <= 0) {
            return 'unpaid';
        }

        if ($paid < $total) {
            return 'partial';
        }

        return $paid > $total ? 'overpaid' : 'paid';
    }

    private function refreshPaidTotals(Sale $sale): Sale
    {
        $paid = round((float) $sale->payments()->sum('amount'), 2);
        $sale->update([
            'paid' => $paid,
            'payment_status' => $this->paymentStatus($this->netTotal($sale), $paid),
        ]);

        return $sale->refresh();
    }

    private function netTotal(Sale $sale): float
    {
        $returned = (float) $sale->returns()->sum('total_amount');

        return round(max(0, (float) $sale->total - $returned), 2);
    }

    private function nextInvoiceNo(): string
    {
        $id = ((int) Sale::query()->max('id')) + 1;

        do {
            $number = 'SI-'.str_pad((string) $id, 5, '0', STR_PAD_LEFT);
            $id++;
        } while (Sale::where('invoice_no', $number)->exists());

        return $number;
    }
}
