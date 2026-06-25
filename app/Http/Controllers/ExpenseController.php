<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\PaymentMethod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class ExpenseController extends Controller
{
    public function index()
    {
        return Inertia::render('Accounting/Expenses', [
            'expenses' => Expense::query()
                ->with('category:id,name')
                ->latest('expense_on')
                ->latest('id')
                ->get()
                ->map(fn (Expense $expense) => $this->expenseRow($expense)),
            'categories' => ExpenseCategory::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (ExpenseCategory $category) => [
                    'value' => $category->id,
                    'label' => $category->name,
                ]),
            'paymentMethods' => PaymentMethod::activeOptions(),
        ]);
    }

    public function store(Request $request)
    {
        Expense::create([
            ...$this->validateData($request),
            'created_by' => Auth::id(),
        ]);

        return back()->with('success', 'Expense recorded.');
    }

    public function update(Request $request, Expense $expense)
    {
        $expense->update($this->validateData($request));

        return back()->with('success', 'Expense updated.');
    }

    public function destroy(Expense $expense)
    {
        $expense->delete();

        return back()->with('success', 'Expense deleted.');
    }

    private function validateData(Request $request): array
    {
        return $request->validate([
            'expense_on' => ['required', 'date'],
            'expense_category_id' => ['required', 'exists:expense_categories,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_method' => ['nullable', 'string', 'max:40'],
            'reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]);
    }

    private function expenseRow(Expense $expense): array
    {
        return [
            'id' => $expense->id,
            'expense_on' => $expense->expense_on?->format('d/m/Y'),
            'expense_on_input' => $expense->expense_on?->toDateString(),
            'category_id' => $expense->expense_category_id,
            'category' => $expense->category?->name ?? '-',
            'amount' => (float) $expense->amount,
            'payment_method' => $expense->payment_method,
            'reference' => $expense->reference,
            'notes' => $expense->notes,
        ];
    }
}
