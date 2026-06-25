<?php

namespace App\Http\Controllers\BusinessSettings;

use App\Http\Controllers\Controller;
use App\Models\Part;
use App\Models\PartConversionRule;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class PartConversionRuleController extends Controller
{
    public function index()
    {
        return Inertia::render('BusinessSettings/PartConversionRules/Index', [
            'rules' => PartConversionRule::query()
                ->with(['fromProductVariant.product', 'fromPart', 'toProductVariant.product', 'toPart'])
                ->latest()
                ->get()
                ->map(fn (PartConversionRule $rule) => [
                    'id' => $rule->id,
                    'from_product_variant_id' => $rule->from_product_variant_id,
                    'from_part_id' => $rule->from_part_id,
                    'to_product_variant_id' => $rule->to_product_variant_id,
                    'to_part_id' => $rule->to_part_id,
                    'from_label' => $this->variantLabel($rule->fromProductVariant).' - '.$rule->fromPart->name,
                    'to_label' => $this->variantLabel($rule->toProductVariant).' - '.$rule->toPart->name,
                    'output_per_input' => $rule->output_per_input,
                    'is_active' => $rule->is_active,
                ]),
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
        ]);
    }

    public function store(Request $request)
    {
        PartConversionRule::create($this->validateData($request));

        return back()->with('success', 'Part conversion rule added.');
    }

    public function update(Request $request, PartConversionRule $partConversionRule)
    {
        $partConversionRule->update($this->validateData($request, $partConversionRule));

        return back()->with('success', 'Part conversion rule updated.');
    }

    public function destroy(PartConversionRule $partConversionRule)
    {
        $partConversionRule->delete();

        return back()->with('success', 'Part conversion rule deleted.');
    }

    private function validateData(Request $request, ?PartConversionRule $rule = null): array
    {
        $data = $request->validate([
            'from_product_variant_id' => ['required', 'exists:product_variants,id'],
            'from_part_id' => ['required', 'exists:parts,id'],
            'to_product_variant_id' => ['required', 'exists:product_variants,id'],
            'to_part_id' => ['required', 'exists:parts,id'],
            'output_per_input' => ['required', 'numeric', 'min:0.001'],
            'is_active' => ['boolean'],
        ]);

        if (
            (int) $data['from_product_variant_id'] === (int) $data['to_product_variant_id']
            && (int) $data['from_part_id'] === (int) $data['to_part_id']
        ) {
            validator([], [])->after(function ($validator): void {
                $validator->errors()->add('to_part_id', 'Source and target part cannot be the same.');
            })->validate();
        }

        $duplicate = PartConversionRule::query()
            ->where('from_product_variant_id', $data['from_product_variant_id'])
            ->where('from_part_id', $data['from_part_id'])
            ->where('to_product_variant_id', $data['to_product_variant_id'])
            ->where('to_part_id', $data['to_part_id'])
            ->when($rule, fn ($query) => $query->whereKeyNot($rule->id))
            ->exists();

        if ($duplicate) {
            validator([], [])->after(function ($validator): void {
                $validator->errors()->add('to_part_id', 'This conversion rule already exists.');
            })->validate();
        }

        $data['is_active'] = (bool) ($data['is_active'] ?? true);

        return $data;
    }

    private function variantLabel(ProductVariant $variant): string
    {
        return $variant->product->name.' - '.$variant->name;
    }
}
