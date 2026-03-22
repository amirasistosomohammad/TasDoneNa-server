<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AccomplishmentReport;
use App\Models\Task;
use App\Models\User;
use App\Services\AccomplishmentReportExcelExportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
     * Download filled DepEd-style Excel for a report (officer: own only; admin: any).
     */
    public function export(Request $request, int $id, AccomplishmentReportExcelExportService $excel): JsonResponse|StreamedResponse
    {
        $user = $request->user();
        $report = AccomplishmentReport::with(['user:id,name,email,position,division,school_name'])->find($id);

        if (! $report) {
            return response()->json(['message' => 'Accomplishment report not found.'], 404);
        }

        if ($user->role === 'officer' && $report->user_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        if ($user->role !== 'admin' && $user->role !== 'officer') {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        return $this->streamFilledAccomplishmentExcel($report, $excel, null, null);
    }

    /**
     * Officer: build Excel from completed tasks for a year/month and download immediately.
     * Does not create or update accomplishment_reports rows.
     */
    public function exportFromPeriod(Request $request, AccomplishmentReportExcelExportService $excel): JsonResponse|StreamedResponse
    {
        $auth = $request->user();
        if ($auth->role !== 'officer') {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $validated = $request->validate([
            'year' => ['required', 'integer', 'min:2020', 'max:2100'],
            'month' => ['required', 'integer', 'min:1', 'max:12'],
            'school_head_name' => ['required', 'string', 'max:200'],
            'school_head_designation' => ['required', 'string', 'max:300'],
        ]);

        $currentYear = (int) date('Y');
        $currentMonth = (int) date('m');
        if ($validated['year'] > $currentYear || ($validated['year'] === $currentYear && $validated['month'] > $currentMonth)) {
            return response()->json([
                'message' => 'Cannot generate reports for future months.',
            ], 422);
        }

        $officer = User::query()
            ->select(['id', 'name', 'email', 'position', 'division', 'school_name'])
            ->find($auth->id);

        if (! $officer) {
            return response()->json(['message' => 'User not found.'], 404);
        }

        $tasksSummary = $this->tasksSummaryForOfficerMonth($officer->id, $validated['year'], $validated['month']);

        $report = new AccomplishmentReport([
            'year' => $validated['year'],
            'month' => $validated['month'],
            'tasks_summary' => $tasksSummary,
        ]);
        $report->setRelation('user', $officer);

        return $this->streamFilledAccomplishmentExcel(
            $report,
            $excel,
            $validated['school_head_name'],
            $validated['school_head_designation']
        );
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

        $tasksSummary = $this->tasksSummaryForOfficerMonth($user->id, $validated['year'], $validated['month']);

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

    /**
     * @return list<array{kra: string, kra_weight: mixed, tasks: list<array<string, mixed>>}>
     */
    private function tasksSummaryForOfficerMonth(int $officerUserId, int $year, int $month): array
    {
        $startDate = sprintf('%04d-%02d-01', $year, $month);
        $endDate = date('Y-m-t', strtotime($startDate));

        $tasks = Task::where('created_by', $officerUserId)
            ->where('status', 'completed')
            ->whereBetween(DB::raw('DATE(updated_at)'), [$startDate, $endDate])
            ->orderBy('kra')
            ->orderBy('created_at')
            ->get();

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

        return array_values($tasksByKra);
    }

    private function streamFilledAccomplishmentExcel(
        AccomplishmentReport $report,
        AccomplishmentReportExcelExportService $excel,
        ?string $certifiedByName,
        ?string $certifiedByDesignation
    ): JsonResponse|StreamedResponse {
        if (! $excel->templateExists()) {
            return response()->json([
                'message' => 'Excel template is not installed on the server. Place Accomplishment-Report_AO_Final.xlsx under storage/app/templates/ (or set ACCOMPLISHMENT_REPORT_TEMPLATE / ACCOMPLISHMENT_REPORT_TEMPLATE_RELATIVE in .env). See storage/app/templates/README.txt.',
            ], 503);
        }

        try {
            $spreadsheet = $excel->fill($report, $certifiedByName, $certifiedByDesignation);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'message' => 'Failed to build the Excel file.',
                'detail' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }

        $slug = Str::slug($report->user?->name ?? 'report', '_') ?: 'report';
        $filename = sprintf(
            'Accomplishment_Report_%04d-%02d_%s.xlsx',
            $report->year,
            $report->month,
            substr($slug, 0, 80)
        );

        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
