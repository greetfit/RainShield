<?php

namespace App\Http\Controllers;

use App\Models\Delivery;
use App\Models\FinishedGood;
use App\Models\ProductVariant;
use App\Services\FinishedGoodsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class DeliveryController extends Controller
{
    public function index()
    {
        return Inertia::render('Deliveries/Index', [
            'deliveries' => Delivery::with('productVariant.product')
                ->latest('id')
                ->get()
                ->map(fn ($d) => [
                    'id' => $d->id,
                    'code' => $d->code,
                    'customer_name' => $d->customer_name,
                    'product' => $d->productVariant->product->name.' · '.$d->productVariant->name,
                    'quantity' => $d->quantity,
                    'dispatched_on' => $d->dispatched_on->toDateString(),
                    'delivered_on' => $d->delivered_on?->toDateString(),
                    'lead_time' => $d->lead_time,
                    'status' => $d->status,
                ]),
        ]);
    }

    public function create()
    {
        // Only variants that currently have finished stock.
        $options = FinishedGood::with('productVariant.product')
            ->where('quantity', '>', 0)
            ->get()
            ->map(fn ($f) => [
                'id' => $f->product_variant_id,
                'label' => $f->productVariant->product->name.' · '.$f->productVariant->name,
                'available' => $f->quantity,
            ])
            ->sortBy('label')
            ->values();

        return Inertia::render('Deliveries/Create', [
            'variantOptions' => $options,
            'today' => now()->toDateString(),
        ]);
    }

    public function store(Request $request, FinishedGoodsService $finishedGoods)
    {
        $data = $request->validate([
            'customer_name' => ['required', 'string', 'max:255'],
            'product_variant_id' => ['required', 'exists:product_variants,id'],
            'quantity' => ['required', 'integer', 'min:1'],
            'dispatched_on' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        $available = $finishedGoods->available($data['product_variant_id']);
        if ($available < $data['quantity']) {
            throw ValidationException::withMessages([
                'quantity' => "Only {$available} in finished-goods stock for this variant.",
            ]);
        }

        DB::transaction(function () use ($data, $finishedGoods) {
            $delivery = Delivery::create([
                ...$data,
                'status' => 'dispatched',
                'created_by' => Auth::id(),
            ]);
            $delivery->update(['code' => 'DLV-'.str_pad((string) $delivery->id, 5, '0', STR_PAD_LEFT)]);

            $finishedGoods->remove(
                $data['product_variant_id'],
                $data['quantity'],
                Delivery::class,
                $delivery->id,
                'Dispatch '.($delivery->code ?? 'DLV#'.$delivery->id),
            );
        });

        return redirect()->route('deliveries.index')->with('success', 'Delivery dispatched.');
    }

    public function markDelivered(Request $request, Delivery $delivery)
    {
        $data = $request->validate([
            'delivered_on' => ['required', 'date', 'after_or_equal:'.$delivery->dispatched_on->toDateString()],
        ]);

        $delivery->update([
            'delivered_on' => $data['delivered_on'],
            'status' => 'delivered',
        ]);

        return back()->with('success', 'Marked as delivered.');
    }
}
