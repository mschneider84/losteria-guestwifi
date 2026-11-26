<?php

namespace App\Services\Network;

use App\Models\AccessPoint;
use App\Models\Location;
use App\Support\DeviceType;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Netzwerk-Auswertungen für Admin und Dashboard-Schnittstelle. Alle Zeiten Europe/Berlin.
 * Ergebnisse enthalten nur Anzahlen und Durchschnitte, nie einzelne Geräte.
 */
class NetworkStats
{
    /** Bis zu so vielen Minuten alt darf der letzte Zählstand für „live“ sein. */
    public const LIVE_MAX_AGE = 15;

    public const WEEKDAYS = [1 => 'Mo', 2 => 'Di', 3 => 'Mi', 4 => 'Do', 5 => 'Fr', 6 => 'Sa', 7 => 'So'];

    /** Aktuelle Auslastung je Standort und Bereich. */
    public function live(bool $demo, ?Location $only = null): array
    {
        $now = now();
        $locations = $this->locations($demo, $only);

        return $locations->map(function (Location $l) use ($demo, $now) {
            $latest = DB::table('network_snapshots')->where('location_id', $l->id)->where('taken_at', '<=', $now)
                ->when(! $demo, fn ($q) => $q->where('taken_at', '>=', $now->copy()->subMinutes(self::LIVE_MAX_AGE)))
                ->max('taken_at');
            $rows = $latest ? DB::table('network_snapshots')->where('location_id', $l->id)->where('taken_at', $latest)->get() : collect();
            $total = $rows->firstWhere('area', 'gesamt');
            // Bisheriger Höchststand (30 Tage) als Maßstab für „voll“
            $peak = (int) DB::table('network_snapshots')->where('location_id', $l->id)->where('area', 'gesamt')
                ->whereBetween('taken_at', [$now->copy()->subDays(30), $now])->max('guests');
            $guests = (int) ($total->guests ?? 0);

            return [
                'standort' => ['slug' => $l->slug, 'name' => $l->name, 'stadt' => $l->city],
                'stand' => $latest ? Carbon::parse($latest)->toIso8601String() : null,
                'geraete' => (int) ($total->clients ?? 0),
                'gaeste' => $guests,
                'auslastung_quote' => $peak ? round(min(1, $guests / $peak), 2) : null,
                'hoechststand_30_tage' => $peak,
                'bereiche' => $rows->where('area', '!=', 'gesamt')->sortByDesc('guests')
                    ->map(fn ($r) => ['bereich' => $r->area, 'geraete' => (int) $r->clients, 'gaeste' => (int) $r->guests])->values()->all(),
            ];
        })->values()->all();
    }

    /** Auswertung eines Zeitraums: Bereiche, Auslastung, Verweildauer, Stammgäste, Aktionen, Technik. */
    public function analysis(CarbonImmutable $from, CarbonImmutable $to, bool $demo, ?Location $only = null): array
    {
        $to = $to->min(CarbonImmutable::now());

        return [
            'zeitraum' => ['von' => $from->toDateString(), 'bis' => $to->toDateString(), 'zeitzone' => 'Europe/Berlin'],
            'standorte' => $this->locations($demo, $only)->map(fn (Location $l) => [
                'standort' => ['slug' => $l->slug, 'name' => $l->name, 'stadt' => $l->city],
                'bereiche' => $this->areas($l, $from, $to),
                'auslastung' => $this->occupancy($l, $from, $to),
                'verweildauer' => $this->dwell($l, $from, $to),
                'stammgaeste' => $this->regulars($l, $from, $to),
                'aktionen_wirkung' => $this->promotionEffect($l, $from, $to),
                'technik' => $this->technical($l, $from, $to),
            ])->values()->all(),
        ];
    }

    private function locations(bool $demo, ?Location $only): Collection
    {
        return Location::where('is_demo', $demo)->when($only, fn ($q) => $q->whereKey($only->id))->orderBy('name')->get();
    }

