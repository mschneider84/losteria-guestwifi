@extends('admin.guest')
@section('title', __('Anmelden'))
@section('content')
@php($sso = \App\Support\DashboardSso::active())
@if($sso)
    @error('email')<p class="flash bad">{{ $message }}</p>@enderror
    <p class="muted">{{ __('Die Verwaltung des Gäste-WLANs öffnest du im L\'Osteria Dashboard über „Gäste-WLAN öffnen“.') }}</p>
    @if(\App\Support\DashboardSso::dashboardUrl())
        <p><a class="btn" style="display:block;text-align:center" href="{{ \App\Support\DashboardSso::dashboardUrl() }}">{{ __('Zum L\'Osteria Dashboard') }}</a></p>
    @endif
    <details class="small mt" @if($errors->any() && old('email')) open @endif>
        <summary>{{ __('Notfall-Zugang (nur Superadmin, wenn das Dashboard nicht erreichbar ist)') }}</summary>
@endif
<form method="post" action="{{ route('login') }}" class="stack">
    @csrf
    <input type="text" name="website" class="hp" tabindex="-1" autocomplete="off">
    <label>{{ __('E-Mail') }}<input type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username"></label>
    <label>{{ __('Passwort') }}<input type="password" name="password" required autocomplete="current-password"></label>
    <label class="inline"><input type="checkbox" name="remember" value="1"> {{ __('Angemeldet bleiben') }}</label>
    <button class="btn">{{ __('Anmelden') }}</button>
</form>
@if($sso)</details>@endif
@endsection
