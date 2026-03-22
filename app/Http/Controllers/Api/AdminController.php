<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\OfficerApprovalMail;
use App\Mail\OfficerDeactivationMail;
use App\Mail\OfficerRejectionMail;
use App\Models\ActivityLog;
use App\Models\Task;
use App\Models\User;
use App\Support\UserPublicMedia;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class AdminController extends Controller
{
    /**
     * List all officers (any status) with optional search/status filters.
     */
    public function officers(Request $request): JsonResponse
    {
        $query = User::where('role', 'officer');

        $status = $request->input('status');
        if ($status && in_array($status, ['pending', 'approved', 'rejected'], true)) {
            $query->where('status', $status);
        }

        $search = trim((string) $request->input('q', ''));
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('employee_id', 'like', "%{$search}%")
                    ->orWhere('position', 'like', "%{$search}%")
                    ->orWhere('division', 'like', "%{$search}%")
                    ->orWhere('school_name', 'like', "%{$search}%");
            });
        }

        $officers = $query
            ->orderBy('created_at', 'desc')
            ->get([
                'id',
                'name',
                'email',
                'employee_id',
                'position',
                'division',
                'school_name',
                'status',
                'is_active',
                'avatar_url',
                'profile_avatar_url',
                'approval_remarks',
                'deactivation_reason',
                'rejection_reason',
                'activation_remarks',
                'approved_at',
                'rejected_at',
                'deactivated_at',
                'activated_at',
                'created_at',
                'updated_at',
            ]);

        $officers->each->makeVisible([
            'rejection_reason',
            'deactivation_reason',
            'approval_remarks',
            'activation_remarks',
            'approved_at',
            'rejected_at',
            'deactivated_at',
            'activated_at',
        ]);

        $keys = [
            'id', 'name', 'email', 'employee_id', 'position', 'division', 'school_name',
            'status', 'is_active', 'avatar_url', 'profile_avatar_url', 'approval_remarks',
            'deactivation_reason', 'rejection_reason', 'activation_remarks', 'approved_at',
            'rejected_at', 'deactivated_at', 'activated_at', 'created_at', 'updated_at',
        ];

        return response()->json([
            'officers' => $officers->map(function (User $officer) use ($keys) {
                $row = $officer->only($keys);
                $row['avatar_url'] = UserPublicMedia::avatarUrlForClient($officer);
                $row['profile_avatar_url'] = UserPublicMedia::avatarUrlForClient($officer);

                return $row;
            }),
        ]);
    }

    /**
     * Deactivate an approved officer with an optional reason (admin only).
     */
    public function deactivateOfficer(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $user = User::where('role', 'officer')->find($id);

        if (! $user) {
            throw ValidationException::withMessages([
                'id' => ['User not found.'],
            ]);
        }

        if ($user->status !== 'approved') {
            return response()->json([
                'message' => 'Only approved officers can be deactivated.',
            ], 422);
        }

        $user->update([
            'is_active' => false,
            'deactivation_reason' => $validated['reason'] ?? null,
            'deactivated_at' => now(),
        ]);

        Mail::to($user->email)->send(new OfficerDeactivationMail(
            name: $user->name,
            reason: $validated['reason'] ?? null,
        ));

        ActivityLog::log(
            'personnel_deactivated',
            "Deactivated personnel: {$user->name} ({$user->email})",
            $request->user()?->id,
            $request->ip(),
            ['user_id' => $user->id, 'reason' => $validated['reason'] ?? null]
        );

        return response()->json([
            'message' => 'Officer account deactivated.',
            'user' => $user->only(['id', 'name', 'email', 'status', 'is_active']),
        ]);
    }

    /**
     * Reactivate a previously deactivated officer (admin only).
     */
    public function activateOfficer(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'remarks' => ['nullable', 'string', 'max:1000'],
        ]);

        $user = User::where('role', 'officer')->find($id);

        if (! $user) {
            throw ValidationException::withMessages([
                'id' => ['User not found.'],
            ]);
        }

        if ($user->status !== 'approved') {
            return response()->json([
                'message' => 'Only approved officers can be activated.',
            ], 422);
        }

        $user->update([
            'is_active' => true,
            'deactivation_reason' => null,
            'deactivated_at' => null,
            'activated_at' => now(),
            'activation_remarks' => $validated['remarks'] ?? null,
        ]);

        ActivityLog::log(
            'personnel_activated',
            "Activated personnel: {$user->name} ({$user->email})",
            $request->user()?->id,
            $request->ip(),
            ['user_id' => $user->id]
        );

        return response()->json([
            'message' => 'Officer account activated.',
            'user' => $user->only(['id', 'name', 'email', 'status', 'is_active']),
        ]);
    }

    /**
     * List officers with status = pending (admin only).
     */
    public function pendingUsers(Request $request): JsonResponse
    {
        $users = User::where('role', 'officer')
            ->where('status', 'pending')
            ->whereNotNull('email_verified_at')
            ->orderBy('created_at', 'desc')
            ->get([
                'id',
                'name',
                'email',
                'employee_id',
                'position',
                'division',
                'school_name',
                'status',
                'avatar_url',
                'profile_avatar_url',
                'created_at',
                'updated_at',
            ]);

        return response()->json([
            'users' => $users->map(function (User $u) {
                $row = $u->only([
                    'id', 'name', 'email', 'employee_id', 'position', 'division', 'school_name',
                    'status', 'avatar_url', 'profile_avatar_url', 'created_at',
                ]);
                $row['avatar_url'] = UserPublicMedia::avatarUrlForClient($u);
                $row['profile_avatar_url'] = UserPublicMedia::avatarUrlForClient($u);

                return $row;
            }),
        ]);
    }

    /**
     * Approve a pending or previously rejected user (admin only).
     */
    public function approve(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'remarks' => ['nullable', 'string', 'max:1000'],
        ]);

        $user = User::where('role', 'officer')->find($id);

        if (! $user) {
            throw ValidationException::withMessages([
                'id' => ['User not found.'],
            ]);
        }

        if (! in_array($user->status, ['pending', 'rejected'], true)) {
            return response()->json([
                'message' => 'User cannot be approved in the current status.',
            ], 422);
        }

        $user->update([
            'status' => 'approved',
            'is_active' => true,
            'rejection_reason' => null,
            'approval_remarks' => $validated['remarks'] ?? null,
            'deactivation_reason' => null,
            'approved_at' => now(),
            'rejected_at' => null,
        ]);

        Mail::to($user->email)->send(new OfficerApprovalMail(
            name: $user->name,
            remarks: $validated['remarks'] ?? null,
        ));

        ActivityLog::log(
            'personnel_approved',
            "Approved personnel: {$user->name} ({$user->email})",
            $request->user()?->id,
            $request->ip(),
            ['user_id' => $user->id]
        );

        return response()->json([
            'message' => 'User approved successfully.',
            'user' => $user->only(['id', 'name', 'email', 'status']),
        ]);
    }

    /**
     * Reject a pending user with optional reason (admin only).
     */
    public function reject(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $user = User::where('role', 'officer')->find($id);

        if (! $user) {
            throw ValidationException::withMessages([
                'id' => ['User not found.'],
            ]);
        }

        if ($user->status !== 'pending') {
            return response()->json([
                'message' => 'User is not pending approval.',
            ], 422);
        }

        $user->update([
            'status' => 'rejected',
            'rejection_reason' => $validated['reason'] ?? null,
            'rejected_at' => now(),
            'approved_at' => null,
        ]);

        Mail::to($user->email)->send(new OfficerRejectionMail(
            name: $user->name,
            reason: $validated['reason'] ?? null,
        ));

        ActivityLog::log(
            'personnel_rejected',
            "Rejected personnel: {$user->name} ({$user->email})",
            $request->user()?->id,
            $request->ip(),
            ['user_id' => $user->id, 'reason' => $validated['reason'] ?? null]
        );

        return response()->json([
            'message' => 'User rejected.',
            'user' => $user->only(['id', 'name', 'email', 'status']),
        ]);
    }

    /**
     * Soft-delete a rejected officer with no activity (admin only).
     * Safeguards: only rejected status, no tasks assigned to or created by this user.
     */
    public function deleteOfficer(Request $request, int $id): JsonResponse
    {
        $user = User::where('role', 'officer')->find($id);

        if (! $user) {
            throw ValidationException::withMessages([
                'id' => ['Personnel not found.'],
            ]);
        }

        // Check for tasks/activity - personnel with activity cannot be deleted
        $hasAssignedTasks = Task::where('assigned_to', $user->id)->exists();
        $hasCreatedTasks = Task::where('created_by', $user->id)->exists();

        if ($hasAssignedTasks || $hasCreatedTasks) {
            return response()->json([
                'message' => 'This personnel has activity in the system (tasks assigned or created) and cannot be removed. Please deactivate the account instead.',
            ], 422);
        }

        $name = $user->name;
        $email = $user->email;
        $user->delete();

        ActivityLog::log(
            'personnel_deleted',
            "Removed personnel from directory: {$name} ({$email})",
            $request->user()?->id,
            $request->ip(),
            ['user_id' => $id]
        );

        return response()->json([
            'message' => 'Personnel removed from directory.',
        ]);
    }
}
