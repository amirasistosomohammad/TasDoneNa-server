<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Task API for personnel (officers).
 * Personnel create and manage their own tasks.
 */
class OfficerTaskController extends Controller
{
    /**
     * List tasks created by the current officer (own tasks only).
     */
    public function index(Request $request): JsonResponse
    {
        $userId = $request->user()->id;
        $query = Task::where('created_by', $userId)
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
     * Get a single task by ID (own tasks only).
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $userId = $request->user()->id;
        $task = Task::where('created_by', $userId)->find($id);

        if (! $task) {
            return response()->json(['message' => 'Task not found.'], 404);
        }

        return response()->json(['task' => $task]);
    }

    /**
     * Create a new task (for self).
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
            'status' => ['nullable', 'string', 'in:pending,completed,cancelled'],
            'priority' => ['nullable', 'string', 'in:low,medium,high'],
            'timeline_start' => ['nullable', 'date'],
            'timeline_end' => ['nullable', 'date', 'after_or_equal:timeline_start'],
            'performance_criteria' => ['nullable', 'array'],
        ]);

        $validated['kra'] = trim($validated['kra']);

        $validated['created_by'] = $request->user()->id;
        $validated['assigned_to'] = $request->user()->id;
        $validated['status'] = $validated['status'] ?? 'pending';
        $validated['priority'] = $validated['priority'] ?? 'medium';

        $task = Task::create($validated);

        return response()->json([
            'message' => 'Task created successfully.',
            'task' => $task,
        ], 201);
    }
}
