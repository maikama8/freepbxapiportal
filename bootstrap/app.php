<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Register custom middleware aliases
        $middleware->alias([
            'role' => \App\Http\Middleware\RoleMiddleware::class,
            'permission' => \App\Http\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \App\Http\Middleware\RoleOrPermissionMiddleware::class,
            'account.status' => \App\Http\Middleware\AccountStatusMiddleware::class,
            'api.rate_limit' => \App\Http\Middleware\ApiRateLimitMiddleware::class,
            'api.throttle' => \App\Http\Middleware\ApiThrottleMiddleware::class,
            'security' => \App\Http\Middleware\SecurityMiddleware::class,
            'security.monitoring' => \App\Http\Middleware\SecurityMonitoringMiddleware::class,
            'session.security' => \App\Http\Middleware\SessionSecurityMiddleware::class,
            'ip.blocking' => \App\Http\Middleware\IpBlockingMiddleware::class,
        ]);

        // Add security and account status middleware to web group
        $middleware->web(append: [
            \App\Http\Middleware\IpBlockingMiddleware::class,
            \App\Http\Middleware\SecurityMiddleware::class,
            \App\Http\Middleware\SessionSecurityMiddleware::class,
            \App\Http\Middleware\SecurityMonitoringMiddleware::class,
            \App\Http\Middleware\AccountStatusMiddleware::class,
        ]);

        // Add security middleware to API group
        $middleware->api(append: [
            \App\Http\Middleware\IpBlockingMiddleware::class,
            \App\Http\Middleware\SecurityMiddleware::class,
            \App\Http\Middleware\SecurityMonitoringMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
