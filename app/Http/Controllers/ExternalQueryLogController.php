<?php

namespace App\Http\Controllers;

use App\Enums\ExternalQueryJobType;
use App\Enums\ExternalQueryStatus;
use App\Enums\Role;
use App\Models\ExternalQueryLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ExternalQueryLogController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()?->hasRole(Role::Admin), 403);

        $jobType = $request->string('job_type')->toString();
        $status = $request->string('status')->toString();
        $sessionId = $request->integer('session_id') ?: null;

        $jobType = in_array($jobType, ExternalQueryJobType::values(), true) ? $jobType : '';
        $status = in_array($status, ExternalQueryStatus::values(), true) ? $status : '';

        $perPage = 50;
        $page = max(1, (int) $request->get('page', 1));

        $query = ExternalQueryLog::query()
            ->with([
                'user:id,name,email',
                'stockReconSession:id,name',
            ])
            ->latest('id');

        if ($jobType !== '') {
            $query->where('job_type', $jobType);
        }

        if ($status !== '') {
            $query->where('status', $status);
        }

        if ($sessionId !== null) {
            $query->where('stock_recon_session_id', $sessionId);
        }

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        $logs = $paginator->getCollection()->map(fn (ExternalQueryLog $log): array => [
            'id' => $log->id,
            'job_type' => $log->job_type->value,
            'status' => $log->status->value,
            'context' => $log->context,
            'zwing_query_ms' => $log->zwing_query_ms,
            'erp_query_ms' => $log->erp_query_ms,
            'failure_reason' => $log->failure_reason,
            'started_at' => $log->started_at?->toIso8601String(),
            'finished_at' => $log->finished_at?->toIso8601String(),
            'created_at' => $log->created_at?->toIso8601String(),
            'user' => $log->user === null ? null : [
                'id' => $log->user->id,
                'name' => $log->user->name,
                'email' => $log->user->email,
            ],
            'session' => $log->stockReconSession === null ? null : [
                'id' => $log->stockReconSession->id,
                'name' => $log->stockReconSession->name,
            ],
        ])->values()->all();

        return Inertia::render('external-query-logs/index', [
            'logs' => $logs,
            'pagination' => [
                'total' => $paginator->total(),
                'per_page' => $paginator->perPage(),
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
            ],
            'filters' => [
                'job_type' => $jobType,
                'status' => $status,
                'session_id' => $sessionId,
            ],
            'jobTypeOptions' => ExternalQueryJobType::values(),
            'statusOptions' => ExternalQueryStatus::values(),
        ]);
    }

    public function show(Request $request, ExternalQueryLog $externalQueryLog): JsonResponse
    {
        abort_if($request->user() === null, 403);
        abort_if($externalQueryLog->user_id !== $request->user()->id, 403);

        return response()->json($externalQueryLog->toPollPayload());
    }
}
