<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AccomplishmentReport;
use App\Services\AccomplishmentReportDataService;
use App\Services\AccomplishmentReportExcelExportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AccomplishmentReportController extends Controller
{
    /**
     * List accomplishment reports (officers: own reports only).
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user->role !== 'officer') {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $query = AccomplishmentReport::with(['user:id,name,email', 'notedBy:id,name'])
            ->where('user_id', $user->id);

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

        if ($user->role !== 'officer' || $report->user_id !== $user->id) {
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
     * Officer: queue Excel build after the HTTP response (avoids reverse-proxy 504 on slow hosts).
     * Client polls exportFromPeriodStatus then downloads exportFromPeriodDownload.
     */
    public function exportFromPeriod(Request $request, AccomplishmentReportExcelExportService $excel): JsonResponse
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

        if (! $excel->templateExists()) {
            return response()->json([
                'message' => 'Excel template is not installed on the server. Place Accomplishment-Report_AO_Final.xlsx under storage/app/templates/ (or set ACCOMPLISHMENT_REPORT_TEMPLATE / ACCOMPLISHMENT_REPORT_TEMPLATE_RELATIVE in .env). See storage/app/templates/README.txt.',
            ], 503);
        }

        $userId = $auth->id;
        $token = Str::random(48);
        $ttlSeconds = (int) config('accomplishment_report_export.export_cache_ttl_seconds', 900);
        $payload = $validated;

        $exportDir = storage_path('app/temp/accomplishment_exports');
        $statusDir = $exportDir.'/status';
        if (! is_dir($exportDir)) {
            mkdir($exportDir, 0755, true);
        }
        if (! is_dir($statusDir)) {
            mkdir($statusDir, 0755, true);
        }

        $statusPath = $statusDir.'/'.$userId.'_'.$token.'.json';
        $exportPath = $exportDir.'/'.$userId.'_'.$token.'.xlsx';
        $expiresAt = now()->addSeconds($ttlSeconds)->timestamp;

        $statusWrite = function (array $status) use ($statusPath, $expiresAt): void {
            $tmp = $statusPath.'.tmp';
            $status['expires_at'] = $status['expires_at'] ?? $expiresAt;
            file_put_contents($tmp, json_encode($status, JSON_UNESCAPED_UNICODE));
            @rename($tmp, $statusPath);
        };

        $statusWrite([
            'state' => 'pending',
            'expires_at' => $expiresAt,
            'payload' => $payload,
        ]);

        $responseData = [
            'export_token' => $token,
            'status' => 'queued',
            'poll_interval_ms' => 500,
            'expires_in_seconds' => $ttlSeconds,
        ];
        // Never generate inside this request (DO App Platform gateways can time out).
        // Spawn a separate PHP process that writes the export + updates the status file.
        $spawned = false;
        if (function_exists('exec')) {
            $artisanPath = base_path('artisan');
            $cmd = sprintf(
                '%s %s accomplishment:generate-export %d %s > /dev/null 2>&1 &',
                escapeshellarg(PHP_BINARY),
                escapeshellarg($artisanPath),
                $userId,
                escapeshellarg($token)
            );
            $output = [];
            $returnVar = 1;
            @exec($cmd, $output, $returnVar);
            $spawned = $returnVar === 0;
        }

        if (! $spawned) {
            $statusWrite([
                'state' => 'failed',
                'message' => 'Background export could not start. Please try again.',
            ]);
        }

        return response()->json($responseData, 202);
    }

    /**
     * Poll generation status for exportFromPeriod (pending | ready | failed | expired).
     */
    public function exportFromPeriodStatus(Request $request, string $token): JsonResponse
    {
        $user = $request->user();
        if ($user->role !== 'officer') {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        if (! preg_match('/^[A-Za-z0-9]{32,80}$/', $token)) {
            return response()->json(['message' => 'Invalid export token.'], 422);
        }

        $exportDir = storage_path('app/temp/accomplishment_exports');
        $statusDir = $exportDir.'/status';
        $statusPath = $statusDir.'/'.$user->id.'_'.$token.'.json';
        if (! is_readable($statusPath)) {
            return response()->json([
                'status' => 'expired',
                'message' => 'This export has expired or is invalid. Generate the report again.',
            ], 410);
        }
        $data = json_decode((string) file_get_contents($statusPath), true);
        if (! is_array($data)) {
            return response()->json([
                'status' => 'expired',
                'message' => 'This export has expired or is invalid. Generate the report again.',
            ], 410);
        }

        $expiresAt = $data['expires_at'] ?? null;
        if (is_int($expiresAt) && time() > $expiresAt) {
            return response()->json([
                'status' => 'expired',
                'message' => 'This export has expired. Generate the report again.',
            ], 410);
        }

        $state = $data['state'] ?? 'pending';

        if ($state === 'ready') {
            return response()->json(['status' => 'ready']);
        }

        if ($state === 'failed') {
            return response()->json([
                'status' => 'failed',
                'message' => $data['message'] ?? 'Export failed.',
            ]);
        }

        return response()->json([
            'status' => 'pending',
        ]);
    }

    /**
     * Download a completed period export (one-time; file is removed after send).
     */
    public function exportFromPeriodDownload(Request $request, string $token): JsonResponse|BinaryFileResponse
    {
        $user = $request->user();
        if ($user->role !== 'officer') {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        if (! preg_match('/^[A-Za-z0-9]{32,80}$/', $token)) {
            return response()->json(['message' => 'Invalid export token.'], 422);
        }

        $exportDir = storage_path('app/temp/accomplishment_exports');
        $statusDir = $exportDir.'/status';
        $statusPath = $statusDir.'/'.$user->id.'_'.$token.'.json';
        if (! is_readable($statusPath)) {
            return response()->json([
                'message' => 'Report is not ready yet. Wait for generation to finish, then try again.',
            ], 409);
        }

        $data = json_decode((string) file_get_contents($statusPath), true);
        if (! is_array($data) || ($data['state'] ?? '') !== 'ready') {
            return response()->json([
                'message' => 'Report is not ready yet. Wait for generation to finish, then try again.',
            ], 409);
        }

        $path = $data['path'] ?? null;
        $filename = $data['filename'] ?? 'Accomplishment_Report.xlsx';

        if (! is_string($path) || ! is_readable($path)) {
            return response()->json([
                'message' => 'Export file is no longer available. Generate the report again.',
            ], 410);
        }

        $statusPathCopy = $statusPath;
        register_shutdown_function(function () use ($statusPathCopy) {
            @unlink($statusPathCopy);
        });

        return response()->download($path, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
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

        $tasksSummary = app(AccomplishmentReportDataService::class)
            ->tasksSummaryForOfficerMonth($user->id, $validated['year'], $validated['month']);

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

    private static function accomplishmentExportCacheKey(int $userId, string $token): string
    {
        return 'accomplishment_export:'.$userId.':'.$token;
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
            try {
                $writer = new Xlsx($spreadsheet);
                $writer->setPreCalculateFormulas(false);
                $writer->setUseDiskCaching(true, storage_path('framework/cache'));
                $writer->save('php://output');
            } finally {
                $spreadsheet->disconnectWorksheets();
            }
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
