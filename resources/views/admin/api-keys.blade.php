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
@endsection
