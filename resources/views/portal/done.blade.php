@extends('portal.layout')

@section('content')
    <div class="hero done">
        <div class="ok" aria-hidden="true">✓</div>
        <h1>{{ $location->guestText('done_title', $lang) }}</h1>
        <p class="lead">{{ $location->guestText('done_text', $lang) }}</p>
        <div class="chips"><span class="chip">⏱ {{ __('portal.session_info', ['duration' => $location->sessionLabel()]) }}</span></div>
    </div>
    @if($newsletter)
        <div class="notice">✉️ {{ __('portal.done_newsletter') }}</div>
    @endif
    @if($promotion)
        @include('portal.promotion', ['promotion' => $promotion, 'withButton' => true])
    @endif
    @if($redirect)
        @unless($promotion)<p class="muted center" id="redir">{{ __('portal.done_redirect') }}</p>@endunless
        <a href="{{ $redirect }}" class="btn">{{ __('portal.continue') }}</a>
    @endif
@endsection

@if($redirect && !$demo && !$promotion)
@push('scripts')
<script>setTimeout(function () { location.href = @json($redirect); }, 4000);</script>
@endpush
@endif
