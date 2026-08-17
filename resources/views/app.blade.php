<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title inertia>{{ config('app.name', 'Laravel') }}</title>

        <meta name="theme-color" content="#14324F">

        {{-- L'onglet porte la marque. En SVG plutot qu'en ICO : un seul
             fichier d'un demi-kilo-octet reste net a toutes les tailles,
             de l'onglet au raccourci d'ecran d'accueil. --}}
        <link rel="icon" type="image/svg+xml" href="/favicon.svg">

        {{-- Sans ces balises, les trois versions d'une page se font
             concurrence dans l'index : le moteur en retient une et ignore
             les autres. hreflang declare qu'elles sont equivalentes et
             designe celle qui convient a chaque langue. --}}
        @php
            $chemin = trim(request()->path(), '/');
            $segments = $chemin === '' ? [] : explode('/', $chemin);
            $reste = (isset($segments[0]) && in_array($segments[0], ['fr', 'nl', 'en'], true))
                ? implode('/', array_slice($segments, 1))
                : $chemin;
        @endphp
        @foreach (['fr', 'nl', 'en'] as $code)
            <link rel="alternate" hreflang="{{ $code }}-BE" href="{{ url($code.($reste === '' ? '' : '/'.$reste)) }}">
        @endforeach
        <link rel="alternate" hreflang="x-default" href="{{ url('fr'.($reste === '' ? '' : '/'.$reste)) }}">
        <link rel="canonical" href="{{ url(app()->getLocale().($reste === '' ? '' : '/'.$reste)) }}">

        {{-- Les polices sont servies par l'application, aucune dependance externe. --}}
        <link rel="preload" href="/fonts/inter-latin-400-normal.woff2" as="font" type="font/woff2" crossorigin>
        <link rel="preload" href="/fonts/inter-latin-600-normal.woff2" as="font" type="font/woff2" crossorigin>

        {{-- Ziggy pose ici le seul script en ligne de l'application. Le
             nonce vient du middleware des en-tetes de securite : c'est ce
             qui permet d'interdire tous les autres. --}}
        <!-- Scripts -->
        @routes(nonce: Illuminate\Support\Facades\Vite::cspNonce())
        @viteReactRefresh
        @vite(['resources/js/app.jsx', "resources/js/Pages/{$page['component']}.jsx"])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
