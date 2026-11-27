@extends('layouts.admin')
@section('title', __('Netzwerk'))
@section('content')
@php
    // Diagramm „Bereiche im Tagesverlauf“ vorberechnen (Stunden 10–23)
    $hoursRange = range(10, 23);
    $W = 760; $H = 200; $pad = 30;
    $areaLines = [];
    $areaMax = 1;
    foreach (($a['bereiche'] ?? []) as $ar) {
        foreach ($hoursRange as $hr) { $areaMax = max($areaMax, $ar['pro_stunde'][$hr] ?? 0); }
    }
    $stepX = ($W - $pad - 10) / (count($hoursRange) - 1);
    foreach (($a['bereiche'] ?? []) as $ar) {
        $pts = [];
        foreach ($hoursRange as $i => $hr) {
            $pts[] = round($pad + $i * $stepX, 1).','.round($H - ($ar['pro_stunde'][$hr] ?? 0) / $areaMax * ($H - 12), 1);
        }
        $areaLines[] = ['name' => $ar['bereich'], 'color' => $colors[$ar['bereich']] ?? '#888', 'points' => implode(' ', $pts), 'share' => round($ar['anteil'] * 100), 'peak' => $ar['hoechststand']];
    }
    $areaTicks = [];
    foreach ($hoursRange as $i => $hr) { $areaTicks[] = ['x' => round($pad + $i * $stepX, 1), 'l' => $hr]; }

    // Heatmap Wochentag × Uhrzeit
    $heat = $a['auslastung']['heatmap'] ?? [];
    $heatMax = 1;
    foreach ($heat as $row) { foreach ($hoursRange as $hr) { $heatMax = max($heatMax, $row[$hr] ?? 0); } }
    $weekdays = \App\Services\Network\NetworkStats::WEEKDAYS;

    $dw = $a['verweildauer'] ?? [];
    $dwHourMax = max(1, max(array_values($dw['nach_ankunftsstunde'] ?? []) ?: [0]));
    $dwDayMax = max(1, max(array_filter(array_values($dw['nach_wochentag'] ?? [])) ?: [0]));
    $dwDist = $dw['verteilung'] ?? [];
    $dwDistMax = max(1, max($dwDist ?: [0]));
    $rg = $a['stammgaeste'] ?? [];
    $gapMax = max(1, max($rg['abstand_verteilung'] ?? [0]));
    $vpdMax = max(1, max($rg['besuche_je_geraet'] ?? [0]));
    $tech = $a['technik'] ?? [];
    $typesTotal = max(1, array_sum($tech['geraetetypen'] ?? []));
    $fmt = fn ($n) => $n === null ? '–' : number_format($n, 0, ',', '.');
    $dur = fn ($m) => $m === null ? '–' : ($m >= 60 ? intdiv($m, 60).' h '.str_pad($m % 60, 2, '0', STR_PAD_LEFT).' min' : $m.' min');
@endphp

<div class="pagehead">
    <h1>📶 {{ __('Netzwerk') }}</h1>
    @if($locations->isNotEmpty())
    <form method="get" class="filters">
        <select name="standort" onchange="this.form.submit()">
            @foreach($locations as $l)<option value="{{ $l->id }}" @selected($location?->id === $l->id)>{{ $l->name }}</option>@endforeach
        </select>
        <input type="hidden" name="tage" value="{{ $days }}">
        <div class="seg small">
            @foreach([7, 30, 90] as $d)
                <a href="?tage={{ $d }}&standort={{ $location?->id }}" @class(['on' => $days === $d])>{{ $d }} {{ __('Tage') }}</a>
            @endforeach
        </div>
    </form>
    @endif
</div>

@if(! $demo && ! $enabled)
    <div class="flash warn">{{ __('Die Netzwerk-Auswertungen sind in den Einstellungen ausgeschaltet.') }}</div>
@elseif(! $demo && ! $lastRun)
    <div class="flash info">{{ __('Noch keine Daten: Die erste Momentaufnahme kommt, sobald der Zeitplan läuft und die UniFi-Cloud eingerichtet ist (alle 5 Minuten).') }}</div>
