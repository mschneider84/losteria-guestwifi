<!DOCTYPE html>
<html lang="{{ $lang }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex">
    <meta name="theme-color" content="{{ $location?->brand_color ?? '#db052c' }}">
    <title>{{ $location?->name ?? config('app.name') }} · {{ __('portal.title') }}</title>
    <link rel="preload" href="{{ asset('fonts/league-gothic-latin-400-normal.woff2') }}" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="{{ asset('css/portal.css') }}?v={{ \App\Support\Version::build() }}">
    <style>:root{--brand: {{ $location?->brand_color ?? '#db052c' }};}</style>
</head>
<body>
<header class="top">
    @if($location?->logoUrl())
        <img src="{{ $location->logoUrl() }}" alt="{{ $location->name }}" class="logo">
    @else
        <span class="wordmark">{{ $location?->name ?? config('app.name') }}</span>
    @endif
    @if($location?->city)<span class="city">{{ $location->city }}</span>@endif
</header>
<main class="wrap">
    @if(!empty($demo))
        <div class="demo-band">🎭 {{ __('portal.demo') }}</div>
    @endif
    @yield('content')
</main>
<footer class="foot">
    <nav class="langs" aria-label="Sprache">
        @foreach(['de' => 'Deutsch', 'en' => 'English', 'it' => 'Italiano'] as $code => $label)
            <a href="?lang={{ $code }}" class="{{ $lang === $code ? 'active' : '' }}" hreflang="{{ $code }}">{{ $label }}</a>
        @endforeach
    </nav>
    <div class="legal">
        @if($location)
            @foreach(\App\Support\Legal::links($location, $lang) as $link)<a href="{{ $link['url'] }}">{{ $link['label'] }}</a>@endforeach
        @endif
    </div>
    <small>{{ \App\Support\Brand::copyright() }}</small>
</footer>
@stack('scripts')
</body>
</html>
