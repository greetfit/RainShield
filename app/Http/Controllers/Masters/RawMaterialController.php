<?php

namespace App\Http\Controllers\Masters;

use App\Http\Controllers\Controller;
use App\Models\RawMaterial;
use App\Models\UnitOfMeasure;
use Illuminate\Http\Request;
use Inertia\Inertia;

class RawMaterialController extends Controller
{
    public function index()
    {
        return Inertia::render('Masters/RawMaterials/Index', [
            'materials' => RawMaterial::withCount('variants')
                ->orderBy('name')
                ->get(['id', 'name', 'unit', 'alert_quantity', 'description', 'is_active']),
            'units' => UnitOfMeasure::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->pluck('name')
                ->values(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);
        RawMaterial::create($data);

        return back()->with('success', 'Raw material added.');
    }

    public function update(Request $request, RawMaterial $rawMaterial)
    {
        $data = $this->validateData($request);
        $rawMaterial->update($data);

        return back()->with('success', 'Raw material updated.');
    }

    public function destroy(RawMaterial $rawMaterial)
    {
        $rawMaterial->delete();

        return back()->with('success', 'Raw material deleted.');
    }

    public function variants(RawMaterial $rawMaterial)
    {
        return Inertia::render('Masters/RawMaterials/Variants', [
            'material' => $rawMaterial->only('id', 'name', 'unit'),
            'variants' => $rawMaterial->variants()
                ->orderBy('name')
                ->get(['id', 'raw_material_id', 'name', 'code', 'is_active']),
        ]);
    }

    private function validateData(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'unit' => ['required', 'string', 'max:30'],
            'alert_quantity' => ['nullable', 'numeric', 'min:0'],
            'description' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ]);
    }
}
