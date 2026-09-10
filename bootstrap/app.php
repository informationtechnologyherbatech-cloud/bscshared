<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->redirectGuestsTo(function (Request $request) {
            if ($request->expectsJson()) {
                return null;
            }
            // Store friendly expiry message 
            if (! $request->is('login') && ! $request->is('logout')) {
                session()->flash('expired_msg', 'Sesi Anda telah kedaluwarsa, silakan login kembali.');
            }
            return route('login');
        });
        // Header keamanan (nosniff, anti-clickjacking, CSP) untuk seluruh halaman web.
        $middleware->web(append: [
            \App\Http\Middleware\SecurityHeaders::class,
        ]);

        $middleware->alias([
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
            'active' => \App\Http\Middleware\EnsureUserIsActive::class,
            'password.change' => \App\Http\Middleware\RequirePasswordChange::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Sesi telah kedaluwarsa. Silakan login kembali.'], 401);
            }
            if (! $request->is('login')) {
                session()->flash('expired_msg', 'Sesi Anda telah kedaluwarsa, silakan login kembali.');
            }
            return redirect()->guest(route('login'));
        });
    })->create();
