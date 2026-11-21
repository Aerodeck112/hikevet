<!doctype html>
<html lang="ro">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $seo->title }}</title>
    <meta name="robots" content="noindex, nofollow">
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    @vite(['resources/css/site.css', 'resources/js/site.js'])
</head>
<body class="bare">
<header class="bare-head no-print"><div class="wrap"><img src="{{ asset('media/brand/logo-negru.webp') }}" alt="Clinica HiKeVet" width="900" height="654"></div></header>
<main id="continut">@yield('content')</main>
</body>
</html>
