<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Api\TaskController as ApiTaskController;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\PreparesTaskViews;
use App\Models\ActivityLog;
use App\Models\Label;
use App\Models\Session;
use App\Models\Task;
use App\Models\TaskComment;
use App\Models\TaskUpdate;
use App\Support\ActivityLogger;
use App\Support\Actor;
use App\Support\CommentFormNonce;
use App\Support\TaskRules;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TaskController extends Controller
{
    use PreparesTaskViews;

    public function create()
    {
        return view('tasks.create', $this->formViewData(null));
    }

    public function store(Request $request)
    {
        $task = $this->persistTask($request, null);

        return redirect()->route('tasks.show', $task);
    }

    public function show(Task $task)
    {
        $task->load(['assigneeSession', 'project', 'labels']);

        $statusLabels = $this->statusLabels();
        $priorityLabels = $this->priorityLabels();

        $updates = $task->updates()
            ->orderByDesc('id')
            ->get();

        $commentIds = $task->comments()->withTrashed()->pluck('id');

        $activityLogs = ActivityLog::query()
            ->where(function ($query) use ($task, $commentIds) {
                $query->where(function ($q) use ($task) {
                    $q->where('subject_type', Task::class)
                        ->where('subject_id', $task->id);
                });
                if ($commentIds->isNotEmpty()) {
                    $query->orWhere(function ($q) use ($commentIds) {
                        $q->where('subject_type', TaskComment::class)
                            ->whereIn('subject_id', $commentIds);
                    });
                }
            })
            ->orderByDesc('id')
            ->get();

        $allComments = $task->comments()
            ->withTrashed()
            ->orderByDesc('id')
            ->get();

        $topLevelComments = $allComments->whereNull('parent_id')->values();

        $activeTab = request()->query('tab', 'diskusi');
        if (! in_array($activeTab, ['diskusi', 'status', 'perubahan'], true)) {
            $activeTab = 'diskusi';
        }

        return view('tasks.show', [
            'task' => $task,
            'statusLabels' => $statusLabels,
            'priorityLabels' => $priorityLabels,
            'updates' => $updates,
            'activityLogs' => $activityLogs,
            'topLevelComments' => $topLevelComments,
            'allComments' => $allComments,
            'activeTab' => $activeTab,
            'commentFormNonce' => CommentFormNonce::issue(),
            'pageTitle' => $task->code.' · '.$task->title,
        ]);
    }

    public function edit(Task $task)
    {
        $task->load(['labels']);

        return view('tasks.edit', $this->formViewData($task));
    }

    public function update(Request $request, Task $task)
    {
        $this->persistTask($request, $task);

        return redirect()->route('tasks.show', $task);
    }

    private function formViewData(?Task $task): array
    {
        return [
            'task' => $task,
            'statusLabels' => $this->statusLabels(),
            'priorityLabels' => $this->priorityLabels(),
            'sessions' => Session::query()->where('is_active', true)->orderBy('label')->get(['id', 'label', 'topic_id']),
            'projects' => $this->filterOptions()['projects'],
            'labels' => Label::query()->orderBy('name')->get(['id', 'name', 'color']),
            'pageTitle' => $task ? 'Edit task' : 'New task',
        ];
    }

    private function persistTask(Request $request, ?Task $task): Task
    {
        $validated = $request->validate([
            'title' => [$task ? 'sometimes' : 'required', 'string', 'max:255'],
            'description_md' => ['nullable', 'string'],
            'status' => ['sometimes', TaskRules::statusRule()],
            'priority' => ['sometimes', TaskRules::priorityRule()],
            'assignee_session_id' => ['nullable', 'integer', 'exists:sessions,id'],
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'due_date' => ['nullable', 'date'],
            'start_date' => ['nullable', 'date'],
            'external_ref' => ['nullable', 'string', 'max:255'],
            'label_ids' => ['sometimes', 'array'],
            'label_ids.*' => ['integer', 'exists:labels,id'],
        ]);

        $actor = Actor::from($request);

        if ($task === null) {
            return DB::transaction(function () use ($validated, $actor, $request) {
                $placeholder = 'tmp-'.uniqid();

                $task = Task::create([
                    'code' => $placeholder,
                    'title' => $validated['title'],
                    'description_md' => $validated['description_md'] ?? null,
                    'status' => $validated['status'] ?? 'todo',
                    'priority' => $validated['priority'] ?? 'normal',
                    'assignee_session_id' => $validated['assignee_session_id'] ?? null,
                    'project_id' => $validated['project_id'] ?? null,
                    'due_date' => $validated['due_date'] ?? null,
                    'start_date' => $validated['start_date'] ?? null,
                    'external_ref' => $validated['external_ref'] ?? null,
                    'created_by_type' => Actor::morphType($actor),
                    'created_by_id' => $actor->getKey(),
                ]);

                $task->update(['code' => 'CLK-'.$task->id]);

                $task->labels()->sync($request->input('label_ids', []));

                ActivityLogger::log('task.created', $actor, $task, [
                    'title' => $task->title,
                    'status' => $task->status,
                ]);

                return $task;
            });
        }

        $oldStatus = $task->status;
        $changes = [];

        if (array_key_exists('title', $validated)) {
            $changes['title'] = ['old' => $task->title, 'new' => $validated['title']];
            $task->title = $validated['title'];
        }
        if (array_key_exists('description_md', $validated)) {
            $task->description_md = $validated['description_md'];
        }
        if (array_key_exists('status', $validated)) {
            $changes['status'] = ['old' => $task->status, 'new' => $validated['status']];
            $task->status = $validated['status'];
            if ($validated['status'] === 'done' && ! $task->done_at) {
                $task->done_at = now();
            }
        }
        if (array_key_exists('priority', $validated)) {
            $changes['priority'] = ['old' => $task->priority, 'new' => $validated['priority']];
            $task->priority = $validated['priority'];
        }
        if (array_key_exists('assignee_session_id', $validated)) {
            $changes['assignee_session_id'] = ['old' => $task->assignee_session_id, 'new' => $validated['assignee_session_id']];
            $task->assignee_session_id = $validated['assignee_session_id'];
        }
        if (array_key_exists('project_id', $validated)) {
            $task->project_id = $validated['project_id'];
        }
        if (array_key_exists('due_date', $validated)) {
            $task->due_date = $validated['due_date'];
        }
        if (array_key_exists('start_date', $validated)) {
            $task->start_date = $validated['start_date'];
        }
        if (array_key_exists('external_ref', $validated)) {
            $task->external_ref = $validated['external_ref'];
        }

        $task->save();

        $task->labels()->sync($request->input('label_ids', []));

        if (isset($validated['status']) && $validated['status'] !== $oldStatus) {
            TaskUpdate::create([
                'task_id' => $task->id,
                'from_status' => $oldStatus,
                'to_status' => $validated['status'],
                'actor_type' => Actor::morphType($actor),
                'actor_id' => $actor->getKey(),
            ]);

            ApiTaskController::notifyAssigneeStatusChanged($task);
        }

        if ($changes !== []) {
            ActivityLogger::log('task.updated', $actor, $task, $changes);
        }

        return $task;
    }
}
