<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'Imposter') }}</title>
    <link href="https://fonts.googleapis.com/css2?family=Lalezar&display=swap" rel="stylesheet">
    <link rel="icon" href="/favicon.ico" sizes="16x16 32x32 48x48" />
    <link rel="apple-touch-icon" href="/logo-192.png" />
    <link rel="apple-touch-startup-image" href="/splash-screen.png" />
    <link rel="manifest" href="/manifest.json" />
    <meta name="theme-color" content="#8b2500" />
    <meta name="apple-mobile-web-app-capable" content="yes" />
    @if(config('services.gtm.id'))
    <!-- Google Tag Manager -->
    <script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
    new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
    j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
    'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
    })(window,document,'script','dataLayer','{{ config('services.gtm.id') }}');</script>
    <!-- End Google Tag Manager -->
    @endif
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @inertiaHead
</head>
<body class="bg-[#2b1d14] text-[#3b2a1a]" style="background-image: repeating-linear-gradient(90deg, transparent, transparent 40px, rgba(0,0,0,0.1) 40px, rgba(0,0,0,0.1) 80px); font-family: 'Lalezar', cursive; overflow-x: hidden;">
    @if(config('services.gtm.id'))
    <!-- Google Tag Manager (noscript) -->
    <noscript><iframe src="https://www.googletagmanager.com/ns.html?id={{ config('services.gtm.id') }}"
    height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
    <!-- End Google Tag Manager (noscript) -->
    @endif
    @inertia
</body>
</html>
