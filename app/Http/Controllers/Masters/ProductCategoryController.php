<?php

namespace App\Http\Controllers\Masters;

use App\Http\Controllers\Controller;
use App\Models\ProductCategory;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class ProductCategoryController extends Controller
{
    public function index()
    {
        return Inertia::render('Masters/ProductCategories/Index', [
            'categories' => ProductCategory::withCount('products')
                ->orderBy('name')
                ->get(['id', 'name', 'description', 'is_active']),
        ]);
    }

    public function store(Request $request)
    {
        ProductCategory::create($this->validateData($request));

        return back()->with('success', 'Product category added.');
    }

    public function update(Request $request, ProductCategory $category)
    {
        $category->update($this->validateData($request, $category));

        return back()->with('success', 'Product category updated.');
    }

    public function destroy(ProductCategory $category)
    {
        $category->delete();

        return back()->with('success', 'Product category deleted.');
    }

    private function validateData(Request $request, ?ProductCategory $category = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('product_categories', 'name')->ignore($category)],
            'description' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ]);
    }
}
