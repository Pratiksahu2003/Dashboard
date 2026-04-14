<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        @if (! empty($inertiaPageSeo))
        <title>{{ $inertiaPageSeo['title'] }}</title>
        <meta name="description" content="{{ $inertiaPageSeo['description'] }}">
        <link rel="canonical" href="{{ $inertiaPageSeo['canonical_url'] }}">
        <meta property="og:type" content="{{ $inertiaPageSeo['og_type'] }}">
        <meta property="og:site_name" content="{{ $inertiaPageSeo['site_name'] }}">
        <meta property="og:locale" content="en_IN">
        <meta property="og:url" content="{{ $inertiaPageSeo['canonical_url'] }}">
        <meta property="og:title" content="{{ $inertiaPageSeo['title'] }}">
        <meta property="og:description" content="{{ $inertiaPageSeo['description'] }}">
        <meta property="og:image" content="{{ $inertiaPageSeo['og_image'] }}">
        <meta property="og:image:secure_url" content="{{ $inertiaPageSeo['og_image'] }}">
        <meta name="twitter:card" content="summary_large_image">
        <meta name="twitter:title" content="{{ $inertiaPageSeo['title'] }}">
        <meta name="twitter:description" content="{{ $inertiaPageSeo['description'] }}">
        <meta name="twitter:image" content="{{ $inertiaPageSeo['og_image'] }}">
        @else
        <title inertia>{{ config('app.name', 'SuGanta Intl') }}</title>
        @endif

        <!-- Favicon -->
        <link rel="icon" type="image/x-icon" href="/logo/favicon.ico">

        <link rel="preconnect" href="https://fonts.bunny.net" crossorigin>
        <link rel="stylesheet" href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap">

        <!-- Scripts: single entry — page chunks load on demand via Inertia -->
        @routes
        @vite(['resources/js/app.js'])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
