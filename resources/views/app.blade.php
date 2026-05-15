<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'Imposter') }}</title>
    <link href="https://fonts.googleapis.com/css2?family=Lalezar&display=swap" rel="stylesheet">
    <link rel="icon" href="/favicon.ico" />
    <link rel="apple-touch-icon" href="/logo-192.png" />
    <link rel="apple-touch-startup-image" href="/splash-screen.png" />
    <link rel="manifest" href="/manifest.json" />
    <meta name="theme-color" content="#8b2500" />
    <meta name="apple-mobile-web-app-capable" content="yes" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @inertiaHead
</head>
<body class="bg-[#2b1d14] text-[#3b2a1a]" style="background-image: repeating-linear-gradient(90deg, transparent, transparent 40px, rgba(0,0,0,0.1) 40px, rgba(0,0,0,0.1) 80px); font-family: 'Lalezar', cursive; overflow-x: hidden;">
    @inertia
</body>
</html>
