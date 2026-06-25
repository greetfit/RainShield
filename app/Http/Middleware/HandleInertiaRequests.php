<?php

namespace App\Http\Middleware;

use App\Models\SystemSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $settings = SystemSetting::values([
            'company_name' => 'RainShield',
            'company_phone' => null,
            'company_email' => null,
            'company_logo_path' => null,
            'currency_code' => 'LKR',
            'currency_symbol' => 'Rs',
        ]);

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user(),
                'roles' => $request->user()
                    ? $request->user()->getRoleNames()
                    : [],
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
            'appSettings' => [
                'company_name' => $settings['company_name'],
                'company_phone' => $settings['company_phone'],
                'company_email' => $settings['company_email'],
                'company_logo_url' => $settings['company_logo_path'] ? Storage::url($settings['company_logo_path']) : null,
                'currency_code' => $settings['currency_code'],
                'currency_symbol' => $settings['currency_symbol'],
                'signature' => 'By H to G',
            ],
        ];
    }
}
