@extends('portal.layout')

@section('content')
    <div class="hero">
        <div class="wifi" aria-hidden="true">
            <svg viewBox="0 0 24 24" width="30" height="30" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M2 8.5a15 15 0 0 1 20 0"/><path d="M5 12a10 10 0 0 1 14 0"/><path d="M8.5 15.5a5 5 0 0 1 7 0"/><circle cx="12" cy="19" r="1" fill="currentColor"/></svg>
        </div>
        <h1>{{ $location->text('headline', $lang, __('portal.headline')) }}</h1>
        <p class="lead">{{ $location->text('welcome_text', $lang, __('portal.welcome')) }}</p>
        <div class="chips">
            <span class="chip">⏱ {{ __('portal.session_info', ['duration' => $location->sessionLabel()]) }}</span>
            @if($location->down_kbps)
                <span class="chip">⚡ {{ __('portal.speed_info', ['mbit' => round($location->down_kbps / 1000)]) }}</span>
            @endif
        </div>
    </div>

    @if($invalid)
        <div class="notice">{{ __('portal.invalid', ['ssid' => $location->name]) }}</div>
    @else
        @if(session('portal_error'))
            <div class="alert">
                {{ __('portal.error') }}
                <small>{{ __('portal.error_code') }}: {{ session('portal_error') }}</small>
            </div>
        @endif

        <form method="post" action="{{ route('portal.connect', $location->slug) }}" class="form" id="connect">
            @csrf
            <input type="hidden" name="lang" value="{{ $lang }}">
            <input type="text" name="website" class="hp" tabindex="-1" autocomplete="off" aria-hidden="true">

            @if($location->newsletter_enabled)
                <label class="check">
                    <input type="checkbox" name="newsletter" value="1" id="nl" @checked(old('newsletter'))>
                    <span>{{ __('portal.newsletter') }} <em>({{ __('portal.optional') }})</em></span>
                </label>
                <div class="field {{ old('newsletter') ? '' : 'hidden' }}" id="email-field">
                    <label for="email">{{ __('portal.email') }}</label>
                    <input type="email" name="email" id="email" inputmode="email" autocomplete="email"
                           placeholder="{{ __('portal.email_placeholder') }}" value="{{ old('email') }}">
                    @error('email')<p class="err">{{ $message }}</p>@enderror
                </div>
            @endif

            <label class="check">
                <input type="checkbox" name="terms" value="1" required @checked(old('terms'))>
                <span>{!! __('portal.terms_accept', ['terms' => $termsUrl ?: '#terms', 'privacy' => $privacyUrl ?: '#terms']) !!}</span>
            </label>
            @error('terms')<p class="err">{{ $message }}</p>@enderror

            <button type="submit" class="btn">{{ __('portal.connect') }}</button>
        </form>

        @unless($termsUrl)
            <details class="terms" id="terms">
                <summary>{{ __('portal.terms') }}</summary>
                <p>{{ __('portal.terms_text') }}</p>
            </details>
        @endunless
    @endif
@endsection

@push('scripts')
<script>
    (function () {
        var nl = document.getElementById('nl'), f = document.getElementById('email-field');
        if (!nl || !f) return;
        nl.addEventListener('change', function () {
            f.classList.toggle('hidden', !nl.checked);
            if (nl.checked) document.getElementById('email').focus();
        });
        document.getElementById('connect').addEventListener('submit', function (e) {
            var b = this.querySelector('.btn'); b.disabled = true; b.classList.add('busy');
        });
    })();
</script>
@endpush