@endif

<p class="muted small">🔒 {{ __('Datenschutz: Geräte werden nur pseudonym gezählt (verschlüsselte Kennung, keine MAC-Adresse, kein Name). Erfasst werden nur Geräte im Gäste-WLAN. Besuche werden nach :d Tagen gelöscht.', ['d' => \App\Support\Settings::get('privacy.visit_days')]) }}</p>

{{-- Live --}}
<section class="panel">
    <div class="panel-head">
        <h2>🟢 {{ __('Jetzt gerade') }} <i class="tip" title="{{ __('Gastgeräte im WLAN laut letzter Momentaufnahme (höchstens 15 Minuten alt). „Auslastung“ misst am höchsten Stand der letzten 30 Tage.') }}">ⓘ</i></h2>
        @if($lastRun && ! $demo)<span class="muted small">{{ __('Stand') }} {{ \Carbon\Carbon::parse($lastRun['at'])->format('H:i') }}</span>@endif
    </div>
    <div class="live-grid">
        @foreach($live as $lv)
            @php($q = $lv['auslastung_quote'])
            <a class="live" href="?standort={{ $locations->firstWhere('slug', $lv['standort']['slug'])?->id }}&tage={{ $days }}">
                <span class="live-name">{{ $lv['standort']['name'] }} <span class="muted">{{ $lv['standort']['stadt'] }}</span></span>
                <strong>{{ $lv['stand'] ? $lv['gaeste'] : '–' }}</strong>
                <span class="muted small">{{ __('Gäste im WLAN') }}</span>
                <span class="bar-track"><span style="width: {{ round(($q ?? 0) * 100) }}%; background: {{ ($q ?? 0) >= .85 ? 'var(--bad)' : (($q ?? 0) >= .6 ? 'var(--warn)' : 'var(--ok)') }}"></span></span>
                <span class="small">{{ $q === null ? __('noch kein Vergleich') : __('Auslastung :p %', ['p' => round($q * 100)]) }}</span>
                <span class="chips">
                    @foreach($lv['bereiche'] as $b)<span class="chip"><i style="background: {{ $colors[$b['bereich']] ?? '#888' }}"></i>{{ $b['bereich'] }} {{ $b['gaeste'] }}</span>@endforeach
                </span>
            </a>
        @endforeach
    </div>
</section>

@if($a)
{{-- Bereiche --}}
<section class="panel">
    <div class="panel-head">
        <h2>📍 {{ __('Wo sitzen die Gäste?') }} <i class="tip" title="{{ __('Durchschnittlich anwesende Gastgeräte je Bereich und Uhrzeit. Der Bereich ergibt sich aus dem Access Point, mit dem das Gerät verbunden ist.') }}">ⓘ</i></h2>
        <div class="legend">@foreach($areaLines as $l)<span><i style="background: {{ $l['color'] }}"></i>{{ $l['name'] }} · {{ $l['share'] }} %</span>@endforeach</div>
    </div>
    @if($areaLines)
        <svg viewBox="0 0 {{ $W }} {{ $H + 24 }}" class="chart" role="img" aria-label="{{ __('Bereiche im Tagesverlauf') }}">
            @foreach([0, .5, 1] as $g)
                <line x1="{{ $pad }}" x2="{{ $W }}" y1="{{ $H - $g * ($H - 12) }}" y2="{{ $H - $g * ($H - 12) }}" class="grid"/>
                <text x="{{ $pad - 6 }}" y="{{ $H - $g * ($H - 12) + 4 }}" class="axis" text-anchor="end">{{ round($areaMax * $g) }}</text>
            @endforeach
            @foreach($areaLines as $l)
                <polyline points="{{ $l['points'] }}" fill="none" stroke="{{ $l['color'] }}" stroke-width="2.5" stroke-linejoin="round"><title>{{ $l['name'] }}</title></polyline>
            @endforeach
            @foreach($areaTicks as $t)<text x="{{ $t['x'] }}" y="{{ $H + 16 }}" class="axis" text-anchor="middle">{{ $t['l'] }}</text>@endforeach
        </svg>
    @else
        <p class="muted">{{ __('Noch keine Bereiche: Ordne unten den Access Points einen Bereich zu.') }}</p>
    @endif
