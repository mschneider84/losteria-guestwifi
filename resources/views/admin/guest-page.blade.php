@extends('layouts.admin')
@section('title', __('Gästeseite & Rechtstexte'))
@section('content')
<a href="{{ route('admin.settings') }}" class="back">← {{ __('Einstellungen') }}</a>
<h1>🖼 {{ __('Gästeseite & Rechtstexte') }}</h1>
<p class="muted">{{ __('Gilt für alle Restaurants. Unter Standorte → Bearbeiten kann jedes Restaurant ein eigenes Logo, eigene Texte und eigene Freischaltwerte bekommen; leere Felder dort nehmen die Werte von hier.') }}</p>
<form method="post" action="{{ route('admin.guest-page.update') }}" enctype="multipart/form-data">
    @csrf @method('PUT')
    <section class="panel">
        <h2>{{ __('Standard-Logo') }}</h2>
        <div class="logo-preview">
            @if($logoPath)
                <img src="{{ route('media.default-logo', ['v' => substr(md5($logoPath), 0, 8)]) }}" alt="Logo">
                <label class="inline"><input type="checkbox" name="logo_remove" value="1"> {{ __('Logo entfernen') }}</label>
            @else
                <span class="muted">{{ __('Noch kein Logo – die Gästeseite zeigt den Namen des Restaurants.') }}</span>
            @endif
        </div>
        <label>{{ __('Neues Logo hochladen (PNG, JPG oder WebP, höchstens 2 MB; am besten quer mit transparentem Hintergrund)') }}
            <input type="file" name="logo" accept="image/png,image/jpeg,image/webp" class="drop"></label>
    </section>

    <section class="panel">
        <h2>✏️ {{ __('Texte auf der Gästeseite') }}</h2>
        <p class="muted small">{{ __('Leere Felder zeigen den eingebauten Text (grau vorgeschlagen).') }}</p>
        @foreach(['de' => 'Deutsch', 'en' => 'English', 'it' => 'Italiano'] as $code => $lang)
            <details class="legal-edit" @if($loop->first) open @endif>
                <summary><strong>{{ $lang }}</strong></summary>
                @foreach(\App\Support\GuestDefaults::TEXTS as $field => [$label, $max])
                    <label>{{ __($label) }}<input name="ui[{{ $field }}][{{ $code }}]" maxlength="{{ $max }}" value="{{ old("ui.$field.$code", $uiTexts[$field][$code] ?? '') }}" placeholder="{{ trans('portal.'.$field, [], $code) }}"></label>
                @endforeach
            </details>
        @endforeach
    </section>

    <section class="panel">
        <h2>⏱ {{ __('Freischaltung (Standard)') }}</h2>
        <p class="muted small">{{ __('Gilt für alle Restaurants mit Haken „Standardwerte verwenden“ (Standorte → Bearbeiten). Alle Grenzen gelten je Gast-Gerät und werden von der UDM durchgesetzt; Gäste sehen nur die Dauer. Leer = unbegrenzt.') }}</p>
        <div class="two">
            <label>{{ __('WLAN frei pro Gast (Minuten)') }}<input type="number" name="limits[session_minutes]" min="15" max="1440" required value="{{ old('limits.session_minutes', $limits['session_minutes']) }}"></label>
            <label>{{ __('Datenvolumen pro Gast (MB, Download + Upload zusammen)') }}<input type="number" name="limits[quota_mb]" min="10" max="100000" value="{{ old('limits.quota_mb', $limits['quota_mb']) }}" placeholder="{{ __('unbegrenzt') }}"></label>
            <label>{{ __('Max. Download pro Gast (kbit/s, 20000 = 20 Mbit/s)') }}<input type="number" name="limits[down_kbps]" min="256" max="1000000" value="{{ old('limits.down_kbps', $limits['down_kbps']) }}" placeholder="{{ __('unbegrenzt') }}"></label>
            <label>{{ __('Max. Upload pro Gast (kbit/s)') }}<input type="number" name="limits[up_kbps]" min="256" max="1000000" value="{{ old('limits.up_kbps', $limits['up_kbps']) }}" placeholder="{{ __('unbegrenzt') }}"></label>
        </div>
        <p class="muted small">{{ __(':n von :t Restaurants verwenden die Standardwerte.', ['n' => $usingDefaults, 't' => $locationCount]) }}</p>
    </section>

    <section class="panel">
        <h2>{{ __('Rechtstexte') }}</h2>
        <p class="muted small">{{ __('Erscheinen auf der Gästeseite (unten verlinkt und beim Haken „Ich akzeptiere …“), vor der Anmeldung erreichbar. Leere Sprachen zeigen den deutschen Text. Formatierung: Leerzeile = neuer Absatz, ## Überschrift, **fett**, - Aufzählung, [Linktext](https://…).') }}</p>
        <div class="tabs-legal">
            @foreach(\App\Support\Legal::LABELS as $key => $label)
                <details class="legal-edit" @if($loop->first) open @endif>
                    <summary><strong>{{ __($label) }}</strong>
                        <span class="muted small">{{ collect($texts[$key] ?? [])->filter()->keys()->map(fn ($l) => strtoupper($l))->implode(' · ') ?: __('noch leer') }}</span>
                        @if($previewLocation && ! empty($texts[$key]))<a class="small" target="_blank" href="{{ route('portal.legal', [$previewLocation->slug, \App\Support\Legal::PAGES[$key][0]]) }}">{{ __('ansehen') }} ↗</a>@endif
                    </summary>
                    @foreach(['de' => 'Deutsch', 'en' => 'English', 'it' => 'Italiano'] as $code => $lang)
                        <label>{{ $lang }}
                            <textarea name="texts[{{ $key }}][{{ $code }}]" rows="{{ $code === 'de' ? 14 : 6 }}" @if($key === 'terms' && $code === 'de') placeholder="{{ trans('portal.terms_text', [], 'de') }}" @endif>{{ old("texts.$key.$code", $texts[$key][$code] ?? '') }}</textarea>
                        </label>
                    @endforeach
                </details>
            @endforeach
        </div>
    </section>
    <div class="formactions"><button class="btn">{{ __('Speichern') }}</button></div>
</form>
@endsection
