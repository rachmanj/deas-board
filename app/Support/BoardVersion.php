<?php

namespace App\Support;

use Illuminate\Http\Request;

class BoardVersion
{
    /**
     * @return array{hash: string, tasks: int, updated_at: string|null}
     */
    public static function forRequest(Request $request): array
    {
        $tasks = TaskFilterQuery::fromRequest($request)->get([
            'id',
            'status',
            'priority',
            'updated_at',
        ]);

        $rows = $tasks->sortBy('id')->map(fn ($task) => [
            'id' => $task->id,
            'status' => $task->status,
            'priority' => $task->priority,
            'updated_at' => $task->updated_at?->toIso8601String() ?? '',
            'comments_count' => (int) $task->comments_count,
        ])->values()->all();

        $hash = hash('sha256', json_encode($rows, JSON_THROW_ON_ERROR));

        $latest = $tasks->max('updated_at');

        return [
            'hash' => $hash,
            'tasks' => $tasks->count(),
            'updated_at' => $latest?->toIso8601String(),
        ];
    }
}
