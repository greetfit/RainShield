<?php

namespace App\Http\Controllers\Masters;

use App\Http\Controllers\Controller;
use App\Models\Part;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class PartController extends Controller
{
    public function index()
    {
        return Inertia::render('Masters/Parts/Index', [
            'parts' => Part::orderBy('name')->get(['id', 'name', 'description', 'is_active']),
        ]);
    }

    public function store(Request $request)
    {
        Part::create($this->validateData($request));

        return back()->with('success', 'Part added.');
    }

    public function update(Request $request, Part $part)
    {
        $part->update($this->validateData($request, $part->id));

        return back()->with('success', 'Part updated.');
    }

    public function destroy(Part $part)
    {
        $part->delete();

        return back()->with('success', 'Part deleted.');
    }

    private function validateData(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('parts', 'name')->ignore($ignoreId)],
            'description' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ]);
    }
}
