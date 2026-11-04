<?php

namespace App\Support;

class HealthThresholds
{
    public const SESSION_ACTIVE_MINUTES = 15;

    public const SESSION_IDLE_MAX_HOURS = 2;

    public const SESSION_TOUCH_INTERVAL_SECONDS = 60;

    public const IN_PROGRESS_STALE_HOURS = 4;

    public const REVIEW_STALE_HOURS = 24;

    public const DONE_WITHOUT_EVIDENCE_DAYS = 7;

    public static function doneWithoutEvidenceCutoff(int $days = self::DONE_WITHOUT_EVIDENCE_DAYS): \Illuminate\Support\Carbon
    {
        return now()->subDays($days);
    }

    public static function sessionStaleCutoff(): \Illuminate\Support\Carbon
    {
        return now()->subHours(self::SESSION_IDLE_MAX_HOURS);
    }

    public static function inProgressStaleCutoff(): \Illuminate\Support\Carbon
    {
        return now()->subHours(self::IN_PROGRESS_STALE_HOURS);
    }

    public static function reviewStaleCutoff(): \Illuminate\Support\Carbon
    {
        return now()->subHours(self::REVIEW_STALE_HOURS);
    }
}
