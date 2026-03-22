<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Task;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;

class MonitorOfficersController extends Controller
{
    /**
     * GET /api/admin/monitor-officers
     * Returns officers with task progress (pending, missing/overdue, completed).
     */
    public function index(): JsonResponse
    {
        $today = Carbon::today();

        $officers = User::where('role', 'officer')
            ->where('status', 'approved')
            ->where('is_active', true)
            ->orderBy('name')
            ->get([
                'id',
                'name',
                'email',
                'employee_id',
                'position',
                'division',
                'school_name',
                'avatar_url',
                'profile_avatar_url',
            ]);

        $tasksByOfficer = Task::whereIn('assigned_to', $officers->pluck('id'))
            ->whereIn('status', ['pending', 'completed'])
            ->get()
            ->groupBy('assigned_to');

        $schools = $officers
            ->pluck('school_name')
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();

        $result = [];
        foreach ($officers as $officer) {
            $officerTasks = $tasksByOfficer->get($officer->id, collect());

            $pending = $officerTasks->filter(function ($t) use ($today) {
                return $t->status === 'pending' && (!$t->due_date || $t->due_date->gte($today));
            })->values()->all();

            $missing = $officerTasks->filter(function ($t) use ($today) {
                return $t->status === 'pending' && $t->due_date && $t->due_date->lt($today);
            })->values()->all();

            $completed = $officerTasks->filter(fn ($t) => $t->status === 'completed')->values()->all();

            $result[] = [
                'id' => $officer->id,
                'name' => $officer->name,
                'email' => $officer->email,
                'employee_id' => $officer->employee_id,
                'position' => $officer->position,
                'division' => $officer->division,
                'school_name' => $officer->school_name,
                'avatar_url' => $officer->avatar_url,
                'profile_avatar_url' => $officer->profile_avatar_url,
                'pending_count' => count($pending),
                'missing_count' => count($missing),
                'completed_count' => count($completed),
                'pending' => $this->formatTaskList($pending),
                'missing' => $this->formatTaskList($missing),
                'completed' => $this->formatTaskList($completed),
            ];
        }

        return response()->json([
            'officers' => $result,
            'schools' => $schools,
        ]);
    }

    private function formatTaskList($tasks): array
    {
        return array_map(function ($t) {
            return [
                'id' => $t->id,
                'task' => [
                    'id' => $t->id,
                    'title' => $t->title,
                    'description' => $t->description,
                    'due_date' => $t->due_date?->format('Y-m-d'),
                    'cutoff_date' => $t->cutoff_date?->format('Y-m-d'),
                    'status' => $t->status,
                    'kra' => $t->kra,
                    'mfo' => $t->mfo,
                    'assigned_to' => $t->assigned_to,
                ],
                'due_date' => $t->due_date?->format('Y-m-d'),
            ];
        }, $tasks);
    }
}
