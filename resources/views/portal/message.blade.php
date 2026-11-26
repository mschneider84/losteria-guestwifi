@extends('portal.layout')

@section('content')
    <div class="hero done">
        <div class="ok" aria-hidden="true">✓</div>
        <h1>{{ $title }}</h1>
        <p class="lead">{{ $text }}</p>
    </div>
@endsection
