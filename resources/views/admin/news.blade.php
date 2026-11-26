@extends('layouts.admin')
@section('title', __('Neuigkeiten'))
@section('content')
<h1>📰 {{ __('Neuigkeiten') }}</h1>
@foreach($entries as $version => $items)
    <section class="panel">
        <h2>{{ __('Version') }} {{ $version }} <span class="muted small">· Build {{ $items[0]['build'] }} · {{ \Carbon\Carbon::parse($items[0]['date'])->format('d.m.Y') }}</span></h2>
        <ul class="news">
            @foreach($items as $e)
                <li><span @class(['pill', 'ok' => $e['type'] === 'new', 'info' => $e['type'] === 'improved', 'warn' => $e['type'] === 'fix'])>{{ ['new' => __('Neu'), 'improved' => __('Verbessert'), 'fix' => __('Behoben')][$e['type']] }}</span>
                    <strong>{{ $e['area'] }}</strong> – {{ app()->getLocale() === 'en' ? ($e['text_en'] ?? $e['text']) : $e['text'] }}</li>
            @endforeach
        </ul>
    </section>
@endforeach
@endsection
