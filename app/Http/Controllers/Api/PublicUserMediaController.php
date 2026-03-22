<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\UserPublicMedia;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Streams user avatars / school logos via opaque tokens (no signed URLs, no /storage/ symlink).
 */
class PublicUserMediaController extends Controller
{
    public function avatar(string $token): BinaryFileResponse
    {
        $user = User::where('avatar_public_token', $token)->first();
        if (! $user) {
            abort(404);
        }

        $raw = $user->profile_avatar_url ?? $user->avatar_url;
        $path = UserPublicMedia::storageRelativePathFromPublicUrl($raw);
        if ($path === null || ! Storage::disk('public')->exists($path)) {
            abort(404);
        }

        return response()->file(Storage::disk('public')->path($path), [
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }

    public function schoolLogo(string $token): BinaryFileResponse
    {
        $user = User::where('school_logo_public_token', $token)->first();
        if (! $user) {
            abort(404);
        }

        if ($user->role !== 'officer' && $user->role !== 'admin') {
            abort(404);
        }

        $path = UserPublicMedia::storageRelativePathFromPublicUrl($user->school_logo_url);
        if ($path === null || ! Storage::disk('public')->exists($path)) {
            abort(404);
        }

        return response()->file(Storage::disk('public')->path($path), [
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }
}
