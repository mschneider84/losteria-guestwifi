@extends('layouts.admin')
@section('title', __('Standorte'))
@section('content')
<div class="pagehead">
    <h1>📍 {{ __('Standorte') }}</h1>
    <a href="{{ route('admin.locations.create') }}" class="btn">＋ {{ __('Standort anlegen') }}</a>
</div>
@if($locations->isEmpty())
    <div class="empty"><p>{{ __('Noch kein Standort. Jedes Restaurant bekommt eine eigene Gästeseite und eine UniFi-Site.') }}</p>
        <a href="{{ route('admin.locations.create') }}" class="btn">＋ {{ __('Standort anlegen') }}</a></div>
@else
<div class="cards">
    @foreach($locations as $l)
        <article class="loc" style="--c: {{ $l->brand_color }}">
            <div class="loc-head">
                <div><h3>{{ $l->name }}</h3><span class="muted">{{ $l->city }}</span></div>
                <span @class(['pill', 'ok' => $l->active, 'off' => !$l->active])>{{ $l->active ? __('aktiv') : __('pausiert') }}</span>
            </div>
            <dl class="facts">
                <div><dt>{{ __('UniFi-Konsole') }}</dt><dd>{{ \Illuminate\Support\Str::before(\App\Support\UnifiConsoles::labels()[$l->unifi_console_id] ?? ($l->unifi_console_id ? \Illuminate\Support\Str::limit($l->unifi_console_id, 14) : __('Standard')), ' · ') }} <span class="muted small">/ {{ $l->unifi_site }}</span></dd></div>
                <div><dt>{{ __('Zeitlimit') }}</dt><dd>{{ $l->sessionLabel() }}</dd></div>
                <div><dt>{{ __('Bandbreite') }}</dt><dd>{{ $l->down_kbps ? round($l->down_kbps / 1000).' / '.round(($l->up_kbps ?? 0) / 1000).' Mbit/s' : __('unbegrenzt') }}</dd></div>
                <div><dt>{{ __('Heute / online') }}</dt><dd>{{ $today[$l->id] ?? 0 }} / {{ $online[$l->id] ?? 0 }}</dd></div>
            </dl>
            <div class="portal-url"><span class="muted small">{{ __('Portal-URL für die UDM') }}</span><code>{{ url('/guest/s/'.$l->unifi_site.'/') }}</code></div>
            <div class="row">
                <a href="{{ route('admin.locations.edit', $l) }}" class="btn ghost">✏️ {{ __('Bearbeiten') }}</a>
                <a href="{{ route('portal.preview', $l->slug) }}" target="_blank" class="btn ghost">👁 {{ __('Vorschau') }}</a>
            </div>
        </article>
    @endforeach
</div>
@endif
@endsection
