<?php

namespace App\Http\Controllers\Masters;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class SupplierController extends Controller
{
    public function index()
    {
        return Inertia::render('Masters/Suppliers/Index', [
            'suppliers' => Supplier::orderBy('name')->get([
                'id', 'name', 'phone', 'email', 'address', 'notes', 'is_active',
            ]),
        ]);
    }

    public function store(Request $request)
    {
        Supplier::create($this->validateData($request));

        return back()->with('success', 'Supplier added.');
    }

    public function update(Request $request, Supplier $supplier)
    {
        $supplier->update($this->validateData($request, $supplier));

        return back()->with('success', 'Supplier updated.');
    }

    public function destroy(Supplier $supplier)
    {
        $supplier->delete();

        return back()->with('success', 'Supplier deleted.');
    }

    private function validateData(Request $request, ?Supplier $supplier = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('suppliers', 'name')->ignore($supplier)],
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
