<?php

namespace App\Http\Controllers\Masters;

use App\Http\Controllers\Controller;
use App\Models\ProductGrade;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class ProductGradeController extends Controller
{
    public function index()
    {
        return Inertia::render('Masters/ProductGrades/Index', [
            'grades' => ProductGrade::withCount('variants')->orderBy('name')->get(['id', 'name', 'description', 'is_active']),
        ]);
    }

    public function store(Request $request)
    {
        ProductGrade::create($this->validateData($request));

        return back()->with('success', 'Grade added.');
    }

    public function update(Request $request, ProductGrade $grade)
    {
        $grade->update($this->validateData($request, $grade));

        return back()->with('success', 'Grade updated.');
    }

    public function destroy(ProductGrade $grade)
    {
        $grade->delete();

        return back()->with('success', 'Grade deleted.');
    }

    private function validateData(Request $request, ?ProductGrade $grade = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('product_grades', 'name')->ignore($grade)],
            'description' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ]);
    }
}
