<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class BackupSetting extends Model
{
    protected $table = 'backup_settings';

    public const FREQUENCY_OFF = 'off';
    public const FREQUENCY_DAILY = 'daily';
    public const FREQUENCY_WEEKLY = 'weekly';

    public const TIMEZONE_DEFAULT = 'Asia/Manila';

    protected $fillable = [
        'frequency',
        'run_at_time',
        'timezone',
        'last_run_at',
        'next_run_at',
        'last_backup_path',
    ];

    protected $casts = [
        'last_run_at' => 'datetime',
        'next_run_at' => 'datetime',
    ];

    /** Cache key for the singleton row */
    private const CACHE_KEY = 'backup_settings';

    /** Cache TTL in seconds (5 minutes). */
    private const CACHE_TTL = 300;

    /**
     * Get the single backup settings row (cached).
     */
    public static function get(): self
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function () {
            $row = self::query()->first();
            if (!$row) {
                $row = self::query()->create([
                    'frequency' => self::FREQUENCY_OFF,
                    'run_at_time' => '02:00',
                    'timezone' => self::TIMEZONE_DEFAULT,
                    'last_run_at' => null,
                    'next_run_at' => null,
                    'last_backup_path' => null,
                ]);
            }
            return $row;
        });
    }

    /**
     * Clear the backup settings cache (call after update).
     */
    public static function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
