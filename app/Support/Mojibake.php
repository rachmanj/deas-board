<?php

namespace App\Support;

final class Mojibake
{
    public const DETECTION_PATTERN = '/â[\x{2020}\x{0086}].|â€[\x{0094}\x{0099}\x{009c}\x{009d}]|â€œ|â€™|â€.|Ã©|Ã¢/u';

    /**
     * @var list<array{table: string, columns: list<string>}>
     */
    public const SCAN_TARGETS = [
        ['table' => 'tasks', 'columns' => ['title', 'description_md']],
        ['table' => 'task_comments', 'columns' => ['body_md']],
        ['table' => 'task_updates', 'columns' => ['note', 'evidence']],
        ['table' => 'emails', 'columns' => ['subject', 'summary']],
        ['table' => 'sessions', 'columns' => ['label', 'notes']],
        ['table' => 'projects', 'columns' => ['name']],
        ['table' => 'labels', 'columns' => ['name']],
    ];

    public static function containsPattern(?string $value): bool
    {
        if ($value === null || $value === '') {
            return false;
        }

        return (bool) preg_match(self::DETECTION_PATTERN, $value);
    }

    public static function repair(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return $value;
        }

        if (! self::containsPattern($value)) {
            return $value;
        }

        $latin1 = mb_convert_encoding($value, 'ISO-8859-1', 'UTF-8');
        $repaired = mb_convert_encoding($latin1, 'UTF-8', 'UTF-8');

        if ($repaired === false || $repaired === '') {
            return $value;
        }

        return $repaired;
    }

    public static function sample(string $value, int $max = 60): string
    {
        if (mb_strlen($value) <= $max) {
            return $value;
        }

        return mb_substr($value, 0, $max).'…';
    }
}
