<?php

namespace App\Support;

use App\Models\Session;
use Illuminate\Support\Carbon;

class SessionPresence
{
    public const TZ = 'Asia/Jakarta';

    /**
     * @return 'aktif'|'idle'|'tidak_aktif'
     */
    public static function activityKey(?Carbon $lastSeenAt): string
    {
        if ($lastSeenAt === null) {
            return 'tidak_aktif';
        }

        $minutes = $lastSeenAt->diffInMinutes(now());

        if ($minutes < HealthThresholds::SESSION_ACTIVE_MINUTES) {
            return 'aktif';
        }

        if ($lastSeenAt->greaterThan(HealthThresholds::sessionStaleCutoff())) {
            return 'idle';
        }

        return 'tidak_aktif';
    }

    public static function activityLabel(?Carbon $lastSeenAt): string
    {
        return match (self::activityKey($lastSeenAt)) {
            'aktif' => 'Aktif',
            'idle' => 'Idle',
            default => 'Tidak aktif',
        };
    }

    public static function isMenggantung(Session $session): bool
    {
        $inProgressCount = $session->in_progress_tasks_count
            ?? $session->assignedTasks()->where('status', 'in_progress')->count();

        if ($inProgressCount < 1) {
            return false;
        }

        if ($session->last_seen_at === null) {
            return true;
        }

        return $session->last_seen_at->lessThan(HealthThresholds::sessionStaleCutoff());
    }

    public static function formatLastSeenWib(?Carbon $lastSeenAt): string
    {
        if ($lastSeenAt === null) {
            return '—';
        }

        return $lastSeenAt->copy()->timezone(self::TZ)->format('Y-m-d H:i').' WIB';
    }
}
