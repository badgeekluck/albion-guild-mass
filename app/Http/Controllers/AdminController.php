<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\RequestLog;
use App\Models\User;
use App\Services\ServerStats;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/** Admin-only: who did what (activity) and how the server is doing. */
class AdminController extends Controller
{
    public const TABS = ['requests', 'changes'];

    public function activity(Request $request): View
    {
        $this->authorizeAdmin($request);

        $filters = $request->validate([
            'tab' => ['nullable', 'in:'.implode(',', self::TABS)],
            'user' => ['nullable', 'integer'],
            'day' => ['nullable', 'date_format:Y-m-d'],
            'only' => ['nullable', 'in:views,actions,errors'],
        ]);
        $tab = $filters['tab'] ?? 'requests';
        $day = isset($filters['day']) ? Carbon::parse($filters['day']) : null;

        $requests = RequestLog::with(['user', 'link'])
            ->when($filters['user'] ?? null, fn ($q, $id) => $q->where('user_id', $id))
            ->when($day, fn ($q) => $q->whereBetween('created_at', [$day->copy()->startOfDay(), $day->copy()->endOfDay()]))
            ->when(($filters['only'] ?? null) === 'views', fn ($q) => $q->where('method', 'GET'))
            ->when(($filters['only'] ?? null) === 'actions', fn ($q) => $q->where('method', '!=', 'GET'))
            ->when(($filters['only'] ?? null) === 'errors', fn ($q) => $q->where('status', '>=', 400))
            ->latest('id');

        $changes = AuditLog::with(['user', 'link'])
            ->when($filters['user'] ?? null, fn ($q, $id) => $q->where('user_id', $id))
            ->when($day, fn ($q) => $q->whereBetween('created_at', [$day->copy()->startOfDay(), $day->copy()->endOfDay()]))
            ->latest('id');

        return view('admin.activity', [
            'tab' => $tab,
            'filters' => $filters,
            'entries' => ($tab === 'changes' ? $changes : $requests)->paginate(100)->withQueryString(),
            'users' => User::whereIn('id', RequestLog::select('user_id')->distinct())->orWhereIn('id', AuditLog::select('user_id')->distinct())->orderBy('name')->get(['id', 'name']),
            'online' => RequestLog::with('user')->where('created_at', '>=', now()->subMinutes(5))
                ->select('user_id', DB::raw('MAX(created_at) as last_seen'), DB::raw('MAX(id) as last_id'))->groupBy('user_id')->orderByDesc('last_id')->get(),
            'today' => RequestLog::with('user')->where('created_at', '>=', now()->startOfDay())
                ->select('user_id', DB::raw("SUM(CASE WHEN method = 'GET' THEN 1 ELSE 0 END) as views"), DB::raw("SUM(CASE WHEN method <> 'GET' THEN 1 ELSE 0 END) as actions"), DB::raw('MAX(created_at) as last_seen'))
                ->groupBy('user_id')->orderByDesc('actions')->orderByDesc('views')->limit(15)->get(),
        ]);
    }

    public function server(Request $request, ServerStats $stats): View
    {
        $this->authorizeAdmin($request);

        return view('admin.server', ['app' => $stats->app(), 'snapshot' => $stats->snapshot()]);
    }

    /** Polled by the server screen every few seconds. */
    public function serverStats(Request $request, ServerStats $stats): JsonResponse
    {
        $this->authorizeAdmin($request);

        return response()->json($stats->snapshot());
    }

    private function authorizeAdmin(Request $request): void
    {
        abort_unless($request->user()?->isAdmin(), 403, 'Bu ekran sadece admin içindir.');
    }
}
