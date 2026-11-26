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
        return view('admin.api-keys', ['keys' => ApiKey::latest()->get()]);
    }

    public function storeKey(Request $request): RedirectResponse
    {
        $data = $request->validate(['name' => 'required|max:80', 'can_write' => 'boolean', 'expires_days' => 'nullable|integer|min:1|max:1095']);
        [$key, $plain] = ApiKey::generate($data['name'], $request->boolean('can_write'),
            ! empty($data['expires_days']) ? now()->addDays((int) $data['expires_days']) : null, $request->user()->id);
        Activity::log('api_key.created', $key->name, ['write' => $key->can_write]);

        return back()->with('new_key', $plain)->with('ok', __('Schlüssel angelegt. Er wird nur jetzt einmal angezeigt.'));
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
