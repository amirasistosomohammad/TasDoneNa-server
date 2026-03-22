<?php

namespace App\Support;

use App\Models\User;

/**
 * Build browser-safe URLs for user avatars / school logos on the public disk.
 * Uses opaque tokens + GET /api/public/... (no signed URLs, no /storage/ exposure).
 */
final class UserPublicMedia
{
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

        if (! $user->avatar_public_token) {
            return null;
        }

        $v = (string) ($user->updated_at?->getTimestamp() ?? 0);

        return '/api/public/avatar/'.$user->avatar_public_token.'?v='.$v;
    }

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

        if (! $user->school_logo_public_token) {
            return null;
        }

        $v = (string) ($user->updated_at?->getTimestamp() ?? 0);

        return '/api/public/school-logo/'.$user->school_logo_public_token.'?v='.$v;
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
