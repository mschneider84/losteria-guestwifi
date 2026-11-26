<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Activity;
use App\Support\Permissions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(): View
    {
        return view('admin.users', ['users' => User::orderBy('name')->get(), 'roles' => Permissions::ROLES, 'perms' => Permissions::ALL]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => 'required|max:100',
            'email' => 'required|email|unique:users,email',
            'role' => ['required', Rule::in($this->assignableRoles($request->user()))],
        ]);
        $password = Str::password(16);
        User::create($data + ['password' => $password, 'active' => true]);
        Activity::log('user.created', $data['email'], ['role' => $data['role']]);

        return back()->with('ok', __('Benutzer angelegt. Einmal-Passwort: :p – bitte persönlich weitergeben.', ['p' => $password]));
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'role' => ['required', Rule::in($this->assignableRoles($request->user()))],
            'active' => 'boolean',
        ]);
        abort_if($user->id === $request->user()->id, 422, __('Du kannst deine eigene Rolle nicht ändern.'));
        if ($user->role === 'superadmin' && ($data['role'] !== 'superadmin' || ! $request->boolean('active'))
            && User::where('role', 'superadmin')->where('active', true)->count() <= 1) {
            return back()->with('error', __('Der letzte aktive Superadmin bleibt geschützt.'));
        }
        $user->update(['role' => $data['role'], 'active' => $request->boolean('active')]);
        Activity::log('user.updated', $user->email, ['role' => $data['role']]);

        return back()->with('ok', __('Gespeichert.'));
    }

    /** Keine Rechte-Ausweitung: nur Rollen, deren Rechte man selbst hat. */
    private function assignableRoles(User $me): array
    {
        $mine = Permissions::forRole($me->role);

        return array_keys(array_filter(Permissions::ROLES, fn ($r, $key) => ($key !== 'superadmin' || $me->role === 'superadmin')
            && empty(array_diff(Permissions::forRole($key), $mine)), ARRAY_FILTER_USE_BOTH));
    }
}
