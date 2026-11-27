@extends('layouts.admin')
@section('title', __('Übersicht'))
@section('content')
@php
    $colors = $locations->pluck('brand_color', 'id');
    $names = $locations->pluck('name', 'id');
    $max = max(1, collect($series)->max('total'));
    $n = count($series);
    $W = 760; $H = 220; $pad = 28; $bw = ($W - $pad) / $n;
    $bars = []; $ticks = [];
    foreach ($series as $i => $day) {
        $x = $pad + $i * $bw + $bw * .15; $y0 = $H;
        foreach ($day['by'] as $lid => $cnt) {
            $h = $cnt / $max * ($H - 10);
            $bars[] = ['x' => round($x, 1), 'y' => round($y0 - $h, 1), 'w' => round($bw * .7, 1), 'h' => round($h, 1),
                'c' => $colors[$lid] ?? '#999', 't' => $day['label'].' · '.($names[$lid] ?? '').': '.$cnt];
            $y0 -= $h;
        }
        $step = $n <= 14 ? 1 : ($n <= 31 ? 2 : 7);
        if ($i % $step === 0) { $ticks[] = ['x' => round($x + $bw * .35, 1), 'l' => $day['label']]; }
    }
@endphp
<div class="pagehead">
    <h1>📊 {{ __('Übersicht') }}</h1>
    <form method="get" class="filters">
        <select name="standort" onchange="this.form.submit()">
            <option value="">{{ __('Alle Standorte') }}</option>
            @foreach($locations as $l)<option value="{{ $l->id }}" @selected($locationId == $l->id)>{{ $l->name }}</option>@endforeach
        </select>
        <div class="seg small">
            @foreach([7, 14, 30, 90] as $d)
                <a href="?tage={{ $d }}&standort={{ $locationId }}" @class(['on' => $days === $d])>{{ $d }} {{ __('Tage') }}</a>
            @endforeach
        </div>
    </form>
</div>

@if($locations->isEmpty())
    <div class="empty">
        <p>{{ __('Noch kein Standort angelegt. Lege dein erstes Restaurant an und verbinde es mit der UDM SE.') }}</p>
        @can('locations.manage')<a href="{{ route('admin.locations.create') }}" class="btn">＋ {{ __('Standort anlegen') }}</a>@endcan
        @can('demo')<form method="post" action="{{ route('admin.demo') }}" class="inline">@csrf<button class="btn ghost">🎭 {{ __('Demo ansehen') }}</button></form>@endcan
    </div>
@else
<div class="kpis">
    <div class="kpi"><span class="kpi-label">{{ __('Gerade online') }} <i class="tip" title="{{ __('Gäste, deren Freischaltung noch läuft.') }}">ⓘ</i></span><strong>{{ number_format($kpi['online'], 0, ',', '.') }}</strong><span class="kpi-sub">{{ __(':n heute angemeldet', ['n' => number_format($kpi['today'], 0, ',', '.')]) }}</span></div>
    <div class="kpi"><span class="kpi-label">{{ __('Anmeldungen') }} <i class="tip" title="{{ __('Erfolgreiche Freischaltungen im Zeitraum.') }}">ⓘ</i></span><strong>{{ number_format($kpi['total'], 0, ',', '.') }}</strong><span class="kpi-sub">{{ __(':n verschiedene Geräte', ['n' => number_format($kpi['devices'], 0, ',', '.')]) }}</span></div>
    <div class="kpi"><span class="kpi-label">{{ __('Wiederkehrer') }} <i class="tip" title="{{ __('Anteil der Anmeldungen von Geräten, die schon einmal am selben Standort waren. Die Geräte-Kennung wird nur verschlüsselt gespeichert.') }}">ⓘ</i></span><strong>{{ $kpi['returning_rate'] }} %</strong><span class="kpi-sub">{{ __('im Zeitraum') }}</span></div>
    @if(\App\Support\Settings::get('modules.newsletter'))<div class="kpi"><span class="kpi-label">{{ __('Newsletter') }} <i class="tip" title="{{ __('Bestätigte Anmeldungen (Double-Opt-in).') }}">ⓘ</i></span><strong>+{{ number_format($kpi['newsletter_new'], 0, ',', '.') }}</strong><span class="kpi-sub">{{ __(':n bestätigt insgesamt', ['n' => number_format($kpi['newsletter_total'], 0, ',', '.')]) }}</span></div>@endif

