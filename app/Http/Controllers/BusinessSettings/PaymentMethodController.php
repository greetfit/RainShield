<?php

namespace App\Http\Controllers\BusinessSettings;

use App\Http\Controllers\Controller;
use App\Models\PaymentMethod;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class PaymentMethodController extends Controller
{
    public function index()
    {
        return Inertia::render('BusinessSettings/PaymentMethods/Index', [
            'methods' => PaymentMethod::query()
                ->orderBy('label')
                ->get(['id', 'name', 'label', 'description', 'is_active']),
        ]);
    }

    public function store(Request $request)
    {
        PaymentMethod::create($this->validateData($request));

        return back()->with('success', 'Payment method added.');
    }

    public function update(Request $request, PaymentMethod $paymentMethod)
    {
        $paymentMethod->update($this->validateData($request, $paymentMethod));

        return back()->with('success', 'Payment method updated.');
    }

    public function destroy(PaymentMethod $paymentMethod)
    {
        $paymentMethod->delete();

        return back()->with('success', 'Payment method deleted.');
    }

    private function validateData(Request $request, ?PaymentMethod $paymentMethod = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:40', 'regex:/^[a-z0-9_-]+$/', Rule::unique('payment_methods', 'name')->ignore($paymentMethod)],
            'label' => ['required', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:255'],
            'is_active' => ['boolean'],
        ]);

        $data['name'] = strtolower($data['name']);

        return $data;
    }
}
