@extends('layouts.admin')
@section('title', __('Dashboard-Schnittstelle'))
@section('content')
<a href="{{ route('admin.settings') }}" class="back">← {{ __('Einstellungen') }}</a>
<h1>🔌 {{ __('Dashboard-Schnittstelle') }}</h1>
<p class="muted">{{ __('Über diese Schnittstelle holt das L\'Osteria Dashboard die WLAN-Auswertungen ab und schickt Marketingaktionen für die Gästeseite. Adresse:') }} <code>{{ url('/api/v1') }}</code></p>

@if(session('new_key'))
    <div class="panel keybox">
        <strong>{{ __('Neuer Schlüssel – jetzt kopieren und im Dashboard eintragen:') }}</strong>
        <code class="key" id="newkey">{{ session('new_key') }}</code>
        <p class="muted small">{{ __('Er wird nur jetzt angezeigt. Gespeichert wird nur eine verschlüsselte Prüfsumme.') }}</p>
    </div>
@endif

<div class="grid2">
<section class="panel flush">
    <h2 class="pad">{{ __('Schlüssel') }}</h2>
    @if($keys->isEmpty())
        <p class="pad muted">{{ __('Noch kein Schlüssel. Lege rechts einen für das Dashboard an.') }}</p>
    @else
    <table class="table">
        <thead><tr><th>{{ __('Name') }}</th><th>{{ __('Darf') }}</th><th>{{ __('Zuletzt benutzt') }}</th><th>{{ __('Läuft ab') }}</th><th></th></tr></thead>
        <tbody>
        @foreach($keys as $k)
            <tr>
                <td>{{ $k->name }}<br><code class="small">{{ $k->prefix }}…</code></td>
                <td><span @class(['pill', 'warn' => $k->can_write])>{{ $k->can_write ? __('lesen + Aktionen') : __('nur lesen') }}</span></td>
                <td>{{ $k->last_used_at?->format('d.m.Y H:i') ?? '–' }}</td>
                <td>{{ $k->expires_at?->format('d.m.Y') ?? __('nie') }}</td>
                <td class="num"><form method="post" action="{{ route('admin.api-keys.destroy', $k) }}">@csrf @method('DELETE')<button class="btn ghost small">{{ __('Sperren') }}</button></form></td>
            </tr>
        @endforeach
        </tbody>
    </table>
    @endif
</section>
<section class="panel">
    <h2>＋ {{ __('Schlüssel anlegen') }}</h2>
    <form method="post" action="{{ route('admin.api-keys.store') }}" class="stack">
        @csrf
        <label>{{ __('Name') }}<input name="name" value="L'Osteria Dashboard" required></label>
        <label class="inline"><input type="checkbox" name="can_write" value="1" checked> {{ __('Darf Marketingaktionen senden') }}</label>
        <label>{{ __('Gültig für (Tage, leer = unbegrenzt)') }}<input type="number" name="expires_days" min="1" max="1095"></label>
        <button class="btn">{{ __('Schlüssel erzeugen') }}</button>
    </form>
    <p class="muted small mt">{{ __('Die genaue Beschreibung der Schnittstelle steht im Repository unter docs/dashboard-schnittstelle.md.') }}</p>
</section>
</div>

<section class="panel mt" id="sso">
    <h2>🔑 {{ __('Anmeldung über das Dashboard') }}</h2>
    <p class="muted">{{ __('Das Gäste-WLAN hat keine eigene Benutzerverwaltung mehr: Wer im L\'Osteria Dashboard angemeldet ist, öffnet die Verwaltung dort über „Gäste-WLAN öffnen“. Name und Rolle kommen aus dem Dashboard. Der Passwort-Login bleibt nur als Notfall-Zugang für Superadmins, falls das Dashboard nicht erreichbar ist.') }}</p>
    @if(session('new_sso_secret'))
        <div class="keybox">
            <strong>{{ __('Gemeinsamer Schlüssel – jetzt kopieren und im Dashboard eintragen:') }}</strong>
            <code class="key">{{ session('new_sso_secret') }}</code>
            <p class="muted small">{{ __('Er wird nur jetzt angezeigt und verschlüsselt gespeichert.') }}</p>
        </div>
    @endif
    <p>{{ __('Status:') }} @if($ssoActive)<span class="pill okpill">✓ {{ __('aktiv') }}</span> @if($ssoLast)<span class="muted small">{{ __('letzte Anmeldung über das Dashboard: :t', ['t' => \Carbon\Carbon::parse($ssoLast)->timezone('Europe/Berlin')->format('d.m.Y H:i')]) }}</span>@endif @else<span class="pill">{{ __('aus – Passwort-Login') }}</span>@endif</p>
    <form method="post" action="{{ route('admin.sso.update') }}" class="stack">
        @csrf @method('PUT')
        <label>{{ __('Adresse des Dashboards (dorthin führt der Knopf auf der Anmeldeseite)') }}<input type="url" name="dashboard_url" value="{{ old('dashboard_url', $ssoUrl) }}" placeholder="https://dashboard.losteria.at"></label>
        <p class="muted small">{{ __('Einstiegsadresse für das Dashboard:') }} <code>{{ route('sso.login') }}?token=…</code></p>
        <div class="row">
            <button class="btn" name="action" value="save">{{ __('Speichern') }}</button>
            <button class="btn ghost" name="action" value="generate" @if($ssoActive) onclick="return confirm('{{ __('Neuen Schlüssel erzeugen? Der alte gilt dann nicht mehr, bis er im Dashboard ersetzt ist.') }}')" @endif>🔑 {{ $ssoActive ? __('Neuen Schlüssel erzeugen') : __('Schlüssel erzeugen und aktivieren') }}</button>
            @if($ssoActive)<button class="btn ghost" name="action" value="disable" onclick="return confirm('{{ __('Wieder auf Passwort-Login umstellen?') }}')">{{ __('Abschalten') }}</button>@endif
        </div>
    </form>
</section>
@endsection
