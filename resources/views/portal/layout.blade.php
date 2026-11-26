<!DOCTYPE html>
<html lang="{{ $lang }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex">
    <meta name="theme-color" content="{{ $location?->brand_color ?? '#b3261e' }}">
    <title>{{ $location?->name ?? config('app.name') }} · {{ __('portal.title') }}</title>
    <link rel="stylesheet" href="{{ asset('css/portal.css') }}?v={{ \App\Support\Version::build() }}">
    <style>:root{--brand: {{ $location?->brand_color ?? '#b3261e' }};}</style>
</head>
<body>
<div class="bg" aria-hidden="true"></div>
<main class="wrap">
    @if(!empty($demo))
        <div class="demo-band">🎭 {{ __('portal.demo') }}</div>
    @endif
    <div class="card">
        <header class="brand">
            @if($location?->logoUrl())
                <img src="{{ $location->logoUrl() }}" alt="{{ $location->name }}" class="logo">
            @else
                <div class="logo-placeholder" title="Logo-Platzhalter">
                    <span class="mark">{{ $location?->name ?? 'Osteria' }}</span>
                    <span class="tricolore"><i></i><i></i><i></i></span>
                    @if($location?->city)<span class="city">{{ $location->city }}</span>@endif
                </div>
            @endif
        </header>
        @yield('content')
    </div>
    <nav class="langs" aria-label="Sprache">
        @foreach(['de' => 'Deutsch', 'en' => 'English', 'it' => 'Italiano'] as $code => $label)
            <a href="?lang={{ $code }}" class="{{ $lang === $code ? 'active' : '' }}" hreflang="{{ $code }}">{{ $label }}</a>
        @endforeach
    </nav>
    <footer class="legal">
        @if(!empty($imprintUrl))<a href="{{ $imprintUrl }}" target="_blank">{{ __('portal.imprint') }}</a>@endif
        @if(!empty($privacyUrl))<a href="{{ $privacyUrl }}" target="_blank">{{ __('portal.privacy') }}</a>@endif
        <span>{{ \App\Support\Brand::copyright() }}</span>
    </footer>
</main>
@stack('scripts')
</body>
</html>
