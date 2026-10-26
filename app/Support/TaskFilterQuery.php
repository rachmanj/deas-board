<?php

namespace App\Support;

use App\Models\Task;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class TaskFilterQuery
{
    private const FILTER_KEYS = [
        'status',
        'priority',
        'assignee',
        'project',
        'q',
        'session_id',
        'overdue',
    ];

    public static function hasActiveFilters(Request $request): bool
    {
        foreach (self::FILTER_KEYS as $key) {
            if ($request->filled($key)) {
                return true;
            }
        }

        return false;
    }

    public static function normalizeRequestFilters(Request $request): void
    {
        $query = $request->query->all();

        foreach (self::FILTER_KEYS as $key) {
            if (! array_key_exists($key, $query)) {
                continue;
            }

            $value = $query[$key];

            if (is_array($value)) {
                $scalars = array_values(array_filter($value, fn ($v) => is_scalar($v)));
                $value = $scalars !== [] ? $scalars[array_key_last($scalars)] : '';
            }

            if ($value === '' || $value === null) {
                unset($query[$key]);
            } else {
                $query[$key] = $value;
            }
        }

        $request->query->replace($query);
    }

    public static function fromRequest(Request $request): Builder
    {
        self::normalizeRequestFilters($request);

        $request->validate([
            'status' => ['nullable', TaskRules::statusRule()],
            'priority' => ['nullable', TaskRules::priorityRule()],
            'assignee' => ['nullable', 'integer', 'exists:sessions,id'],
            'project' => ['nullable', 'string'],
            'q' => ['nullable', 'string', 'max:255'],
            'session_id' => ['nullable', 'string', 'max:20', 'regex:/^[0-9]+$/'],
            'overdue' => ['nullable', 'in:1'],
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
