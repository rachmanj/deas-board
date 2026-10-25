<?php

namespace App\Support;

use App\Models\ActivityLog;

class ActivityLogger
{
    public static function log(string $action, ?object $actor, ?object $subject, array $changes = []): ActivityLog
    {
        $normalizedChanges = [];
        foreach ($changes as $attribute => $value) {
            if (is_array($value) && array_key_exists('old', $value) && array_key_exists('new', $value)) {
                $normalizedChanges[$attribute] = [
                    'old' => $value['old'],
                    'new' => $value['new'],
                ];
            } else {
                $normalizedChanges[$attribute] = $value;
            }
        }

        return ActivityLog::create([
            'actor_type' => $actor ? $actor::class : null,
            'actor_id' => $actor?->getKey(),
            'action' => $action,
            'subject_type' => $subject ? $subject::class : null,
            'subject_id' => $subject?->getKey(),
            'changes' => $normalizedChanges !== [] ? $normalizedChanges : null,
            'ip' => request()?->ip(),
            'created_at' => now(),
        ]);
    }
}