</div>
@if($kpi['failed'])
    <div class="flash warn">⚠ {{ __(':n Freischaltungen sind in den letzten 24 Stunden fehlgeschlagen.', ['n' => $kpi['failed']]) }} @can('status.view')<a href="{{ route('admin.status') }}">{{ __('Systemstatus') }}</a>@endcan</div>
@endif

<section class="panel">
    <div class="panel-head">
        <h2>{{ __('Anmeldungen pro Tag') }}</h2>
        <div class="legend">@foreach($locations as $l)@if(!$locationId || $locationId == $l->id)<span><i style="background:{{ $l->brand_color }}"></i>{{ $l->name }}</span>@endif @endforeach</div>
    </div>
    <svg viewBox="0 0 {{ $W }} {{ $H + 24 }}" class="chart" role="img" aria-label="{{ __('Anmeldungen pro Tag') }}">
        @foreach([0, .5, 1] as $g)
            @php($y = $H - $g * ($H - 10))
            <line x1="{{ $pad }}" x2="{{ $W }}" y1="{{ $y }}" y2="{{ $y }}" class="grid"/>
            <text x="{{ $pad - 6 }}" y="{{ $y + 4 }}" class="axis" text-anchor="end">{{ round($max * $g) }}</text>
        @endforeach
        @foreach($bars as $b)
            <rect x="{{ $b['x'] }}" y="{{ $b['y'] }}" width="{{ $b['w'] }}" height="{{ $b['h'] }}" fill="{{ $b['c'] }}" rx="2"><title>{{ $b['t'] }}</title></rect>
        @endforeach
        @foreach($ticks as $t)
            <text x="{{ $t['x'] }}" y="{{ $H + 16 }}" class="axis" text-anchor="middle">{{ $t['l'] }}</text>
        @endforeach
    </svg>
</section>

<div class="grid2">
    <section class="panel">
        <h2>{{ __('Nach Restaurant') }}</h2>
        <table class="table">
            <thead><tr><th>{{ __('Standort') }}</th><th class="num">{{ __('Anmeldungen') }}</th><th class="num">{{ __('Wiederkehrer') }}</th><th></th></tr></thead>
            <tbody>
            @foreach($locations as $l)
                @php($row = $perLocation[$l->id] ?? null)
                <tr>
                    <td><i class="dot" style="background:{{ $l->brand_color }}"></i>{{ $l->name }} <span class="muted">{{ $l->city }}</span></td>
                    <td class="num">{{ number_format($row->n ?? 0, 0, ',', '.') }}</td>
                    <td class="num">{{ $row && $row->n ? round($row->r / $row->n * 100) : 0 }} %</td>
                    <td class="num"><a href="{{ route('portal.preview', $l->slug) }}" target="_blank" class="small">{{ __('Gästeseite') }} ↗</a></td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </section>
    <section class="panel">
        <h2>{{ __('Stoßzeiten') }} <i class="tip" title="{{ __('Anmeldungen nach Uhrzeit im gewählten Zeitraum.') }}">ⓘ</i></h2>
        @php($hmax = max(1, max($hours ?: [0])))
        <div class="hours">
            @for($h = 10; $h <= 23; $h++)
                @php($v = $hours[$h] ?? 0)
                <div class="hour" title="{{ $h }}:00 · {{ $v }}"><span style="height: {{ round($v / $hmax * 100) }}%"></span><small>{{ $h }}</small></div>
            @endfor
        </div>
        <h2 class="mt">{{ __('Sprachen der Gäste') }}</h2>
        @php($ltotal = max(1, array_sum($languages)))
        @foreach(['de' => '🇩🇪 Deutsch', 'en' => '🇬🇧 English', 'it' => '🇮🇹 Italiano'] as $code => $label)
            @php($p = round(($languages[$code] ?? 0) / $ltotal * 100))
            <div class="bar"><span class="bar-label">{{ $label }}</span><span class="bar-track"><span style="width: {{ $p }}%"></span></span><span class="bar-val">{{ $p }} %</span></div>
        @endforeach
    </section>
</div>
@endif
@endsection
