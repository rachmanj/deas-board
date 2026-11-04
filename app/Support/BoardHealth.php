<?php

namespace App\Support;

use App\Models\Session;
use App\Models\Task;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class BoardHealth
{
    /**
     * @return array{
     *     in_progress_stale: int,
     *     review_stale: int,
     *     done_without_evidence: int,
     *     todo_commit_hint: int,
     * }
     */
    public static function counts(?int $doneWithoutEvidenceDays = null): array
    {
        return [
            'in_progress_stale' => self::inProgressStaleQuery()->count(),
            'review_stale' => self::reviewStaleQuery()->count(),
            'done_without_evidence' => self::doneWithoutEvidence($doneWithoutEvidenceDays)->count(),
            'todo_commit_hint' => self::todoCommitHintTasks()->count(),
        ];
    }

    public static function resolveDoneWithoutEvidenceDays(?int $days): int
    {
        $days = $days ?? HealthThresholds::DONE_WITHOUT_EVIDENCE_DAYS;

        return $days < 1 ? HealthThresholds::DONE_WITHOUT_EVIDENCE_DAYS : $days;
    }

    public static function totalDeviations(array $counts): int
    {
        return array_sum($counts);
    }

    public static function inProgressStaleQuery(): Builder
    {
        $cutoff = HealthThresholds::inProgressStaleCutoff();

        return Task::query()
            ->where('status', 'in_progress')
            ->whereDoesntHave('updates', fn (Builder $q) => $q->where('created_at', '>=', $cutoff))
            ->whereDoesntHave('comments', fn (Builder $q) => $q->where('created_at', '>=', $cutoff))
            ->where('updated_at', '<', $cutoff);
    }

    public static function reviewStaleQuery(): Builder
    {
        $cutoff = HealthThresholds::reviewStaleCutoff();

        return Task::query()
            ->where('status', 'review')
            ->whereDoesntHave('updates', fn (Builder $q) => $q->where('created_at', '>=', $cutoff))
            ->whereDoesntHave('comments', fn (Builder $q) => $q->where('created_at', '>=', $cutoff))
            ->where('updated_at', '<', $cutoff);
    }

    /**
     * @return Collection<int, Task>
     */
    public static function doneWithoutEvidence(?int $days = null): Collection
    {
        $days = self::resolveDoneWithoutEvidenceDays($days);
        $cutoff = HealthThresholds::doneWithoutEvidenceCutoff($days);

        return Task::query()
            ->where('status', 'done')
            ->where(function (Builder $q) use ($cutoff) {
                $q->where(function (Builder $inner) use ($cutoff) {
                    $inner->whereNotNull('done_at')->where('done_at', '>=', $cutoff);
                })->orWhere(function (Builder $inner) use ($cutoff) {
                    $inner->whereNull('done_at')->where('updated_at', '>=', $cutoff);
                });
            })
            ->where(function (Builder $q) {
                $q->whereHas('updates', fn (Builder $u) => $u->where('actor_type', Session::class))
                    ->orWhereHas('comments', fn (Builder $c) => $c->where('author_type', Session::class));
            })
            ->with(['comments', 'updates'])
            ->get()
            ->filter(fn (Task $task) => ! EvidenceRule::forDone($task)['ok'])
            ->values();
    }

    public static function todoCommitHintTasks(): Collection
    {
        return Task::query()
            ->where('status', 'todo')
            ->get()
            ->filter(fn (Task $task) => self::todoLooksLikeCommitWork($task))
            ->values();
    }

    public static function todoLooksLikeCommitWork(Task $task): bool
    {
        if ($task->external_ref !== null && preg_match('/\b[0-9a-f]{7,40}\b/i', (string) $task->external_ref)) {
            return true;
        }

        return preg_match('/commit/ui', (string) $task->title) === 1;
    }

    public static function formatAge(Carbon $since): string
    {
        $hours = (int) $since->diffInHours(now());

        if ($hours < 48) {
            return $hours.' jam';
        }

        return (int) $since->diffInDays(now()).' hari';
    }

    public static function taskActivityAnchor(Task $task): Carbon
    {
        $latestUpdate = $task->updates()->max('created_at');
        $latestComment = $task->comments()->max('created_at');
        $candidates = array_filter([$latestUpdate, $latestComment, $task->updated_at?->toDateTimeString()]);

        if ($candidates === []) {
            return $task->created_at ?? now();
        }

        return Carbon::parse(max($candidates));
    }
}
