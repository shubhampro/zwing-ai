<?php

namespace App\Http\Controllers;

use App\Http\Requests\RefreshServerHealthRequest;
use App\Models\DbHealthCheck;
use App\Services\DbHealth\DbHealthChecker;
use App\Support\Permissions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ServerHealthController extends Controller
{
    public function index(Request $request, DbHealthChecker $checker): Response
    {
        abort_unless($request->user()?->can(Permissions::ServerHealthView), 403);

        $snapshot = $checker->snapshot();
        $ttl = (int) config('server_health.cache_ttl_seconds');
        $cacheFresh = $snapshot !== null;
        $locked = (bool) ($snapshot['locked'] ?? false)
            || cache()->has(config('server_health.lock_key').':held');

        $history = DbHealthCheck::query()
            ->orderByDesc('ran_at')
            ->limit((int) config('server_health.history_limit'))
            ->get(['id', 'ran_at', 'overall_status', 'results'])
            ->map(fn (DbHealthCheck $check): array => [
                'id' => $check->id,
                'ran_at' => $check->ran_at?->toIso8601String(),
                'overall_status' => $check->overall_status,
                'results' => $check->results,
            ])
            ->values()
            ->all();

        return Inertia::render('server-health/index', [
            'snapshot' => $snapshot,
            'history' => $history,
            'cache_ttl_seconds' => $ttl,
            'cache_fresh' => $cacheFresh,
            'locked' => $locked,
            'can_refresh' => $request->user()?->can(Permissions::ServerHealthManage) ?? false,
        ]);
    }

    public function refresh(RefreshServerHealthRequest $request, DbHealthChecker $checker): RedirectResponse
    {
        if ($checker->snapshot() !== null) {
            return back()->with('info', 'Cached snapshot still fresh. Wait for TTL before refreshing.');
        }

        $result = $checker->run();

        if ($result === null) {
            return back()->with('error', 'A health check is already running. Try again shortly.');
        }

        return back()->with('success', 'DB health check completed: '.$result['overall_status']);
    }
}
