<?php

namespace App\Support;

class TaskRules
{
    public const STATUSES = ['todo', 'in_progress', 'blocked', 'review', 'done'];

    /** @var list<string> */
    public const BOARD_COLUMN_STATUSES = ['todo', 'in_progress', 'blocked', 'review'];

    public const PRIORITIES = ['low', 'normal', 'high', 'urgent'];

    public static function statusRule(): string
    {
        return 'in:'.implode(',', self::STATUSES);
    }

    public static function priorityRule(): string
    {
        return 'in:'.implode(',', self::PRIORITIES);
    }
}