</section>

<div class="grid2">
    {{-- Auslastung --}}
    <section class="panel">
        <h2>🔥 {{ __('Auslastung nach Wochentag und Uhrzeit') }} <i class="tip" title="{{ __('Durchschnittlich anwesende Gastgeräte. Je dunkler, desto voller.') }}">ⓘ</i></h2>
        <div class="heat">
            <span></span>@foreach($hoursRange as $hr)<span class="heat-h">{{ $hr }}</span>@endforeach
            @foreach($weekdays as $wd => $label)
                <span class="heat-d">{{ $label }}</span>
                @foreach($hoursRange as $hr)
                    @php($v = $heat[$wd][$hr] ?? 0)
                    <span class="heat-c" style="--o: {{ round($v / $heatMax, 2) }}" title="{{ $label }} {{ $hr }} Uhr: Ø {{ number_format($v, 1, ',', '') }}"></span>
                @endforeach
            @endforeach
        </div>
        @if($a['auslastung']['spitzenzeiten'])
            <p class="small">{{ __('Am vollsten:') }} @foreach($a['auslastung']['spitzenzeiten'] as $s){{ $s['wochentag'] }} {{ $s['stunde'] }} Uhr (Ø {{ number_format($s['gaeste'], 0) }})@if(! $loop->last), @endif @endforeach</p>
        @endif
    </section>

    {{-- Verweildauer --}}
    <section class="panel">
        <h2>⏱ {{ __('Verweildauer') }} <i class="tip" title="{{ __('Wie lange ein Gastgerät im WLAN war (Besuche ab 5 Minuten).') }}">ⓘ</i></h2>
        <div class="minikpis">
            <div><strong>{{ $dur($dw['durchschnitt_minuten']) }}</strong><span>{{ __('Durchschnitt') }}</span></div>
            <div><strong>{{ $dur($dw['median_minuten']) }}</strong><span>{{ __('typisch (Median)') }}</span></div>
            <div><strong>{{ $fmt($dw['besuche']) }}</strong><span>{{ __('Besuche') }}</span></div>
        </div>
        <h3 class="sub">{{ __('Nach Ankunftszeit') }}</h3>
        <div class="hours">
            @for($hr = 10; $hr <= 23; $hr++)
                @php($v = $dw['nach_ankunftsstunde'][$hr] ?? 0)
                <div class="hour" title="{{ $hr }}:00 · Ø {{ $v }} min"><span style="height: {{ round($v / $dwHourMax * 100) }}%"></span><small>{{ $hr }}</small></div>
            @endfor
        </div>
        <h3 class="sub">{{ __('Nach Bereich') }}</h3>
        @foreach($dw['nach_bereich'] as $name => $m)
            <div class="bar"><span class="bar-label"><i class="dot" style="background: {{ $colors[$name] ?? '#888' }}"></i>{{ $name }}</span><span class="bar-track"><span style="width: {{ round($m / max(1, max($dw['nach_bereich'])) * 100) }}%"></span></span><span class="bar-val">{{ $m }} min</span></div>
        @endforeach
        <h3 class="sub">{{ __('Nach Wochentag') }}</h3>
        @foreach($dw['nach_wochentag'] as $label => $m)
            <div class="bar"><span class="bar-label">{{ $label }}</span><span class="bar-track"><span style="width: {{ round(($m ?? 0) / $dwDayMax * 100) }}%"></span></span><span class="bar-val">{{ $m ?? '–' }}</span></div>
        @endforeach
    </section>
</div>

