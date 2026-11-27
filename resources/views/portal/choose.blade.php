@extends('portal.layout')

@section('content')
    <div class="hero">
        <h1>{{ __('portal.choose') }}</h1>
    </div>
    <div class="choose">
        @foreach($locations as $l)
            <a class="btn" style="--brand: {{ $l->brand_color }}" href="{{ url('/guest/s/'.$site.'/').'?'.http_build_query(array_merge($query, ['standort' => $l->slug])) }}">{{ $l->name }}@if($l->city) · {{ $l->city }}@endif</a>
        @endforeach
    </div>
@endsection