    /** Durchschnittlich anwesende Gastgeräte je Bereich und Uhrzeit sowie Anteil je Bereich. */
    private function areas(Location $l, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $h = $this->hourExpr('taken_at');
        $rows = DB::table('network_snapshots')->where('location_id', $l->id)->where('area', '!=', 'gesamt')
            ->whereBetween('taken_at', [$from, $to])
            ->selectRaw("area, {$h} as h, AVG(guests) as avg_guests, MAX(guests) as max_guests")->groupBy('area', 'h')->get()->groupBy('area');
        $sum = $rows->map(fn ($r) => $r->sum('avg_guests'));
        $all = $sum->sum();

        return $rows->map(fn ($r, $area) => [
            'bereich' => $area,
            'anteil' => $all ? round($sum[$area] / $all, 3) : 0,
            'hoechststand' => (int) $r->max('max_guests'),
            'pro_stunde' => collect(range(0, 23))->mapWithKeys(fn ($hr) => [$hr => round((float) ($r->firstWhere('h', $hr)->avg_guests ?? 0), 1)])->all(),
        ])->sortByDesc('anteil')->values()->all();
    }

    /** Heatmap Wochentag × Uhrzeit (durchschnittlich anwesende Gastgeräte) und Spitzenzeiten. */
    private function occupancy(Location $l, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $h = $this->hourExpr('taken_at');
        $w = $this->weekdayExpr('taken_at');
        $rows = DB::table('network_snapshots')->where('location_id', $l->id)->where('area', 'gesamt')
            ->whereBetween('taken_at', [$from, $to])
            ->selectRaw("{$w} as wd, {$h} as h, AVG(guests) as g")->groupBy('wd', 'h')->get();
        $heat = [];
        foreach (self::WEEKDAYS as $wd => $label) {
            $heat[$wd] = collect(range(0, 23))->mapWithKeys(fn ($hr) => [$hr => round((float) ($rows->first(fn ($r) => (int) $r->wd === $wd && (int) $r->h === $hr)->g ?? 0), 1)])->all();
        }
        $top = $rows->sortByDesc('g')->take(3)->map(fn ($r) => ['wochentag' => self::WEEKDAYS[(int) $r->wd], 'stunde' => (int) $r->h, 'gaeste' => round((float) $r->g, 1)])->values()->all();
        $max = DB::table('network_snapshots')->where('location_id', $l->id)->where('area', 'gesamt')->whereBetween('taken_at', [$from, $to])->orderByDesc('guests')->first();

        return [
            'heatmap' => $heat,
            'spitzenzeiten' => $top,
            'hoechststand' => $max ? ['gaeste' => (int) $max->guests, 'zeitpunkt' => Carbon::parse($max->taken_at)->toIso8601String()] : null,
        ];
    }

    /** Verweildauer der Gastgeräte (Besuche ab 5 Minuten). */
    private function dwell(Location $l, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $visits = DB::table('client_visits')->where('location_id', $l->id)->whereBetween('first_seen_at', [$from, $to])
            ->where('minutes', '>=', 5)->get(['first_seen_at', 'minutes', 'area']);
        $avg = fn (Collection $c) => $c->isEmpty() ? null : (int) round($c->avg('minutes'));
        $visits = $visits->map(function ($v) {
            $t = Carbon::parse($v->first_seen_at);
            $v->h = $t->hour;
            $v->wd = $t->isoWeekday();

            return $v;
        });
        $sorted = $visits->pluck('minutes')->sort()->values();
        $buckets = ['unter 30 min' => [0, 29], '30–59 min' => [30, 59], '1–1,5 h' => [60, 89], '1,5–2 h' => [90, 119], 'über 2 h' => [120, PHP_INT_MAX]];

        return [
            'besuche' => $visits->count(),
            'durchschnitt_minuten' => $avg($visits),
            'median_minuten' => $sorted->isEmpty() ? null : (int) $sorted[intdiv($sorted->count(), 2)],
            'nach_ankunftsstunde' => $visits->groupBy('h')->sortKeys()->map(fn ($c) => $avg($c))->all(),
            'nach_wochentag' => collect(self::WEEKDAYS)->mapWithKeys(fn ($label, $wd) => [$label => $avg($visits->where('wd', $wd))])->all(),
            'nach_bereich' => $visits->groupBy(fn ($v) => $v->area ?: 'Ohne Bereich')->map(fn ($c) => $avg($c))->sortDesc()->all(),
            'verteilung' => collect($buckets)->map(fn ($b) => $visits->whereBetween('minutes', $b)->count())->all(),
        ];
    }

