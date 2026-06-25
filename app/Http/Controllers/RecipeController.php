<?php

namespace App\Http\Controllers;

use App\Models\Part;
use App\Models\ProductVariant;
use App\Models\RawMaterialVariant;
use App\Models\RecipeMaterial;
use App\Models\RecipePart;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class RecipeController extends Controller
{
    public function edit(ProductVariant $variant)
    {
        $variant->load('product');

        return Inertia::render('Recipes/Edit', [
            'variant' => [
                'id' => $variant->id,
                'name' => $variant->name,
                'layer' => $variant->layer,
                'product' => $variant->product->name,
            ],
            'partOptions' => Part::where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name']),
            'materialOptions' => RawMaterialVariant::query()
                ->with('rawMaterial:id,name,unit,is_active')
                ->where('is_active', true)
                ->whereHas('rawMaterial', fn ($query) => $query->where('is_active', true))
                ->get(['id', 'raw_material_id', 'name'])
                ->sortBy(fn (RawMaterialVariant $variant) => $variant->rawMaterial->name.' '.$variant->name)
                ->map(fn (RawMaterialVariant $variant) => [
                    'id' => $variant->id,
                    'label' => $variant->rawMaterial->name.' - '.$variant->name,
                    'unit' => $variant->rawMaterial->unit,
                ])
                ->values(),
            'materials' => $variant->recipeMaterials()
                ->with('rawMaterialVariant.rawMaterial:id,name,unit')
                ->get()
                ->map(fn ($material) => [
                    'id' => $material->id,
                    'raw_material_variant_id' => $material->raw_material_variant_id,
                    'name' => $material->rawMaterialVariant->rawMaterial->name.' - '.$material->rawMaterialVariant->name,
                    'quantity' => $material->quantity,
                    'unit' => $material->unit ?: $material->rawMaterialVariant->rawMaterial->unit,
                ]),
            'parts' => $variant->recipeParts()
                ->with('part:id,name')
                ->get()
                ->map(fn ($part) => [
                    'id' => $part->id,
                    'part_id' => $part->part_id,
                    'name' => $part->part->name,
                    'quantity_per_garment' => $part->quantity_per_garment,
                ]),
        ]);
    }

    public function storeMaterial(Request $request, ProductVariant $variant)
    {
        $data = $request->validate([
            'raw_material_variant_id' => [
                'required',
                'exists:raw_material_variants,id',
                Rule::unique('recipe_materials')->where('product_variant_id', $variant->id),
            ],
            'quantity' => ['required', 'numeric', 'min:0.001'],
        ]);

        $rawMaterialVariant = RawMaterialVariant::with('rawMaterial:id,unit')
            ->findOrFail($data['raw_material_variant_id']);

        $variant->recipeMaterials()->create([
            'raw_material_variant_id' => $rawMaterialVariant->id,
            'quantity' => $data['quantity'],
            'unit' => $rawMaterialVariant->rawMaterial->unit,
        ]);

        return back()->with('success', 'Material added to recipe.');
    }

    public function updateMaterial(Request $request, RecipeMaterial $material)
    {
        $data = $request->validate([
            'quantity' => ['required', 'numeric', 'min:0.001'],
        ]);

        $material->update($data);

        return back()->with('success', 'Material quantity updated.');
    }

    public function destroyMaterial(RecipeMaterial $material)
    {
        $material->delete();

        return back()->with('success', 'Material removed from recipe.');
    }

    public function storePart(Request $request, ProductVariant $variant)
    {
        $data = $request->validate([
            'part_id' => [
                'required',
                'exists:parts,id',
                Rule::unique('recipe_parts')->where('product_variant_id', $variant->id),
            ],
            'quantity_per_garment' => ['required', 'integer', 'min:1'],
        ]);

        $variant->recipeParts()->create($data);

        return back()->with('success', 'Part added to recipe.');
    }

    public function updatePart(Request $request, RecipePart $part)
    {
        $data = $request->validate([
            'quantity_per_garment' => ['required', 'integer', 'min:1'],
        ]);

        $part->update($data);

        return back()->with('success', 'Part quantity updated.');
    }

    public function destroyPart(RecipePart $part)
    {
        $part->delete();

        return back()->with('success', 'Part removed from recipe.');
    }
}
