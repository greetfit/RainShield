<?php

namespace App\Http\Controllers\Masters;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class CustomerController extends Controller
{
    public function index()
    {
        return Inertia::render('Masters/Customers/Index', [
            'customers' => Customer::orderBy('name')->get([
                'id', 'name', 'phone', 'email', 'address', 'notes', 'is_active',
            ]),
        ]);
    }

    public function store(Request $request)
    {
        Customer::create($this->validateData($request));

        return back()->with('success', 'Customer added.');
    }

    public function update(Request $request, Customer $customer)
    {
        $customer->update($this->validateData($request, $customer));

        return back()->with('success', 'Customer updated.');
    }

    public function destroy(Customer $customer)
    {
        $customer->delete();

        return back()->with('success', 'Customer deleted.');
    }

    private function validateData(Request $request, ?Customer $customer = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('customers', 'name')->ignore($customer)],
            'phone' => ['nullable', 'string', 'regex:/^\d{3}\s?\d{3}\s?\d{4}$/'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ]);

        $data['phone'] = isset($data['phone']) ? preg_replace('/\D/', '', $data['phone']) : null;

        return $data;
    }
}
