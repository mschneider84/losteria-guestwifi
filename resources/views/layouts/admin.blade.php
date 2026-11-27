<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" data-theme="auto">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>@yield('title') · {{ \App\Support\Brand::name() }}</title>
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}?v={{ \App\Support\Version::build() }}">
    <style>:root{--brand: {{ \App\Support\Brand::color() }};}</style>
</head>
<body class="{{ session('demo') ? 'is-demo' : '' }}">
@php($u = auth()->user())
<header class="topbar">
    <button class="burger" onclick="document.body.classList.toggle('nav-open')" aria-label="Menü">☰</button>
    <a href="{{ route('admin.dashboard') }}" class="logo"><span class="logo-dot"></span>{{ \App\Support\Brand::name() }}</a>
    <nav class="mainnav">
        @can('dashboard.view')<a href="{{ route('admin.dashboard') }}" @class(['active' => request()->routeIs('admin.dashboard')])>📊 {{ __('Übersicht') }}</a>@endcan
        @can('dashboard.view')<a href="{{ route('admin.network') }}" @class(['active' => request()->routeIs('admin.network')])>📶 {{ __('Netzwerk') }}</a>@endcan
        @can('locations.manage')<a href="{{ route('admin.locations.index') }}" @class(['active' => request()->routeIs('admin.locations.*')])>📍 {{ __('Standorte') }}</a>@endcan
        @can('dashboard.view')<a href="{{ route('admin.promotions.index') }}" @class(['active' => request()->routeIs('admin.promotions.*')])>📣 {{ __('Aktionen') }}</a>@endcan
        @if(\App\Support\Settings::get('modules.newsletter'))@can('contacts.view')<a href="{{ route('admin.contacts.index') }}" @class(['active' => request()->routeIs('admin.contacts.*')])>✉️ {{ __('Newsletter') }}</a>@endcan @endif
        @can('status.view')<a href="{{ route('admin.status') }}" @class(['active' => request()->routeIs('admin.status')])>🩺 {{ __('Systemstatus') }}</a>@endcan
    </nav>
    <div class="topright">
        <button class="iconbtn" onclick="document.getElementById('fb').showModal()" title="{{ __('Wunsch oder Fehler melden') }}">💡</button>
        <details class="usermenu">
            <summary class="avatar" title="{{ $u->name }}">{{ $u->initials() }}</summary>
            <div class="menu">
                <div class="menu-head"><strong>{{ $u->name }}</strong><span>{{ \App\Support\Permissions::ROLES[$u->role][0] ?? $u->role }}</span></div>
                @can('settings.manage')<a href="{{ route('admin.settings') }}">⚙️ {{ __('Einstellungen') }}</a>@endcan
                @can('settings.manage')<a href="{{ route('admin.guest-page.edit') }}">🖼 {{ __('Gästeseite & Rechtstexte') }}</a>@endcan
                @can('settings.manage')<a href="{{ route('admin.api-keys.index') }}">🔌 {{ __('Dashboard-Schnittstelle') }}</a>@endcan
                @can('users.manage')<a href="{{ route('admin.users.index') }}">👥 {{ __('Benutzer & Rollen') }}</a>@endcan
                @can('demo')
                    <form method="post" action="{{ route('admin.demo') }}">@csrf<button>🎭 {{ session('demo') ? __('Demo-Modus beenden') : __('Demo-Modus') }}</button></form>
                @endcan
                <a href="{{ route('admin.news') }}">📰 {{ __('Neuigkeiten') }}
                    @if($u->changelog_seen_version !== \App\Support\Version::version())<span class="badge">{{ __('Neu') }}</span>@endif</a>
                @can('meldungen.manage')<a href="{{ route('admin.meldungen.index') }}">💡 {{ __('Wünsche & Fehler') }}</a>@endcan
                <span class="menu-version">{{ __('Version') }} {{ \App\Support\Version::version() }} · Build {{ \App\Support\Version::build() }}</span>
                <form method="post" action="{{ route('admin.logout') }}">@csrf<button>↪ {{ __('Abmelden') }}</button></form>
            </div>
        </details>
    </div>
</header>
@if(session('demo'))
    <div class="demo-band">🎭 {{ __('Demo-Modus – erfundene Daten, an der UDM wird nichts geändert.') }}</div>
@endif
<main class="page">
    @include('admin.partials.flash')
    @yield('content')
</main>
<footer class="foot">
    <a href="{{ route('admin.news') }}">{{ \App\Support\Version::label() }}</a>
    <span>{{ \App\Support\Brand::copyright() }}</span>
</footer>

<button class="fb-tab" onclick="document.getElementById('fb').showModal()">💡 {{ __('Feedback') }}</button>
<dialog id="fb" class="dialog">
    <form method="post" action="{{ route('admin.meldungen.store') }}">
        @csrf
        <h3>{{ __('Was möchtest du uns sagen?') }}</h3>
        <div class="seg">
            <label><input type="radio" name="type" value="wish" checked> 💡 {{ __('Wunsch') }}</label>
            <label><input type="radio" name="type" value="bug"> 🐞 {{ __('Fehler') }}</label>
            <label><input type="radio" name="type" value="praise"> 👍 {{ __('Lob') }}</label>
        </div>
        <textarea name="description" rows="5" required placeholder="{{ __('Beschreibe kurz, was passieren soll oder was nicht klappt …') }}"></textarea>
        <input type="hidden" name="page_url" value="{{ request()->fullUrl() }}">
        <input type="hidden" name="page_title" value="@yield('title')">
        <p class="muted small">{{ __('Seite, Version und dein Name werden automatisch mitgeschickt.') }}</p>
        <div class="actions">
            <button type="button" class="btn ghost" onclick="this.closest('dialog').close()">{{ __('Abbrechen') }}</button>
            <button class="btn">{{ __('Senden') }}</button>
        </div>
    </form>
</dialog>
<nav class="tabbar">
    <a href="{{ route('admin.dashboard') }}">📊<span>{{ __('Übersicht') }}</span></a>
    @can('dashboard.view')<a href="{{ route('admin.network') }}">📶<span>{{ __('Netzwerk') }}</span></a>@endcan
    @can('locations.manage')<a href="{{ route('admin.locations.index') }}">📍<span>{{ __('Standorte') }}</span></a>@endcan
    @if(\App\Support\Settings::get('modules.newsletter'))@can('contacts.view')<a href="{{ route('admin.contacts.index') }}">✉️<span>{{ __('Newsletter') }}</span></a>@endcan @endif
    @can('status.view')<a href="{{ route('admin.status') }}">🩺<span>{{ __('Status') }}</span></a>@endcan
</nav>
</body>
</html>
