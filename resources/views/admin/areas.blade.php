@extends('layouts.admin')
@section('title', __('Bereiche'))
@section('content')
<a href="{{ route('admin.settings') }}" class="back">← {{ __('Einstellungen') }}</a>
<h1>🗺 {{ __('Bereiche') }}</h1>
<p class="muted">{{ __('Eine Liste für alle Restaurants. Unter Netzwerk ordnest du jedem Access Point einen Bereich zu; die Auswertungen zeigen dann Auslastung und Verweildauer je Bereich. Umbenennen gilt auch für bisherige Auswertungen.') }}</p>

<section class="panel">
    <form method="post" action="{{ route('admin.areas.store') }}" class="row">
        @csrf
        <input name="name" maxlength="40" required placeholder="{{ __('Neuer Bereich, z. B. Wintergarten') }}" value="{{ old('name') }}">
        <button class="btn">＋ {{ __('Anlegen') }}</button>
    </form>
</section>

@if($areas->isEmpty())
    <div class="empty muted">{{ __('Noch keine Bereiche.') }}</div>
@else
<form method="post" action="{{ route('admin.areas.update') }}" class="panel flush">
    @csrf @method('PUT')
    <input type="hidden" name="order" value="{{ $areas->pluck('id')->implode(',') }}">
    <table class="table">
        <thead><tr><th>{{ __('Reihenfolge') }}</th><th>{{ __('Name') }}</th><th>{{ __('Farbe') }}</th><th class="num">{{ __('Access Points') }}</th><th>{{ __('Löschen') }}</th></tr></thead>
        <tbody>
        @foreach($areas as $a)
            <tr>
                <td class="nowrap">
                    <button class="iconbtn" formaction="{{ route('admin.areas.move', [$a, 'up']) }}" formmethod="post" name="_method" value="POST" @disabled($loop->first) title="{{ __('nach oben') }}">▲</button>
                    <button class="iconbtn" formaction="{{ route('admin.areas.move', [$a, 'down']) }}" formmethod="post" name="_method" value="POST" @disabled($loop->last) title="{{ __('nach unten') }}">▼</button>
                </td>
                <td><input name="areas[{{ $a->id }}][name]" value="{{ old("areas.{$a->id}.name", $a->name) }}" maxlength="40" required></td>
                <td><input type="color" name="areas[{{ $a->id }}][color]" value="{{ $a->color }}" style="width:60px"></td>
                <td class="num">{{ $used[$a->name] ?? 0 }}</td>
                <td><label class="inline"><input type="checkbox" name="areas[{{ $a->id }}][delete]" value="1"> {{ __('löschen') }}</label></td>
            </tr>
        @endforeach
        </tbody>
    </table>
    <p class="pad"><button class="btn">{{ __('Speichern') }}</button> <span class="muted small">{{ __('Gelöschte Bereiche werden von den Access Points entfernt.') }}</span></p>
</form>
@endif
@endsection
