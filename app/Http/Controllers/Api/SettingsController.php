<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

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
     * Sanitize stored logo path (relative to the public disk root).
     */
    private function safeLogoPath(?string $logoPath): ?string
    {
        if (! $logoPath) {
            return null;
        }
        $path = ltrim(str_replace(['../', '..\\'], '', $logoPath), '/');
        if ($path === '' || str_contains($path, '..')) {
            return null;
        }

        return $path;
    }

    /**
     * Build logo URL for JSON. Use API route so <img src> hits PHP and avoids 403 when
     * /storage is not exposed (e.g. some reverse proxies / App Platform layouts).
     */
    private function settingsResponse(SystemSetting $s): JsonResponse
    {
        $logoUrl = null;
        $path = $this->safeLogoPath($s->logo_path);
        if ($path !== null && Storage::disk('public')->exists($path)) {
            $logoUrl = '/api/settings/logo';
        }

        return response()->json([
            'app_name' => $s->app_name,
            'logo_url' => $logoUrl,
            'tagline' => $s->tagline,
        ]);
    }

    /**
     * GET /api/settings/logo — stream the current system logo (public, same as metadata).
     * Uses response()->file() so static analysis sees a known API (Filesystem contract has no response()).
     */
    public function logo(): BinaryFileResponse
    {
        $s = SystemSetting::get();
        $path = $this->safeLogoPath($s->logo_path);
        if ($path === null || ! Storage::disk('public')->exists($path)) {
            abort(404);
        }

        return response()->file(Storage::disk('public')->path($path));
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
            // Use `file` not `image` — `image` rejects SVG, which we allow in mimes.
            'logo' => ['required', 'file', 'mimes:jpeg,jpg,png,gif,webp,svg', 'max:2048'],
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
