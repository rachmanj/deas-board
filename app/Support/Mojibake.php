<?php

namespace App\Support;

final class Mojibake
{
    public const DETECTION_PATTERN = '/â[\x{2020}\x{0086}\x{0080}].|â€[\x{0094}\x{0099}\x{009c}\x{009d}]|â€œ|â€™|â€.|Ã©|Ã¢|Ã¨|Ã¡|Ã³|Ã¼|Ã±|Ã‰|Ã§|Ã´/u';

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
        'Ã¢' => 'â',
        'Ã´' => 'ô',
        'Ã§' => 'ç',
        'Ã‰' => 'É',
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
     * @return array{
     *     value: ?string,
     *     action: 'none'|'repaired'|'skipped',
     *     skip_reason: ?string,
     *     unrecognized_remainder: bool
     * }
     */
    public static function repairWithStatus(?string $value): array
    {
        $none = [
            'value' => $value,
            'action' => 'none',
            'skip_reason' => null,
            'unrecognized_remainder' => false,
        ];

        if ($value === null || $value === '') {
            return $none;
        }

        if (! self::containsPattern($value)) {
            return $none;
        }

        if (! mb_check_encoding($value, 'UTF-8')) {
            return [
                'value' => $value,
                'action' => 'skipped',
                'skip_reason' => 'string bukan UTF-8 valid',
                'unrecognized_remainder' => false,
            ];
        }

        $mapped = strtr($value, self::EXPLICIT_MAP);
        $working = $mapped;
        $changed = $mapped !== $value;

        if (self::containsPattern($working) && self::isAllLatin1($working)) {
            $reversed = self::tryByteReversal($working);
            if ($reversed !== null) {
                $working = $reversed;
                $changed = true;
            }
        }

        if ($changed && $working !== $value) {
            return [
                'value' => $working,
                'action' => 'repaired',
                'skip_reason' => null,
                'unrecognized_remainder' => self::containsPattern($working),
            ];
        }

        if (! $changed && self::isAllLatin1($value)) {
            $reversed = self::tryByteReversal($value);
            if ($reversed !== null) {
                return [
                    'value' => $reversed,
                    'action' => 'repaired',
                    'skip_reason' => null,
                    'unrecognized_remainder' => self::containsPattern($reversed),
                ];
            }
        }

        return [
            'value' => $value,
            'action' => 'skipped',
            'skip_reason' => self::remainingPatternReason($value),
            'unrecognized_remainder' => false,
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

    public static function remainingPatternReason(string $value): string
    {
        if (preg_match(self::DETECTION_PATTERN, $value, $matches)) {
            return 'poli mojibake tersisa: '.self::sample($matches[0], 24);
        }

        return 'poli mojibake tersisa tidak dikenali';
    }

    private static function isAllLatin1(string $value): bool
    {
        $chars = preg_split('//u', $value, -1, PREG_SPLIT_NO_EMPTY);
        if (! is_array($chars)) {
            return false;
        }

        foreach ($chars as $char) {
            if (mb_ord($char) > 255) {
                return false;
            }
        }

        return true;
    }

    private static function tryByteReversal(string $value): ?string
    {
        $chars = preg_split('//u', $value, -1, PREG_SPLIT_NO_EMPTY);
        if (! is_array($chars)) {
            return null;
        }

        $bytes = '';
        foreach ($chars as $char) {
            $bytes .= chr(mb_ord($char));
        }

        if (! mb_check_encoding($bytes, 'UTF-8') || $bytes === $value) {
            return null;
        }

        return $bytes;
    }
}
