@extends('layouts.admin')
@section('title', __('Benutzer & Rollen'))
@section('content')
<h1>👥 {{ __('Benutzer & Rollen') }}</h1>
@if($sso)<p class="muted">{{ __('Benutzer und Rollen kommen aus dem L\'Osteria Dashboard und werden bei jeder Anmeldung übernommen. Hier siehst du, wer schon im Gäste-WLAN war, und kannst jemanden sperren.') }}</p>@endif
<section class="panel flush">
<table class="table">
    <thead><tr><th>{{ __('Name') }}</th><th>{{ __('E-Mail') }}</th><th>{{ __('Rolle') }}</th><th>{{ __('Zuletzt angemeldet') }}</th><th></th></tr></thead>
    <tbody>
    @foreach($users as $user)
        <tr>
            <td>{{ $user->name }} @if($user->sso_sub)<span class="pill small">{{ __('Dashboard') }}</span>@endif</td><td>{{ $user->email }}</td>
            <td><select name="role" form="u{{ $user->id }}" @disabled($user->id === auth()->id() || ($sso && $user->sso_sub))>@foreach($roles as $key => [$label])<option value="{{ $key }}" @selected($user->role === $key)>{{ $label }}</option>@endforeach</select></td>
            <td>{{ $user->last_login_at?->timezone('Europe/Berlin')->format('d.m.Y H:i') ?? '–' }}</td>
            <td class="num">@if($user->id !== auth()->id())<form method="post" id="u{{ $user->id }}" action="{{ route('admin.users.update', $user) }}" class="row">@csrf @method('PUT')<label class="inline small"><input type="checkbox" name="active" value="1" @checked($user->active)> {{ __('aktiv') }}</label> <button class="btn ghost small">{{ __('Speichern') }}</button></form>@endif</td>
        </tr>
    @endforeach
    </tbody>
</table>
</section>
<div class="grid2">
@unless($sso)
<section class="panel">
    <h2>＋ {{ __('Benutzer anlegen') }}</h2>
    <form method="post" action="{{ route('admin.users.store') }}" class="stack">
        @csrf
        <label>{{ __('Name') }}<input name="name" required></label>
        <label>{{ __('E-Mail') }}<input type="email" name="email" required></label>
        <label>{{ __('Rolle') }}<select name="role">@foreach($roles as $key => [$label])<option value="{{ $key }}" @selected($key === 'manager')>{{ $label }}</option>@endforeach</select></label>
        <button class="btn">{{ __('Anlegen') }}</button>
    </form>
</section>
@endunless
<section class="panel">
    <h2>{{ __('Rechte je Rolle') }}</h2>
    <table class="table matrix">
        <thead><tr><th></th>@foreach($roles as [$label])<th>{{ $label }}</th>@endforeach</tr></thead>
        <tbody>
        @foreach($perms as $key => [$area, $label, $info])
            <tr><td>{{ __($label) }} <i class="tip" title="{{ __($info) }}">ⓘ</i></td>
                @foreach($roles as $rk => $r)<td class="center">{{ in_array($key, \App\Support\Permissions::forRole($rk)) ? '✓' : '·' }}</td>@endforeach</tr>
        @endforeach
        </tbody>
    </table>
</section>
</div>
@endsection
