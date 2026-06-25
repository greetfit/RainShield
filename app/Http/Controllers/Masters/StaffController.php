<?php

namespace App\Http\Controllers\Masters;

use App\Http\Controllers\Controller;
use App\Models\Designation;
use App\Models\Staff;
use Illuminate\Http\Request;
use Inertia\Inertia;

class StaffController extends Controller
{
    public function index()
    {
        return Inertia::render('Masters/Staff/Index', [
            'staff' => Staff::with('designationRecord:id,name,priority_level')
                ->leftJoin('designations', 'designations.id', '=', 'staff.designation_id')
                ->orderByRaw('designations.priority_level is null')
                ->orderBy('designations.priority_level')
                ->orderBy('staff.name')
                ->get([
                    'staff.id',
                    'staff.name',
                    'staff.phone',
                    'staff.designation_id',
                    'staff.designation',
                    'designations.priority_level as designation_priority_level',
                    'staff.salary_type',
                    'staff.monthly_salary',
                    'staff.is_active',
                ])
                ->map(fn (Staff $staff): array => [
                    'id' => $staff->id,
                    'name' => $staff->name,
                    'phone' => $staff->phone,
                    'designation_id' => $staff->designation_id,
                    'designation' => $staff->designationRecord?->name ?? $staff->designation,
                    'designation_priority_level' => $staff->designation_priority_level,
                    'salary_type' => $staff->salary_type,
                    'salary_type_label' => $staff->salary_type === 'monthly' ? 'Monthly salary' : 'Piece rate',
                    'monthly_salary' => $staff->monthly_salary,
                    'is_active' => $staff->is_active,
                ]),
            'designationOptions' => Designation::where('is_active', true)
                ->orderByRaw('priority_level is null')
                ->orderBy('priority_level')
                ->orderBy('name')
                ->get(['id', 'name', 'priority_level']),
        ]);
    }

    public function store(Request $request)
    {
        Staff::create($this->validateData($request));

        return back()->with('success', 'Staff member added.');
    }

    public function update(Request $request, Staff $staff)
    {
        $staff->update($this->validateData($request));

        return back()->with('success', 'Staff member updated.');
    }

    public function destroy(Staff $staff)
    {
        $staff->delete();

        return back()->with('success', 'Staff member deleted.');
    }

    private function validateData(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'regex:/^\d{3}\s?\d{3}\s?\d{4}$/'],
            'designation_id' => ['required', 'exists:designations,id'],
            'salary_type' => ['nullable', 'in:piece_rate,monthly'],
            'monthly_salary' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['boolean'],
        ]);

        $data['phone'] = isset($data['phone']) ? preg_replace('/\D/', '', $data['phone']) : null;
        $data['salary_type'] = $data['salary_type'] ?? 'piece_rate';
        $data['monthly_salary'] = $data['salary_type'] === 'monthly'
            ? ($data['monthly_salary'] ?? 0)
            : null;
        $data['designation'] = $data['designation_id']
            ? Designation::whereKey($data['designation_id'])->value('name')
            : null;

        return $data;
    }
}
