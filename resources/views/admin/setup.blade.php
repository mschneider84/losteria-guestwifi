@extends('admin.guest')
@section('title', __('Einrichtung'))
@section('content')
<p class="muted">{{ __('Willkommen! Lege jetzt das erste Admin-Konto an. Diese Seite gibt es nur, solange noch kein Benutzer existiert.') }}</p>
<form method="post" class="stack">
    @csrf
    <label>{{ __('Name') }}<input name="name" value="{{ old('name') }}" required></label>
    <label>{{ __('E-Mail') }}<input type="email" name="email" value="{{ old('email') }}" required></label>
    <label>{{ __('Passwort (mind. 12 Zeichen)') }}<input type="password" name="password" required minlength="12"></label>
    <label>{{ __('Passwort wiederholen') }}<input type="password" name="password_confirmation" required></label>
    <button class="btn">{{ __('Konto anlegen') }}</button>
</form>
@endsection
