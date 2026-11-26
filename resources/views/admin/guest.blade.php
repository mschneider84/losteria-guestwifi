<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>@yield('title') · {{ \App\Support\Brand::name() }}</title>
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}?v={{ \App\Support\Version::build() }}">
    <style>:root{--brand: {{ \App\Support\Brand::color() }};}</style>
</head>
<body class="auth">
<main class="auth-card">
    <div class="auth-logo"><span class="logo-dot"></span>{{ \App\Support\Brand::name() }}</div>
    @include('admin.partials.flash')
    @yield('content')
    <p class="auth-version">{{ \App\Support\Version::label() }}</p>
</main>
</body>
</html>