    /** Wiederkehr von Stammgästen: Anteil, Abstand zwischen Besuchen, Besuche je Gerät. */
    private function regulars(Location $l, CarbonImmutable $from, CarbonImmutable $to): array
    {
        // Besuchstage je Gerät (Anmeldungen am Portal zählen mit, auch wenn noch keine Netzwerkdaten da sind)
        $days = DB::table('guest_sessions')->where('location_id', $l->id)->where('status', 'authorized')->where('created_at', '<=', $to)
            ->get(['device_hash', 'created_at'])->map(fn ($r) => [$r->device_hash, substr((string) $r->created_at, 0, 10)])
            ->concat(DB::table('client_visits')->where('location_id', $l->id)->where('first_seen_at', '<=', $to)
                ->get(['device_hash', 'first_seen_at'])->map(fn ($r) => [$r->device_hash, substr((string) $r->first_seen_at, 0, 10)]))
            ->groupBy(0)->map(fn ($c) => $c->pluck(1)->unique()->sort()->values());

        $fromDay = $from->toDateString();
        $inPeriod = $days->filter(fn ($d) => $d->last() >= $fromDay);
        $gaps = collect();
        $returning = 0;
        $visitsPer = ['1' => 0, '2' => 0, '3–5' => 0, '6+' => 0];
        foreach ($inPeriod as $d) {
            $periodDays = $d->filter(fn ($x) => $x >= $fromDay)->values();
            if ($d->first() < $fromDay || $periodDays->count() > 1) {
                $returning++;
            }
            for ($i = 1; $i < $d->count(); $i++) {
                if ($d[$i] >= $fromDay) {
                    $gaps->push(Carbon::parse($d[$i - 1])->diffInDays(Carbon::parse($d[$i])));
                }
            }
            $n = $periodDays->count();
            $visitsPer[$n <= 1 ? '1' : ($n === 2 ? '2' : ($n <= 5 ? '3–5' : '6+'))]++;
        }
        $total = $inPeriod->count();

        return [
            'geraete' => $total,
            'wiederkehrer' => $returning,
            'wiederkehrer_quote' => $total ? round($returning / $total, 3) : 0,
            'abstand_tage_durchschnitt' => $gaps->isEmpty() ? null : round($gaps->avg(), 1),
            'abstand_verteilung' => [
                'bis 7 Tage' => $gaps->filter(fn ($g) => $g <= 7)->count(),
                '8–14 Tage' => $gaps->filter(fn ($g) => $g > 7 && $g <= 14)->count(),
                '15–30 Tage' => $gaps->filter(fn ($g) => $g > 14 && $g <= 30)->count(),
                'über 30 Tage' => $gaps->filter(fn ($g) => $g > 30)->count(),
            ],
            'besuche_je_geraet' => $visitsPer,
        ];
    }

