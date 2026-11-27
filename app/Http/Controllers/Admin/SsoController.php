<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Activity;
use App\Support\DashboardSso;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use RuntimeException;

/** Einstieg aus dem L'Osteria Dashboard: Knopf „Gäste-WLAN öffnen“ → /sso?token=… */
class SsoController extends Controller
{
    public function login(Request $request): RedirectResponse
    {
        try {
            $c = DashboardSso::verify((string) $request->input('token', ''));
        } catch (RuntimeException $e) {
            Activity::log('sso.denied', null, ['reason' => $e->getMessage()]);

            return redirect()->route('login')->withErrors(['email' => __('Die Anmeldung über das Dashboard ist abgelaufen oder ungültig. Bitte im Dashboard noch einmal auf „Gäste-WLAN öffnen“ klicken.')]);
        }

        $user = User::where('sso_sub', $c['sub'])->first() ?? User::where('email', $c['email'])->first();
        if ($user && ! $user->active) {
            Activity::log('sso.blocked', $c['email']);

            return redirect()->route('login')->withErrors(['email' => __('Dein Zugang zum Gäste-WLAN ist gesperrt.')]);
        }
        $user ??= new User(['password' => Str::password(40)]);
        $user->forceFill([
            'sso_sub' => $c['sub'], 'email' => $c['email'], 'name' => $c['name'], 'role' => $c['role'],
            'active' => true, 'last_login_at' => now(),
        ])->save();

        Auth::login($user);
        $request->session()->regenerate();
        Activity::log('sso.login', $user->email, ['role' => $user->role]);

        return redirect($c['redirect'] ?? route('admin.dashboard'));
    }
}
