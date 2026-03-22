<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SettingsController extends Controller
{
    /**
     * Ensure the user is an admin (central admin).
     */
    private function ensureAdmin(Request $request): void
    {
        if ($request->user()?->role !== 'admin') {
            abort(403, 'Access denied. Administrator only.');
        }
    }

    /**
     * Build logo URL so it works in deployment.
     */
    private function settingsResponse(SystemSetting $s): JsonResponse
    {
        $logoUrl = null;
        if ($s->logo_path) {
            $path = ltrim(str_replace(['../', '..\\'], '', $s->logo_path), '/');
            if ($path !== '' && !str_contains($path, '..')) {
                // Storage::url() returns a path like /storage/path/to/file
                // We'll return it as-is and let the frontend handle the base URL
                $logoUrl = Storage::url($path);
            }
        }

        return response()->json([
            'app_name' => $s->app_name,
            'logo_url' => $logoUrl,
            'tagline' => $s->tagline,
        ]);
    }

    /**
     * GET /api/settings — app_name, logo_url, tagline. Public for layout/login.
     */
    public function index(): JsonResponse
    {
        $s = SystemSetting::get();
        return $this->settingsResponse($s);
    }

    /**
     * PUT /api/admin/settings — update app_name, tagline. Admin only.
     */
    public function update(Request $request): JsonResponse
    {
        $this->ensureAdmin($request);

        $valid = $request->validate([
            'app_name' => ['required', 'string', 'max:255'],
            'tagline' => ['nullable', 'string', 'max:500'],
        ]);

        $s = SystemSetting::get();
        $s->update([
            'app_name' => $valid['app_name'],
            'tagline' => $valid['tagline'] ?? null,
        ]);
        SystemSetting::clearCache();

        return $this->settingsResponse($s->fresh());
    }

    /**
     * POST /api/admin/settings/logo — upload logo image. Admin only.
     */
    public function uploadLogo(Request $request): JsonResponse
    {
        $this->ensureAdmin($request);

        $request->validate([
            'logo' => ['required', 'image', 'mimes:jpeg,jpg,png,gif,webp,svg', 'max:2048'],
        ]);

        $s = SystemSetting::get();

        if ($s->logo_path && Storage::disk('public')->exists($s->logo_path)) {
            Storage::disk('public')->delete($s->logo_path);
        }

        $path = $request->file('logo')->store('settings', 'public');
        $s->update(['logo_path' => $path]);
        SystemSetting::clearCache();

        return $this->settingsResponse($s->fresh());
    }
}
