<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AccessPoint;
use App\Models\Location;
use App\Services\Network\NetworkStats;
use App\Support\Activity;
use App\Support\Settings;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

/** Netzwerk-Auswertungen: Live-Auslastung, Bereiche, Verweildauer, Stammgäste, Aktionen, Technik. */
class NetworkController extends Controller
{
    public const AREA_COLORS = ['Innen' => '#2a5bb8', 'Terrasse' => '#1f8a4c', 'Bar' => '#c77700', 'Eingang' => '#7b4bb8'];

    public function index(Request $request, NetworkStats $stats): View
    {
        $demo = (bool) session('demo');
        $days = in_array((int) $request->query('tage'), [7, 30, 90], true) ? (int) $request->query('tage') : 30;
        $locations = Location::mode($demo)->orderBy('name')->get();
        $location = $locations->firstWhere('id', $request->integer('standort')) ?? $locations->first();

        $to = CarbonImmutable::now();
        $from = $to->subDays($days - 1)->startOfDay();
        $analysis = $location
            ? Cache::remember('network.admin.'.md5(json_encode([$location->id, $days, $demo, $to->format('Y-m-d H:i')])), 60,
                fn () => $stats->analysis($from, $to, $demo, $location)['standorte'][0] ?? null)
            : null;

        return view('admin.network', [
            'locations' => $locations,
            'location' => $location,
            'days' => $days,
            'live' => $stats->live($demo),
            'a' => $analysis,
            'aps' => $location ? AccessPoint::where('location_id', $location->id)->orderBy('area')->orderBy('name')->get() : collect(),
            'areas' => \App\Models\Area::names(),
            'colors' => \App\Models\Area::colors() + self::AREA_COLORS,
            'lastRun' => Cache::get('network.last_run'),
            'enabled' => (bool) Settings::get('network.enabled'),
            'demo' => $demo,
        ]);
    }

    /** Bereiche der Access Points zuordnen (Innen, Terrasse, Bar …). */
    public function updateAreas(Request $request, Location $location): RedirectResponse
    {
        abort_unless($location->is_demo === (bool) session('demo'), 404);
        $data = $request->validate(['area' => ['array'], 'area.*' => ['nullable', 'string', \Illuminate\Validation\Rule::in(\App\Models\Area::names())]]);
        foreach ($data['area'] ?? [] as $id => $area) {
            AccessPoint::where('location_id', $location->id)->whereKey($id)->update(['area' => trim((string) $area) ?: null]);
        }
        Activity::log('network.areas', $location->slug);

        return back()->with('ok', __('Bereiche gespeichert. Neue Zählstände nutzen die Zuordnung ab sofort.'));
    }
}
