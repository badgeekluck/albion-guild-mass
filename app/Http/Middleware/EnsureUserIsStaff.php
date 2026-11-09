<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsStaff
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->isStaff()) {
            if ($request->expectsJson()) {
                abort(403, 'Bu alana giriş yetkiniz yok.');
            }

            return redirect()->route('home')->with('error', 'Bu alana giriş yetkiniz yok.');
        }

        return $next($request);
    }
}
