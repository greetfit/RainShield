<?php

namespace App\Http\Controllers\Masters;

use App\Http\Controllers\Controller;
use App\Models\ProductSize;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class ProductSizeController extends Controller
{
    public function index()
    {
        return Inertia::render('Masters/ProductSizes/Index', [
            'sizes' => ProductSize::withCount('variants')->orderBy('name')->get(['id', 'name', 'description', 'is_active']),
        ]);
    }

    public function store(Request $request)
    {
        ProductSize::create($this->validateData($request));

        return back()->with('success', 'Size added.');
    }

    public function update(Request $request, ProductSize $size)
    {
        $size->update($this->validateData($request, $size));

        return back()->with('success', 'Size updated.');
    }

    public function destroy(ProductSize $size)
    {
        $size->delete();

        return back()->with('success', 'Size deleted.');
    }

    private function validateData(Request $request, ?ProductSize $size = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('product_sizes', 'name')->ignore($size)],
            'description' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ]);
    }
}
