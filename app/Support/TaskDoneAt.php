<?php

namespace App\Support;

use App\Models\Task;
use Illuminate\Support\Carbon;

class TaskDoneAt
{
    public static function sync(Task $task, string $fromStatus, string $toStatus, ?\DateTimeInterface $sourceDoneAt = null): void
    {
        if ($fromStatus === $toStatus) {
            return;
        }

        if ($toStatus === 'done') {
            $task->done_at = $sourceDoneAt ?? now();

            return;
        }

        if ($fromStatus === 'done') {
            $task->done_at = null;
        }
    }

    public static function forImportedTask(string $status, mixed $sourceDoneAt): ?Carbon
    {
        if ($status !== 'done') {
            return null;
        }

        return self::parseImportValue($sourceDoneAt) ?? now();
    }

    public static function parseImportValue(mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }
}
