<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\OfficerApprovalMail;
use App\Mail\OfficerDeactivationMail;
use App\Mail\OfficerRejectionMail;
use App\Models\Task;
use App\Models\User;
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
                'deactivation_reason',
                'rejection_reason',
                'created_at',
            ]);

        $officers->each->makeVisible(['rejection_reason', 'deactivation_reason']);

        return response()->json([
            'officers' => $officers,
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
        ]);

        Mail::to($user->email)->send(new OfficerDeactivationMail(
            name: $user->name,
            reason: $validated['reason'] ?? null,
        ));

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
        ]);

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
                'created_at',
            ]);

        return response()->json([
            'users' => $users,
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
        ]);

        Mail::to($user->email)->send(new OfficerApprovalMail(
            name: $user->name,
            remarks: $validated['remarks'] ?? null,
        ));

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
        ]);

        Mail::to($user->email)->send(new OfficerRejectionMail(
            name: $user->name,
            reason: $validated['reason'] ?? null,
        ));

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

        if ($user->status !== 'rejected') {
            return response()->json([
                'message' => 'Only rejected personnel with no activity can be removed.',
            ], 422);
        }

        $hasAssignedTasks = Task::where('assigned_to', $user->id)->exists();
        $hasCreatedTasks = Task::where('created_by', $user->id)->exists();

        if ($hasAssignedTasks || $hasCreatedTasks) {
            return response()->json([
                'message' => 'This personnel has activity in the system (tasks assigned or created) and cannot be removed.',
            ], 422);
        }

        $user->delete();

        return response()->json([
            'message' => 'Personnel removed from directory.',
        ]);
    }
}
