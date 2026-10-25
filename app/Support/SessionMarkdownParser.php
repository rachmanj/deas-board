<?php

namespace App\Support;

class SessionMarkdownParser
{
    /**
     * @return list<array{topic_id: string, label: string, notes: string|null}>
     */
    public function parse(string $markdown): array
    {
        $markdown = $this->extractApplicationSessionsSection($markdown);
        $rows = [];
        $lines = preg_split('/\R/', $markdown) ?: [];

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || ! str_starts_with($line, '|')) {
                continue;
            }

            if (! str_contains($line, '**')) {
                continue;
            }

            if (! preg_match('/\*\*(\d+)\*\*/', $line, $topicMatch)) {
                continue;
            }

            $cells = $this->splitTableRow($line);
            if ($cells === []) {
                continue;
            }

            $topicId = $topicMatch[1];
            $appCell = $cells[0] ?? '';
            $roleCell = trim($cells[1] ?? '');
            $detailCell = trim($cells[2] ?? '');

            $label = $this->cleanLabel($appCell);
            if ($label === '') {
                continue;
            }

            $notesParts = array_values(array_filter(
                [$roleCell, $detailCell],
                fn (string $part) => $part !== '',
            ));
            $notes = $notesParts !== []
                ? mb_substr(implode(' — ', $notesParts), 0, 500)
                : null;

            $rows[] = [
                'topic_id' => $topicId,
                'label' => $label,
                'notes' => $notes,
            ];
        }

        return $rows;
    }

    private function extractApplicationSessionsSection(string $markdown): string
    {
        $lines = preg_split('/\R/', $markdown) ?: [];
        $sectionStart = null;

        foreach ($lines as $index => $line) {
            $trimmed = trim($line);
            if (str_starts_with($trimmed, '## 3. Sesi aplikasi')) {
                $sectionStart = $index + 1;
                break;
            }
        }

        if ($sectionStart === null) {
            return $markdown;
        }

        $sectionLines = [];
        for ($i = $sectionStart, $count = count($lines); $i < $count; $i++) {
            $trimmed = trim($lines[$i]);
            if (preg_match('/^##\s/', $trimmed)) {
                break;
            }
            $sectionLines[] = $lines[$i];
        }

        return implode("\n", $sectionLines);
    }

    /**
     * @return list<string>
     */
    private function splitTableRow(string $line): array
    {
        $line = trim($line, '|');
        $parts = array_map('trim', explode('|', $line));

        return array_values(array_filter($parts, fn (string $part) => $part !== ''));
    }

    private function cleanLabel(string $label): string
    {
        $label = preg_replace('/\*\*\d+\*\*/', '', $label) ?? $label;
        $label = preg_replace('/\([^)]*\)/', '', $label) ?? $label;
        $label = str_replace('*', '', $label);

        return trim(preg_replace('/\s+/', ' ', $label) ?? $label);
    }
}
