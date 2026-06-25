<?php

namespace App\Http\Controllers\BusinessSettings;

use App\Http\Controllers\Controller;
use App\Models\CuttingYieldRule;
use App\Models\Part;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\RawMaterialVariant;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class CuttingYieldRuleController extends Controller
{
    public function index(Request $request)
    {
        $selectedVariant = null;

        if ($request->filled('product_variant_id')) {
            $selectedVariantModel = ProductVariant::query()
                ->with('product:id,name,source_type')
                ->whereHas('product', fn ($query) => $query->whereIn('source_type', [Product::SOURCE_IN_HOUSE, Product::SOURCE_BOTH]))
                ->findOrFail($request->integer('product_variant_id'));

            $selectedVariant = [
                'id' => $selectedVariantModel->id,
                'label' => $this->variantLabel($selectedVariantModel),
            ];
        }

        return Inertia::render('BusinessSettings/CuttingYieldRules/Index', [
            'rules' => CuttingYieldRule::query()
                ->with(['rawMaterialVariant.rawMaterial', 'productVariant.product', 'part'])
                ->when($selectedVariant, fn ($query) => $query->where('product_variant_id', $selectedVariant['id']))
                ->latest()
                ->get()
                ->map(fn (CuttingYieldRule $rule) => [
                    'id' => $rule->id,
                    'raw_material_variant_id' => $rule->raw_material_variant_id,
                    'product_variant_id' => $rule->product_variant_id,
                    'part_id' => $rule->part_id,
                    'material_label' => $rule->rawMaterialVariant->rawMaterial->name.' - '.$rule->rawMaterialVariant->name,
                    'product_label' => $this->variantLabel($rule->productVariant),
                    'part' => $rule->part->name,
                    'yield_per_material_unit' => $rule->yield_per_material_unit,
                    'unit' => $rule->rawMaterialVariant->rawMaterial->unit,
                    'is_active' => $rule->is_active,
                ]),
            'rawMaterialOptions' => RawMaterialVariant::query()
                ->with('rawMaterial:id,name,unit')
                ->where('is_active', true)
                ->get()
                ->map(fn (RawMaterialVariant $variant) => [
                    'id' => $variant->id,
                    'label' => $variant->rawMaterial->name.' - '.$variant->name,
                    'unit' => $variant->rawMaterial->unit,
                ])
                ->sortBy('label')
                ->values(),
            'variantOptions' => ProductVariant::query()
                ->with('product:id,name,source_type')
                ->where('is_active', true)
                ->whereHas('product', fn ($query) => $query->whereIn('source_type', [Product::SOURCE_IN_HOUSE, Product::SOURCE_BOTH]))
                ->get()
                ->map(fn (ProductVariant $variant) => [
                    'id' => $variant->id,
                    'label' => $this->variantLabel($variant),
                ])
                ->sortBy('label')
                ->values(),
            'partOptions' => Part::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'selectedVariant' => $selectedVariant,
        ]);
    }

    public function store(Request $request)
    {
        CuttingYieldRule::create($this->validateData($request));

        return back()->with('success', 'Cutting yield rule added.');
    }

    public function update(Request $request, CuttingYieldRule $cuttingYieldRule)
    {
        $cuttingYieldRule->update($this->validateData($request, $cuttingYieldRule));

        return back()->with('success', 'Cutting yield rule updated.');
    }

    public function destroy(CuttingYieldRule $cuttingYieldRule)
    {
        $cuttingYieldRule->delete();

        return back()->with('success', 'Cutting yield rule deleted.');
    }

    private function validateData(Request $request, ?CuttingYieldRule $rule = null): array
    {
        $data = $request->validate([
            'raw_material_variant_id' => ['required', 'exists:raw_material_variants,id'],
            'product_variant_id' => ['required', 'exists:product_variants,id'],
            'part_id' => ['required', 'exists:parts,id'],
            'yield_per_material_unit' => ['required', 'numeric', 'min:0.001'],
            'is_active' => ['boolean'],
        ]);

        validator($data, [
            'raw_material_variant_id' => [
                Rule::unique('cutting_yield_rules')
                    ->where('product_variant_id', $data['product_variant_id'])
                    ->where('part_id', $data['part_id'])
                    ->ignore($rule),
            ],
        ], [
            'raw_material_variant_id.unique' => 'This material/product/part yield rule already exists.',
        ])->validate();

        $data['is_active'] = (bool) ($data['is_active'] ?? true);

        return $data;
    }

    private function variantLabel(ProductVariant $variant): string
    {
        return $variant->product->name.' - '.$variant->name;
    }
}
