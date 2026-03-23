<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\UserPublicMedia;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class UserProfileController extends Controller
{
    /**
     * PUT /api/user/profile — update profile (name, employee_id, position, division, district, school_name).
     */
    public function updateProfile(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'employee_id' => ['nullable', 'string', 'max:100'],
            'position' => ['nullable', 'string', 'max:255'],
            'division' => ['nullable', 'string', 'max:255'],
            'district' => ['nullable', 'string', 'max:255'],
            'school_name' => ['nullable', 'string', 'max:255'],
        ]);

        $user = $request->user();
        $user->update([
            'name' => $validated['name'],
            'employee_id' => $validated['employee_id'] ?? null,
            'position' => $validated['position'] ?? null,
            'division' => $validated['division'] ?? null,
            'district' => $validated['district'] ?? null,
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
            'avatar' => ['required', 'file', 'mimes:jpeg,jpg,png,gif,webp', 'max:2048'],
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
        $user->update([
            'profile_avatar_url' => $url,
            'avatar_public_token' => Str::random(48),
        ]);

        $user->refresh();

        return response()->json([
            'message' => 'Profile photo updated.',
            'user' => $this->userResponse($user),
        ]);
    }

    /**
     * POST /api/user/school-logo — upload school logo (officers only).
     */
    public function uploadSchoolLogo(Request $request): JsonResponse
    {
        $request->validate([
            'school_logo' => ['required', 'file', 'mimes:jpeg,jpg,png,gif,webp', 'max:2048'],
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
        $user->update([
            'school_logo_url' => $url,
            'school_logo_public_token' => Str::random(48),
        ]);
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
        $user->ensurePublicMediaTokens();

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
            'district',
            'school_name',
        ]) + [
            'avatar_url' => UserPublicMedia::avatarUrlForClient($user),
            'school_logo_url' => UserPublicMedia::schoolLogoUrlForClient($user),
        ];
    }
}
