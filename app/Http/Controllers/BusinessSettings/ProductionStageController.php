<?php

namespace App\Http\Controllers\BusinessSettings;

use App\Http\Controllers\Controller;
use App\Models\ProductionStage;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class ProductionStageController extends Controller
{
    public function index()
    {
        return Inertia::render('BusinessSettings/ProductionStages/Index', [
            'stages' => ProductionStage::query()
                ->orderBy('priority_level')
                ->orderBy('name')
                ->get(['id', 'name', 'slug', 'priority_level', 'description', 'is_active']),
        ]);
    }

    public function store(Request $request)
    {
        ProductionStage::create($this->validateData($request));

        return back()->with('success', 'Production stage added.');
    }

    public function update(Request $request, ProductionStage $productionStage)
    {
        $productionStage->update($this->validateData($request, $productionStage));

        return back()->with('success', 'Production stage updated.');
    }

    public function destroy(ProductionStage $productionStage)
    {
        if ($productionStage->slug && (
            \App\Models\JobCard::where('stage', $productionStage->slug)->exists()
            || \App\Models\PieceRate::where('stage', $productionStage->slug)->exists()
        )) {
            return back()->with('error', 'This stage is already used. Deactivate it instead of deleting.');
        }

        $productionStage->delete();

        return back()->with('success', 'Production stage deleted.');
    }

    private function validateData(Request $request, ?ProductionStage $stage = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'nullable',
                'string',
                'max:20',
                'regex:/^[a-z0-9_]+$/',
                Rule::unique('production_stages', 'slug')->ignore($stage),
            ],
            'priority_level' => ['required', 'integer', 'min:1'],
            'description' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ], [
            'slug.regex' => 'Use lowercase letters, numbers and underscores only.',
        ]);

        $data['slug'] = $data['slug'] ?: Str::slug($data['name'], '_');
        $data['is_active'] = (bool) ($data['is_active'] ?? true);

        validator($data, [
            'slug' => [
                'required',
                'max:20',
                'regex:/^[a-z0-9_]+$/',
                Rule::unique('production_stages', 'slug')->ignore($stage),
            ],
        ])->validate();

        return $data;
    }
}
