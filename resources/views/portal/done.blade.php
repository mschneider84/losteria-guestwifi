@extends('portal.layout')

@section('content')
    <div class="hero done">
        <div class="ok" aria-hidden="true">✓</div>
        <h1>{{ __('portal.done_title') }}</h1>
        <p class="lead">{{ __('portal.done_text') }}</p>
        <div class="chips"><span class="chip">⏱ {{ __('portal.session_info', ['duration' => $location->sessionLabel()]) }}</span></div>
    </div>
    @if($newsletter)
        <div class="notice">✉️ {{ __('portal.done_newsletter') }}</div>
    @endif
    @if($redirect)
        <p class="muted center" id="redir">{{ __('portal.done_redirect') }}</p>
        <a href="{{ $redirect }}" class="btn">{{ __('portal.continue') }}</a>
    @endif
@endsection

@if($redirect && !$demo)
@push('scripts')
<script>setTimeout(function () { location.href = @json($redirect); }, 4000);</script>
@endpush
@endif
