<?php

namespace App\Http\Controllers\Masters;

use App\Http\Controllers\Controller;
use App\Models\RawMaterial;
use App\Models\RawMaterialVariant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

    public function duplicate(RawMaterialVariant $variant)
    {
        DB::transaction(function () use ($variant) {
            $copy = $variant->replicate();
            $copy->name = $this->duplicateName($variant);
            $copy->save();
        });

        return back()->with('success', 'Raw material variant duplicated.');
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

    private function duplicateName(RawMaterialVariant $variant): string
    {
        $base = 'Copy of '.$variant->name;
        $name = $base;
        $count = 2;

        while (RawMaterialVariant::query()
            ->where('raw_material_id', $variant->raw_material_id)
            ->where('name', $name)
            ->exists()) {
            $name = "Copy {$count} of {$variant->name}";
            $count++;
        }

        return $name;
    }
}
