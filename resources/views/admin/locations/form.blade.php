@extends('layouts.admin')
@section('title', $location->exists ? $location->name : __('Neuer Standort'))
@section('content')
<a href="{{ route('admin.locations.index') }}" class="back">← {{ __('Liste') }}</a>
<h1>{{ $location->exists ? $location->name : __('Neuer Standort') }}</h1>
<form method="post" enctype="multipart/form-data" action="{{ $location->exists ? route('admin.locations.update', $location) : route('admin.locations.store') }}" class="formgrid">
    @csrf @if($location->exists) @method('PUT') @endif
    <section class="panel">
        <h2>{{ __('Allgemein') }}</h2>
        <label>{{ __('Name') }}<input name="name" value="{{ old('name', $location->name) }}" required></label>
        <label>{{ __('Stadt') }}<input name="city" value="{{ old('city', $location->city) }}"></label>
        <label>{{ __('Kurzname in der URL') }} <i class="tip" title="{{ __('Leer lassen, dann wird er aus dem Namen gebildet.') }}">ⓘ</i><input name="slug" value="{{ old('slug', $location->slug) }}"></label>
        <label class="inline"><input type="checkbox" name="active" value="1" @checked(old('active', $location->active))> {{ __('Aktiv') }}</label>
    </section>
    <section class="panel">
        <h2>📡 {{ __('UniFi') }}</h2>
        @php($consoleId = old('unifi_console_id', $location->unifi_console_id))
        <label>{{ __('UniFi-Konsole (UDM SE dieses Restaurants)') }}
            @if($consoles)
                <select name="unifi_console_id">
                    <option value="">{{ __('– Standard aus den Einstellungen –') }}</option>
                    @foreach($consoles as $id => $text)<option value="{{ $id }}" @selected($consoleId === $id)>{{ $text }}</option>@endforeach
                    @if($consoleId && ! isset($consoles[$consoleId]))<option value="{{ $consoleId }}" selected>{{ $consoleId }}</option>@endif
                </select>
            @else
                <input name="unifi_console_id" value="{{ $consoleId }}" placeholder="{{ __('Konsolen-ID – oder erst unter Einstellungen den API-Schlüssel speichern') }}">
            @endif
        </label>
        @if($location->unifi_wan_ip)<p class="muted small">{{ __('Öffentliche IP der UDM: :ip – darüber erkennt die Gästeseite das Restaurant.', ['ip' => $location->unifi_wan_ip]) }}</p>@endif
        <details class="small">
            <summary>{{ __('Eigener API-Schlüssel für diese Konsole (optional)') }}</summary>
            <p class="muted">{{ __('Nur nötig, wenn diese UDM einem anderen UI-Konto gehört oder der Standard-Schlüssel diese Site nicht enthält. Der Schlüssel braucht den Bereich „UniFi-Anwendungen“ (Network) mit dieser Site.') }}</p>
            <input type="password" name="unifi_api_key" autocomplete="new-password" placeholder="{{ $location->unifi_api_key ? '•••••••• '.__('(gespeichert – leer lassen, um es zu behalten)') : __('leer = Schlüssel aus den Einstellungen') }}">
            @if($location->unifi_api_key)<label class="inline"><input type="checkbox" name="unifi_api_key_remove" value="1"> {{ __('Eigenen Schlüssel entfernen') }}</label>@endif
        </details>
        <label>{{ __('UniFi-Site') }} <i class="tip" title="{{ __('Kurzname der Site in der UDM, steht in der Adresse /network/<site>/… – bei nur einer Site „default“.') }}">ⓘ</i><input name="unifi_site" value="{{ old('unifi_site', $location->unifi_site) }}" required></label>
        @if($location->exists)
            <p><button class="btn ghost" formaction="{{ route('admin.locations.test', $location) }}" formmethod="post" name="_method" value="POST" formnovalidate>🔌 {{ __('Verbindung testen') }}</button> <span class="muted small">{{ __('(vorher speichern)') }}</span></p>
        @endif
        <h3 class="mt">⏱ {{ __('Freischaltung') }}</h3>
        <label class="inline"><input type="checkbox" name="use_default_limits" value="1" id="use-defaults" @checked(old('use_default_limits', $location->use_default_limits))> {{ __('Standardwerte verwenden') }} <span class="muted small">({{ __('aus „Gästeseite & Rechtstexte“: :d', ['d' => $defaultsLabel]) }})</span></label>
        <div id="own-limits">
            <div class="two">
                <label>{{ __('WLAN frei pro Gast (Minuten)') }}<input type="number" name="session_minutes" value="{{ old('session_minutes', $location->session_minutes) }}" min="15" max="1440" required></label>
                <label>{{ __('Datenvolumen pro Gast (MB, Download + Upload zusammen)') }}<input type="number" name="quota_mb" value="{{ old('quota_mb', $location->quota_mb) }}" placeholder="{{ __('unbegrenzt') }}"></label>
                <label>{{ __('Max. Download pro Gast (kbit/s)') }}<input type="number" name="down_kbps" value="{{ old('down_kbps', $location->down_kbps) }}" placeholder="{{ __('unbegrenzt') }}"></label>
                <label>{{ __('Max. Upload pro Gast (kbit/s)') }}<input type="number" name="up_kbps" value="{{ old('up_kbps', $location->up_kbps) }}" placeholder="{{ __('unbegrenzt') }}"></label>
            </div>
        </div>
    </section>
    <section class="panel">
        <h2>🎨 {{ __('Gästeseite') }}</h2>
        <div class="two">
            <label>{{ __('Farbe') }}<input type="color" name="brand_color" value="{{ old('brand_color', $location->brand_color) }}"></label>
            <label>{{ __('Eigenes Logo (sonst Standard-Logo)') }}<input type="file" name="logo" accept="image/png,image/jpeg,image/webp" class="drop"></label>
        </div>
        @if($location->exists && $location->logoUrl())
            <div class="logo-preview"><img src="{{ $location->logoUrl() }}" alt="Logo"> <span class="muted small">{{ $location->logo_path ? __('eigenes Logo') : __('Standard-Logo') }}</span>
                @if($location->logo_path)<label class="inline"><input type="checkbox" name="logo_remove" value="1"> {{ __('eigenes Logo entfernen') }}</label>@endif</div>
        @endif
        <p class="muted small">{{ __('Leer lassen = Standardtext aus „Gästeseite & Rechtstexte“ (grau vorgeschlagen).') }}</p>
        @foreach(['de' => 'Deutsch', 'en' => 'English', 'it' => 'Italiano'] as $code => $label)
            <fieldset>
                <legend>{{ $label }}</legend>
                @foreach(\App\Support\GuestDefaults::TEXTS as $field => [$tl, $max])
                    <label>{{ __($tl) }}<input name="texts[{{ $field }}][{{ $code }}]" maxlength="{{ $max }}" value="{{ old("texts.$field.$code", $location->ownText($field)[$code] ?? '') }}" placeholder="{{ \App\Support\GuestDefaults::text($field, $code) ?: trans('portal.'.$field, [], $code) }}"></label>
                @endforeach
            </fieldset>
        @endforeach
        <label>{{ __('Weiterleitung nach dem Verbinden') }}<input type="url" name="redirect_url" value="{{ old('redirect_url', $location->redirect_url) }}" placeholder="https://"></label>
        @if(\App\Support\Settings::get('modules.newsletter'))
        <label class="inline"><input type="checkbox" name="newsletter_enabled" value="1" @checked(old('newsletter_enabled', $location->newsletter_enabled))> {{ __('Newsletter-Anmeldung anbieten') }}</label>
        @else
            <input type="hidden" name="newsletter_enabled" value="{{ $location->newsletter_enabled ? 1 : 0 }}">
        @endif
        <input type="hidden" name="collect_email" value="1">
    </section>
    <div class="formactions"><button class="btn">{{ __('Speichern') }}</button></div>
</form>
@endsection
@push('scripts')
<script>
(function () {
    var c = document.getElementById('use-defaults'), box = document.getElementById('own-limits');
    if (!c || !box) return;
    function sync() { box.querySelectorAll('input').forEach(function (i) { i.disabled = c.checked; }); box.style.opacity = c.checked ? .5 : 1; }
    c.addEventListener('change', sync); sync();
})();
</script>
@endpush
