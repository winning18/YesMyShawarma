<?php

use App\Http\Middleware\ResolveCurrentBranch;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'branch' => ResolveCurrentBranch::class,
        ]);

        // Laravel's default "guest" redirect target is a hardcoded
        // route('dashboard') regardless of which guard matched — that
        // would send an already-logged-in customer hitting /login into the
        // staff-only dashboard. Send each guard's already-authenticated
        // visitor somewhere that actually makes sense for them.
        $middleware->redirectUsersTo(
            fn (Request $request) => Auth::guard('customer')->check() ? route('home') : route('dashboard')
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // The default skeleton's callback only checked the URL prefix, which
        // replaces (not adds to) Laravel's normal expectsJson() detection —
        // that silently broke JSON error responses on the dashboard's own
        // JSON endpoints, which live under routes/web.php, not /api/*.
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
