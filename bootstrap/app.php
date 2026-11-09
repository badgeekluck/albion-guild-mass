<?php

use App\Http\Middleware\EnsureGuildMembership;
use App\Http\Middleware\EnsureUserIsStaff;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    // Registers POST /broadcasting/auth (Echo's default endpoint) for presence channels.
    ->withBroadcasting(
        __DIR__.'/../routes/channels.php',
        ['middleware' => ['web', 'auth']],
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'staff' => EnsureUserIsStaff::class,
        ]);

        $middleware->web(append: [
            EnsureGuildMembership::class,
        ]);

        // Guests land on the welcome page (Discord / dev login); the intended URL is kept.
        $middleware->redirectGuestsTo(fn () => route('home'));
        $middleware->redirectUsersTo(fn () => route('home'));

        // Behind nginx / a load balancer.
        $middleware->trustProxies(at: '*');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
