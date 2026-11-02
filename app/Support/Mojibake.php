<?php

namespace App\Support;

final class Mojibake
{
    public const DETECTION_PATTERN = '/â[\x{2020}\x{20AC}\x{2026}\x{2021}\x{2018}\x{2019}\x{201C}\x{201D}].|â[\x{0080}\x{0086}].|â€.|Ã©|Ã¢|Ã¨|Ã¡|Ã³|Ã¼|Ã±|Ã‰|Ã§|Ã´/u';

    /**
     * @var array<string, string>
     */
    public const EXPLICIT_MAP = [
        "\u{00E2}\u{2020}\u{2019}" => '→',
        "\u{00E2}\u{0086}\u{0092}" => '→',
        "\u{00E2}\u{20AC}\u{201D}" => '—',
        "\u{00E2}\u{0080}\u{0094}" => '—',
        "\u{00E2}\u{0080}\u{0093}" => '–',
        "\u{00E2}\u{20AC}\u{2122}" => '’',
        "\u{00E2}\u{0080}\u{0099}" => '’',
        "\u{00E2}\u{20AC}\u{0153}" => '“',
        "\u{00E2}\u{0080}\u{009C}" => '“',
        "\u{00E2}\u{20AC}\u{009D}" => '”',
        "\u{00E2}\u{0080}\u{009D}" => '”',
        "\u{00E2}\u{20AC}\u{00A6}" => '…',
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

    /** @var array<int, int>|null Unicode code point → single CP1252 byte */
    private static ?array $cp1252CharToByte = null;

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

        $repaired = self::attemptRepair($value);
        if ($repaired !== null && $repaired !== $value) {
            return [
                'value' => $repaired,
                'action' => 'repaired',
                'skip_reason' => null,
                'unrecognized_remainder' => self::containsPattern($repaired),
            ];
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

    private static function attemptRepair(string $value): ?string
    {
        $cp1252 = self::tryCp1252Reversal($value);
        if ($cp1252 !== null) {
            return $cp1252;
        }

        $latin1 = self::tryLatin1Reversal($value);
        if ($latin1 !== null) {
            return $latin1;
        }

        $mapped = strtr($value, self::EXPLICIT_MAP);
        if ($mapped !== $value) {
            if (! self::containsPattern($mapped)) {
                return $mapped;
            }

            $segmented = self::repairSegments($mapped);
            if ($segmented !== null) {
                return $segmented;
            }

            return $mapped;
        }

        $segmented = self::repairSegments($value);
        if ($segmented !== null) {
            return $segmented;
        }

        return null;
    }

    private static function repairSegments(string $value): ?string
    {
        $chars = preg_split('//u', $value, -1, PREG_SPLIT_NO_EMPTY);
        if (! is_array($chars) || $chars === []) {
            return null;
        }

        $n = count($chars);
        $out = '';
        $i = 0;
        $changed = false;

        while ($i < $n) {
            $lead = $chars[$i];
            if ($lead !== "\u{00E2}" && $lead !== "\u{00C3}") {
                $out .= $lead;
                $i++;

                continue;
            }

            $replaced = false;
            $maxLen = min(4, $n - $i);
            for ($len = $maxLen; $len >= 2; $len--) {
                $segment = implode('', array_slice($chars, $i, $len));
                $fixed = self::fixSegment($segment);
                if ($fixed !== null && $fixed !== $segment) {
                    $out .= $fixed;
                    $i += $len;
                    $replaced = true;
                    $changed = true;
                    break;
                }
            }

            if (! $replaced) {
                $out .= $lead;
                $i++;
            }
        }

        if (! $changed || $out === $value) {
            return null;
        }

        return $out;
    }

    private static function fixSegment(string $segment): ?string
    {
        $cp1252 = self::tryCp1252Reversal($segment);
        if ($cp1252 !== null) {
            return $cp1252;
        }

        if (self::isAllLatin1($segment)) {
            $latin1 = self::tryLatin1Reversal($segment);
            if ($latin1 !== null) {
                return $latin1;
            }
        }

        $mapped = strtr($segment, self::EXPLICIT_MAP);
        if ($mapped !== $segment) {
            return $mapped;
        }

        return null;
    }

    private static function cp1252CharToByteMap(): array
    {
        if (self::$cp1252CharToByte !== null) {
            return self::$cp1252CharToByte;
        }

        $map = [];
        for ($byte = 0xA0; $byte <= 0xFF; $byte++) {
            $map[$byte] = $byte;
        }

        $special = [
            0x80 => 0x20AC,
            0x82 => 0x201A,
            0x83 => 0x0192,
            0x84 => 0x201E,
            0x85 => 0x2026,
            0x86 => 0x2020,
            0x87 => 0x2021,
            0x88 => 0x02C6,
            0x89 => 0x2030,
            0x8A => 0x0160,
            0x8B => 0x2039,
            0x8C => 0x0152,
            0x8E => 0x017D,
            0x91 => 0x2018,
            0x92 => 0x2019,
            0x93 => 0x201C,
            0x94 => 0x201D,
            0x95 => 0x2022,
            0x96 => 0x2013,
            0x97 => 0x2014,
            0x98 => 0x02DC,
            0x99 => 0x2122,
            0x9A => 0x0161,
            0x9B => 0x203A,
            0x9C => 0x0153,
            0x9E => 0x017E,
            0x9F => 0x0178,
        ];

        foreach ($special as $byte => $codePoint) {
            $map[$codePoint] = $byte;
        }

        self::$cp1252CharToByte = $map;

        return self::$cp1252CharToByte;
    }

    private static function tryCp1252Reversal(string $value): ?string
    {
        $chars = preg_split('//u', $value, -1, PREG_SPLIT_NO_EMPTY);
        if (! is_array($chars)) {
            return null;
        }

        $map = self::cp1252CharToByteMap();
        $bytes = '';

        foreach ($chars as $char) {
            $ord = mb_ord($char);
            if ($ord <= 0x7F) {
                $bytes .= chr($ord);

                continue;
            }

            if (! isset($map[$ord])) {
                return null;
            }

            $bytes .= chr($map[$ord]);
        }

        if (! mb_check_encoding($bytes, 'UTF-8') || $bytes === $value) {
            return null;
        }

        return $bytes;
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

    private static function tryLatin1Reversal(string $value): ?string
    {
        if (! self::isAllLatin1($value)) {
            return null;
        }

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
