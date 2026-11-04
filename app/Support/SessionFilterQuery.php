<?php

namespace App\Support;

use App\Models\Session;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class SessionFilterQuery
{
    private const FILTER_KEYS = ['q', 'status', 'sort', 'status_kerja'];

    public static function hasActiveFilters(Request $request): bool
    {
        foreach (self::FILTER_KEYS as $key) {
            if ($key === 'sort') {
                if ($request->filled('sort') && $request->string('sort')->toString() !== 'label') {
                    return true;
                }

                continue;
            }

            if ($request->filled($key)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, string>
     */
    public static function redirectParams(Request $request): array
    {
        $params = [];

        foreach (self::FILTER_KEYS as $key) {
            $value = $request->query($key, $request->input($key));
            if ($value === null || $value === '') {
                continue;
            }
            if ($key === 'sort' && $value === 'label') {
                continue;
            }
            $params[$key] = (string) $value;
        }

        return $params;
    }

    public static function fromRequest(Request $request): Builder
    {
        $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'in:aktif,nonaktif'],
            'status_kerja' => ['nullable', 'in:menggantung'],
            'sort' => ['nullable', 'in:label,topic,tasks'],
        ]);

        $query = Session::query()
            ->withCount([
                'tokens as active_tokens_count',
                'assignedTasks as open_tasks_count' => fn ($q) => $q->where('status', '!=', 'done'),
                'assignedTasks as in_progress_tasks_count' => fn ($q) => $q->where('status', 'in_progress'),
                'assignedTasks as tasks_todo_count' => fn ($q) => $q->where('status', 'todo'),
                'assignedTasks as tasks_in_progress_count' => fn ($q) => $q->where('status', 'in_progress'),
                'assignedTasks as tasks_blocked_count' => fn ($q) => $q->where('status', 'blocked'),
                'assignedTasks as tasks_review_count' => fn ($q) => $q->where('status', 'review'),
                'assignedTasks as tasks_done_count' => fn ($q) => $q->where('status', 'done'),
            ])
            ->with([
                'tokens' => fn ($q) => $q->orderByDesc('id'),
                'assignedTasks' => fn ($q) => $q->where('status', 'in_progress')->orderByDesc('updated_at')->limit(1),
            ]);

        if ($request->filled('q')) {
            $term = '%'.$request->string('q').'%';
            $query->where(function (Builder $q) use ($term) {
                $q->where('label', 'like', $term)
                    ->orWhere('topic_id', 'like', $term)
                    ->orWhere('chat_id', 'like', $term)
                    ->orWhere('host', 'like', $term);
            });
        }

        if ($request->filled('status')) {
            $query->where('is_active', $request->string('status')->toString() === 'aktif');
        }

        if ($request->string('status_kerja')->toString() === 'menggantung') {
            $staleCutoff = HealthThresholds::sessionStaleCutoff();
            $query->whereHas('assignedTasks', fn (Builder $q) => $q->where('status', 'in_progress'))
                ->where(function (Builder $q) use ($staleCutoff) {
                    $q->whereNull('last_seen_at')
                        ->orWhere('last_seen_at', '<', $staleCutoff);
                });
        }

        $sort = $request->string('sort', 'label')->toString();

        if ($sort === 'topic') {
            $query->orderByRaw('CAST(topic_id AS INTEGER)');
        } elseif ($sort === 'tasks') {
            $query->orderByDesc('open_tasks_count');
        } else {
            $query->orderBy('label');
        }

        return $query;
    }
}
