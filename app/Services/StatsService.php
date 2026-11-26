<?php

namespace App\Services;

use App\Models\GuestSession;
use App\Models\Location;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\DB;

/** Auswertungen für die Dashboard-Schnittstelle. Alle Zeiten Europe/Berlin. */
class StatsService
{
    public function summary(CarbonImmutable $from, CarbonImmutable $to, ?Location $location = null, bool $demo = false): array
    {
        $locations = Location::where('is_demo', $demo)->when($location, fn ($q) => $q->whereKey($location->id))
            ->orderBy('name')->get(['id', 'slug', 'name', 'city']);
        $ids = $locations->pluck('id');

        $base = fn () => GuestSession::query()->where('is_demo', $demo)->where('status', 'authorized')
            ->whereIn('location_id', $ids)->whereBetween('created_at', [$from, $to]);

        $perLoc = $base()->selectRaw('location_id, COUNT(*) as logins, COUNT(DISTINCT device_hash) as devices, SUM(CASE WHEN is_returning = 1 THEN 1 ELSE 0 END) as returning_logins, SUM(CASE WHEN method = \'email\' THEN 1 ELSE 0 END) as email_logins')
            ->groupBy('location_id')->get()->keyBy('location_id');

        $daily = $base()->selectRaw('location_id, DATE(created_at) as d, COUNT(*) as n')
            ->groupBy('location_id', 'd')->get()->groupBy('location_id');

        $hourExpr = DB::connection()->getDriverName() === 'sqlite' ? "CAST(strftime('%H', created_at) AS INTEGER)" : 'HOUR(created_at)';
        $hours = $base()->selectRaw("location_id, {$hourExpr} as h, COUNT(*) as n")->groupBy('location_id', 'h')->get()->groupBy('location_id');
        $langs = $base()->selectRaw('location_id, language, COUNT(*) as n')->groupBy('location_id', 'language')->get()->groupBy('location_id');

        $newsletter = DB::table('newsletter_contacts')->where('is_demo', $demo)->whereIn('location_id', $ids)
            ->where('status', 'confirmed')->whereBetween('confirmed_at', [$from, $to])
            ->selectRaw('location_id, COUNT(*) as n')->groupBy('location_id')->pluck('n', 'location_id');

        $promo = DB::table('promotion_stats')->join('promotions', 'promotions.id', '=', 'promotion_stats.promotion_id')
            ->whereIn('location_id', $ids)->whereBetween('day', [$from->toDateString(), $to->toDateString()])
            ->selectRaw('promotions.external_id, location_id, SUM(views) as views, SUM(clicks) as clicks')
            ->groupBy('promotions.external_id', 'location_id')->get()->groupBy('location_id');

        $days = collect(CarbonPeriod::create($from->startOfDay(), $to->startOfDay()))->map->toDateString();

        $result = $locations->map(function ($l) use ($perLoc, $daily, $hours, $langs, $newsletter, $promo, $days) {
            $p = $perLoc[$l->id] ?? null;
            $logins = (int) ($p->logins ?? 0);
            $byDay = collect($daily[$l->id] ?? [])->pluck('n', 'd');

            return [
                'standort' => ['slug' => $l->slug, 'name' => $l->name, 'stadt' => $l->city],
                'anmeldungen' => $logins,
                'geraete' => (int) ($p->devices ?? 0),
                'wiederkehrer_anmeldungen' => (int) ($p->returning_logins ?? 0),
                'wiederkehrer_quote' => $logins ? round($p->returning_logins / $logins, 3) : 0,
                'newsletter_bestaetigt' => (int) ($newsletter[$l->id] ?? 0),
                'pro_tag' => $days->mapWithKeys(fn ($d) => [$d => (int) ($byDay[$d] ?? 0)])->all(),
                'pro_stunde' => collect(range(0, 23))->mapWithKeys(fn ($h) => [$h => (int) (collect($hours[$l->id] ?? [])->firstWhere('h', $h)->n ?? 0)])->all(),
                'sprachen' => collect($langs[$l->id] ?? [])->pluck('n', 'language')->map(fn ($n) => (int) $n)->all(),
                'aktionen' => collect($promo[$l->id] ?? [])->map(fn ($r) => ['id' => $r->external_id, 'aufrufe' => (int) $r->views, 'klicks' => (int) $r->clicks])->values()->all(),
            ];
        });

        return [
            'zeitraum' => ['von' => $from->toDateString(), 'bis' => $to->toDateString(), 'zeitzone' => 'Europe/Berlin'],
            'gesamt' => [
                'anmeldungen' => $result->sum('anmeldungen'),
                'geraete' => $result->sum('geraete'),
                'wiederkehrer_anmeldungen' => $result->sum('wiederkehrer_anmeldungen'),
                'newsletter_bestaetigt' => $result->sum('newsletter_bestaetigt'),
            ],
            'standorte' => $result->values()->all(),
        ];
    }
}
