<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Atrás do Caddy (TLS termina no proxy; o app recebe HTTP em 127.0.0.1).
        // Confiar no proxy faz o Laravel reconhecer o https (via X-Forwarded-Proto)
        // e gerar URLs/cookies corretos. O Caddy roda no MESMO host e conecta
        // pelo loopback, então confiamos só em 127.0.0.1/::1 (em vez de '*'):
        // fecha spoof de X-Forwarded-* sem perder a detecção de https.
        $middleware->trustProxies(at: ['127.0.0.1', '::1']);

        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
