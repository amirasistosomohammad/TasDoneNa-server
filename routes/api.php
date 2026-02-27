<?php

use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\TaskController;
use Illuminate\Support\Facades\Route;

// Public
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/verify-email', [AuthController::class, 'verifyEmail']);
Route::post('/resend-otp', [AuthController::class, 'resendOtp']);
Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/reset-password', [AuthController::class, 'resetPassword']);

// Protected (auth:sanctum)
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);

    // Admin only (Phase 2: user approval + officers list; Phase 3: task management)
    Route::middleware('admin')->prefix('admin')->group(function () {
        Route::get('/pending-users', [AdminController::class, 'pendingUsers']);
        Route::get('/officers', [AdminController::class, 'officers']);
        Route::post('/users/{id}/approve', [AdminController::class, 'approve']);
        Route::post('/users/{id}/reject', [AdminController::class, 'reject']);
        Route::post('/users/{id}/deactivate', [AdminController::class, 'deactivateOfficer']);
        Route::post('/users/{id}/activate', [AdminController::class, 'activateOfficer']);
        // Phase 3: Task management
        Route::get('/tasks', [TaskController::class, 'index']);
        Route::get('/tasks/officers', [TaskController::class, 'officers']);
        Route::get('/tasks/{id}', [TaskController::class, 'show']);
        Route::post('/tasks', [TaskController::class, 'store']);
        Route::put('/tasks/{id}', [TaskController::class, 'update']);
        Route::delete('/tasks/{id}', [TaskController::class, 'destroy']);
    });
});