    /**
     * Kommen Gäste, die auf eine Aktion geklickt haben, eher wieder?
     * Vergleich: Rückkehr innerhalb von 30 Tagen nach dem Klick vs. alle anderen Gäste im Zeitraum.
     */
    private function promotionEffect(Location $l, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $sessions = DB::table('guest_sessions')->where('location_id', $l->id)->where('status', 'authorized')
            ->where('created_at', '>=', $from)->get(['device_hash', 'created_at'])
            ->groupBy('device_hash')->map(fn ($c) => $c->pluck('created_at')->map(fn ($t) => (string) $t)->sort()->values());
        $returned = function (string $hash, string $after) use ($sessions): bool {
            $start = Carbon::parse($after)->addDay()->startOfDay();
            $end = Carbon::parse($after)->addDays(30);

            return collect($sessions[$hash] ?? [])->contains(fn ($t) => ($c = Carbon::parse($t)) >= $start && $c <= $end);
        };

        $clicks = DB::table('promotion_clicks')->join('promotions', 'promotions.id', '=', 'promotion_clicks.promotion_id')
            ->where('promotion_clicks.location_id', $l->id)->whereBetween('clicked_at', [$from, $to])
            ->get(['promotions.external_id', 'promotions.title', 'device_hash', 'clicked_at']);
        $clickers = $clicks->pluck('device_hash')->unique();

        $others = $sessions->keys()->diff($clickers);
        $baseRet = $others->filter(fn ($h) => $returned($h, $sessions[$h]->first()))->count();
        $baseQuote = $others->count() ? round($baseRet / $others->count(), 3) : null;

        $items = $clicks->groupBy('external_id')->map(function ($c, $id) use ($returned, $baseQuote) {
            $first = $c->sortBy('clicked_at')->unique('device_hash');
            $ret = $first->filter(fn ($r) => $returned($r->device_hash, (string) $r->clicked_at))->count();
            $quote = $first->count() ? round($ret / $first->count(), 3) : 0;
            $title = json_decode((string) $c->first()->title, true);

            return [
                'id' => $id, 'titel' => $title['de'] ?? $id,
                'geraete_mit_klick' => $first->count(), 'wiedergekommen' => $ret, 'rueckkehr_quote' => $quote,
                'unterschied_prozentpunkte' => $baseQuote === null ? null : round(($quote - $baseQuote) * 100, 1),
            ];
        })->values()->all();

        return ['vergleich_rueckkehr_quote_ohne_klick' => $baseQuote, 'zeitfenster_tage' => 30, 'aktionen' => $items];
    }

    /** Gerätetypen, Datenverbrauch und Zustand der Access Points. */
    private function technical(Location $l, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $types = DB::table('guest_sessions')->where('location_id', $l->id)->where('status', 'authorized')
            ->whereBetween('created_at', [$from, $to])->whereNotNull('device_type')
            ->selectRaw('device_type, COUNT(DISTINCT device_hash) as n')->groupBy('device_type')->pluck('n', 'device_type');
        $usage = DB::table('client_visits')->where('location_id', $l->id)->whereBetween('first_seen_at', [$from, $to])
            ->whereNotNull('rx_mb')->selectRaw('COUNT(*) as n, SUM(rx_mb) as rx, SUM(COALESCE(tx_mb, 0)) as tx')->first();
        $usageArea = DB::table('client_visits')->where('location_id', $l->id)->whereBetween('first_seen_at', [$from, $to])
            ->whereNotNull('rx_mb')->selectRaw('area, AVG(rx_mb + COALESCE(tx_mb, 0)) as mb')->groupBy('area')->get();

        return [
            'geraetetypen' => collect(DeviceType::LABELS)->mapWithKeys(fn ($label, $key) => [$label => (int) ($types[$key] ?? 0)])->filter()->sortDesc()->all(),
            'datenverbrauch' => ($usage && $usage->n) ? [
                'besuche_mit_daten' => (int) $usage->n,
                'gesamt_gb' => round(($usage->rx + $usage->tx) / 1024, 1),
                'je_besuch_mb' => (int) round(($usage->rx + $usage->tx) / $usage->n),
                'je_bereich_mb' => $usageArea->mapWithKeys(fn ($r) => [$r->area ?: 'Ohne Bereich' => (int) round($r->mb)])->all(),
            ] : null,
            'access_points' => AccessPoint::where('location_id', $l->id)->orderBy('area')->orderBy('name')->get()->map(fn (AccessPoint $ap) => [
                'name' => $ap->name, 'bereich' => $ap->area, 'modell' => $ap->model, 'zustand' => $ap->state,
                'geraete' => $ap->clients, 'hoechststand' => $ap->peak_clients, 'funk_wiederholungen_prozent' => $ap->retries_pct,
                'hinweise' => $ap->problems(),
            ])->all(),
        ];
    }

    private function hourExpr(string $col): string
    {
        return DB::connection()->getDriverName() === 'sqlite' ? "CAST(strftime('%H', {$col}) AS INTEGER)" : "HOUR({$col})";
    }

    /** ISO-Wochentag 1 = Montag … 7 = Sonntag */
    private function weekdayExpr(string $col): string
    {
        return DB::connection()->getDriverName() === 'sqlite'
            ? "((CAST(strftime('%w', {$col}) AS INTEGER) + 6) % 7 + 1)"
            : "WEEKDAY({$col}) + 1";
    }
}
