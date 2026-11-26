<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GuestSession;
use App\Models\Location;
use App\Models\NewsletterContact;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $demo = (bool) session('demo');
        $days = in_array((int) $request->query('tage'), [7, 14, 30, 90], true) ? (int) $request->query('tage') : 14;
        $from = now()->subDays($days - 1)->startOfDay();
        $locationId = $request->integer('standort') ?: null;

        $base = fn () => GuestSession::query()
            ->where('is_demo', $demo)
            ->where('status', 'authorized')
            ->when($locationId, fn ($q) => $q->where('location_id', $locationId));

        $locations = Location::mode($demo)->orderBy('name')->get(['id', 'name', 'brand_color', 'city', 'slug', 'active']);

        // Logins je Tag und Standort, gruppiert vorgeladen (keine N+1-Abfragen)
        $rows = $base()->where('created_at', '>=', $from)
            ->selectRaw('DATE(created_at) as d, location_id, COUNT(*) as n')
            ->groupBy('d', 'location_id')->get();

        $series = [];
        foreach (CarbonPeriod::create($from, now()->startOfDay()) as $day) {
            $key = $day->toDateString();
            $series[$key] = ['label' => $day->format('d.m.'), 'weekday' => $day->isoFormat('dd'), 'total' => 0, 'by' => []];
        }
        foreach ($rows as $r) {
            if (isset($series[$r->d])) {
                $series[$r->d]['total'] += $r->n;
                $series[$r->d]['by'][$r->location_id] = (int) $r->n;
            }
        }

        $period = $base()->where('created_at', '>=', $from);
        $total = (clone $period)->count();
        $returning = (clone $period)->where('is_returning', true)->count();
        $devices = (clone $period)->distinct('device_hash')->count('device_hash');

        $perLocation = $base()->where('created_at', '>=', $from)
            ->selectRaw('location_id, COUNT(*) as n, SUM(CASE WHEN is_returning = 1 THEN 1 ELSE 0 END) as r')
            ->groupBy('location_id')->get()->keyBy('location_id');

        $languages = $base()->where('created_at', '>=', $from)
            ->selectRaw('language, COUNT(*) as n')->groupBy('language')->pluck('n', 'language')->all();

        $hours = $base()->where('created_at', '>=', $from)
            ->selectRaw(DB::connection()->getDriverName() === 'sqlite'
                ? "CAST(strftime('%H', created_at) AS INTEGER) as h, COUNT(*) as n"
                : 'HOUR(created_at) as h, COUNT(*) as n')
            ->groupBy('h')->pluck('n', 'h')->all();

        $contacts = NewsletterContact::where('is_demo', $demo)
            ->when($locationId, fn ($q) => $q->where('location_id', $locationId));

        return view('admin.dashboard', [
            'days' => $days,
            'locationId' => $locationId,
            'locations' => $locations,
            'series' => array_values($series),
            'kpi' => [
                'today' => $base()->where('created_at', '>=', now()->startOfDay())->count(),
                'total' => $total,
                'devices' => $devices,
                'returning_rate' => $total ? round($returning / $total * 100) : 0,
                'newsletter_new' => (clone $contacts)->where('status', 'confirmed')->where('confirmed_at', '>=', $from)->count(),
                'newsletter_total' => (clone $contacts)->where('status', 'confirmed')->count(),
                'online' => $base()->where('expires_at', '>', now())->count(),
                'failed' => GuestSession::where('is_demo', $demo)->where('status', 'failed')->where('created_at', '>=', now()->subDay())->count(),
            ],
            'perLocation' => $perLocation,
            'languages' => $languages,
            'hours' => $hours,
        ]);
    }
}
