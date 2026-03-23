<?php

namespace App\Services;

use App\Models\Task;
use Carbon\Carbon;

class AccomplishmentReportDataService
{
    /**
     * @return list<array{kra: string, kra_weight: mixed, tasks: list<array<string, mixed>>}>
     */
    public function tasksSummaryForOfficerMonth(int $officerUserId, int $year, int $month): array
    {
        $monthStart = Carbon::create($year, $month, 1)->startOfDay();
        $monthEnd = $monthStart->copy()->endOfMonth();

        $tasks = Task::query()
            ->select([
                'id', 'kra', 'kra_weight', 'title', 'description', 'mfo', 'objective', 'movs',
                'due_date', 'updated_at', 'created_at',
            ])
            ->where('created_by', $officerUserId)
            ->where('status', 'completed')
            ->whereBetween('updated_at', [$monthStart, $monthEnd])
            ->orderBy('kra')
            ->orderBy('created_at')
            ->get();

        $tasksByKra = [];
        foreach ($tasks as $task) {
            $kra = $task->kra ?: 'Uncategorized';
            if (! isset($tasksByKra[$kra])) {
                $tasksByKra[$kra] = [
                    'kra' => $kra,
                    'kra_weight' => $task->kra_weight ?? 0,
                    'tasks' => [],
                ];
            }
            $tasksByKra[$kra]['tasks'][] = [
                'id' => $task->id,
                'title' => $task->title,
                'description' => $task->description,
                'mfo' => $task->mfo,
                'objective' => $task->objective,
                'movs' => $task->movs ?? [],
                'due_date' => $task->due_date?->format('Y-m-d'),
                'completed_at' => $task->updated_at?->format('Y-m-d H:i:s'),
            ];
        }

        return array_values($tasksByKra);
    }
}
