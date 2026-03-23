<?php

use App\Models\AccomplishmentReport;
use App\Models\User;
use App\Services\AccomplishmentReportDataService;
use App\Services\AccomplishmentReportExcelExportService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('accomplishment:generate-export {userId} {token}', function (string $userId, string $token): void {
    $cacheKey = 'accomplishment_export:'.$userId.':'.$token;
    $data = Cache::get($cacheKey);
    $ttlSeconds = (int) config('accomplishment_report_export.export_cache_ttl_seconds', 900);

    if (! is_array($data) || ($data['state'] ?? '') === '') {
        $this->warn('Export token not found in cache: '.$token);

        return;
    }

    if (($data['state'] ?? '') !== 'pending' && ($data['state'] ?? '') !== 'processing') {
        $this->warn('Export token not pending/processing: '.$token);

        return;
    }

    $payload = $data['payload'] ?? null;
    if (! is_array($payload)) {
        Cache::put($cacheKey, [
            'state' => 'failed',
            'message' => 'Missing export payload.',
        ], now()->addSeconds($ttlSeconds));

        return;
    }

    Cache::put($cacheKey, array_merge($data, ['state' => 'processing']), now()->addSeconds($ttlSeconds));

    try {
        $year = (int) ($payload['year'] ?? 0);
        $month = (int) ($payload['month'] ?? 0);
        $schoolHeadName = (string) ($payload['school_head_name'] ?? '');
        $schoolHeadDesignation = (string) ($payload['school_head_designation'] ?? '');

        $officer = User::query()
            ->select(['id', 'name', 'email', 'position', 'division', 'school_name'])
            ->find((int) $userId);

        if (! $officer) {
            Cache::put($cacheKey, [
                'state' => 'failed',
                'message' => 'User not found.',
            ], now()->addSeconds($ttlSeconds));

            return;
        }

        $dataService = app(AccomplishmentReportDataService::class);
        $excelService = app(AccomplishmentReportExcelExportService::class);

        if (! $excelService->templateExists()) {
            Cache::put($cacheKey, [
                'state' => 'failed',
                'message' => 'Excel template is missing or not readable on the server.',
            ], now()->addSeconds($ttlSeconds));

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

        $dir = storage_path('app/temp/accomplishment_exports');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $path = $dir.'/'.$userId.'_'.$token.'.xlsx';
        $writer = new Xlsx($spreadsheet);
        $writer->setPreCalculateFormulas(false);
        $writer->setUseDiskCaching(true, storage_path('framework/cache'));
        $writer->save($path);
        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet, $writer);

        $slug = Str::slug($officer->name ?? 'report', '_') ?: 'report';
        $filename = sprintf(
            'Accomplishment_Report_%04d-%02d_%s.xlsx',
            $year,
            $month,
            substr($slug, 0, 80)
        );

        Cache::put($cacheKey, [
            'state' => 'ready',
            'path' => $path,
            'filename' => $filename,
        ], now()->addSeconds($ttlSeconds));
    } catch (\Throwable $e) {
        report($e);
        Cache::put($cacheKey, [
            'state' => 'failed',
            'message' => 'Failed to build the Excel file.',
        ], now()->addSeconds($ttlSeconds));
    }
})->purpose('Generate accomplishment report export XLSX in background');
