@extends('admin.guest')
@section('title', __('Anmelden'))
@section('content')
<form method="post" class="stack">
    @csrf
    <input type="text" name="website" class="hp" tabindex="-1" autocomplete="off">
    <label>{{ __('E-Mail') }}<input type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username"></label>
    <label>{{ __('Passwort') }}<input type="password" name="password" required autocomplete="current-password"></label>
    <label class="inline"><input type="checkbox" name="remember" value="1"> {{ __('Angemeldet bleiben') }}</label>
    <button class="btn">{{ __('Anmelden') }}</button>
</form>
@endsection
