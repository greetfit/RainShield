<?php

namespace App\Http\Controllers\Masters;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductGrade;
use App\Models\ProductLayer;
use App\Models\ProductSize;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ProductController extends Controller
{
    public function index()
    {
        return Inertia::render('Masters/Products/Index', [
            'products' => Product::with('productCategory:id,name')
                ->withCount('variants')
                ->orderBy('name')
                ->get(['id', 'name', 'product_category_id', 'source_type', 'category', 'description', 'is_active'])
                ->map(fn ($product) => [
                    'id' => $product->id,
                    'name' => $product->name,
                    'product_category_id' => $product->product_category_id,
                    'source_type' => $product->source_type ?? Product::SOURCE_IN_HOUSE,
                    'source_label' => $product->sourceLabel(),
                    'category' => $product->productCategory?->name ?? $product->category,
                    'description' => $product->description,
                    'is_active' => $product->is_active,
                    'variants_count' => $product->variants_count,
                ]),
            'categoryOptions' => ProductCategory::where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name']),
            'sourceOptions' => Product::sourceOptions(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);
        Product::create($data);

        return back()->with('success', 'Product added.');
    }

    public function update(Request $request, Product $product)
    {
        $data = $this->validateData($request);
        $product->update($data);

        return back()->with('success', 'Product updated.');
    }

    public function destroy(Product $product)
    {
        $product->delete();

        return back()->with('success', 'Product deleted.');
    }

    public function variants(Product $product)
    {
        return Inertia::render('Masters/Products/Variants', [
            'product' => $product->only('id', 'name'),
            'variants' => $product->variants()
                ->with(['productSize:id,name', 'productLayer:id,name', 'productGrade:id,name', 'finishedGood:product_variant_id,quantity,average_cost,alert_quantity'])
                ->orderBy('name')
                ->get(['id', 'product_id', 'name', 'product_size_id', 'size', 'product_layer_id', 'layer', 'product_grade_id', 'grade', 'sku', 'selling_price', 'profit_margin_percent', 'profit_markup_type', 'profit_markup_amount', 'is_active'])
                ->map(fn ($variant) => [
                    'id' => $variant->id,
                    'product_id' => $variant->product_id,
                    'name' => $variant->name,
                    'product_size_id' => $variant->product_size_id,
                    'size' => $variant->productSize?->name ?? $variant->size,
                    'product_layer_id' => $variant->product_layer_id,
                    'layer' => $variant->productLayer?->name ?? $variant->layer,
                    'product_grade_id' => $variant->product_grade_id,
                    'grade' => $variant->productGrade?->name ?? $variant->grade,
                    'sku' => $variant->sku,
                    'selling_price' => (float) $variant->selling_price,
                    'profit_margin_percent' => (float) $variant->profit_margin_percent,
                    'profit_markup_type' => $variant->profit_markup_type ?? 'percent',
                    'profit_markup_amount' => (float) $variant->profit_markup_amount,
                    'stock_quantity' => (int) ($variant->finishedGood?->quantity ?? 0),
                    'stock_average_cost' => (float) ($variant->finishedGood?->average_cost ?? 0),
                    'stock_alert_quantity' => (int) ($variant->finishedGood?->alert_quantity ?? 0),
                    'stock_value' => round((int) ($variant->finishedGood?->quantity ?? 0) * (float) ($variant->finishedGood?->average_cost ?? 0), 2),
                    'is_active' => $variant->is_active,
                ]),
            'sizeOptions' => ProductSize::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'layerOptions' => ProductLayer::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'gradeOptions' => ProductGrade::where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    private function validateData(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'product_category_id' => ['nullable', 'exists:product_categories,id'],
            'source_type' => ['nullable', 'in:in_house,outsourced,both'],
            'description' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ]);

        $data['category'] = $data['product_category_id']
            ? ProductCategory::whereKey($data['product_category_id'])->value('name')
            : null;
        $data['source_type'] = $data['source_type'] ?? Product::SOURCE_IN_HOUSE;

        return $data;
    }
}
