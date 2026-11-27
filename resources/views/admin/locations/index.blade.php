@extends('layouts.admin')
@section('title', __('Standorte'))
@section('content')
<div class="pagehead">
    <h1>📍 {{ __('Standorte') }}</h1>
    <a href="{{ route('admin.locations.create') }}" class="btn">＋ {{ __('Standort anlegen') }}</a>
</div>
@php
    // Öffentliche Portal-Adresse aus APP_URL (z. B. guest.losteria.at), sonst die aktuelle
    $appHost = parse_url((string) config('app.url'), PHP_URL_HOST);
    $portalHost = ($appHost && ! in_array($appHost, ['localhost', '127.0.0.1'], true)) ? $appHost : request()->getHost();
    $portalIp = \Illuminate\Support\Facades\Cache::remember('portal.ip.'.$portalHost, 3600, fn () => (($ip = gethostbyname($portalHost)) !== $portalHost) ? $ip : null);
@endphp
<details class="panel udm-guide" @if($locations->isEmpty()) open @endif>
    <summary><strong>📡 {{ __('So stellst du die UDM eines Restaurants ein') }}</strong></summary>
    @if($portalHost !== request()->getHost())<p class="flash warn small">{{ __('Du bist gerade über :a angemeldet, die Gäste kommen über :b. Die Anleitung nutzt :b.', ['a' => request()->getHost(), 'b' => $portalHost]) }}</p>@endif
    <p class="muted small">{{ __('In UniFi Network der jeweiligen UDM (z. B. AT_WOERGL_UDM_01). Die Texte im „Landing Page Designer“ von UniFi werden nicht gebraucht – Gäste sehen die Seite aus diesem Portal.') }}</p>
    <ol class="steps">
        <li>{{ __('Hotspot → Authentication: alle Haken bei Password, Payment, Vouchers, RADIUS raus. Unter „One Way Methods“ „External Portal Server“ anhaken.') }}</li>
        <li>{{ __('Als Server') }} <code>{{ $portalHost }}</code> {{ __('eintragen. Verlangt das Feld eine IPv4-Adresse:') }} <code>{{ $portalIp ?? '–' }}</code> {{ __('eintragen und zusätzlich „Redirect using hostname“ (bzw. „Domain“) einschalten und dort') }} <code>{{ $portalHost }}</code> {{ __('eintragen. Nur so bleibt es stabil, denn die IP von Laravel Cloud kann sich ändern.') }}</li>
        <li>{{ __('„HTTPS Redirection“ / „Encrypted URL“ aus lassen, „Secure Portal“ an.') }}</li>
        <li>{{ __('Pre-Authorization Allowances (Walled Garden):') }} <code>{{ $portalHost }}</code> {{ __('hinzufügen.') }}</li>
        <li>{{ __('Settings → WiFi → Gäste-WLAN: Sicherheit „Open“, „Hotspot Portal“ (bzw. Application „Hotspot“) einschalten, speichern.') }}</li>
        <li>{{ __('Hier im Portal beim Standort die UniFi-Konsole wählen, „Verbindung testen“ und dann mit dem Handy ins Gäste-WLAN.') }}</li>
    </ol>
</details>

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
