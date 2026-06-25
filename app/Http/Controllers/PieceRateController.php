<?php

namespace App\Http\Controllers;

use App\Models\PieceRate;
use App\Models\ProductionStage;
use App\Models\ProductVariant;
use App\Models\Staff;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class PieceRateController extends Controller
{
    public function index()
    {
        return Inertia::render('PieceRates/Index', [
            'rates' => PieceRate::with(['productVariant.product', 'staff'])
                ->orderBy('stage')
                ->get()
                ->map(fn (PieceRate $rate) => [
                    'id' => $rate->id,
                    'stage' => $rate->stage,
                    'staff_id' => $rate->staff_id,
                    'product_variant_id' => $rate->product_variant_id,
                    'staff' => $rate->staff?->name ?? 'Default staff',
                    'scope' => $this->rateScope($rate),
                    'rate' => $rate->rate,
                ]),
            'stages' => ProductionStage::options(),
            'staffOptions' => Staff::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (Staff $staff) => ['id' => $staff->id, 'label' => $staff->name])
                ->values(),
            'variantOptions' => ProductVariant::with('product:id,name')
                ->where('is_active', true)
                ->get()
                ->map(fn (ProductVariant $variant) => ['id' => $variant->id, 'label' => $variant->product->name.' - '.$variant->name])
                ->sortBy('label')
                ->values(),
        ]);
    }

    public function store(Request $request)
    {
        PieceRate::create($this->validateData($request));

        return back()->with('success', 'Piece rate added.');
    }

    public function update(Request $request, PieceRate $pieceRate)
    {
        $pieceRate->update($this->validateData($request, $pieceRate->id));

        return back()->with('success', 'Piece rate updated.');
    }

    public function destroy(PieceRate $pieceRate)
    {
        $pieceRate->delete();

        return back()->with('success', 'Piece rate removed.');
    }

    private function validateData(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'stage' => ['required', Rule::exists('production_stages', 'slug')->where('is_active', true)],
            'staff_id' => ['nullable', 'exists:staff,id'],
            'product_variant_id' => [
                'nullable',
                'exists:product_variants,id',
                Rule::unique('piece_rates')
                    ->where(fn ($query) => $query
                        ->where('stage', $request->input('stage'))
                        ->where('staff_id', $request->input('staff_id'))
                        ->where('product_variant_id', $request->input('product_variant_id')))
                    ->ignore($ignoreId),
            ],
            'rate' => ['required', 'numeric', 'min:0'],
        ]);
    }

    private function rateScope(PieceRate $rate): string
    {
        $staff = $rate->staff?->name ?? 'Default staff';
        $variant = $rate->productVariant
            ? $rate->productVariant->product->name.' - '.$rate->productVariant->name
            : 'All variants';

        return $staff.' / '.$variant;
    }
}
