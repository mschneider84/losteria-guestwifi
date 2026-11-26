@extends('layouts.admin')
@section('title', __('Einstellungen'))
@section('content')
<h1>⚙️ {{ __('Einstellungen') }}</h1>
<p class="muted">{{ __('Alle Zugänge und Schalter werden hier gepflegt, nicht in der Server-Konfiguration. Passwörter werden verschlüsselt gespeichert und nicht mehr angezeigt.') }}</p>
<p><a href="{{ route('admin.api-keys.index') }}" class="btn ghost">🔌 {{ __('Dashboard-Schnittstelle (Auswertungen & Aktionen)') }}</a></p>
<form method="post" action="{{ route('admin.settings.update') }}" class="formgrid">
    @csrf @method('PUT')
    @foreach($groups as $group => $title)
        <section class="panel">
            <h2>{{ $title }}</h2>
            @foreach($fields as $key => [$g, $label, $type, $default, $secret])
                @continue($g !== $group)
                @php($name = str_replace('.', '__', $key))
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
                @elseif($key === 'unifi.console_id' && $consoles)
                    <label>{{ __($label) }}
                        <select name="{{ $name }}">
                            <option value="">{{ __('– bitte wählen –') }}</option>
                            @foreach($consoles as $id => $text)<option value="{{ $id }}" @selected(\App\Support\Settings::get($key) === $id)>{{ $text }}</option>@endforeach
                        </select>
                    </label>
                @elseif($secret)
                    <label>{{ __($label) }}
                        <input type="password" name="{{ $name }}" autocomplete="new-password" placeholder="{{ \App\Support\Settings::has($key) ? '•••••••• '.__('(gespeichert – leer lassen, um es zu behalten)') : __('noch nicht hinterlegt') }}">
                    </label>
                @else
                    <label>{{ __($label) }}
                        <input type="{{ ['int' => 'number', 'url' => 'url', 'email' => 'email', 'color' => 'color'][$type] ?? 'text' }}" name="{{ $name }}" value="{{ \App\Support\Settings::get($key) }}">
                    </label>
                @endif
            @endforeach
            @if($group === 'unifi')
                <p class="muted small">{{ __('Über die UniFi-Cloud braucht es weder VPN noch Portfreigabe: API-Schlüssel unter unifi.ui.com → API anlegen, speichern, dann „Konsolen laden“ und die UDM SE wählen.') }}</p>
                <div class="row">
                    <button class="btn ghost" formaction="{{ route('admin.settings.unifi-consoles') }}" formmethod="post" name="_method" value="POST">☁️ {{ __('Konsolen laden') }}</button>
                    <button class="btn ghost" formaction="{{ route('admin.settings.unifi-test') }}" formmethod="post" name="_method" value="POST">🔌 {{ __('Verbindung testen') }}</button>
                </div>
            @endif
        </section>
    @endforeach
    <div class="formactions"><button class="btn">{{ __('Speichern') }}</button></div>
</form>
@endsection
