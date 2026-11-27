<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ApiKey;
use App\Models\Location;
use App\Models\Promotion;
use App\Support\Activity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class IntegrationController extends Controller
{
    /** Einstellungen → Dashboard-Schnittstelle: Schlüssel verwalten */
    public function keys(): View
    {
        return view('admin.api-keys', [
            'keys' => ApiKey::latest()->get(),
            'ssoActive' => \App\Support\DashboardSso::active(),
            'ssoUrl' => \App\Support\DashboardSso::dashboardUrl(),
            'ssoLast' => \App\Models\User::whereNotNull('sso_sub')->max('last_login_at'),
        ]);
    }

    public function storeKey(Request $request): RedirectResponse
    {
        $data = $request->validate(['name' => 'required|max:80', 'can_write' => 'boolean', 'expires_days' => 'nullable|integer|min:1|max:1095']);
        [$key, $plain] = ApiKey::generate($data['name'], $request->boolean('can_write'),
            ! empty($data['expires_days']) ? now()->addDays((int) $data['expires_days']) : null, $request->user()->id);
        Activity::log('api_key.created', $key->name, ['write' => $key->can_write]);

        return back()->with('new_key', $plain)->with('ok', __('Schlüssel angelegt. Er wird nur jetzt einmal angezeigt.'));
    }

    /** Anmeldung über das Dashboard: Adresse speichern, Schlüssel erzeugen (nur einmal sichtbar) oder abschalten. */
    public function updateSso(Request $request): RedirectResponse
    {
        $data = $request->validate(['dashboard_url' => 'nullable|url:https|max:255', 'action' => 'nullable|in:save,generate,disable']);
        \App\Support\Settings::set('sso.dashboard_url', $data['dashboard_url'] ?? null);
        if (($data['action'] ?? null) === 'generate') {
            $secret = \App\Support\DashboardSso::generateSecret();
            Activity::log('sso.secret_generated');

            return back()->with('new_sso_secret', $secret)->with('ok', __('Neuer Schlüssel erzeugt – jetzt im Dashboard eintragen. Ab sofort meldet ihr euch über das Dashboard an.'));
        }
        if (($data['action'] ?? null) === 'disable') {
            \App\Support\Settings::set('sso.secret', null);
            Activity::log('sso.disabled');

            return back()->with('ok', __('Anmeldung über das Dashboard abgeschaltet. Es gilt wieder der Passwort-Login.'));
        }

        return back()->with('ok', __('Gespeichert.'));
    }

    public function destroyKey(ApiKey $key): RedirectResponse
    {
        $key->delete();
        Activity::log('api_key.revoked', $key->name);

        return back()->with('ok', __('Schlüssel gesperrt.'));
    }

    /** Aktionen aus dem Dashboard – nur ansehen, gepflegt werden sie im Dashboard. */
    public function promotions(): View
    {
        $demo = (bool) session('demo');
        $promotions = Promotion::where('is_demo', $demo)->orderByDesc('active')->orderByDesc('priority')->get();
        $stats = DB::table('promotion_stats')->whereIn('promotion_id', $promotions->pluck('id'))
            ->where('day', '>=', now()->subDays(29)->toDateString())
            ->selectRaw('promotion_id, SUM(views) as views, SUM(clicks) as clicks')->groupBy('promotion_id')->get()->keyBy('promotion_id');

        return view('admin.promotions', [
            'promotions' => $promotions,
            'stats' => $stats,
            'locations' => Location::where('is_demo', $demo)->pluck('name', 'slug'),
        ]);
    }
}
