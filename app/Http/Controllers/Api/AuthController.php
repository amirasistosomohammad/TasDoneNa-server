<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\OtpMail;
use App\Mail\ResetPasswordMail;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Allowed institutional email domain (DepEd).
     */
    private const INSTITUTIONAL_EMAIL_DOMAIN = 'deped.gov.ph';

    /**
     * OTP validity in minutes.
     */
    private const OTP_EXIRY_MINUTES = 15;

    /**
     * Password reset token expiry in minutes.
     */
    private const RESET_TOKEN_EXPIRE_MINUTES = 60;

    /**
     * Register a new officer (status: pending). Sends OTP to email for verification.
     * If the email already exists but is not verified, updates the user and sends a new OTP
     * so they can try again. Resend OTP can be used repeatedly until the correct OTP is entered.
     */
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'employee_id' => ['nullable', 'string', 'max:100'],
            'position' => ['required', 'string', 'max:255'],
            'division' => ['required', 'string', 'max:255'],
            'school_name' => ['required', 'string', 'max:255'],
        ]);

        // Check for existing user (including soft-deleted)
        $existing = User::withTrashed()->where('email', $validated['email'])->first();

        if ($existing && $existing->email_verified_at && !$existing->trashed()) {
            throw ValidationException::withMessages([
                'email' => ['This email is already registered. Please sign in.'],
            ]);
        }

        // Handle soft-deleted (rejected) user trying to register again
        if ($existing && $existing->trashed()) {
            // Restore the soft-deleted user and update with new registration data
            $existing->restore();
            $existing->update([
                'name' => $validated['name'],
                'password' => $validated['password'],
                'employee_id' => $validated['employee_id'] ?? null,
                'position' => $validated['position'],
                'division' => $validated['division'],
                'school_name' => $validated['school_name'],
                'status' => 'pending', // Reset to pending for admin review
                'email_verified_at' => null, // Reset email verification
                'rejection_reason' => null, // Clear previous rejection reason
                'otp' => null, // Clear old OTP
                'otp_expires_at' => null,
            ]);
            $user = $existing;
        } elseif ($existing && ! $existing->email_verified_at) {
            // Existing unverified user - update their info
            $existing->update([
                'name' => $validated['name'],
                'password' => $validated['password'],
                'employee_id' => $validated['employee_id'] ?? null,
                'position' => $validated['position'],
                'division' => $validated['division'],
                'school_name' => $validated['school_name'],
            ]);
            $user = $existing;
        } else {
            // New user - create fresh record
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => $validated['password'],
                'role' => 'officer',
                'status' => 'pending',
                'employee_id' => $validated['employee_id'] ?? null,
                'position' => $validated['position'],
                'division' => $validated['division'],
                'school_name' => $validated['school_name'],
            ]);
        }

        $this->sendOtpToUser($user);

        return response()->json([
            'message' => 'Registration successful. Check your email for a one-time password (OTP) to verify your account.',
            'email' => $user->email,
            'user' => $user->only(['id', 'name', 'email', 'role', 'status']),
        ], 201);
    }

    /**
     * Verify email with OTP.
     */
    public function verifyEmail(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email'],
            'otp' => ['required', 'string', 'size:6'],
        ]);

        $user = User::where('email', $validated['email'])->first();

        if (! $user) {
            throw ValidationException::withMessages(['email' => ['No account found for this email.']]);
        }

        if ($user->email_verified_at) {
            return response()->json([
                'message' => 'Email is already verified. You may log in after admin approval.',
            ]);
        }

        if (! $user->otp || $user->otp !== $validated['otp']) {
            throw ValidationException::withMessages(['otp' => ['Invalid or expired OTP.']]);
        }

        if ($user->otp_expires_at && $user->otp_expires_at->isPast()) {
            $user->update(['otp' => null, 'otp_expires_at' => null]);
            throw ValidationException::withMessages(['otp' => ['OTP has expired. Please request a new one.']]);
        }

        $user->update([
            'email_verified_at' => now(),
            'otp' => null,
            'otp_expires_at' => null,
        ]);

        return response()->json([
            'message' => 'Email verified successfully. You can log in after an administrator approves your account.',
        ]);
    }

    /**
     * Resend OTP to email.
     */
    public function resendOtp(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email'],
        ]);

        $user = User::where('email', $validated['email'])->first();

        if (! $user) {
            throw ValidationException::withMessages(['email' => ['No account found for this email.']]);
        }

        if ($user->email_verified_at) {
            return response()->json([
                'message' => 'Email is already verified. You may log in after admin approval.',
            ]);
        }

        $this->sendOtpToUser($user);

        return response()->json([
            'message' => 'A new OTP has been sent to your email. It expires in ' . self::OTP_EXIRY_MINUTES . ' minutes.',
        ]);
    }

    private function sendOtpToUser(User $user): void
    {
        $otp = (string) random_int(100000, 999999);
        $user->update([
            'otp' => $otp,
            'otp_expires_at' => now()->addMinutes(self::OTP_EXIRY_MINUTES),
        ]);
        Mail::to($user->email)->send(new OtpMail($otp, $user->name));
    }

    /**
     * Forgot password: send reset link to email if account exists.
     * Always returns the same success message to prevent email enumeration.
     */
    public function forgotPassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email'],
        ]);

        $user = User::where('email', $validated['email'])->first();

        // Only send reset link if the account exists and email is verified (OTP completed).
        if ($user && $user->email_verified_at) {
            $token = Str::random(64);
            $hashedToken = Hash::make($token);

            DB::table('password_reset_tokens')->updateOrInsert(
                ['email' => $user->email],
                [
                    'token' => $hashedToken,
                    'created_at' => now(),
                ]
            );

            $frontendUrl = rtrim(config('app.frontend_url'), '/');
            $resetUrl = $frontendUrl . '/reset-password?token=' . urlencode($token) . '&email=' . urlencode($user->email);

            Mail::to($user->email)->send(new ResetPasswordMail(
                $resetUrl,
                $user->name,
                self::RESET_TOKEN_EXPIRE_MINUTES
            ));
        }

        // Always return the same message (no hint that email is unverified or missing).
        return response()->json([
            'message' => 'If an account exists with this email, a password reset link has been sent. Please check your inbox.',
            'success' => true,
        ]);
    }

    /**
     * Reset password: validate token and set new password.
     */
    public function resetPassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email'],
            'token' => ['required', 'string'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $row = DB::table('password_reset_tokens')->where('email', $validated['email'])->first();

        if (! $row || ! Hash::check($validated['token'], $row->token)) {
            throw ValidationException::withMessages([
                'token' => ['This password reset link is invalid or has expired. Please request a new one.'],
            ]);
        }

        $createdAt = \Carbon\Carbon::parse($row->created_at);
        if ($createdAt->copy()->addMinutes(self::RESET_TOKEN_EXPIRE_MINUTES)->isPast()) {
            DB::table('password_reset_tokens')->where('email', $validated['email'])->delete();
            throw ValidationException::withMessages([
                'token' => ['This password reset link has expired. Please request a new one.'],
            ]);
        }

        $user = User::where('email', $validated['email'])->first();
        if (! $user) {
            DB::table('password_reset_tokens')->where('email', $validated['email'])->delete();
            throw ValidationException::withMessages([
                'email' => ['No account found for this email.'],
            ]);
        }

        $user->update(['password' => $validated['password']]);
        DB::table('password_reset_tokens')->where('email', $validated['email'])->delete();

        return response()->json([
            'message' => 'Your password has been reset successfully. You can now sign in with your new password.',
            'success' => true,
        ]);
    }

    /**
     * Login: returns token + user. Officers must be approved.
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required'],
        ]);

        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Invalid credentials.'],
            ]);
        }

        // Officers must have verified their email before they can log in. Do not reveal that the email exists.
        if ($user->role === 'officer' && ! $user->email_verified_at) {
            throw ValidationException::withMessages([
                'email' => ['Invalid credentials.'],
            ]);
        }

        // Rejected users cannot log in. Pending users can log in and see the dashboard (with a pending message).
        if ($user->status === 'rejected') {
            return response()->json([
                'message' => 'Your account has been rejected.',
                'reason' => $user->rejection_reason,
                'status' => 'rejected',
            ], 403);
        }

        // Deactivated officers cannot log in; include reason so frontend can show a dedicated message/modal.
        // Use !$user->is_active so we block when DB returns 0 (uncast) or false (cast).
        if ($user->role === 'officer' && ! $user->is_active) {
            return response()->json([
                'message' => 'Your account has been deactivated.',
                'reason' => $user->deactivation_reason,
                'status' => 'deactivated',
            ], 403);
        }

        $user->tokens()->where('name', 'auth')->delete();
        $token = $user->createToken('auth')->plainTextToken;

        ActivityLog::log(
            'user_login',
            "User signed in: {$user->name} ({$user->email})",
            $user->id,
            $request->ip()
        );

        $avatarUrl = $user->profile_avatar_url ?? $user->avatar_url;
        $userData = $user->only(['id', 'name', 'email', 'role', 'status', 'is_active', 'employee_id', 'position', 'division', 'school_name']);
        $userData['avatar_url'] = $avatarUrl;
        $userData['school_logo_url'] = $user->school_logo_url ?? null;

        return response()->json([
            'message' => 'Login successful.',
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => $userData,
        ]);
    }

    /**
     * Logout: revoke current token.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out successfully.']);
    }

    /**
     * Get authenticated user. Deactivated or rejected users get 403 and their token is revoked.
     */
    public function user(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->status === 'rejected') {
            $request->user()->currentAccessToken()->delete();
            return response()->json([
                'message' => 'Your account has been rejected.',
                'reason' => $user->rejection_reason,
                'status' => 'rejected',
            ], 403);
        }

        if ($user->role === 'officer' && ! $user->is_active) {
            $request->user()->currentAccessToken()->delete();
            return response()->json([
                'message' => 'Your account has been deactivated.',
                'reason' => $user->deactivation_reason,
                'status' => 'deactivated',
            ], 403);
        }

        $avatarUrl = $user->profile_avatar_url ?? $user->avatar_url;
        $userData = $user->only(['id', 'name', 'email', 'role', 'status', 'is_active', 'employee_id', 'position', 'division', 'school_name']);
        $userData['avatar_url'] = $avatarUrl;
        $userData['school_logo_url'] = $user->school_logo_url ?? null;

        return response()->json([
            'user' => $userData,
        ]);
    }

    /**
     * Update password for authenticated user.
     */
    public function updatePassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'new_password' => ['required', 'confirmed', Password::defaults()],
            'new_password_confirmation' => ['required', 'string'],
        ]);

        $user = $request->user();

        if (!Hash::check($validated['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['The current password is incorrect.'],
            ]);
        }

        $user->update(['password' => $validated['new_password']]);

        return response()->json([
            'message' => 'Password updated successfully.',
        ]);
    }
}
