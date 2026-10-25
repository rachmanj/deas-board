<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Task;
use App\Models\TaskUpdate;
use App\Support\ActivityLogger;
use App\Support\Actor;
use App\Support\ApiResponse;
use App\Support\TaskRules;
use Illuminate\Http\Request;

class TaskUpdateController extends Controller
{
    public function store(Request $request, Task $task)
    {
        $validated = $request->validate([
            'status' => ['required', TaskRules::statusRule()],
            'note' => ['sometimes', 'nullable', 'string'],
            'evidence' => ['sometimes', 'nullable', 'string'],
        ]);

        $actor = Actor::from($request);
        $fromStatus = $task->status;
        $toStatus = $validated['status'];

        $update = TaskUpdate::create([
            'task_id' => $task->id,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'note' => $validated['note'] ?? null,
            'evidence' => $validated['evidence'] ?? null,
            'actor_type' => Actor::morphType($actor),
            'actor_id' => $actor->getKey(),
        ]);

        if ($toStatus !== $fromStatus) {
            $task->status = $toStatus;
            if ($toStatus === 'done' && ! $task->done_at) {
                $task->done_at = now();
            }
            $task->save();

            TaskController::notifyAssigneeStatusChanged($task);
        }

        ActivityLogger::log('task.update_recorded', $actor, $task, [
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'update_id' => $update->id,
        ]);

        return ApiResponse::data([
            'id' => $update->id,
            'from_status' => $update->from_status,
            'to_status' => $update->to_status,
            'note' => $update->note,
            'evidence' => $update->evidence,
        ], 201);
    }
}
