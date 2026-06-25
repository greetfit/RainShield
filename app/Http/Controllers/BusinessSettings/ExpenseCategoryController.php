<?php

namespace App\Http\Controllers\BusinessSettings;

use App\Http\Controllers\Controller;
use App\Models\ExpenseCategory;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class ExpenseCategoryController extends Controller
{
    public function index()
    {
        return Inertia::render('BusinessSettings/ExpenseCategories/Index', [
            'categories' => ExpenseCategory::query()
                ->orderBy('name')
                ->get(['id', 'name', 'description', 'is_active']),
        ]);
    }

    public function store(Request $request)
    {
        ExpenseCategory::create($this->validateData($request));

        return back()->with('success', 'Expense category added.');
    }

    public function update(Request $request, ExpenseCategory $expenseCategory)
    {
        $expenseCategory->update($this->validateData($request, $expenseCategory));

        return back()->with('success', 'Expense category updated.');
    }

    public function destroy(ExpenseCategory $expenseCategory)
    {
        if ($expenseCategory->expenses()->exists()) {
            return back()->with('error', 'This category has expenses and cannot be deleted.');
        }

        $expenseCategory->delete();

        return back()->with('success', 'Expense category deleted.');
    }

    private function validateData(Request $request, ?ExpenseCategory $expenseCategory = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120', Rule::unique('expense_categories', 'name')->ignore($expenseCategory)],
            'description' => ['nullable', 'string', 'max:255'],
            'is_active' => ['boolean'],
        ]);
    }
}
