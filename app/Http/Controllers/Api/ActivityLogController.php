<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ActivityLogController extends Controller
{
    /**
     * GET /api/admin/activity-logs — list activity logs with filters (admin only).
     */
    /**
     * GET /api/admin/activity-logs
     * - all=1: return all logs (up to 2000) for client-side filtering, no pagination
     * - otherwise: paginated with server-side filters (legacy)
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'all' => ['nullable', 'boolean'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'action' => ['nullable', 'string', 'max:100'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'search' => ['nullable', 'string', 'max:255'],
        ]);

        $fetchAll = $request->boolean('all');

        $query = ActivityLog::with('actor:id,name,email')
            ->orderByDesc('created_at');

        if ($fetchAll) {
            $logs = $query->limit(2000)->get();
            $actions = ActivityLog::query()
                ->select('action')
                ->distinct()
                ->orderBy('action')
                ->pluck('action')
                ->filter()
                ->values()
                ->all();

            return response()->json([
                'logs' => $logs,
                'actions' => $actions,
            ]);
        }

        $perPage = min((int) ($request->input('per_page', 15)), 100);
        if ($action = $request->input('action')) {
            $query->where('action', $action);
        }
        if ($dateFrom = $request->input('date_from')) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }
        if ($dateTo = $request->input('date_to')) {
            $query->whereDate('created_at', '<=', $dateTo);
        }
        if ($search = trim((string) $request->input('search'))) {
            $query->where(function ($q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                    ->orWhere('action', 'like', "%{$search}%")
                    ->orWhereHas('actor', function ($aq) use ($search) {
                        $aq->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        $paginator = $query->paginate($perPage);
        $actions = ActivityLog::query()
            ->select('action')
            ->distinct()
            ->orderBy('action')
            ->pluck('action')
            ->filter()
            ->values()
            ->all();

        return response()->json([
            'logs' => $paginator->items(),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
            'actions' => $actions,
        ]);
    }
}
