<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Activity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function showLogin(): View|RedirectResponse
    {
        if (User::count() === 0) {
            return redirect()->route('setup');
        }

        return view('admin.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $data = $request->validate(['email' => 'required|email', 'password' => 'required', 'website' => 'nullable|max:0']);
        $ipKey = 'login-ip:'.$request->ip();
        $accountKey = 'login-acc:'.strtolower($data['email']);

        if (RateLimiter::tooManyAttempts($ipKey, 5) || RateLimiter::tooManyAttempts($accountKey, 10)) {
            Activity::log('login.locked', $data['email']);

            return back()->withErrors(['email' => __('Zu viele Versuche. Bitte warte kurz.')]);
        }

        if (! Auth::attempt(['email' => $data['email'], 'password' => $data['password'], 'active' => true], $request->boolean('remember'))) {
            RateLimiter::hit($ipKey, 60);
            RateLimiter::hit($accountKey, 900);

            return back()->withInput(['email' => $data['email']])->withErrors(['email' => __('E-Mail oder Passwort stimmt nicht.')]);
        }

        RateLimiter::clear($accountKey);
        $request->session()->regenerate();
        $request->user()->forceFill(['last_login_at' => now()])->save();
        Activity::log('login.ok');

        return redirect()->intended(route('admin.dashboard'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    public function showSetup(): View|RedirectResponse
    {
        return User::count() ? redirect()->route('login') : view('admin.setup');
    }

    public function setup(Request $request): RedirectResponse
    {
        abort_if(User::count() > 0, 403);
        $data = $request->validate([
            'name' => 'required|max:100',
            'email' => 'required|email|max:190',
            'password' => 'required|min:12|confirmed',
        ]);
        $user = User::create($data + ['role' => 'superadmin', 'active' => true]);
        Auth::login($user);
        Activity::log('setup.admin', $user->email);

        return redirect()->route('admin.dashboard')->with('ok', __('Willkommen! Dein Admin-Konto ist angelegt.'));
    }
}
