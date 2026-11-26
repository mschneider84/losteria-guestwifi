@extends('layouts.admin')
@section('title', __('Newsletter-Kontakte'))
@section('content')
<div class="pagehead">
    <h1>✉️ {{ __('Newsletter-Kontakte') }}</h1>
    @can('contacts.export')<a href="{{ route('admin.contacts.export', ['standort' => request('standort')]) }}" class="btn">⬇ {{ __('CSV exportieren') }}</a>@endcan
</div>
<p class="muted">{{ __('Nur bestätigte Kontakte (Double-Opt-in) werden exportiert. Unbestätigte werden nach der Löschfrist automatisch entfernt.') }}</p>
<form method="get" class="filters">
    <div class="seg small">
        <a href="?" @class(['on' => !$status])>{{ __('Alle') }}</a>
        <a href="?status=confirmed" @class(['on' => $status === 'confirmed'])>{{ __('Bestätigt') }} · {{ $counts['confirmed'] ?? 0 }}</a>
        <a href="?status=pending" @class(['on' => $status === 'pending'])>{{ __('Offen') }} · {{ $counts['pending'] ?? 0 }}</a>
        <a href="?status=unsubscribed" @class(['on' => $status === 'unsubscribed'])>{{ __('Abgemeldet') }} · {{ $counts['unsubscribed'] ?? 0 }}</a>
    </div>
    <input type="search" name="q" value="{{ request('q') }}" placeholder="{{ __('E-Mail suchen …') }}">
    <input type="hidden" name="status" value="{{ $status }}">
</form>
@if($contacts->isEmpty())
    <div class="empty"><p>{{ __('Noch keine Kontakte. Sobald Gäste sich auf der Gästeseite für den Newsletter anmelden, erscheinen sie hier.') }}</p></div>
@else
<section class="panel flush">
<table class="table">
    <thead><tr><th>{{ __('E-Mail') }}</th><th>{{ __('Standort') }}</th><th>{{ __('Sprache') }}</th><th>{{ __('Status') }}</th><th>{{ __('Angemeldet') }}</th></tr></thead>
    <tbody>
    @foreach($contacts as $c)
        <tr>
            <td>{{ $c->email }}</td>
            <td>{{ $c->location?->name }}</td>
            <td>{{ strtoupper($c->language) }}</td>
            <td><span @class(['pill', 'ok' => $c->status === 'confirmed', 'warn' => $c->status === 'pending', 'off' => $c->status === 'unsubscribed'])>{{ ['confirmed' => __('bestätigt'), 'pending' => __('offen'), 'unsubscribed' => __('abgemeldet')][$c->status] }}</span></td>
            <td>{{ $c->consent_at?->timezone('Europe/Berlin')->format('d.m.Y H:i') }}</td>
        </tr>
    @endforeach
    </tbody>
</table>
</section>
{{ $contacts->links() }}
@endif
@endsection
