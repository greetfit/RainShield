<?php

namespace App\Http\Controllers\Masters;

use App\Http\Controllers\Controller;
use App\Models\Designation;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class DesignationController extends Controller
{
    public function index()
    {
        return Inertia::render('Masters/Designations/Index', [
            'designations' => Designation::withCount('staff')
                ->orderByRaw('priority_level is null')
                ->orderBy('priority_level')
                ->orderBy('name')
                ->get(['id', 'name', 'description', 'priority_level', 'is_active']),
        ]);
    }

    public function store(Request $request)
    {
        Designation::create($this->validateData($request));

        return back()->with('success', 'Designation added.');
    }

    public function update(Request $request, Designation $designation)
    {
        $designation->update($this->validateData($request, $designation));

        return back()->with('success', 'Designation updated.');
    }

    public function destroy(Designation $designation)
    {
        $designation->delete();

        return back()->with('success', 'Designation deleted.');
    }

    private function validateData(Request $request, ?Designation $designation = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('designations', 'name')->ignore($designation)],
            'description' => ['nullable', 'string'],
            'priority_level' => ['nullable', 'integer', 'min:1'],
            'is_active' => ['boolean'],
        ]);
    }
}
