<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Api\TaskController as ApiTaskController;
use App\Http\Controllers\Controller;
use App\Models\Task;
use App\Models\TaskUpdate;
use App\Support\TaskRules;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TaskStatusController extends Controller
{
    public function update(Request $request, Task $task)
    {
        $validated = $request->validate([
            'status' => ['required', TaskRules::statusRule()],
        ]);

        $oldStatus = $task->status;
        $newStatus = $validated['status'];

        if ($newStatus !== $oldStatus) {
            $task->status = $newStatus;
            if ($newStatus === 'done' && ! $task->done_at) {
                $task->done_at = now();
            }
            $task->save();

            TaskUpdate::create([
                'task_id' => $task->id,
                'from_status' => $oldStatus,
                'to_status' => $newStatus,
                'actor_type' => Auth::user()::class,
                'actor_id' => Auth::id(),
            ]);

            ApiTaskController::notifyAssigneeStatusChanged($task);
        }

        $redirect = $request->input('redirect', '/');
        $query = $request->except(['_token', '_method', 'status', 'redirect']);

        return redirect()->to($redirect.($query !== [] ? '?'.http_build_query($query) : ''));
    }
}
