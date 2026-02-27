<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Task;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class TaskController extends Controller
{
    /**
     * List tasks with optional filters.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Task::with(['creator:id,name', 'assignee:id,name'])
            ->orderBy('created_at', 'desc');

        $status = $request->input('status');
        if ($status && in_array($status, ['pending', 'in_progress', 'completed', 'cancelled'], true)) {
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
     * Create a new task.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'mfo' => ['nullable', 'string', 'max:255'],
            'kra' => ['nullable', 'string', 'max:255'],
            'kra_weight' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'objective' => ['nullable', 'string', 'max:2000'],
            'movs' => ['nullable', 'array'],
            'movs.*' => ['nullable', 'string', 'max:1000'],
            'due_date' => ['nullable', 'date'],
            'cutoff_date' => ['nullable', 'date'],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
            'status' => ['nullable', 'string', 'in:pending,in_progress,completed,cancelled'],
            'priority' => ['nullable', 'string', 'in:low,medium,high'],
            'timeline_start' => ['nullable', 'date'],
            'timeline_end' => ['nullable', 'date', 'after_or_equal:timeline_start'],
            'performance_criteria' => ['nullable', 'array'],
        ]);

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

        $validated = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'mfo' => ['nullable', 'string', 'max:255'],
            'kra' => ['nullable', 'string', 'max:255'],
            'kra_weight' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'objective' => ['nullable', 'string', 'max:2000'],
            'movs' => ['nullable', 'array'],
            'movs.*' => ['nullable', 'string', 'max:1000'],
            'due_date' => ['nullable', 'date'],
            'cutoff_date' => ['nullable', 'date'],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
            'status' => ['nullable', 'string', 'in:pending,in_progress,completed,cancelled'],
            'priority' => ['nullable', 'string', 'in:low,medium,high'],
            'timeline_start' => ['nullable', 'date'],
            'timeline_end' => ['nullable', 'date', 'after_or_equal:timeline_start'],
            'performance_criteria' => ['nullable', 'array'],
        ]);

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
}
