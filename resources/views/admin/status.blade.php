@extends('layouts.admin')
@section('title', __('Systemstatus'))
@section('content')
<h1>🩺 {{ __('Systemstatus') }}</h1>
<p class="muted">{{ $version }}</p>
<div class="grid2">
<section class="panel">
    <h2>{{ __('Selbsttest') }}</h2>
    <ul class="checks">
        @foreach($checks as $c)
            <li><span @class(['state', 'ok' => $c['ok'] === true, 'bad' => $c['ok'] === false, 'unk' => $c['ok'] === null])></span><strong>{{ $c['name'] }}</strong><span class="muted">{{ $c['detail'] }}</span></li>
        @endforeach
    </ul>
    @can('settings.manage')
        <form method="post" action="{{ route('admin.settings.unifi-test') }}" class="mt">@csrf<button class="btn ghost">🔌 {{ __('UniFi jetzt prüfen') }}</button></form>
    @endcan
</section>
<section class="panel">
    <h2>{{ __('Löschfristen') }}</h2>
    @if($lastPurge)
        <p>{{ __('Letzter Lauf :t: :s Anmeldungen, :p unbestätigte und :u abgemeldete Kontakte gelöscht.', ['t' => \Carbon\Carbon::parse($lastPurge['at'])->format('d.m.Y H:i'), 's' => $lastPurge['sessions'], 'p' => $lastPurge['pending'], 'u' => $lastPurge['unsubscribed']]) }}</p>
    @else
        <p class="muted">{{ __('Läuft täglich um 03:30 Uhr – noch kein Lauf.') }}</p>
    @endif
    <h2 class="mt">{{ __('Wünsche & Fehler') }}</h2>
    <p>{{ __(':n Meldungen an die KI übergeben und offen.', ['n' => $openAi]) }}</p>
</section>
</div>
<section class="panel flush">
    <h2 class="pad">{{ __('Letzte Fehler') }}</h2>
    @if($logs->isEmpty())
        <p class="pad muted">{{ __('Keine Fehler protokolliert. 🎉') }}</p>
    @else
    <table class="table">
        <thead><tr><th>{{ __('Zeit') }}</th><th>{{ __('Nummer') }}</th><th>{{ __('Meldung') }}</th></tr></thead>
        <tbody>@foreach($logs as $e)<tr><td>{{ \Carbon\Carbon::parse($e->created_at)->timezone('Europe/Berlin')->format('d.m. H:i') }}</td><td><code>{{ $e->code }}</code></td><td>{{ $e->message }}</td></tr>@endforeach</tbody>
    </table>
    @endif
</section>
@endsection
