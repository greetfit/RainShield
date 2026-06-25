<?php

namespace App\Http\Controllers\Masters;

use App\Http\Controllers\Controller;
use App\Models\ProductLayer;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class ProductLayerController extends Controller
{
    public function index()
    {
        return Inertia::render('Masters/ProductLayers/Index', [
            'layers' => ProductLayer::withCount('variants')->orderBy('name')->get(['id', 'name', 'description', 'is_active']),
        ]);
    }

    public function store(Request $request)
    {
        ProductLayer::create($this->validateData($request));

        return back()->with('success', 'Layer added.');
    }

    public function update(Request $request, ProductLayer $layer)
    {
        $layer->update($this->validateData($request, $layer));

        return back()->with('success', 'Layer updated.');
    }

    public function destroy(ProductLayer $layer)
    {
        $layer->delete();

        return back()->with('success', 'Layer deleted.');
    }

    private function validateData(Request $request, ?ProductLayer $layer = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('product_layers', 'name')->ignore($layer)],
            'description' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ]);
    }
}