<div class="grid2">
    {{-- Stammgäste --}}
    <section class="panel">
        <h2>🔁 {{ __('Stammgäste') }} <i class="tip" title="{{ __('Geräte, die vorher schon einmal da waren oder im Zeitraum mehrmals kamen. Gezählt wird je Besuchstag.') }}">ⓘ</i></h2>
        <div class="minikpis">
            <div><strong>{{ round($rg['wiederkehrer_quote'] * 100) }} %</strong><span>{{ __('kommen wieder') }}</span></div>
            <div><strong>{{ $rg['abstand_tage_durchschnitt'] === null ? '–' : number_format($rg['abstand_tage_durchschnitt'], 0).' '.__('Tage') }}</strong><span>{{ __('Ø Abstand') }}</span></div>
            <div><strong>{{ $fmt($rg['geraete']) }}</strong><span>{{ __('Geräte') }}</span></div>
        </div>
        <h3 class="sub">{{ __('Abstand zwischen zwei Besuchen') }}</h3>
        @foreach($rg['abstand_verteilung'] as $label => $n)
            <div class="bar"><span class="bar-label">{{ $label }}</span><span class="bar-track"><span style="width: {{ round($n / $gapMax * 100) }}%"></span></span><span class="bar-val">{{ $fmt($n) }}</span></div>
        @endforeach
        <h3 class="sub">{{ __('Besuche je Gerät im Zeitraum') }}</h3>
        @foreach($rg['besuche_je_geraet'] as $label => $n)
            <div class="bar"><span class="bar-label">{{ $label }}×</span><span class="bar-track"><span style="width: {{ round($n / $vpdMax * 100) }}%"></span></span><span class="bar-val">{{ $fmt($n) }}</span></div>
        @endforeach

        <h3 class="sub">📣 {{ __('Wirkung von Aktionen') }} <i class="tip" title="{{ __('Anteil der Geräte, die innerhalb von 30 Tagen nach dem Klick wiederkamen – im Vergleich zu allen anderen Gästen im Zeitraum. Ein Hinweis, kein Beweis: Wer klickt, ist vielleicht ohnehin Stammgast.') }}">ⓘ</i></h3>
        @php($pe = $a['aktionen_wirkung'])
        @if($pe['aktionen'])
            <table class="table">
                <thead><tr><th>{{ __('Aktion') }}</th><th class="num">{{ __('Klicks') }}</th><th class="num">{{ __('wieder da') }}</th><th class="num">{{ __('ggü. ohne Klick') }}</th></tr></thead>
                <tbody>
                @foreach($pe['aktionen'] as $p)
                    <tr><td>{{ $p['titel'] }}</td><td class="num">{{ $p['geraete_mit_klick'] }}</td><td class="num">{{ round($p['rueckkehr_quote'] * 100) }} %</td>
                        <td class="num">@if($p['unterschied_prozentpunkte'] !== null)<span class="{{ $p['unterschied_prozentpunkte'] >= 0 ? 'up' : 'down' }}">{{ $p['unterschied_prozentpunkte'] >= 0 ? '+' : '' }}{{ number_format($p['unterschied_prozentpunkte'], 0) }} Pkt.</span>@else – @endif</td></tr>
                @endforeach
                </tbody>
            </table>
            <p class="muted small">{{ __('Ohne Klick kamen :p % wieder.', ['p' => round(($pe['vergleich_rueckkehr_quote_ohne_klick'] ?? 0) * 100)]) }}</p>
        @else
            <p class="muted">{{ __('Noch keine Klicks auf Aktionen im Zeitraum.') }}</p>
        @endif
    </section>

    {{-- Technik --}}
    <section class="panel">
        <h2>🛠 {{ __('Technik') }}</h2>
        <h3 class="sub">{{ __('Gerätetypen') }} <i class="tip" title="{{ __('Grob aus dem Browser der Gästeseite (z. B. iPhone, Android), ohne Modell oder Kennung.') }}">ⓘ</i></h3>
        @forelse($tech['geraetetypen'] as $label => $n)
            <div class="bar"><span class="bar-label">{{ $label }}</span><span class="bar-track"><span style="width: {{ round($n / $typesTotal * 100) }}%"></span></span><span class="bar-val">{{ round($n / $typesTotal * 100) }} %</span></div>
        @empty
            <p class="muted">{{ __('Noch keine Daten.') }}</p>
        @endforelse
        <h3 class="sub">{{ __('Datenverbrauch') }} <i class="tip" title="{{ __('Nur wenn die UDM den Verbrauch je Gerät meldet.') }}">ⓘ</i></h3>
        @if($tech['datenverbrauch'])
            <div class="minikpis">
                <div><strong>{{ number_format($tech['datenverbrauch']['gesamt_gb'], 1, ',', '.') }} GB</strong><span>{{ __('gesamt') }}</span></div>
                <div><strong>{{ $fmt($tech['datenverbrauch']['je_besuch_mb']) }} MB</strong><span>{{ __('je Besuch') }}</span></div>
            </div>
            @foreach($tech['datenverbrauch']['je_bereich_mb'] as $name => $mb)
                <div class="bar"><span class="bar-label"><i class="dot" style="background: {{ $colors[$name] ?? '#888' }}"></i>{{ $name }}</span><span class="bar-track"><span style="width: {{ round($mb / max(1, max($tech['datenverbrauch']['je_bereich_mb'])) * 100) }}%"></span></span><span class="bar-val">{{ $mb }} MB</span></div>
            @endforeach
        @else
            <p class="muted">{{ __('Die UDM liefert (noch) keinen Verbrauch je Gerät.') }}</p>
        @endif
    </section>
