<?php

namespace App\Http\Controllers\BusinessSettings;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class GeneralSettingController extends Controller
{
    public function edit()
    {
        return Inertia::render('BusinessSettings/General/Index', [
            'settings' => SystemSetting::values($this->defaults()),
            'currencyOptions' => [
                ['code' => 'LKR', 'symbol' => 'Rs', 'label' => 'Sri Lankan Rupee'],
                ['code' => 'USD', 'symbol' => '$', 'label' => 'US Dollar'],
                ['code' => 'AED', 'symbol' => 'د.إ', 'label' => 'UAE Dirham'],
                ['code' => 'INR', 'symbol' => '₹', 'label' => 'Indian Rupee'],
            ],
            'timezoneOptions' => [
                'Asia/Colombo',
                'Asia/Dubai',
                'Asia/Kolkata',
                'UTC',
            ],
            'dateFormatOptions' => [
                ['value' => 'd/m/Y', 'label' => '25/06/2026'],
                ['value' => 'Y-m-d', 'label' => '2026-06-25'],
                ['value' => 'd-m-Y', 'label' => '25-06-2026'],
            ],
            'timeFormatOptions' => [
                ['value' => 'h:i A', 'label' => '04:30 PM'],
                ['value' => 'H:i', 'label' => '16:30'],
            ],
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'company_name' => ['nullable', 'string', 'max:120'],
            'company_phone' => ['nullable', 'string', 'max:40'],
            'company_email' => ['nullable', 'email', 'max:120'],
            'currency_code' => ['required', 'string', 'max:10'],
            'currency_symbol' => ['required', 'string', 'max:10'],
            'timezone' => ['required', 'timezone'],
            'date_format' => ['required', Rule::in(['d/m/Y', 'Y-m-d', 'd-m-Y'])],
            'time_format' => ['required', Rule::in(['h:i A', 'H:i'])],
            'company_logo' => ['nullable', 'image', 'max:2048'],
        ]);

        unset($data['company_logo']);
        $data['company_name'] = ($data['company_name'] ?? null) ?: 'RainShield';

        if ($request->hasFile('company_logo')) {
            $oldLogo = SystemSetting::values(['company_logo_path' => null])['company_logo_path'] ?? null;
            if ($oldLogo) {
                Storage::disk('public')->delete($oldLogo);
            }
            $data['company_logo_path'] = $request->file('company_logo')->store('company', 'public');
        }

        SystemSetting::putMany($data);

        return back()->with('success', 'Business settings updated.');
    }

    private function defaults(): array
    {
        return [
            'currency_code' => 'LKR',
            'currency_symbol' => 'Rs',
            'company_name' => 'RainShield',
            'company_phone' => null,
            'company_email' => null,
            'company_logo_path' => null,
            'timezone' => config('app.timezone', 'Asia/Colombo'),
            'date_format' => 'd/m/Y',
            'time_format' => 'h:i A',
        ];
    }
}
