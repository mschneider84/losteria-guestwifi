@extends('portal.layout')

@section('content')
    <article class="legal-text">
        <h1>{{ $title }}</h1>
        {!! $html !!}
    </article>
    <p><a href="{{ route('portal.show', ['location' => $location->slug, 'lang' => $lang]) }}" class="btn ghost">← {{ __('portal.back') }}</a></p>
@endsection
