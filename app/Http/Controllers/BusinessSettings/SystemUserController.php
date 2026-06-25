<?php

namespace App\Http\Controllers\BusinessSettings;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Spatie\Permission\Models\Role;

class SystemUserController extends Controller
{
    public function index()
    {
        $roles = Role::query()
            ->orderBy('name')
            ->pluck('name')
            ->values();

        return Inertia::render('BusinessSettings/SystemUsers/Index', [
            'users' => User::query()
                ->where('is_hidden_system_user', false)
                ->with('roles:id,name')
                ->orderBy('name')
                ->get(['id', 'name', 'email', 'email_verified_at', 'created_at'])
                ->map(fn (User $user) => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->roles->first()?->name,
                    'roles' => $user->roles->pluck('name')->values(),
                    'verified' => (bool) $user->email_verified_at,
                    'created_at' => $user->created_at?->format('d/m/Y h:i A'),
                ]),
            'roles' => $roles,
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'email_verified_at' => now(),
            'is_hidden_system_user' => false,
        ]);

        $user->syncRoles([$data['role']]);

        return back()->with('success', 'System user added.');
    }

    public function update(Request $request, User $user)
    {
        $this->protectHiddenUser($user);

        $data = $this->validateData($request, $user->id, false);

        $payload = [
            'name' => $data['name'],
            'email' => $data['email'],
        ];

        if (! empty($data['password'])) {
            $payload['password'] = $data['password'];
        }

        $user->update($payload);
        $user->syncRoles([$data['role']]);

        return back()->with('success', 'System user updated.');
    }

    public function destroy(User $user)
    {
        $this->protectHiddenUser($user);

        if (auth()->id() === $user->id) {
            return back()->with('error', 'You cannot delete your own user account while logged in.');
        }

        $user->delete();

        return back()->with('success', 'System user deleted.');
    }

    private function validateData(Request $request, ?int $ignoreUserId = null, bool $passwordRequired = true): array
    {
        $roles = Role::query()->pluck('name')->all();

        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($ignoreUserId)],
            'role' => ['required', Rule::in($roles)],
            'password' => [$passwordRequired ? 'required' : 'nullable', 'string', 'min:8', 'confirmed'],
        ]);
    }

    private function protectHiddenUser(User $user): void
    {
        abort_if($user->is_hidden_system_user, 404);
    }
}
