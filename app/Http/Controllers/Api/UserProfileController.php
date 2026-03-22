<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\UserPublicMedia;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class UserProfileController extends Controller
{
    /**
     * PUT /api/user/profile — update profile (name, employee_id, position, division, school_name).
     */
    public function updateProfile(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'employee_id' => ['nullable', 'string', 'max:100'],
            'position' => ['nullable', 'string', 'max:255'],
            'division' => ['nullable', 'string', 'max:255'],
            'school_name' => ['nullable', 'string', 'max:255'],
        ]);

        $user = $request->user();
        $user->update([
            'name' => $validated['name'],
            'employee_id' => $validated['employee_id'] ?? null,
            'position' => $validated['position'] ?? null,
            'division' => $validated['division'] ?? null,
            'school_name' => $validated['school_name'] ?? null,
        ]);
        $user->refresh();

        return response()->json([
            'message' => 'Profile updated successfully.',
            'user' => $this->userResponse($user),
        ]);
    }

    /**
     * POST /api/user/avatar — upload profile avatar.
     */
    public function uploadAvatar(Request $request): JsonResponse
    {
        $request->validate([
            'avatar' => ['required', 'image', 'mimes:jpeg,jpg,png,gif,webp', 'max:2048'],
        ]);

        $user = $request->user();

        if ($user->profile_avatar_url) {
            $oldPath = $this->storagePathFromUrl($user->profile_avatar_url);
            if ($oldPath && Storage::disk('public')->exists($oldPath)) {
                Storage::disk('public')->delete($oldPath);
            }
        }

        $path = $request->file('avatar')->store('avatars', 'public');
        $url = Storage::url($path);
        $user->update(['profile_avatar_url' => $url]);

        $user->refresh();

        return response()->json([
            'message' => 'Profile photo updated.',
            'user' => $this->userResponse($user),
        ]);
    }

    /**
     * GET /api/media/avatar/{user} — stream avatar (signed URL, for <img src> without Bearer token).
     */
    public function showAvatar(Request $request, User $user): BinaryFileResponse
    {
        $expectedV = (string) ($user->updated_at?->getTimestamp() ?? 0);
        if ($request->query('v') !== $expectedV) {
            abort(404);
        }

        $raw = $user->profile_avatar_url ?? $user->avatar_url;
        $path = UserPublicMedia::storageRelativePathFromPublicUrl($raw);
        if ($path === null || ! Storage::disk('public')->exists($path)) {
            abort(404);
        }

        return response()->file(Storage::disk('public')->path($path));
    }

    /**
     * GET /api/media/school-logo/{user} — stream school logo (signed URL).
     */
    public function showSchoolLogo(Request $request, User $user): BinaryFileResponse
    {
        if ($user->role !== 'officer' && $user->role !== 'admin') {
            abort(404);
        }

        $expectedV = (string) ($user->updated_at?->getTimestamp() ?? 0);
        if ($request->query('v') !== $expectedV) {
            abort(404);
        }

        $path = UserPublicMedia::storageRelativePathFromPublicUrl($user->school_logo_url);
        if ($path === null || ! Storage::disk('public')->exists($path)) {
            abort(404);
        }

        return response()->file(Storage::disk('public')->path($path));
    }

    /**
     * POST /api/user/school-logo — upload school logo (officers only).
     */
    public function uploadSchoolLogo(Request $request): JsonResponse
    {
        $request->validate([
            'school_logo' => ['required', 'image', 'mimes:jpeg,jpg,png,gif,webp', 'max:2048'],
        ]);

        $user = $request->user();
        if ($user->role !== 'officer' && $user->role !== 'admin') {
            abort(403, 'Access denied. School logo is for personnel and administrators.');
        }

        if ($user->school_logo_url) {
            $oldPath = $this->storagePathFromUrl($user->school_logo_url);
            if ($oldPath && Storage::disk('public')->exists($oldPath)) {
                Storage::disk('public')->delete($oldPath);
            }
        }

        $path = $request->file('school_logo')->store('school-logos', 'public');
        $url = Storage::url($path);
        $user->update(['school_logo_url' => $url]);
        $user->refresh();

        return response()->json([
            'message' => 'School logo updated.',
            'user' => $this->userResponse($user),
        ]);
    }

    private function storagePathFromUrl(?string $url): ?string
    {
        return UserPublicMedia::storageRelativePathFromPublicUrl($url);
    }

    private function userResponse(User $user): array
    {
        return $user->only([
            'id',
            'name',
            'email',
            'role',
            'status',
            'is_active',
            'employee_id',
            'position',
            'division',
            'school_name',
        ]) + [
            'avatar_url' => UserPublicMedia::avatarUrlForClient($user),
            'school_logo_url' => UserPublicMedia::schoolLogoUrlForClient($user),
        ];
    }
}
