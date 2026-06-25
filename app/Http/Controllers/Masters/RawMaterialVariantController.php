<?php

namespace App\Http\Controllers\Masters;

use App\Http\Controllers\Controller;
use App\Models\RawMaterial;
use App\Models\RawMaterialVariant;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RawMaterialVariantController extends Controller
{
    public function store(Request $request, RawMaterial $rawMaterial)
    {
        $data = $this->validateData($request, $rawMaterial->id);
        $rawMaterial->variants()->create($data);

        return back()->with('success', 'Variant added.');
    }

    public function update(Request $request, RawMaterialVariant $variant)
    {
        $data = $this->validateData($request, $variant->raw_material_id, $variant->id);
        $variant->update($data);

        return back()->with('success', 'Variant updated.');
    }

    public function destroy(RawMaterialVariant $variant)
    {
        $variant->delete();

        return back()->with('success', 'Variant deleted.');
    }

    private function validateData(Request $request, int $materialId, ?int $ignoreId = null): array
    {
        return $request->validate([
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('raw_material_variants', 'name')
                    ->where('raw_material_id', $materialId)
                    ->ignore($ignoreId),
            ],
            'code' => ['nullable', 'string', 'max:50'],
            'is_active' => ['boolean'],
        ]);
    }
}
