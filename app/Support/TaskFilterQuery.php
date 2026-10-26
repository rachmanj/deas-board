<?php

namespace App\Support;

use App\Models\Task;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class TaskFilterQuery
{
    public static function fromRequest(Request $request): Builder
    {
        $request->validate([
            'status' => ['sometimes', TaskRules::statusRule()],
            'priority' => ['sometimes', TaskRules::priorityRule()],
            'assignee' => ['sometimes', 'integer', 'exists:sessions,id'],
            'project' => ['sometimes'],
            'q' => ['sometimes', 'string', 'max:255'],
            'session_id' => ['sometimes', 'string', 'max:20', 'regex:/^[0-9]+$/'],
            'overdue' => ['sometimes', 'in:1'],
        ]);

        $query = Task::query()
            ->with(['assigneeSession:id,label,topic_id', 'project:id,slug,name,color'])
            ->withCount('comments');

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

        if ($request->filled('q')) {
            $term = $request->string('q');
            $query->where(function ($q) use ($term) {
                $q->where('title', 'like', '%'.$term.'%')
                    ->orWhere('code', 'like', '%'.$term.'%')
                    ->orWhereHas('assigneeSession', function ($s) use ($term) {
                        $s->where('label', 'like', '%'.$term.'%')
                            ->orWhere('topic_id', 'like', '%'.$term.'%');
                    });
            });
        }

        if ($request->filled('session_id')) {
            $topicId = $request->string('session_id');
            $query->whereHas('assigneeSession', fn ($s) => $s->where('topic_id', $topicId));
        }

        if ($request->string('overdue') === '1') {
            $query->where('status', '!=', 'done')
                ->whereNotNull('due_date')
                ->whereDate('due_date', '<', now()->toDateString());
        }

        return $query
            ->orderByRaw('due_date IS NULL')
            ->orderBy('due_date')
            ->orderBy('id');
    }
}
