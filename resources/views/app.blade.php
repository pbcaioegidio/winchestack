<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        {{-- Aplica o tema (claro/escuro/sistema) ANTES de renderizar (evita flash) --}}
        <script>
            (function () {
                try {
                    var t = localStorage.getItem('winchestack:theme') || 'system';
                    var dark = t === 'dark' || (t === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);
                    document.documentElement.classList.toggle('dark', dark);
                } catch (e) {}
            })();
        </script>

        {{-- Idioma da página (impede o Chrome de oferecer traduzir pt → pt) --}}
        <meta name="google" content="notranslate">
        <meta http-equiv="Content-Language" content="pt-BR">

        <title inertia>Winchestack</title>

        <meta name="theme-color" content="#020617">
        <meta name="mobile-web-app-capable" content="yes">

        {{-- Ícones / favicon --}}
        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32.png">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">
        <link rel="manifest" href="/manifest.json">

        {{-- Fontes --}}
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @inertiaHead
    </head>
    <body class="antialiased notranslate bg-white dark:bg-slate-950" translate="no">
        @inertia
    </body>
</html>
