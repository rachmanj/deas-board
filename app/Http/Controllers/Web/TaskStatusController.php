<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Api\TaskController as ApiTaskController;
use App\Http\Controllers\Controller;
use App\Models\Task;
use App\Models\TaskUpdate;
use App\Support\Actor;
use App\Support\EvidenceRule;
use App\Support\TaskDoneAt;
use App\Support\TaskRules;
use App\Support\TextNormalizer;
use Illuminate\Http\Request;

class TaskStatusController extends Controller
{
    public function update(Request $request, Task $task)
    {
        $validated = $request->validate([
            'status' => ['required', TaskRules::statusRule()],
            'evidence' => ['sometimes', 'nullable', 'string'],
        ]);

        $validated = TextNormalizer::cleanFields($validated, ['evidence']);

        $oldStatus = $task->status;
        $newStatus = $validated['status'];
        $requestEvidence = $validated['evidence'] ?? null;
        $actor = Actor::from($request);

        if ($newStatus !== $oldStatus) {
            if ($newStatus === 'done' && ! EvidenceRule::allowsDoneTransition($task, $requestEvidence)) {
                EvidenceRule::rejectDone($task, $actor, $request);
            }
            if ($newStatus === 'review' && ! EvidenceRule::allowsReviewTransition($requestEvidence)) {
                EvidenceRule::rejectReview($task, $actor, $request);
            }

            $task->status = $newStatus;
            TaskDoneAt::sync($task, $oldStatus, $newStatus);
            $task->save();

            TaskUpdate::create([
                'task_id' => $task->id,
                'from_status' => $oldStatus,
                'to_status' => $newStatus,
                'evidence' => $requestEvidence,
                'actor_type' => Actor::morphType($actor),
                'actor_id' => $actor->getKey(),
            ]);

            ApiTaskController::notifyAssigneeStatusChanged($task);
        }

        $redirect = $request->input('redirect', '/');
        $query = $request->except(['_token', '_method', 'status', 'redirect']);

        return redirect()->to($redirect.($query !== [] ? '?'.http_build_query($query) : ''));
    }
}
