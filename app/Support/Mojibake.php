<?php

namespace App\Support;

final class Mojibake
{
    public const DETECTION_PATTERN = '/â[\x{2020}\x{0086}\x{0080}].|â€[\x{0094}\x{0099}\x{009c}\x{009d}]|â€œ|â€™|â€.|Ã©|Ã¢/u';

    /**
     * @var array<string, string>
     */
    public const EXPLICIT_MAP = [
        "\u{00E2}\u{0086}\u{0092}" => '→',
        "\u{00E2}\u{0080}\u{0094}" => '—',
        "\u{00E2}\u{0080}\u{0093}" => '–',
        "\u{00E2}\u{0080}\u{0099}" => '’',
        "\u{00E2}\u{0080}\u{009C}" => '“',
        "\u{00E2}\u{0080}\u{009D}" => '”',
        "\u{00E2}\u{0080}\u{00A6}" => '…',
        "\u{00E2}\u{0082}\u{00AC}" => '€',
        'Ã©' => 'é',
        'Ã¨' => 'è',
        'Ã¡' => 'á',
        'Ã³' => 'ó',
        'Ã¼' => 'ü',
        'Ã±' => 'ñ',
    ];

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

    /**
     * @return array{value: ?string, action: 'none'|'repaired'|'skipped', skip_reason: ?string}
     */
    public static function repairWithStatus(?string $value): array
    {
        if ($value === null || $value === '') {
            return ['value' => $value, 'action' => 'none', 'skip_reason' => null];
        }

        if (! self::containsPattern($value)) {
            return ['value' => $value, 'action' => 'none', 'skip_reason' => null];
        }

        $chars = self::utf8Characters($value);
        if ($chars === null) {
            return [
                'value' => $value,
                'action' => 'skipped',
                'skip_reason' => 'string bukan UTF-8 valid',
            ];
        }

        foreach ($chars as $char) {
            if (mb_ord($char) > 255) {
                return [
                    'value' => $value,
                    'action' => 'skipped',
                    'skip_reason' => 'mengandung karakter di luar Latin-1 (code point di atas 255)',
                ];
            }
        }

        $bytes = '';
        foreach ($chars as $char) {
            $bytes .= chr(mb_ord($char));
        }

        if (mb_check_encoding($bytes, 'UTF-8') && $bytes !== $value) {
            return ['value' => $bytes, 'action' => 'repaired', 'skip_reason' => null];
        }

        $mapped = strtr($value, self::EXPLICIT_MAP);
        if ($mapped !== $value) {
            return ['value' => $mapped, 'action' => 'repaired', 'skip_reason' => null];
        }

        if (! mb_check_encoding($bytes, 'UTF-8')) {
            return [
                'value' => $value,
                'action' => 'skipped',
                'skip_reason' => 'pembalikan byte menghasilkan UTF-8 tidak valid',
            ];
        }

        return [
            'value' => $value,
            'action' => 'skipped',
            'skip_reason' => 'tidak bisa dibalik dengan aman',
        ];
    }

    public static function repair(?string $value): ?string
    {
        $result = self::repairWithStatus($value);

        if ($result['action'] === 'repaired') {
            return $result['value'];
        }

        return $value;
    }

    public static function sample(string $value, int $max = 60): string
    {
        if (mb_strlen($value) <= $max) {
            return $value;
        }

        return mb_substr($value, 0, $max).'…';
    }

    /**
     * @return list<string>|null
     */
    private static function utf8Characters(string $value): ?array
    {
        if (! mb_check_encoding($value, 'UTF-8')) {
            return null;
        }

        $chars = preg_split('//u', $value, -1, PREG_SPLIT_NO_EMPTY);

        return is_array($chars) ? $chars : null;
    }
}
