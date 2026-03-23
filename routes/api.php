<?php

use App\Http\Controllers\Api\AccomplishmentReportController;
use App\Http\Controllers\Api\ActivityLogController;
use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BackupController;
use App\Http\Controllers\Api\FilesArchiveController;
use App\Http\Controllers\Api\MonitorOfficersController;
use App\Http\Controllers\Api\PublicUserMediaController;
use App\Http\Controllers\Api\SettingsController;
use App\Http\Controllers\Api\TaskController;
use App\Http\Controllers\Api\UserProfileController;
use Illuminate\Support\Facades\Route;

// Public
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/verify-email', [AuthController::class, 'verifyEmail']);
Route::post('/resend-otp', [AuthController::class, 'resendOtp']);
Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/reset-password', [AuthController::class, 'resetPassword']);

// Public settings (for app name, logo - used in login/layout)
// Logo image is served via API so deployments work without a working /storage symlink or static file route.
Route::get('/settings/logo', [SettingsController::class, 'logo']);
Route::get('/settings', [SettingsController::class, 'index']);

// User media — opaque tokens (no Laravel signed URLs; works behind any reverse proxy path).
Route::get('/public/avatar/{token}', [PublicUserMediaController::class, 'avatar'])
    ->where('token', '[A-Za-z0-9]+');
Route::get('/public/school-logo/{token}', [PublicUserMediaController::class, 'schoolLogo'])
    ->where('token', '[A-Za-z0-9]+');

// Protected (auth:sanctum)
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);
    Route::put('/user/password', [AuthController::class, 'updatePassword']);
    Route::put('/user/profile', [UserProfileController::class, 'updateProfile']);
    Route::post('/user/avatar', [UserProfileController::class, 'uploadAvatar']);
    Route::post('/user/school-logo', [UserProfileController::class, 'uploadSchoolLogo']);

    // Officer (personnel) task API — own tasks only (Phase 2.1, 2.2, 2.8)
    Route::middleware('officer')->group(function () {
        Route::get('/tasks', [TaskController::class, 'officerIndex']);
        Route::get('/tasks/kra-values', [TaskController::class, 'officerKraValues']);
        Route::post('/tasks', [TaskController::class, 'officerStore']);
        Route::get('/tasks/{id}/files', [TaskController::class, 'officerTaskFiles']);
        Route::post('/tasks/{id}/upload', [TaskController::class, 'officerUploadFile']);
        Route::delete('/tasks/{id}/files/{fileId}', [TaskController::class, 'officerDeleteFile']);
        Route::get('/tasks/{id}/files/{fileId}/download', [TaskController::class, 'officerDownloadFile']);
        Route::get('/files-archive', [FilesArchiveController::class, 'index']);
        Route::post('/files-archive/{fileId}/archive', [FilesArchiveController::class, 'archive']);
        Route::post('/files-archive/{fileId}/restore', [FilesArchiveController::class, 'restore']);
        Route::delete('/files-archive/{fileId}', [FilesArchiveController::class, 'destroy']);
        Route::get('/files-archive/{fileId}/download', [FilesArchiveController::class, 'download']);
        Route::get('/tasks/statistics', [TaskController::class, 'officerStatistics']);
        Route::get('/tasks/{id}', [TaskController::class, 'officerShow']);
        Route::put('/tasks/{id}', [TaskController::class, 'officerUpdate']);
        Route::delete('/tasks/{id}', [TaskController::class, 'officerDestroy']);

        // Accomplishment Reports (Phase 3.1, 3.2) — export/{token} routes must be registered before {id}
        Route::get('/accomplishment-reports/export/{token}/status', [AccomplishmentReportController::class, 'exportFromPeriodStatus'])
            ->where('token', '[A-Za-z0-9]{32,80}');
        Route::get('/accomplishment-reports/export/{token}/download', [AccomplishmentReportController::class, 'exportFromPeriodDownload'])
            ->where('token', '[A-Za-z0-9]{32,80}')
            ->middleware('accomplishment_export_timeout');
        Route::get('/accomplishment-reports', [AccomplishmentReportController::class, 'index']);
        Route::post('/accomplishment-reports/export', [AccomplishmentReportController::class, 'exportFromPeriod']);
        Route::post('/accomplishment-reports', [AccomplishmentReportController::class, 'store']);
        Route::get('/accomplishment-reports/{id}', [AccomplishmentReportController::class, 'show']);
        Route::get('/accomplishment-reports/{id}/export', [AccomplishmentReportController::class, 'export'])
            ->middleware('accomplishment_export_timeout');
        Route::put('/accomplishment-reports/{id}', [AccomplishmentReportController::class, 'update']);
    });

    // Admin only (Phase 2: user approval + officers list; Phase 3: task management)
    Route::middleware('admin')->prefix('admin')->group(function () {
        Route::get('/pending-users', [AdminController::class, 'pendingUsers']);
        Route::get('/officers', [AdminController::class, 'officers']);
        Route::get('/monitor-officers', [MonitorOfficersController::class, 'index']);
        Route::post('/users/{id}/approve', [AdminController::class, 'approve']);
        Route::post('/users/{id}/reject', [AdminController::class, 'reject']);
        Route::post('/users/{id}/deactivate', [AdminController::class, 'deactivateOfficer']);
        Route::post('/users/{id}/activate', [AdminController::class, 'activateOfficer']);
        Route::delete('/users/{id}', [AdminController::class, 'deleteOfficer']);
        // Admin task management — view only (Phase 2.7: personnel create their own tasks)
        Route::get('/tasks', [TaskController::class, 'index']);
        Route::get('/tasks/kra-values', [TaskController::class, 'adminKraValues']);
        Route::get('/tasks/officers', [TaskController::class, 'officers']);
        Route::get('/tasks/statistics', [TaskController::class, 'adminStatistics']);
        Route::get('/tasks/{id}', [TaskController::class, 'show']);
        Route::get('/tasks/{id}/files', [TaskController::class, 'adminTaskFiles']);
        Route::get('/tasks/{id}/files/{fileId}/download', [TaskController::class, 'adminDownloadFile']);

        // System Settings (admin only)
        Route::put('/settings', [SettingsController::class, 'update']);
        Route::post('/settings/logo', [SettingsController::class, 'uploadLogo']);

        // Backup (admin only)
        Route::get('/backup', [BackupController::class, 'download']);
        Route::get('/backup/schedule', [BackupController::class, 'getSchedule']);
        Route::put('/backup/schedule', [BackupController::class, 'updateSchedule']);
        Route::get('/backup/list', [BackupController::class, 'listBackups']);
        Route::get('/backup/download/latest', [BackupController::class, 'downloadLatest']);
        Route::get('/backup/download/file/{filename}', [BackupController::class, 'downloadFile']);

        // Activity logs (admin only)
        Route::get('/activity-logs', [ActivityLogController::class, 'index']);
    });
});
