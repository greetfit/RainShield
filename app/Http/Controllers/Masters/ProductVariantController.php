<?php

namespace App\Http\Controllers\Masters;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductGrade;
use App\Models\ProductLayer;
use App\Models\ProductSize;
use App\Models\ProductVariant;
use App\Services\FinishedGoodsService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProductVariantController extends Controller
{
    public function store(Request $request, Product $product)
    {
        $data = $this->validateData($request);
        $product->variants()->create($data);

        return back()->with('success', 'Variant added.');
    }

    public function update(Request $request, ProductVariant $variant)
    {
        $data = $this->validateData($request, $variant->id);
        $variant->update($data);

        return back()->with('success', 'Variant updated.');
    }

    public function destroy(ProductVariant $variant)
    {
        $variant->delete();

        return back()->with('success', 'Variant deleted.');
    }

    public function openingStock(Request $request, ProductVariant $variant, FinishedGoodsService $finishedGoods)
    {
        $data = $request->validate([
            'quantity' => ['required', 'integer', 'min:0'],
            'unit_cost' => ['nullable', 'numeric', 'min:0'],
            'alert_quantity' => ['nullable', 'integer', 'min:0'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $finishedGoods->openingStock(
            $variant->id,
            (int) $data['quantity'],
            (float) ($data['unit_cost'] ?? 0),
            (int) ($data['alert_quantity'] ?? 0),
            $data['note'] ?? null,
        );

        return back()->with('success', 'Variant opening stock saved.');
    }

    private function validateData(Request $request, ?int $ignoreId = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'product_size_id' => ['nullable', 'exists:product_sizes,id'],
            'product_layer_id' => ['nullable', 'exists:product_layers,id'],
            'product_grade_id' => ['nullable', 'exists:product_grades,id'],
            'sku' => ['nullable', 'string', 'max:60', Rule::unique('product_variants', 'sku')->ignore($ignoreId)],
            'selling_price' => ['nullable', 'numeric', 'min:0'],
            'profit_margin_percent' => ['nullable', 'numeric', 'min:0'],
            'profit_markup_type' => ['nullable', 'in:percent,flat'],
            'profit_markup_amount' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['boolean'],
        ]);

        $data['size'] = $data['product_size_id'] ? ProductSize::whereKey($data['product_size_id'])->value('name') : null;
        $data['layer'] = $data['product_layer_id'] ? ProductLayer::whereKey($data['product_layer_id'])->value('name') : null;
        $data['grade'] = $data['product_grade_id'] ? ProductGrade::whereKey($data['product_grade_id'])->value('name') : null;
        $data['selling_price'] = round((float) ($data['selling_price'] ?? 0), 2);
        $data['profit_margin_percent'] = round((float) ($data['profit_margin_percent'] ?? 0), 2);
        $data['profit_markup_type'] = $data['profit_markup_type'] ?? 'percent';
        $data['profit_markup_amount'] = round((float) ($data['profit_markup_amount'] ?? 0), 2);

        if ($data['profit_markup_type'] === 'percent') {
            $data['profit_markup_amount'] = 0;
        } else {
            $data['profit_margin_percent'] = 0;
        }

        return $data;
    }
}
