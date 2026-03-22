<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TaskFile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class FilesArchiveController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        // Base scope: reuse for stats (SQL aggregates) + list query — avoids loading all rows twice.
        $base = TaskFile::query()
            ->join('tasks', 'tasks.id', '=', 'task_files.task_id')
            ->where(function ($q) use ($userId) {
                $q->where('task_files.user_id', $userId)
                    ->orWhere('tasks.created_by', $userId)
                    ->orWhere('tasks.assigned_to', $userId);
            });

        $stats = [
            'total' => (clone $base)->count(),
            'archived' => (clone $base)->whereNotNull('task_files.archived_at')->count(),
            'active' => (clone $base)->whereNull('task_files.archived_at')->count(),
            'total_size' => (int) ((clone $base)->sum('task_files.file_size') ?? 0),
        ];

        $files = (clone $base)
            ->select([
                'task_files.*',
                'tasks.title as task_title',
                'tasks.status as task_status',
                'tasks.due_date as task_due_date',
                'tasks.created_by as task_created_by',
                'tasks.assigned_to as task_assigned_to',
            ])
            ->orderByDesc('task_files.uploaded_at')
            ->orderByDesc('task_files.id')
            ->get();

        return response()->json([
            'files' => $files,
            'stats' => $stats,
        ]);
    }

    public function archive(Request $request, int $fileId): JsonResponse
    {
        $file = $this->findOfficerFile($request, $fileId);
        if (! $file) {
            return response()->json(['message' => 'File not found.'], 404);
        }
        if ($file->archived_at) {
            return response()->json(['message' => 'File is already archived.'], 200);
        }

        $file->archived_at = now();
        $file->save();

        return response()->json([
            'message' => 'File archived successfully.',
            'file' => $file,
        ]);
    }

    public function restore(Request $request, int $fileId): JsonResponse
    {
        $file = $this->findOfficerFile($request, $fileId);
        if (! $file) {
            return response()->json(['message' => 'File not found.'], 404);
        }
        if (! $file->archived_at) {
            return response()->json(['message' => 'File is not archived.'], 200);
        }

        $file->archived_at = null;
        $file->save();

        return response()->json([
            'message' => 'File restored successfully.',
            'file' => $file,
        ]);
    }

    public function destroy(Request $request, int $fileId): JsonResponse
    {
        $file = $this->findOfficerFile($request, $fileId);
        if (! $file) {
            return response()->json(['message' => 'File not found.'], 404);
        }

        if (Storage::disk('local')->exists($file->file_path)) {
            Storage::disk('local')->delete($file->file_path);
        }
        $file->delete();

        return response()->json(['message' => 'File deleted successfully.']);
    }

    public function download(Request $request, int $fileId)
    {
        $file = $this->findOfficerFile($request, $fileId);
        if (! $file) {
            return response()->json(['message' => 'File not found.'], 404);
        }

        if (! Storage::disk('local')->exists($file->file_path)) {
            return response()->json(['message' => 'File not found on server.'], 404);
        }

        return Storage::disk('local')->download($file->file_path, $file->file_name);
    }

    private function findOfficerFile(Request $request, int $fileId): ?TaskFile
    {
        $userId = $request->user()->id;

        $file = TaskFile::query()
            ->join('tasks', 'tasks.id', '=', 'task_files.task_id')
            ->where(function ($q) use ($userId) {
                $q->where('task_files.user_id', $userId)
                    ->orWhere('tasks.created_by', $userId)
                    ->orWhere('tasks.assigned_to', $userId);
            })
            ->where('task_files.id', $fileId)
            ->select('task_files.*')
            ->first();

        return $file;
    }
}

