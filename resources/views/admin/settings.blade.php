@extends('layouts.admin')
@section('title', __('Einstellungen'))
@section('content')
<h1>⚙️ {{ __('Einstellungen') }}</h1>
<p class="muted">{{ __('Alle Zugänge und Schalter werden hier gepflegt, nicht in der Server-Konfiguration. Passwörter werden verschlüsselt gespeichert und nicht mehr angezeigt.') }}</p>
<p class="row"><a href="{{ route('admin.guest-page.edit') }}" class="btn ghost">🖼 {{ __('Gästeseite & Rechtstexte (Logo, Nutzungsbedingungen, Datenschutz, Impressum)') }}</a> <a href="{{ route('admin.api-keys.index') }}" class="btn ghost">🔌 {{ __('Dashboard-Schnittstelle (Auswertungen & Aktionen)') }}</a></p>
<form method="post" action="{{ route('admin.settings.update') }}" class="formgrid">
    @csrf @method('PUT')
    @foreach($groups as $group => $title)
        <section class="panel">
            <h2>{{ $title }}</h2>
            @foreach($fields as $key => [$g, $label, $type, $default, $secret])
                @continue($g !== $group)
                @php($name = str_replace('.', '__', $key))
                @php($localOnly = str_starts_with($label, 'Nur bei „Direkt“'))
                @if($localOnly)<div data-local>@endif
                @if($type === 'bool')
                    <label class="inline"><input type="checkbox" name="{{ $name }}" value="1" @checked(\App\Support\Settings::get($key))> {{ __($label) }}</label>
                @elseif(str_starts_with($type, 'select:'))
                    <label>{{ __($label) }}
                        <select name="{{ $name }}">
                            @foreach(explode(',', substr($type, 7)) as $opt)
                                @php([$val, $text] = explode('=', $opt, 2))
                                <option value="{{ $val }}" @selected(\App\Support\Settings::get($key) === $val)>{{ __($text) }}</option>
                            @endforeach
                        </select>
                    </label>
                @elseif($key === 'unifi.console_id')
                    <label data-cloud>{{ __($label) }}
                        @if($consoles)
                            <select name="{{ $name }}">
                                <option value="">{{ __('– bitte wählen –') }}</option>
                                @foreach($consoles as $id => $text)<option value="{{ $id }}" @selected(\App\Support\Settings::get($key) === $id)>{{ $text }}</option>@endforeach
                            </select>
                        @else
                            <input type="hidden" name="{{ $name }}" value="{{ \App\Support\Settings::get($key) }}">
                            <span class="muted small">{{ \App\Support\Settings::has($key) ? __('gewählt: :id', ['id' => \App\Support\Settings::get($key)]) : __('Noch keine Konsole. Erst den API-Schlüssel eintragen, dann „Konsolen laden“.') }}</span>
                        @endif
                    </label>
                    @if($consoleError)<p class="flash bad small" data-cloud>⚠ {{ $consoleError }}</p>@endif
                    <div class="row" data-cloud><button class="btn ghost" formaction="{{ route('admin.settings.unifi-consoles') }}" formmethod="post" name="_method" value="POST">☁️ {{ $consoles ? __('Konsolen neu laden') : __('Konsolen laden') }}</button></div>
                @elseif($secret)
                    <label>{{ __($label) }}
                        <input type="password" name="{{ $name }}" autocomplete="new-password" placeholder="{{ \App\Support\Settings::has($key) ? '•••••••• '.__('(gespeichert – leer lassen, um es zu behalten)') : __('noch nicht hinterlegt') }}">
                    </label>
                @else
                    <label>{{ __($label) }}
                        <input type="{{ ['int' => 'number', 'url' => 'url', 'email' => 'email', 'color' => 'color'][$type] ?? 'text' }}" name="{{ $name }}" value="{{ \App\Support\Settings::get($key) }}">
                    </label>
                @endif
                @if($localOnly)</div>@endif
            @endforeach
            @if($group === 'unifi')
                <p class="muted small">{{ __('Über die UniFi-Cloud braucht es weder VPN noch Portfreigabe: API-Schlüssel unter unifi.ui.com → API anlegen und speichern. Die UDM jedes Restaurants wählst du dann unter Standorte → Bearbeiten. Wichtig: Beim Anlegen des Schlüssels zusätzlich zu „Site Manager“ den Bereich „UniFi-Anwendungen“ (Network) anhaken und alle Sites auswählen – mit „Site Manager – Nur Lesen“ allein meldet die Cloud „keine Berechtigung“.') }}</p>
                <div class="row">
                    <button class="btn ghost" formaction="{{ route('admin.settings.unifi-test') }}" formmethod="post" name="_method" value="POST">🔌 {{ __('Verbindung testen') }}</button>
                </div>
            @endif
        </section>
    @endforeach
    <div class="formactions"><button class="btn">{{ __('Speichern') }}</button></div>
</form>
<script>
// Felder für „Direkt“ nur zeigen, wenn dieser Weg gewählt ist (und umgekehrt die Cloud-Felder)
(function () {
    var mode = document.querySelector('select[name=unifi__mode]');
    if (!mode) return;
    function apply() {
        var local = mode.value === 'local';
        document.querySelectorAll('[data-local]').forEach(function (el) { el.hidden = !local; });
        document.querySelectorAll('[data-cloud]').forEach(function (el) { el.hidden = local; });
    }
    mode.addEventListener('change', apply); apply();
})();
</script>
@endsection
