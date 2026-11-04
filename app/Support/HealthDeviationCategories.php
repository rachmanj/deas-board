<?php

namespace App\Support;

use App\Models\Task;
use Illuminate\Support\Collection;

class HealthDeviationCategories
{
    public const IN_PROGRESS_STALE = 'in_progress_stale';

    public const REVIEW_STALE = 'review_stale';

    public const DONE_WITHOUT_EVIDENCE = 'done_without_evidence';

    public const TODO_COMMIT_HINT = 'todo_commit_hint';

    /**
     * @return array<string, string>
     */
    public static function titles(): array
    {
        return [
            self::IN_PROGRESS_STALE => 'In progress basi (> '.HealthThresholds::IN_PROGRESS_STALE_HOURS.' jam tanpa update/komentar)',
            self::REVIEW_STALE => 'Review basi (> '.HealthThresholds::REVIEW_STALE_HOURS.' jam tanpa perubahan)',
            self::DONE_WITHOUT_EVIDENCE => 'Done tanpa bukti',
            self::TODO_COMMIT_HINT => 'Todo indikasi commit',
        ];
    }

    /**
     * @return array<string, Collection<int, Task>>
     */
    public static function tasksByCategory(): array
    {
        return [
            self::IN_PROGRESS_STALE => BoardHealth::inProgressStaleQuery()->orderBy('code')->get(),
            self::REVIEW_STALE => BoardHealth::reviewStaleQuery()->orderBy('code')->get(),
            self::DONE_WITHOUT_EVIDENCE => BoardHealth::doneWithoutEvidence()->sortBy('code')->values(),
            self::TODO_COMMIT_HINT => BoardHealth::todoCommitHintTasks()->sortBy('code')->values(),
        ];
    }
}