</div>

{{-- Access Points --}}
<section class="panel">
    <div class="panel-head">
        <h2>📡 {{ __('Access Points') }} <i class="tip" title="{{ __('Ordne jedem Access Point einen Bereich zu. Schwache oder überlastete Geräte werden markiert.') }}">ⓘ</i></h2>
    </div>
    @if($aps->isEmpty())
        <p class="muted">{{ __('Noch keine Access Points – sie erscheinen nach der ersten Momentaufnahme automatisch.') }}</p>
    @else
    <form method="post" action="{{ route('admin.network.areas', $location) }}">
        @csrf @method('PUT')
        @can('settings.manage')<p class="muted small">{{ __('Bereiche anlegen, umbenennen und sortieren:') }} <a href="{{ route('admin.areas.index') }}">{{ __('Einstellungen → Bereiche') }}</a></p>@endcan
        <div class="tablewrap">
        <table class="table">
            <thead><tr><th>{{ __('Access Point') }}</th><th>{{ __('Bereich') }}</th><th class="num">{{ __('Geräte jetzt') }}</th><th class="num">{{ __('Höchststand') }}</th><th class="num">{{ __('Funk-Wiederh.') }}</th><th>{{ __('Zustand') }}</th></tr></thead>
            <tbody>
            @foreach($aps as $ap)
                @php($probs = $ap->problems())
                <tr>
                    <td><strong>{{ $ap->name }}</strong> <span class="muted small">{{ $ap->model }}</span></td>
                    <td>@can('locations.manage')<select name="area[{{ $ap->id }}]" class="area-input"><option value="">{{ __('– ohne Bereich –') }}</option>@foreach($areas as $ar)<option value="{{ $ar }}" @selected($ap->area === $ar)>{{ $ar }}</option>@endforeach @if($ap->area && ! in_array($ap->area, $areas, true))<option value="{{ $ap->area }}" selected>{{ $ap->area }}</option>@endif</select>@else{{ $ap->areaLabel() }}@endcan</td>
                    <td class="num">{{ $ap->clients }}</td>
                    <td class="num">{{ $ap->peak_clients }}</td>
                    <td class="num">{{ $ap->retries_pct === null ? '–' : number_format($ap->retries_pct, 1, ',', '').' %' }}</td>
                    <td>@if($probs)<span class="pill warnpill">⚠ {{ implode(', ', $probs) }}</span>@else<span class="pill okpill">✓ {{ __('in Ordnung') }}</span>@endif</td>
                </tr>
            @endforeach
            </tbody>
        </table>
        </div>
        @can('locations.manage')<p><button class="btn">{{ __('Bereiche speichern') }}</button></p>@endcan
    </form>
    @endif
</section>
@endif

<script>
// Live-Werte alle 2 Minuten auffrischen, solange die Seite sichtbar ist
setInterval(() => { if (document.visibilityState === 'visible') location.reload(); }, 120000);
</script>
@endsection
