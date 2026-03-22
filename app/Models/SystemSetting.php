<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class SystemSetting extends Model
{
    protected $table = 'system_settings';

    protected $fillable = [
        'app_name',
        'logo_path',
        'tagline',
    ];

    /** Cache key for the singleton row */
    private const CACHE_KEY = 'system_settings';

    /** Cache TTL in seconds (5 minutes). */
    private const CACHE_TTL = 300;

    /**
     * Get the single system settings row (cached).
     */
    public static function get(): self
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function () {
            $row = self::query()->first();
            if (!$row) {
                $row = self::query()->create([
                    'app_name' => 'TasDoneNa',
                    'logo_path' => null,
                    'tagline' => null,
                ]);
            }
            return $row;
        });
    }

    /**
     * Clear the settings cache (call after PUT or logo upload).
     */
    public static function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
