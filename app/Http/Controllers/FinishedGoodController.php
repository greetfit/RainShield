<?php

namespace App\Http\Controllers;

use App\Models\FinishedGood;
use App\Models\FinishedGoodMovement;
use App\Models\ProductVariant;
use App\Services\FinishedGoodsService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class FinishedGoodController extends Controller
{
    public function index()
    {
        $variantOptions = ProductVariant::query()
            ->where('is_active', true)
            ->with('product:id,name')
            ->get()
            ->map(fn ($v) => [
                'id' => $v->id,
                'label' => $v->product->name.' - '.$v->name,
            ])
            ->sortBy('label')
            ->values();

        return Inertia::render('FinishedGoods/Index', [
            'rows' => FinishedGood::with('productVariant.product')
                ->get()
                ->map(fn ($f) => [
                    'id' => $f->id,
                    'product_variant_id' => $f->product_variant_id,
                    'label' => $f->productVariant->product->name.' - '.$f->productVariant->name,
                    'quantity' => $f->quantity,
                    'average_cost' => round((float) $f->average_cost, 4),
                    'value' => round((float) $f->quantity * (float) $f->average_cost, 2),
                    'alert_quantity' => $f->alert_quantity,
                ])
                ->sortBy('label')
                ->values(),
            'variantOptions' => $variantOptions,
        ]);
    }

    public function adjust(Request $request, FinishedGoodsService $finishedGoods)
    {
        $data = $request->validate([
            'product_variant_id' => ['required', 'exists:product_variants,id'],
            'counted_quantity' => ['required', 'integer', 'min:0'],
            'alert_quantity' => ['nullable', 'integer', 'min:0'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $finishedGoods->adjust(
            (int) $data['product_variant_id'],
            (int) $data['counted_quantity'],
            $data['note'] ?? null,
            (int) ($data['alert_quantity'] ?? 0),
        );

        return back()->with('success', 'Finished-goods stock adjusted.');
    }

    public function opening(Request $request, FinishedGoodsService $finishedGoods)
    {
        $data = $request->validate([
            'product_variant_id' => ['required', 'exists:product_variants,id'],
            'quantity' => ['required', 'integer', 'min:0'],
            'unit_cost' => ['nullable', 'numeric', 'min:0'],
            'alert_quantity' => ['nullable', 'integer', 'min:0'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $finishedGoods->openingStock(
            (int) $data['product_variant_id'],
            (int) $data['quantity'],
            (float) ($data['unit_cost'] ?? 0),
            (int) ($data['alert_quantity'] ?? 0),
            $data['note'] ?? null,
        );

        return back()->with('success', 'Finished-goods opening stock saved.');
    }

    public function movements()
    {
        return Inertia::render('FinishedGoods/Movements', [
            'movements' => FinishedGoodMovement::query()
                ->with('productVariant.product:id,name')
                ->latest('id')
                ->limit(300)
                ->get()
                ->map(fn ($m) => [
                    'id' => $m->id,
                    'label' => $m->productVariant->product->name.' - '.$m->productVariant->name,
                    'direction' => $m->direction,
                    'quantity' => $m->quantity,
                    'balance_quantity' => $m->balance_quantity,
                    'note' => $m->note,
                    'at' => $m->created_at->format('Y-m-d H:i'),
                ]),
        ]);
    }
}
