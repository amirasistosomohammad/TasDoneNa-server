<?php

use App\Models\AccomplishmentReport;
use App\Models\User;
use App\Services\AccomplishmentReportDataService;
use App\Services\AccomplishmentReportExcelExportService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('accomplishment:generate-export {userId} {token}', function (string $userId, string $token): void {
    $ttlSeconds = (int) config('accomplishment_report_export.export_cache_ttl_seconds', 900);
    $exportDir = storage_path('app/temp/accomplishment_exports');
    $statusDir = $exportDir.'/status';
    $statusPath = $statusDir.'/'.$userId.'_'.$token.'.json';
    $exportPath = $exportDir.'/'.$userId.'_'.$token.'.xlsx';

    if (! is_readable($statusPath)) {
        $this->warn('Export status file not found: '.$statusPath);

        return;
    }

    $status = json_decode((string) file_get_contents($statusPath), true);
    if (! is_array($status)) {
        $this->warn('Export status file unreadable: '.$statusPath);

        return;
    }

    $expiresAt = $status['expires_at'] ?? now()->addSeconds($ttlSeconds)->timestamp;
    $payload = $status['payload'] ?? null;
    if (! is_array($payload)) {
        $tmp = $statusPath.'.tmp';
        file_put_contents($tmp, json_encode([
            'state' => 'failed',
            'expires_at' => $expiresAt,
            'message' => 'Missing export payload.',
        ], JSON_UNESCAPED_UNICODE));
        @rename($tmp, $statusPath);

        return;
    }

    $statusWrite = function (array $update) use ($statusPath, $expiresAt): void {
        $tmp = $statusPath.'.tmp';
        $existing = [];
        if (is_readable($statusPath)) {
            $existing = json_decode((string) file_get_contents($statusPath), true) ?? [];
        }
        if (! is_array($existing)) {
            $existing = [];
        }
        $existing['expires_at'] = $expiresAt;
        foreach ($update as $k => $v) {
            $existing[$k] = $v;
        }
        file_put_contents($tmp, json_encode($existing, JSON_UNESCAPED_UNICODE));
        @rename($tmp, $statusPath);
    };

    $statusWrite(['state' => 'processing']);

    try {
        $year = (int) ($payload['year'] ?? 0);
        $month = (int) ($payload['month'] ?? 0);
        $schoolHeadName = (string) ($payload['school_head_name'] ?? '');
        $schoolHeadDesignation = (string) ($payload['school_head_designation'] ?? '');

        $officer = User::query()
            ->select(['id', 'name', 'email', 'position', 'division', 'school_name'])
            ->find((int) $userId);

        if (! $officer) {
            $statusWrite(['state' => 'failed', 'message' => 'User not found.']);

            return;
        }

        $dataService = app(AccomplishmentReportDataService::class);
        $excelService = app(AccomplishmentReportExcelExportService::class);

        if (! $excelService->templateExists()) {
            $statusWrite([
                'state' => 'failed',
                'message' => 'Excel template is missing or not readable on the server.',
            ]);

            return;
        }

        $tasksSummary = $dataService->tasksSummaryForOfficerMonth((int) $officer->id, $year, $month);

        $report = new AccomplishmentReport([
            'year' => $year,
            'month' => $month,
            'tasks_summary' => $tasksSummary,
        ]);
        $report->setRelation('user', $officer);

        $spreadsheet = $excelService->fill($report, $schoolHeadName, $schoolHeadDesignation);

        $dir = $exportDir;
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $writer = new Xlsx($spreadsheet);
        $writer->setPreCalculateFormulas(false);
        $writer->setUseDiskCaching(true, storage_path('framework/cache'));
        $writer->save($exportPath);
        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet, $writer);

        $slug = Str::slug($officer->name ?? 'report', '_') ?: 'report';
        $filename = sprintf(
            'Accomplishment_Report_%04d-%02d_%s.xlsx',
            $year,
            $month,
            substr($slug, 0, 80)
        );

        $statusWrite([
            'state' => 'ready',
            'path' => $exportPath,
            'filename' => $filename,
        ]);
    } catch (\Throwable $e) {
        report($e);
        $statusWrite([
            'state' => 'failed',
            'message' => 'Failed to build the Excel file.',
        ]);
    }
})->purpose('Generate accomplishment report export XLSX in background');
