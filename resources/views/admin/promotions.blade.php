@extends('layouts.admin')
@section('title', __('Aktionen'))
@section('content')
<div class="pagehead"><h1>📣 {{ __('Marketingaktionen') }}</h1></div>
<p class="muted">{{ __('Die Aktionen werden im L\'Osteria Dashboard gepflegt und hier automatisch übernommen. Sie erscheinen auf der Gästeseite vor oder nach dem Verbinden.') }}</p>
@if($promotions->isEmpty())
    <div class="empty"><p>{{ __('Noch keine Aktion vom Dashboard empfangen. Sobald das Dashboard eine Aktion schickt, erscheint sie hier.') }}</p>
        @can('settings.manage')<a href="{{ route('admin.api-keys.index') }}" class="btn ghost">🔌 {{ __('Schnittstelle einrichten') }}</a>@endcan</div>
@else
<div class="cards">
    @foreach($promotions as $p)
        @php($s = $stats[$p->id] ?? null)
        @php($running = $p->active && (!$p->starts_at || $p->starts_at->isPast()) && (!$p->ends_at || $p->ends_at->isFuture()))
        <article class="loc promo-card" style="--c: var(--brand)">
            @if($p->imageUrl())<img src="{{ $p->imageUrl() }}" alt="" class="promo-thumb">@endif
            <div class="loc-head">
                <div><h3>{{ $p->t('title', 'de') }}</h3><span class="muted small">ID {{ $p->external_id }}</span></div>
                <span @class(['pill', 'ok' => $running, 'off' => !$running])>{{ $running ? __('läuft') : ($p->active ? __('geplant/beendet') : __('pausiert')) }}</span>
            </div>
            @if($p->t('text', 'de'))<p class="small">{{ $p->t('text', 'de') }}</p>@endif
            <dl class="facts">
                <div><dt>{{ __('Zeitraum') }}</dt><dd>{{ $p->starts_at?->format('d.m.') ?? '…' }} – {{ $p->ends_at?->format('d.m.Y') ?? '…' }}</dd></div>
                <div><dt>{{ __('Standorte') }}</dt><dd>{{ $p->location_slugs ? collect($p->location_slugs)->map(fn ($s) => $locations[$s] ?? $s)->join(', ') : __('alle') }}</dd></div>
                <div><dt>{{ __('Wann') }}</dt><dd>{{ $p->weekdays ? collect(explode(',', $p->weekdays))->map(fn ($d) => ['', 'Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa', 'So'][$d])->join(' ') : __('täglich') }}{{ $p->time_from ? ', '.$p->time_from.'–'.$p->time_to : '' }}</dd></div>
                <div><dt>{{ __('Wo') }}</dt><dd>{{ ['start' => __('vor dem Verbinden'), 'online' => __('nach dem Verbinden'), 'both' => __('vorher und nachher')][$p->placement] }}</dd></div>
                <div><dt>{{ __('Aufrufe (30 Tage)') }}</dt><dd>{{ number_format($s->views ?? 0, 0, ',', '.') }}</dd></div>
                <div><dt>{{ __('Klicks') }}</dt><dd>{{ number_format($s->clicks ?? 0, 0, ',', '.') }}{{ ($s->views ?? 0) ? ' · '.round($s->clicks / $s->views * 100, 1).' %' : '' }}</dd></div>
            </dl>
        </article>
    @endforeach
</div>
@endif
@endsection
