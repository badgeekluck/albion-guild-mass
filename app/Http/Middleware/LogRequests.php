<?php

namespace App\Http\Middleware;

use App\Models\RequestLog;
use App\Models\SharedLink;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Records page views and actions of logged-in users for the admin activity screen.
 * Written after the response is sent (terminate), so it never slows a page down.
 * Live-refresh fragments, icons and broadcasting auth are skipped (pure noise).
 */
class LogRequests
{
    private const SKIPPED_ROUTES = ['party.board', 'item-icon', 'admin.server.stats'];

    public function handle(Request $request, Closure $next): Response
    {
        $request->attributes->set('log_started', microtime(true));

        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        $user = $request->user();
        $route = $request->route();
        if (! $user || ! $route || in_array($route->getName(), self::SKIPPED_ROUTES, true) || str_starts_with($request->path(), 'broadcasting')) {
            return;
        }

        try {
            $link = $route->parameter('link');
            RequestLog::create([
                'user_id' => $user->id,
                'method' => $request->method(),
                'route' => $route->getName() ? mb_substr($route->getName(), 0, 80) : null,
                'path' => mb_substr('/'.ltrim($request->path(), '/'), 0, 255),
                'shared_link_id' => $link instanceof SharedLink ? $link->id : null,
                'status' => $response->getStatusCode(),
                'duration_ms' => (int) round(1000 * (microtime(true) - (float) ($request->attributes->get('log_started') ?? microtime(true)))),
                'is_mobile' => (bool) preg_match('/Mobile|Android|iPhone|iPad/i', (string) $request->userAgent()),
            ]);

            // Keep 30 days: an occasional cleanup instead of a scheduler.
            if (random_int(1, 500) === 1) {
                RequestLog::where('created_at', '<', now()->subDays(RequestLog::KEEP_DAYS))->delete();
            }
        } catch (Throwable $e) {
            report($e); // logging must never break the app
        }
    }
}
