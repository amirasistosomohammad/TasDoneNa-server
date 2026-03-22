<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AccomplishmentReport;
use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AccomplishmentReportController extends Controller
{
    /**
     * List accomplishment reports.
     * Personnel: own reports only
     * Admin: all reports
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = AccomplishmentReport::with(['user:id,name,email', 'notedBy:id,name']);

        if ($user->role === 'officer') {
            // Personnel: only own reports
            $query->where('user_id', $user->id);
        }
        // Admin: all reports (no filter)

        $status = $request->input('status');
        if ($status && in_array($status, ['draft', 'submitted', 'noted'], true)) {
            $query->where('status', $status);
        }

        $year = $request->input('year');
        if ($year) {
            $query->where('year', $year);
        }

        $month = $request->input('month');
        if ($month) {
            $query->where('month', $month);
        }

        $reports = $query->orderBy('year', 'desc')
            ->orderBy('month', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json(['reports' => $reports]);
    }

    /**
     * Get single accomplishment report.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $report = AccomplishmentReport::with(['user:id,name,email,position,division,school_name', 'notedBy:id,name'])
            ->find($id);

        if (! $report) {
            return response()->json(['message' => 'Accomplishment report not found.'], 404);
        }

        // Personnel can only view own reports
        if ($user->role === 'officer' && $report->user_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        return response()->json(['report' => $report]);
    }

    /**
     * Generate and create/submit accomplishment report (personnel only).
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->role !== 'officer') {
            return response()->json(['message' => 'Only personnel can create accomplishment reports.'], 403);
        }

        $validated = $request->validate([
            'year' => ['required', 'integer', 'min:2020', 'max:2100'],
            'month' => ['required', 'integer', 'min:1', 'max:12'],
            'remarks' => ['nullable', 'string', 'max:5000'],
            'status' => ['nullable', 'string', 'in:draft,submitted'],
        ]);

        // Prevent generating reports for future months
        $currentYear = (int) date('Y');
        $currentMonth = (int) date('m');
        if ($validated['year'] > $currentYear || ($validated['year'] === $currentYear && $validated['month'] > $currentMonth)) {
            return response()->json([
                'message' => 'Cannot generate reports for future months.',
            ], 422);
        }

        // Check if report already exists for this month
        $existing = AccomplishmentReport::where('user_id', $user->id)
            ->where('year', $validated['year'])
            ->where('month', $validated['month'])
            ->first();

        if ($existing) {
            return response()->json([
                'message' => 'Accomplishment report for this month already exists.',
                'report' => $existing,
            ], 422);
        }

        // Generate tasks summary from completed tasks for this month
        $startDate = sprintf('%04d-%02d-01', $validated['year'], $validated['month']);
        $endDate = date('Y-m-t', strtotime($startDate)); // Last day of month

        $tasks = Task::where('created_by', $user->id)
            ->where('status', 'completed')
            ->whereBetween(DB::raw('DATE(updated_at)'), [$startDate, $endDate])
            ->orderBy('kra')
            ->orderBy('created_at')
            ->get();

        // Group tasks by KRA (handle empty tasks gracefully)
        $tasksByKra = [];
        foreach ($tasks as $task) {
            $kra = $task->kra ?: 'Uncategorized';
            if (! isset($tasksByKra[$kra])) {
                $tasksByKra[$kra] = [
                    'kra' => $kra,
                    'kra_weight' => $task->kra_weight ?? 0,
                    'tasks' => [],
                ];
            }
            $tasksByKra[$kra]['tasks'][] = [
                'id' => $task->id,
                'title' => $task->title,
                'description' => $task->description,
                'mfo' => $task->mfo,
                'objective' => $task->objective,
                'movs' => $task->movs ?? [],
                'due_date' => $task->due_date?->format('Y-m-d'),
                'completed_at' => $task->updated_at?->format('Y-m-d H:i:s'),
            ];
        }

        $tasksSummary = array_values($tasksByKra);

        // Warn if no tasks found (but allow draft reports)
        if (empty($tasksSummary) && ($validated['status'] ?? 'draft') === 'submitted') {
            return response()->json([
                'message' => 'No completed tasks found for this month. Please complete some tasks first or create a draft report.',
            ], 422);
        }

        $report = AccomplishmentReport::create([
            'user_id' => $user->id,
            'year' => $validated['year'],
            'month' => $validated['month'],
            'status' => $validated['status'] ?? 'draft',
            'tasks_summary' => $tasksSummary,
            'remarks' => $validated['remarks'] ?? null,
        ]);

        $report->load(['user:id,name,email', 'notedBy:id,name']);

        return response()->json([
            'message' => $report->status === 'submitted' 
                ? 'Accomplishment report submitted successfully.' 
                : 'Accomplishment report created successfully.',
            'report' => $report,
        ], 201);
    }

    /**
     * Update accomplishment report (personnel only, draft only).
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $report = AccomplishmentReport::find($id);

        if (! $report) {
            return response()->json(['message' => 'Accomplishment report not found.'], 404);
        }

        if ($user->role !== 'officer' || $report->user_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        if ($report->status !== 'draft') {
            return response()->json(['message' => 'Only draft reports can be updated.'], 422);
        }

        $validated = $request->validate([
            'remarks' => ['nullable', 'string', 'max:5000'],
            'status' => ['nullable', 'string', 'in:draft,submitted'],
        ]);

        $report->update($validated);
        $report->load(['user:id,name,email', 'notedBy:id,name']);

        return response()->json([
            'message' => 'Accomplishment report updated successfully.',
            'report' => $report,
        ]);
    }

    /**
     * Admin: Note (approve) accomplishment report.
     */
    public function note(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        if ($user->role !== 'admin') {
            return response()->json(['message' => 'Only administrators can note reports.'], 403);
        }

        $report = AccomplishmentReport::find($id);

        if (! $report) {
            return response()->json(['message' => 'Accomplishment report not found.'], 404);
        }

        if ($report->status !== 'submitted') {
            return response()->json(['message' => 'Only submitted reports can be noted.'], 422);
        }

        $validated = $request->validate([
            'admin_remarks' => ['nullable', 'string', 'max:5000'],
        ]);

        $report->update([
            'status' => 'noted',
            'noted_by' => $user->id,
            'noted_at' => now(),
            'admin_remarks' => $validated['admin_remarks'] ?? null,
        ]);

        $report->load(['user:id,name,email', 'notedBy:id,name']);

        return response()->json([
            'message' => 'Accomplishment report noted successfully.',
            'report' => $report,
        ]);
    }
}
