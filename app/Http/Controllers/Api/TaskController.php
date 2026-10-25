<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\Session;
use App\Models\Task;
use App\Models\TaskUpdate;
use App\Support\ActivityLogger;
use App\Support\Actor;
use App\Support\ApiResponse;
use App\Support\TaskRules;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
class TaskController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'status' => ['sometimes', TaskRules::statusRule()],
            'priority' => ['sometimes', TaskRules::priorityRule()],
            'assignee' => ['sometimes', 'integer', 'exists:sessions,id'],
            'project' => ['sometimes'],
            'label' => ['sometimes'],
            'q' => ['sometimes', 'string', 'max:255'],
            'overdue' => ['sometimes', 'in:1'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ]);

        $query = Task::query()
            ->with(['assigneeSession:id,label,topic_id', 'project:id,slug,name,color']);

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        if ($request->filled('priority')) {
            $query->where('priority', $request->string('priority'));
        }

        if ($request->filled('assignee')) {
            $query->where('assignee_session_id', $request->integer('assignee'));
        }

        if ($request->filled('project')) {
            $project = $request->string('project');
            $query->where(function ($q) use ($project) {
                $q->whereHas('project', fn ($p) => $p->where('slug', $project))
                    ->orWhere('project_id', $project);
            });
        }

        if ($request->filled('label')) {
            $label = $request->input('label');
            $query->whereHas('labels', function ($q) use ($label) {
                if (is_numeric($label)) {
                    $q->where('labels.id', $label);
                } else {
                    $q->where('labels.name', $label);
                }
            });
        }

        if ($request->filled('q')) {
            $query->where('title', 'like', '%'.$request->string('q').'%');
        }

        if ($request->string('overdue') === '1') {
            $query->where('status', '!=', 'done')
                ->whereNotNull('due_date')
                ->whereDate('due_date', '<', now()->toDateString());
        }

        $paginator = $query
            ->orderByRaw('due_date IS NULL')
            ->orderBy('due_date')
            ->orderBy('id')
            ->paginate(25);

        $data = $paginator->getCollection()->map(fn (Task $task) => $this->taskListItem($task));

        return ApiResponse::paginated($paginator, $data);
    }

    public function show(Task $task)
    {
        $task->load([
            'assigneeSession:id,label,topic_id',
            'project:id,slug,name,color',
            'updates' => fn ($q) => $q->orderBy('id'),
            'attachments',
            'emails' => fn ($q) => $q->select([
                'emails.id',
                'emails.uid',
                'emails.mailbox',
                'emails.from_name',
                'emails.from_email',
                'emails.subject',
                'emails.received_at',
                'emails.summary',
                'emails.status',
            ]),
        ]);

        $comments = $task->comments()
            ->withTrashed()
            ->with(['author', 'mentions.mentionable'])
            ->orderBy('id')
            ->get();

        $detail = $this->taskDetail($task);
        $detail['comments'] = $comments->map(fn ($c) => $this->commentItem($c))->values();
        $detail['updates'] = $task->updates->map(fn (TaskUpdate $u) => [
                'id' => $u->id,
                'from_status' => $u->from_status,
                'to_status' => $u->to_status,
                'note' => $u->note,
                'evidence' => $u->evidence,
                'actor_type' => $u->actor_type,
                'actor_id' => $u->actor_id,
                'created_at' => $u->created_at?->toIso8601String(),
            ])->values();
        $detail['attachments'] = $task->attachments->map(fn ($a) => $this->attachmentItem($a))->values();
        $detail['emails'] = $task->emails->map(fn ($e) => [
                'id' => $e->id,
                'uid' => $e->uid,
                'mailbox' => $e->mailbox,
                'from_name' => $e->from_name,
                'from_email' => $e->from_email,
                'subject' => $e->subject,
                'received_at' => $e->received_at?->toIso8601String(),
                'summary' => $e->summary,
                'status' => $e->status,
                'linked_at' => $e->pivot->linked_at,
            ])->values();

        return ApiResponse::data($detail);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => ['sometimes', 'nullable', 'string', 'max:255', 'unique:tasks,code'],
            'title' => ['required', 'string', 'max:255'],
            'description_md' => ['sometimes', 'nullable', 'string'],
            'status' => ['sometimes', TaskRules::statusRule()],
            'priority' => ['sometimes', TaskRules::priorityRule()],
            'assignee' => ['sometimes', 'nullable', 'string'],
            'assignee_session_id' => ['sometimes', 'nullable', 'integer', 'exists:sessions,id'],
            'project_id' => ['sometimes', 'nullable', 'integer', 'exists:projects,id'],
            'due_date' => ['sometimes', 'nullable', 'date'],
            'start_date' => ['sometimes', 'nullable', 'date'],
            'label_ids' => ['sometimes', 'array'],
            'label_ids.*' => ['integer', 'exists:labels,id'],
        ]);

        $actor = Actor::from($request);
        $assigneeSessionId = $this->resolveAssigneeSessionId($validated);

        $task = DB::transaction(function () use ($validated, $actor, $assigneeSessionId, $request) {
            $code = $validated['code'] ?? null;
            $placeholder = 'tmp-'.uniqid();

            $task = Task::create([
                'code' => $code ?: $placeholder,
                'title' => $validated['title'],
                'description_md' => $validated['description_md'] ?? null,
                'status' => $validated['status'] ?? 'todo',
                'priority' => $validated['priority'] ?? 'normal',
                'assignee_session_id' => $assigneeSessionId,
                'project_id' => $validated['project_id'] ?? null,
                'due_date' => $validated['due_date'] ?? null,
                'start_date' => $validated['start_date'] ?? null,
                'created_by_type' => Actor::morphType($actor),
                'created_by_id' => $actor->getKey(),
            ]);

            if (! $code) {
                $task->update(['code' => 'CLK-'.$task->id]);
            }

            if ($request->filled('label_ids')) {
                $task->labels()->sync($request->input('label_ids'));
            }

            ActivityLogger::log('task.created', $actor, $task, [
                'title' => $task->title,
                'status' => $task->status,
            ]);

            return $task->fresh(['assigneeSession', 'project']);
        });

        return ApiResponse::data($this->taskDetail($task), 201);
    }

    public function update(Request $request, Task $task)
    {
        $validated = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'description_md' => ['sometimes', 'nullable', 'string'],
            'status' => ['sometimes', TaskRules::statusRule()],
            'priority' => ['sometimes', TaskRules::priorityRule()],
            'assignee' => ['sometimes', 'nullable', 'string'],
            'assignee_session_id' => ['sometimes', 'nullable', 'integer', 'exists:sessions,id'],
            'project_id' => ['sometimes', 'nullable', 'integer', 'exists:projects,id'],
            'due_date' => ['sometimes', 'nullable', 'date'],
            'start_date' => ['sometimes', 'nullable', 'date'],
            'label_ids' => ['sometimes', 'array'],
            'label_ids.*' => ['integer', 'exists:labels,id'],
        ]);

        $actor = Actor::from($request);
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
        if (array_key_exists('assignee', $validated) || array_key_exists('assignee_session_id', $validated)) {
            $newAssignee = $this->resolveAssigneeSessionId($validated);
            $changes['assignee_session_id'] = ['old' => $task->assignee_session_id, 'new' => $newAssignee];
            $task->assignee_session_id = $newAssignee;
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

        $task->save();

        if ($request->filled('label_ids')) {
            $task->labels()->sync($request->input('label_ids'));
        }

        if (isset($validated['status']) && $validated['status'] !== $oldStatus) {
            TaskUpdate::create([
                'task_id' => $task->id,
                'from_status' => $oldStatus,
                'to_status' => $validated['status'],
                'actor_type' => Actor::morphType($actor),
                'actor_id' => $actor->getKey(),
            ]);

            $this->notifyAssigneeStatusChanged($task);
        }

        if ($changes !== []) {
            ActivityLogger::log('task.updated', $actor, $task, $changes);
        }

        $task->load(['assigneeSession', 'project']);

        return ApiResponse::data($this->taskDetail($task));
    }

    private function resolveAssigneeSessionId(array $validated): ?int
    {
        if (array_key_exists('assignee_session_id', $validated)) {
            return $validated['assignee_session_id'];
        }

        if (! empty($validated['assignee'])) {
            $session = Session::query()->where('topic_id', $validated['assignee'])->first();
            if ($session) {
                return $session->id;
            }
        }

        return null;
    }

    public static function notifyAssigneeStatusChanged(Task $task): void
    {
        if (! $task->assignee_session_id) {
            return;
        }

        Notification::create([
            'notifiable_type' => Session::class,
            'notifiable_id' => $task->assignee_session_id,
            'type' => 'task.status_changed',
            'subject_type' => Task::class,
            'subject_id' => $task->id,
            'payload' => [
                'task_id' => $task->id,
                'status' => $task->status,
            ],
        ]);
    }

    private function taskListItem(Task $task): array
    {
        return [
            'id' => $task->id,
            'code' => $task->code,
            'title' => $task->title,
            'status' => $task->status,
            'priority' => $task->priority,
            'due_date' => $task->due_date?->toDateString(),
            'assignee' => $task->assigneeSession ? [
                'id' => $task->assigneeSession->id,
                'label' => $task->assigneeSession->label,
                'topic_id' => $task->assigneeSession->topic_id,
            ] : null,
            'project' => $task->project ? [
                'id' => $task->project->id,
                'slug' => $task->project->slug,
                'name' => $task->project->name,
            ] : null,
        ];
    }

    private function taskDetail(Task $task): array
    {
        return [
            'id' => $task->id,
            'code' => $task->code,
            'title' => $task->title,
            'description_md' => $task->description_md,
            'status' => $task->status,
            'priority' => $task->priority,
            'due_date' => $task->due_date?->toDateString(),
            'start_date' => $task->start_date?->toDateString(),
            'done_at' => $task->done_at?->toIso8601String(),
            'assignee_session_id' => $task->assignee_session_id,
            'project_id' => $task->project_id,
            'assignee' => $task->assigneeSession ? [
                'id' => $task->assigneeSession->id,
                'label' => $task->assigneeSession->label,
                'topic_id' => $task->assigneeSession->topic_id,
            ] : null,
            'project' => $task->project ? [
                'id' => $task->project->id,
                'slug' => $task->project->slug,
                'name' => $task->project->name,
            ] : null,
        ];
    }

    private function commentItem($comment): array
    {
        return [
            'id' => $comment->id,
            'task_id' => $comment->task_id,
            'parent_id' => $comment->parent_id,
            'body_md' => $comment->trashed() ? null : $comment->body_md,
            'is_deleted' => $comment->trashed(),
            'edited_at' => $comment->edited_at?->toIso8601String(),
            'author_type' => $comment->author_type,
            'author_id' => $comment->author_id,
            'created_at' => $comment->created_at?->toIso8601String(),
            'mentions' => $comment->mentions->map(fn ($m) => [
                'mentionable_type' => $m->mentionable_type,
                'mentionable_id' => $m->mentionable_id,
            ]),
        ];
    }

    private function attachmentItem($attachment): array
    {
        return [
            'id' => $attachment->id,
            'name' => $attachment->name,
            'size' => $attachment->size,
            'sha256' => $attachment->sha256,
        ];
    }
}
