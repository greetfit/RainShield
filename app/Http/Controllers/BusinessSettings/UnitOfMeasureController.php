<?php

namespace App\Http\Controllers\BusinessSettings;

use App\Http\Controllers\Controller;
use App\Models\RawMaterial;
use App\Models\UnitOfMeasure;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class UnitOfMeasureController extends Controller
{
    public function index()
    {
        return Inertia::render('BusinessSettings/UnitOfMeasures/Index', [
            'units' => UnitOfMeasure::query()
                ->orderBy('name')
                ->get()
                ->map(fn (UnitOfMeasure $unit) => [
                    'id' => $unit->id,
                    'name' => $unit->name,
                    'description' => $unit->description,
                    'is_active' => $unit->is_active,
                    'materials_count' => RawMaterial::where('unit', $unit->name)->count(),
                ]),
        ]);
    }

    public function store(Request $request)
    {
        UnitOfMeasure::create($this->validateData($request));

        return back()->with('success', 'Unit of measure added.');
    }

    public function update(Request $request, UnitOfMeasure $unitOfMeasure)
    {
        $oldName = $unitOfMeasure->name;
        $data = $this->validateData($request, $unitOfMeasure);
        $unitOfMeasure->update($data);

        if ($oldName !== $unitOfMeasure->name) {
            RawMaterial::where('unit', $oldName)->update(['unit' => $unitOfMeasure->name]);
        }

        return back()->with('success', 'Unit of measure updated.');
    }

    public function destroy(UnitOfMeasure $unitOfMeasure)
    {
        if (RawMaterial::where('unit', $unitOfMeasure->name)->exists()) {
            return back()->withErrors(['unit' => 'This unit is used by raw materials. Deactivate it instead.']);
        }

        $unitOfMeasure->delete();

        return back()->with('success', 'Unit of measure deleted.');
    }

    private function validateData(Request $request, ?UnitOfMeasure $unit = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:30', Rule::unique('unit_of_measures', 'name')->ignore($unit)],
            'description' => ['nullable', 'string', 'max:255'],
            'is_active' => ['boolean'],
        ]);
    }
}
