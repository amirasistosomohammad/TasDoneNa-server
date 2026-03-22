<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\URL;

/**
 * Build browser-safe URLs for user avatars / school logos stored on the public disk.
 * Direct /storage/... links often 403 on App Platform; signed /api/media/... routes stream via PHP.
 */
final class UserPublicMedia
{
    /**
     * Signed relative URL for profile photo, or external http(s) URL, or null.
     */
    public static function avatarUrlForClient(?User $user): ?string
    {
        if ($user === null) {
            return null;
        }

        $raw = $user->profile_avatar_url ?? $user->avatar_url;
        if ($raw === null || $raw === '') {
            return null;
        }

        if (str_starts_with($raw, 'http://') || str_starts_with($raw, 'https://')) {
            return $raw;
        }

        if (! str_starts_with($raw, '/storage/')) {
            return $raw;
        }

        return URL::temporarySignedRoute(
            'api.media.user-avatar',
            now()->addDays(30),
            [
                'user' => $user->id,
                'v' => (string) ($user->updated_at?->getTimestamp() ?? 0),
            ],
            false
        );
    }

    /**
     * Signed relative URL for school logo, or external http(s) URL, or null.
     */
    public static function schoolLogoUrlForClient(?User $user): ?string
    {
        if ($user === null) {
            return null;
        }

        $raw = $user->school_logo_url;
        if ($raw === null || $raw === '') {
            return null;
        }

        if (str_starts_with($raw, 'http://') || str_starts_with($raw, 'https://')) {
            return $raw;
        }

        if (! str_starts_with($raw, '/storage/')) {
            return $raw;
        }

        return URL::temporarySignedRoute(
            'api.media.user-school-logo',
            now()->addDays(30),
            [
                'user' => $user->id,
                'v' => (string) ($user->updated_at?->getTimestamp() ?? 0),
            ],
            false
        );
    }

    public static function storageRelativePathFromPublicUrl(?string $url): ?string
    {
        if ($url === null || $url === '') {
            return null;
        }
        if (! str_starts_with($url, '/storage/')) {
            return null;
        }
        $path = substr($url, strlen('/storage/'));

        return $path !== '' ? $path : null;
    }
}
