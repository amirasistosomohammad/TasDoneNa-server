<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Task;
use App\Models\TaskFile;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class TaskController extends Controller
{
    /**
     * Scope: tasks the officer can access (created by them or assigned to them).
     */
    private function officerAccessibleTask(Request $request): \Illuminate\Database\Eloquent\Builder
    {
        $userId = $request->user()->id;
        return Task::query()->where(function ($q) use ($userId) {
            $q->where('created_by', $userId)->orWhere('assigned_to', $userId);
        });
    }

    /**
     * List own tasks for officer (personnel). Phase 2.1.
     * Includes tasks created by officer or assigned to them by admin.
     */
    public function officerIndex(Request $request): JsonResponse
    {
        $query = $this->officerAccessibleTask($request)
            ->with(['creator:id,name'])
            ->orderBy('created_at', 'desc');

        $status = $request->input('status');
        if ($status && in_array($status, ['pending', 'completed', 'cancelled'], true)) {
            $query->where('status', $status);
        }

        $kra = trim((string) $request->input('kra', ''));
        if ($kra !== '') {
            $query->where('kra', 'like', "%{$kra}%");
        }

        $search = trim((string) $request->input('q', ''));
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('objective', 'like', "%{$search}%")
                    ->orWhere('mfo', 'like', "%{$search}%")
                    ->orWhere('kra', 'like', "%{$search}%");
            });
        }

        $tasks = $query->get();

        return response()->json(['tasks' => $tasks]);
    }

    /**
     * Distinct non-empty KRA strings from tasks the officer can access (for combobox suggestions).
     */
    public function officerKraValues(Request $request): JsonResponse
    {
        $values = $this->officerAccessibleTask($request)
            ->whereNotNull('kra')
            ->where('kra', '!=', '')
            ->distinct()
            ->orderBy('kra')
            ->pluck('kra')
            ->values();

        return response()->json(['kra_values' => $values]);
    }

    /**
     * Create task for self (officer only). Phase 2.1.
     */
    public function officerStore(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'mfo' => ['nullable', 'string', 'max:255'],
            'kra' => ['required', 'string', 'max:255', 'regex:/\S/u'],
            'kra_weight' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'objective' => ['nullable', 'string', 'max:2000'],
            'movs' => ['nullable', 'array'],
            'movs.*' => ['nullable', 'string', 'max:1000'],
            'due_date' => ['nullable', 'date'],
            'cutoff_date' => ['nullable', 'date', 'before_or_equal:due_date'],
            'status' => ['nullable', 'string', 'in:pending,completed,cancelled'],
            'priority' => ['nullable', 'string', 'in:low,medium,high'],
            'timeline_start' => ['nullable', 'date'],
            'timeline_end' => ['nullable', 'date', 'after_or_equal:timeline_start'],
            'performance_criteria' => ['nullable', 'array'],
        ]);

        $validated['kra'] = trim($validated['kra']);

        $validated['created_by'] = $user->id;
        $validated['assigned_to'] = $user->id; // Task for self
        $validated['status'] = $validated['status'] ?? 'pending';
        $validated['priority'] = $validated['priority'] ?? 'medium';

        $task = Task::create($validated);

        $task->load(['creator:id,name', 'assignee:id,name']);

        return response()->json([
            'message' => 'Task created successfully.',
            'task' => $task,
        ], 201);
    }

    /**
     * Get single task (officer only, own task). Phase 2.2.
     */
    public function officerShow(Request $request, int $id): JsonResponse
    {
        $userId = $request->user()->id;
        $task = Task::with(['creator:id,name', 'assignee:id,name'])
            ->where(function ($q) use ($userId) {
                $q->where('created_by', $userId)->orWhere('assigned_to', $userId);
            })
            ->find($id);

        if (! $task) {
            return response()->json(['message' => 'Task not found.'], 404);
        }

        return response()->json(['task' => $task]);
    }

    /**
     * Update task (officer only, own task). Phase 2.2.
     */
    public function officerUpdate(Request $request, int $id): JsonResponse
    {
        $userId = $request->user()->id;
        $task = Task::where(function ($q) use ($userId) {
            $q->where('created_by', $userId)->orWhere('assigned_to', $userId);
        })->find($id);

        if (! $task) {
            return response()->json(['message' => 'Task not found.'], 404);
        }

        // KRA is required when saving the full task form (includes title). Status-only updates omit title.
        $kraRule = $request->has('title')
            ? ['required', 'string', 'max:255', 'regex:/\S/u']
            : ['sometimes', 'nullable', 'string', 'max:255'];

        $validated = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'mfo' => ['nullable', 'string', 'max:255'],
            'kra' => $kraRule,
            'kra_weight' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'objective' => ['nullable', 'string', 'max:2000'],
            'movs' => ['nullable', 'array'],
            'movs.*' => ['nullable', 'string', 'max:1000'],
            'due_date' => ['nullable', 'date'],
            'cutoff_date' => ['nullable', 'date', 'before_or_equal:due_date'],
            'status' => ['nullable', 'string', 'in:pending,completed,cancelled'],
            'priority' => ['nullable', 'string', 'in:low,medium,high'],
            'timeline_start' => ['nullable', 'date'],
            'timeline_end' => ['nullable', 'date', 'after_or_equal:timeline_start'],
            'performance_criteria' => ['nullable', 'array'],
        ]);

        if (array_key_exists('kra', $validated) && is_string($validated['kra'])) {
            $validated['kra'] = trim($validated['kra']);
        }

        $task->update($validated);

        $task->load(['creator:id,name', 'assignee:id,name']);

        return response()->json([
            'message' => 'Task updated successfully.',
            'task' => $task,
        ]);
    }

    /**
     * Delete task (officer only, own task). Phase 2.2.
     */
    public function officerDestroy(Request $request, int $id): JsonResponse
    {
        $task = Task::where('created_by', $request->user()->id)->find($id);

        if (! $task) {
            return response()->json(['message' => 'Task not found.'], 404);
        }

        $task->delete();

        return response()->json(['message' => 'Task deleted successfully.']);
    }

    /**
     * List files (MOVs) for a task (officer only). Phase 2.8.
     * Includes tasks created by officer or assigned to them.
     */
    public function officerTaskFiles(Request $request, int $id): JsonResponse
    {
        $task = $this->officerAccessibleTask($request)->find($id);

        if (! $task) {
            return response()->json(['message' => 'Task not found.'], 404);
        }

        $files = $task->files()->orderBy('uploaded_at', 'desc')->get();

        return response()->json(['files' => $files]);
    }

    /**
     * Upload file (MOV) for a task (officer only, own task). Phase 2.8.
     */
    public function officerUploadFile(Request $request, int $id): JsonResponse
    {
        $userId = $request->user()->id;
        $task = Task::where(function ($q) use ($userId) {
            $q->where('created_by', $userId)->orWhere('assigned_to', $userId);
        })->find($id);

        if (! $task) {
            return response()->json(['message' => 'Task not found.'], 404);
        }

        try {
            $request->validate([
                'file' => ['required', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx'],
            ]);
        } catch (ValidationException $e) {
            $errors = $e->errors();
            $message = 'File validation failed. ';
            if (isset($errors['file'])) {
                $message .= implode(' ', $errors['file']);
            } else {
                $message .= 'Please check file size (max 10MB) and type (PDF, images, DOC, XLS).';
            }
            return response()->json(['message' => $message], 422);
        }

        $uploaded = $request->file('file');
        
        // Additional validation
        if (!$uploaded->isValid()) {
            return response()->json(['message' => 'Invalid file upload. Please try again.'], 422);
        }

        try {
            $path = $uploaded->store('task-files/'.$task->id, 'local');
        } catch (\Exception $e) {
            \Log::error('File storage failed: ' . $e->getMessage());
            return response()->json(['message' => 'Failed to store file. Server storage may be full. Please contact administrator.'], 500);
        }

        $file = TaskFile::create([
            'task_id' => $task->id,
            'user_id' => $request->user()->id,
            'file_name' => $uploaded->getClientOriginalName(),
            'file_path' => $path,
            'file_type' => $uploaded->getMimeType(),
            'file_size' => $uploaded->getSize(),
        ]);

        return response()->json([
            'message' => 'File uploaded successfully.',
            'file' => $file,
        ], 201);
    }

    /**
     * Delete uploaded file (MOV) for a task (officer only, own task).
     */
    public function officerDeleteFile(Request $request, int $id, int $fileId): JsonResponse
    {
        $userId = $request->user()->id;
        $task = Task::where(function ($q) use ($userId) {
            $q->where('created_by', $userId)->orWhere('assigned_to', $userId);
        })->find($id);

        if (! $task) {
            return response()->json(['message' => 'Task not found.'], 404);
        }

        $file = TaskFile::where('task_id', $task->id)
            ->where('id', $fileId)
            ->where('user_id', $request->user()->id)
            ->first();

        if (! $file) {
            return response()->json(['message' => 'File not found.'], 404);
        }

        // Delete file from storage
        if (Storage::disk('local')->exists($file->file_path)) {
            Storage::disk('local')->delete($file->file_path);
        }

        // Delete record
        $file->delete();

        return response()->json(['message' => 'File deleted successfully.']);
    }

    /**
     * Download uploaded file (MOV) for a task (officer only, own task).
     */
    public function officerDownloadFile(Request $request, int $id, int $fileId)
    {
        $userId = $request->user()->id;
        $task = Task::where(function ($q) use ($userId) {
            $q->where('created_by', $userId)->orWhere('assigned_to', $userId);
        })->find($id);

        if (! $task) {
            return response()->json(['message' => 'Task not found.'], 404);
        }

        $file = TaskFile::where('task_id', $task->id)
            ->where('id', $fileId)
            ->where('user_id', $request->user()->id)
            ->first();

        if (! $file) {
            return response()->json(['message' => 'File not found.'], 404);
        }

        if (! Storage::disk('local')->exists($file->file_path)) {
            return response()->json(['message' => 'File not found on server.'], 404);
        }

        return Storage::disk('local')->download($file->file_path, $file->file_name);
    }

    /**
     * List tasks with optional filters. Admin only.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Task::with(['creator:id,name', 'assignee:id,name'])
            ->orderBy('created_at', 'desc');

        $status = $request->input('status');
        if ($status && in_array($status, ['pending', 'completed', 'cancelled'], true)) {
            $query->where('status', $status);
        }

        $kra = trim((string) $request->input('kra', ''));
        if ($kra !== '') {
            $query->where('kra', 'like', "%{$kra}%");
        }

        $search = trim((string) $request->input('q', ''));
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('objective', 'like', "%{$search}%")
                    ->orWhere('mfo', 'like', "%{$search}%")
                    ->orWhere('kra', 'like', "%{$search}%");
            });
        }

        $tasks = $query->get();

        return response()->json(['tasks' => $tasks]);
    }

    /**
     * Distinct non-empty KRA strings from all tasks (admin combobox suggestions).
     */
    public function adminKraValues(): JsonResponse
    {
        $values = Task::query()
            ->whereNotNull('kra')
            ->where('kra', '!=', '')
            ->distinct()
            ->orderBy('kra')
            ->pluck('kra')
            ->values();

        return response()->json(['kra_values' => $values]);
    }

    /**
     * Get a single task by ID.
     */
    public function show(int $id): JsonResponse
    {
        $task = Task::with(['creator:id,name,email', 'assignee:id,name,email'])
            ->find($id);

        if (! $task) {
            return response()->json(['message' => 'Task not found.'], 404);
        }

        return response()->json(['task' => $task]);
    }

    /**
     * List files uploaded for a task (admin only).
     * GET /admin/tasks/{id}/files?user_id={userId}
     * When user_id is provided, filter by that officer. When omitted, return all files for the task.
     */
    public function adminTaskFiles(Request $request, int $id): JsonResponse
    {
        $task = Task::find($id);

        if (! $task) {
            return response()->json(['message' => 'Task not found.'], 404);
        }

        $query = TaskFile::where('task_id', $task->id)
            ->whereNull('archived_at')
            ->orderBy('uploaded_at', 'desc');

        $userId = $request->query('user_id');
        if ($userId) {
            $query->where('user_id', $userId);
        }

        $files = $query->get();

        return response()->json(['files' => $files]);
    }

    /**
     * Download a file from a task (admin only).
     */
    public function adminDownloadFile(Request $request, int $id, int $fileId)
    {
        $task = Task::find($id);

        if (! $task) {
            return response()->json(['message' => 'Task not found.'], 404);
        }

        $file = TaskFile::where('task_id', $task->id)
            ->where('id', $fileId)
            ->whereNull('archived_at')
            ->first();

        if (! $file) {
            return response()->json(['message' => 'File not found.'], 404);
        }

        if (! Storage::disk('local')->exists($file->file_path)) {
            return response()->json(['message' => 'File not found on server.'], 404);
        }

        return Storage::disk('local')->download($file->file_path, $file->file_name);
    }

    /**
     * Create a new task.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'mfo' => ['nullable', 'string', 'max:255'],
            'kra' => ['required', 'string', 'max:255', 'regex:/\S/u'],
            'kra_weight' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'objective' => ['nullable', 'string', 'max:2000'],
            'movs' => ['nullable', 'array'],
            'movs.*' => ['nullable', 'string', 'max:1000'],
            'due_date' => ['nullable', 'date'],
            'cutoff_date' => ['nullable', 'date'],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
            'status' => ['nullable', 'string', 'in:pending,completed,cancelled'],
            'priority' => ['nullable', 'string', 'in:low,medium,high'],
            'timeline_start' => ['nullable', 'date'],
            'timeline_end' => ['nullable', 'date', 'after_or_equal:timeline_start'],
            'performance_criteria' => ['nullable', 'array'],
        ]);

        $validated['kra'] = trim($validated['kra']);

        $validated['created_by'] = $request->user()->id;
        $validated['status'] = $validated['status'] ?? 'pending';
        $validated['priority'] = $validated['priority'] ?? 'medium';

        $task = Task::create($validated);

        $task->load(['creator:id,name', 'assignee:id,name']);

        return response()->json([
            'message' => 'Task created successfully.',
            'task' => $task,
        ], 201);
    }

    /**
     * Update an existing task.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $task = Task::find($id);

        if (! $task) {
            return response()->json(['message' => 'Task not found.'], 404);
        }

        $kraRule = $request->has('title')
            ? ['required', 'string', 'max:255', 'regex:/\S/u']
            : ['sometimes', 'nullable', 'string', 'max:255'];

        $validated = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'mfo' => ['nullable', 'string', 'max:255'],
            'kra' => $kraRule,
            'kra_weight' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'objective' => ['nullable', 'string', 'max:2000'],
            'movs' => ['nullable', 'array'],
            'movs.*' => ['nullable', 'string', 'max:1000'],
            'due_date' => ['nullable', 'date'],
            'cutoff_date' => ['nullable', 'date'],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
            'status' => ['nullable', 'string', 'in:pending,completed,cancelled'],
            'priority' => ['nullable', 'string', 'in:low,medium,high'],
            'timeline_start' => ['nullable', 'date'],
            'timeline_end' => ['nullable', 'date', 'after_or_equal:timeline_start'],
            'performance_criteria' => ['nullable', 'array'],
        ]);

        if (array_key_exists('kra', $validated) && is_string($validated['kra'])) {
            $validated['kra'] = trim($validated['kra']);
        }

        $task->update($validated);

        $task->load(['creator:id,name', 'assignee:id,name']);

        return response()->json([
            'message' => 'Task updated successfully.',
            'task' => $task,
        ]);
    }

    /**
     * Delete a task.
     */
    public function destroy(int $id): JsonResponse
    {
        $task = Task::find($id);

        if (! $task) {
            return response()->json(['message' => 'Task not found.'], 404);
        }

        $task->delete();

        return response()->json(['message' => 'Task deleted successfully.']);
    }

    /**
     * Get officers list for assignment dropdown (admin only).
     */
    public function officers(): JsonResponse
    {
        $officers = User::where('role', 'officer')
            ->where('status', 'approved')
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        return response()->json(['officers' => $officers]);
    }

    /**
     * Get task statistics for officer dashboard.
     */
    public function officerStatistics(Request $request): JsonResponse
    {
        $userId = $request->user()->id;
        $now = now();
        $startOfWeek = $now->copy()->startOfWeek();
        $startOfMonth = $now->copy()->startOfMonth();

        $totalTasks = Task::where('created_by', $userId)->count();
        $pending = Task::where('created_by', $userId)->where('status', 'pending')->count();
        $completed = Task::where('created_by', $userId)->where('status', 'completed')->count();

        // Due this week
        $dueThisWeek = Task::where('created_by', $userId)
            ->where('status', '!=', 'completed')
            ->whereBetween('due_date', [$startOfWeek->format('Y-m-d'), $now->copy()->endOfWeek()->format('Y-m-d')])
            ->count();

        // Overdue (due date passed and not completed)
        $overdue = Task::where('created_by', $userId)
            ->where('status', '!=', 'completed')
            ->where('due_date', '<', $now->format('Y-m-d'))
            ->count();

        // Completed this month
        $completedThisMonth = Task::where('created_by', $userId)
            ->where('status', 'completed')
            ->whereBetween(DB::raw('DATE(updated_at)'), [$startOfMonth->format('Y-m-d'), $now->format('Y-m-d')])
            ->count();

        return response()->json([
            'total_tasks' => $totalTasks,
            'pending' => $pending,
            'completed' => $completed,
            'due_this_week' => $dueThisWeek,
            'overdue' => $overdue,
            'completed_this_month' => $completedThisMonth,
            'completion_rate' => $totalTasks > 0 ? round(($completed / $totalTasks) * 100, 1) : 0,
        ]);
    }

    /**
     * Get task statistics for admin dashboard.
     */
    public function adminStatistics(Request $request): JsonResponse
    {
        $now = now();
        $startOfWeek = $now->copy()->startOfWeek();

        $totalTasks = Task::count();
        $pending = Task::where('status', 'pending')->count();
        $completed = Task::where('status', 'completed')->count();
        $cancelled = Task::where('status', 'cancelled')->count();

        $dueThisWeek = Task::where('status', '!=', 'completed')
            ->whereBetween('due_date', [$startOfWeek->format('Y-m-d'), $now->copy()->endOfWeek()->format('Y-m-d')])
            ->count();

        $overdue = Task::where('status', '!=', 'completed')
            ->where('due_date', '<', $now->format('Y-m-d'))
            ->count();

        return response()->json([
            'total_tasks' => $totalTasks,
            'pending' => $pending,
            'completed' => $completed,
            'cancelled' => $cancelled,
            'due_this_week' => $dueThisWeek,
            'overdue' => $overdue,
        ]);
    }
}
