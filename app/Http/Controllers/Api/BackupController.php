<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BackupSetting;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class BackupController extends Controller
{
    /**
     * Ensure the user is an admin.
     */
    private function ensureAdmin(Request $request): void
    {
        if ($request->user()?->role !== 'admin') {
            abort(403, 'Access denied. Administrator only.');
        }
    }

    /**
     * GET /api/admin/backup — generate SQL dump and return as file download (Admin only).
     */
    public function download(Request $request): Response|JsonResponse
    {
        $this->ensureAdmin($request);

        try {
            $result = $this->runPhpSqlDump(false);
            $sql = $result['sql'];
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Backup failed: ' . $e->getMessage(),
            ], 500);
        }

        $filename = 'tasdonena-backup-' . now()->format('Y-m-d-His') . '.sql';

        return response($sql, 200, [
            'Content-Type' => 'application/sql',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Content-Length' => (string) strlen($sql),
        ]);
    }

    /**
     * PHP-based SQL dump fallback when mysqldump is not available.
     */
    private function runPhpSqlDump(bool $toFile = false, ?string $targetPath = null): array
    {
        $sql = "-- TasDoneNa SQL Backup (PHP fallback)\n";
        $sql .= "-- Generated: " . now()->toIso8601String() . "\n\n";
        $sql .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

        $tables = DB::select('SHOW TABLES');
        $dbName = DB::connection()->getDatabaseName();

        foreach ($tables as $row) {
            $rowArr = (array) $row;
            $table = reset($rowArr);
            $create = DB::selectOne('SHOW CREATE TABLE `' . str_replace('`', '``', $table) . '`');
            $createVals = array_values((array) $create);
            $createSql = $createVals[1] ?? '';
            $sql .= "DROP TABLE IF EXISTS `" . str_replace('`', '``', $table) . "`;\n";
            $sql .= $createSql . ";\n\n";

            $rows = DB::table($table)->get();
            if ($rows->isEmpty()) {
                continue;
            }

            $columns = array_keys((array) $rows->first());
            $colList = implode('`, `', array_map(fn ($c) => str_replace('`', '``', $c), $columns));
            $sql .= "INSERT INTO `" . str_replace('`', '``', $table) . "` (`" . $colList . "`) VALUES\n";

            $values = [];
            foreach ($rows as $r) {
                $rowArr = (array) $r;
                $vals = array_map(function ($v) {
                    if ($v === null) {
                        return 'NULL';
                    }
                    if (is_array($v) || is_object($v)) {
                        $v = json_encode($v);
                    }
                    return "'" . addslashes((string) $v) . "'";
                }, array_values($rowArr));
                $values[] = '(' . implode(', ', $vals) . ')';
            }
            $sql .= implode(",\n", $values) . ";\n\n";
        }

        $sql .= "SET FOREIGN_KEY_CHECKS=1;\n";

        if ($toFile && $targetPath) {
            $dir = dirname($targetPath);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            file_put_contents($targetPath, $sql);
            return ['path' => $targetPath];
        }

        return ['sql' => $sql];
    }

    /**
     * GET /api/admin/backup/schedule — get backup schedule (Admin only).
     */
    public function getSchedule(Request $request): JsonResponse
    {
        $this->ensureAdmin($request);

        $s = BackupSetting::get();
        $tz = BackupSetting::TIMEZONE_DEFAULT;
        if ($s->timezone !== $tz) {
            $s->timezone = $tz;
            $s->save();
        }

        // Return timestamps in UTC so the frontend can display in the user's local timezone.
        $lastRunUtc = $s->last_run_at ? Carbon::parse($s->last_run_at)->setTimezone('UTC')->toIso8601String() : null;
        $nextRunUtc = $s->next_run_at ? Carbon::parse($s->next_run_at)->setTimezone('UTC')->toIso8601String() : null;

        return response()->json([
            'frequency' => $s->frequency,
            'run_at_time' => $s->run_at_time,
            'timezone' => $tz,
            'last_run_at' => $lastRunUtc,
            'next_run_at' => $nextRunUtc,
            'has_latest_file' => $s->last_backup_path && Storage::disk('local')->exists($s->last_backup_path),
        ]);
    }

    /**
     * PUT /api/admin/backup/schedule — update backup schedule (Admin only).
     */
    public function updateSchedule(Request $request): JsonResponse
    {
        $this->ensureAdmin($request);

        $valid = $request->validate([
            'frequency' => ['required', 'string', 'in:off,daily,weekly'],
            'run_at_time' => ['required', 'string', 'regex:/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/'],
        ]);

        $s = BackupSetting::get();
        $s->frequency = $valid['frequency'];
        $s->run_at_time = $valid['run_at_time'];
        $s->timezone = BackupSetting::TIMEZONE_DEFAULT;

        // Store timestamps in UTC to avoid app timezone (UTC) shifting display by +8 hours.
        $nextPh = self::computeNextRunAt($s->frequency, $s->run_at_time, $s->timezone);
        $s->next_run_at = $nextPh?->copy()->setTimezone('UTC');
        $s->save();
        BackupSetting::clearCache();

        return response()->json([
            'frequency' => $s->frequency,
            'run_at_time' => $s->run_at_time,
            'timezone' => BackupSetting::TIMEZONE_DEFAULT,
            'next_run_at' => $s->next_run_at ? Carbon::parse($s->next_run_at)->setTimezone('UTC')->toIso8601String() : null,
        ]);
    }

    /**
     * Compute next run timestamp from frequency, time, and timezone.
     */
    public static function computeNextRunAt(string $frequency, string $runAtTime, string $timezone): ?Carbon
    {
        if ($frequency === BackupSetting::FREQUENCY_OFF) {
            return null;
        }

        try {
            $tz = new \DateTimeZone($timezone);
        } catch (\Throwable $e) {
            $tz = new \DateTimeZone('UTC');
        }

        $now = Carbon::now($tz);
        $parts = explode(':', $runAtTime);
        $hour = (int) ($parts[0] ?? 0);
        $minute = (int) ($parts[1] ?? 0);

        $next = $now->copy()->setTime($hour, $minute, 0);

        if ($next->lte($now)) {
            $next = $frequency === BackupSetting::FREQUENCY_DAILY ? $next->addDay() : $next->addWeek();
        }

        return $next;
    }

    /**
     * GET /api/admin/backup/download/latest — download last scheduled backup file (Admin only).
     */
    public function downloadLatest(Request $request): BinaryFileResponse|JsonResponse
    {
        $this->ensureAdmin($request);

        $s = BackupSetting::get();
        if (!$s->last_backup_path || !Storage::disk('local')->exists($s->last_backup_path)) {
            return response()->json(['message' => 'No scheduled backup file available.'], 404);
        }

        $path = Storage::disk('local')->path($s->last_backup_path);
        $filename = basename($s->last_backup_path);

        return response()->download($path, $filename, [
            'Content-Type' => 'application/sql',
        ]);
    }

    /**
     * GET /api/admin/backup/list — list automated backup files with date (Admin only).
     */
    public function listBackups(Request $request): JsonResponse
    {
        $this->ensureAdmin($request);

        $dir = 'backups';
        $disk = Storage::disk('local');
        if (!$disk->exists($dir)) {
            return response()->json(['backups' => []]);
        }

        $files = $disk->files($dir);
        $backups = [];
        foreach ($files as $path) {
            $basename = basename($path);
            // Accept both midtask- and tasdonena- prefixed backups
            if (preg_match('/^(midtask|tasdonena)-\d{4}-\d{2}-\d{2}-\d{6}\.sql$/', $basename) !== 1) {
                continue;
            }
            $fullPath = $disk->path($path);
            $mtime = file_exists($fullPath) ? filemtime($fullPath) : null;
            $createdAtIso = $mtime
                ? Carbon::createFromTimestampUTC($mtime)->toIso8601String()
                : null;
            $backups[] = [
                'filename' => $basename,
                'created_at' => $createdAtIso,
            ];
        }

        usort($backups, fn ($a, $b) => strcmp($b['created_at'] ?? '', $a['created_at'] ?? ''));

        return response()->json(['backups' => $backups]);
    }

    /**
     * GET /api/admin/backup/download/file/{filename} — download a specific backup file (Admin only).
     */
    public function downloadFile(Request $request, string $filename): BinaryFileResponse|JsonResponse
    {
        $this->ensureAdmin($request);

        // Accept both midtask- and tasdonena- prefixed backups
        if (preg_match('/^(midtask|tasdonena)-\d{4}-\d{2}-\d{2}-\d{6}\.sql$/', $filename) !== 1) {
            return response()->json(['message' => 'Invalid backup filename.'], 400);
        }

        $path = 'backups/' . $filename;
        if (!Storage::disk('local')->exists($path)) {
            return response()->json(['message' => 'Backup file not found.'], 404);
        }

        $fullPath = Storage::disk('local')->path($path);

        return response()->download($fullPath, $filename, [
            'Content-Type' => 'application/sql',
        ]);
    }
}
