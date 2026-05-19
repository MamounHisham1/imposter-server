<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', config('app.name', 'Traitor'))</title>
    <meta name="description" content="@yield('meta_description', 'Traitor (al-Khaina) is a free online social deduction party game. One player is the secret imposter — give one-word clues, vote to find the traitor. Play with friends in Arabic or English!')" />

    <link rel="canonical" href="@yield('canonical_url', config('app.url') . '/' . ltrim(request()->path(), '/'))" />
    @php $currentPath = ltrim(request()->path(), '/'); @endphp
    <link rel="alternate" hreflang="ar" href="{{ config('app.url') . ($currentPath ? '/' . $currentPath : '') }}?lang=ar" />
    <link rel="alternate" hreflang="en" href="{{ config('app.url') . ($currentPath ? '/' . $currentPath : '') }}?lang=en" />
    <link rel="alternate" hreflang="x-default" href="{{ config('app.url') }}/" />

    <meta property="og:type" content="@yield('og_type', 'website')" />
    <meta property="og:site_name" content="Traitor - الخائن" />
    <meta property="og:title" content="@yield('og_title', 'Traitor - Free Social Deduction Party Game')" />
    <meta property="og:description" content="@yield('og_description', 'A free online multiplayer social deduction game. Find the imposter among your friends! Play now in Arabic or English.')" />
    <meta property="og:url" content="@yield('og_url', config('app.url') . '/' . ltrim(request()->path(), '/'))" />
    <meta property="og:image" content="@yield('og_image', config('app.url') . '/logo-512.png')" />
    <meta property="og:image:width" content="512" />
    <meta property="og:image:height" content="512" />
    <meta property="og:locale" content="{{ app()->getLocale() === 'ar' ? 'ar_SA' : 'en_US' }}" />
    <meta property="og:locale:alternate" content="{{ app()->getLocale() === 'ar' ? 'en_US' : 'ar_SA' }}" />

    <meta name="twitter:card" content="summary" />
    <meta name="twitter:title" content="@yield('twitter_title', 'Traitor - Free Social Deduction Party Game')" />
    <meta name="twitter:description" content="@yield('twitter_description', 'Find the imposter among your friends! Free online multiplayer social deduction game.')" />
    <meta name="twitter:image" content="@yield('twitter_image', config('app.url') . '/logo-512.png')" />

    @yield('meta_robots')

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

    @if(!request()->is('room/*') && !request()->is('game/*'))
    <script type="application/ld+json">
    {
        "@@context": "https://schema.org",
        "@@graph": [
            {
                "@@type": "WebSite",
                "@@id": "{{ config('app.url') }}/#website",
                "url": "{{ config('app.url') }}/",
                "name": "Traitor (الخائن)",
                "description": "Free online social deduction party game — find the imposter among your friends",
                "inLanguage": ["ar", "en"],
                "potentialAction": {
                    "@@type": "PlayAction",
                    "target": "{{ config('app.url') }}/",
                    "name": "Play Traitor"
                }
            },
            {
                "@@type": "WebApplication",
                "@@id": "{{ config('app.url') }}/#webapp",
                "url": "{{ config('app.url') }}/",
                "name": "Traitor",
                "alternateName": "الخائن",
                "applicationCategory": "GameApplication",
                "operatingSystem": "Any",
                "offers": {
                    "@@type": "Offer",
                    "price": "0",
                    "priceCurrency": "USD"
                },
                "browserRequirements": "Requires JavaScript. Requires HTML5.",
                "softwareVersion": "1.0",
                "inLanguage": ["ar", "en"],
                "description": "A free real-time multiplayer social deduction word game. One player is secretly the imposter and receives a vague hint. Players give one-word clues, then vote to identify the imposter. Wild West themed.",
                "genre": "Social Deduction",
                "applicationSubCategory": "Party Game",
                "screenshot": "{{ config('app.url') }}/splash-screen.png",
                "installUrl": "{{ config('app.url') }}/"
            },
            {
                "@@type": "VideoGame",
                "@@id": "{{ config('app.url') }}/#videogame",
                "name": "Traitor (الخائن)",
                "description": "A real-time multiplayer social deduction word game with a Wild West theme. One player is secretly the imposter — give one-word clues, then vote to find the traitor.",
                "genre": "Social Deduction, Party Game, Word Game",
                "gamePlatform": "Web Browser",
                "applicationCategory": "Game",
                "numberOfPlayers": {
                    "@@type": "QuantitativeValue",
                    "minValue": 3,
                    "maxValue": 10
                },
                "inLanguage": ["ar", "en"],
                "url": "{{ config('app.url') }}/",
                "image": "{{ config('app.url') }}/logo-512.png"
            }
        ]
    }
    </script>
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
