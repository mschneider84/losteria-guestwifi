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
        <label>{{ __('UniFi-Site') }} <i class="tip" title="{{ __('Kurzname der Site in der UDM, steht in der Adresse /network/<site>/… – bei nur einer Site „default“.') }}">ⓘ</i><input name="unifi_site" value="{{ old('unifi_site', $location->unifi_site) }}" required></label>
        <label>{{ __('Zeitlimit (Minuten)') }}<input type="number" name="session_minutes" value="{{ old('session_minutes', $location->session_minutes) }}" min="15" max="1440" required></label>
        <div class="two">
            <label>{{ __('Download (kbit/s)') }}<input type="number" name="down_kbps" value="{{ old('down_kbps', $location->down_kbps) }}" placeholder="{{ __('unbegrenzt') }}"></label>
            <label>{{ __('Upload (kbit/s)') }}<input type="number" name="up_kbps" value="{{ old('up_kbps', $location->up_kbps) }}" placeholder="{{ __('unbegrenzt') }}"></label>
        </div>
        <label>{{ __('Datenvolumen (MB)') }}<input type="number" name="quota_mb" value="{{ old('quota_mb', $location->quota_mb) }}" placeholder="{{ __('unbegrenzt') }}"></label>
    </section>
    <section class="panel">
        <h2>🎨 {{ __('Gästeseite') }}</h2>
        <div class="two">
            <label>{{ __('Farbe') }}<input type="color" name="brand_color" value="{{ old('brand_color', $location->brand_color) }}"></label>
            <label>{{ __('Logo') }}<input type="file" name="logo" accept="image/*" class="drop"></label>
        </div>
        @foreach(['de' => 'Deutsch', 'en' => 'English', 'it' => 'Italiano'] as $code => $label)
            <fieldset>
                <legend>{{ $label }}</legend>
                <label>{{ __('Überschrift') }}<input name="headline[{{ $code }}]" value="{{ old("headline.$code", $location->headline[$code] ?? '') }}" placeholder="{{ trans('portal.headline', [], $code) }}"></label>
                <label>{{ __('Begrüßung') }}<input name="welcome_text[{{ $code }}]" value="{{ old("welcome_text.$code", $location->welcome_text[$code] ?? '') }}" placeholder="{{ trans('portal.welcome', [], $code) }}"></label>
            </fieldset>
        @endforeach
        <label>{{ __('Weiterleitung nach dem Verbinden') }}<input type="url" name="redirect_url" value="{{ old('redirect_url', $location->redirect_url) }}" placeholder="https://"></label>
        <label class="inline"><input type="checkbox" name="newsletter_enabled" value="1" @checked(old('newsletter_enabled', $location->newsletter_enabled))> {{ __('Newsletter-Anmeldung anbieten') }}</label>
        <input type="hidden" name="collect_email" value="1">
    </section>
    <div class="formactions"><button class="btn">{{ __('Speichern') }}</button></div>
</form>
@endsection
