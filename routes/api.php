<?php

use App\Http\Controllers\Api\AuthController;
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
});
