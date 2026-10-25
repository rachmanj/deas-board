<?php

namespace App\Console\Commands;

use App\Models\Session;
use App\Support\ActivityLogger;
use App\Support\SessionMarkdownParser;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class ClickImportSessionsCommand extends Command
{
    protected $signature = 'click:import-sessions
                            {--from= : Path to markdown source file}
                            {--extra-sessions= : Path to JSON array of extra sessions (topic_id, label, notes)}
                            {--chat-id= : Optional chat_id for new sessions}
                            {--dry-run : Preview import without writing}
                            {--apply : Apply import to database}';

    protected $description = 'Import board sessions from a markdown table file';

    public function handle(SessionMarkdownParser $parser): int
    {
        $from = $this->option('from');
        if (! is_string($from) || $from === '') {
            $this->error('Opsi --from wajib diisi.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $apply = (bool) $this->option('apply');

        if ($dryRun === $apply) {
            $this->error('Pilih tepat satu: --dry-run atau --apply.');

            return self::FAILURE;
        }

        $path = $this->resolvePath($from);
        if (! File::isFile($path)) {
            $this->error("Berkas tidak ditemukan: {$path}");

            return self::FAILURE;
        }

        $rows = $parser->parse(File::get($path));
        $merged = $this->mergeExtraSessions($rows);
        if ($merged === null) {
            return self::FAILURE;
        }
        $rows = $merged;
        $chatId = $this->option('chat-id');
        $chatId = is_string($chatId) && $chatId !== '' ? $chatId : null;

        $existingTopicIds = Session::query()
            ->whereIn('topic_id', array_column($rows, 'topic_id'))
            ->pluck('topic_id')
            ->all();
        $existingSet = array_flip($existingTopicIds);

        $verbose = $this->output->isVerbose();
        $plan = [];
        $newCount = 0;
        $existingCount = 0;

        foreach ($rows as $row) {
            $exists = isset($existingSet[$row['topic_id']]);
            if ($exists) {
                $existingCount++;
                $status = 'ada';
            } else {
                $newCount++;
                $status = 'baru';
            }

            $plan[] = $this->planTableRow($row, $status, $verbose);
        }

        if ($dryRun) {
            $this->renderImportPlanTable($plan, $verbose);
            $this->line('Baris terbaca: '.count($rows));
            $this->line("Sesi baru: {$newCount}");
            $this->line("Sesi sudah ada (dilewati): {$existingCount}");

            return self::SUCCESS;
        }

        $this->renderImportPlanTable($plan, $verbose);

        $imported = 0;
        foreach ($rows as $row) {
            if (isset($existingSet[$row['topic_id']])) {
                continue;
            }

            $session = Session::create([
                'label' => $row['label'],
                'topic_id' => $row['topic_id'],
                'chat_id' => $chatId,
                'notes' => $row['notes'],
                'is_active' => true,
            ]);

            ActivityLogger::log('session.imported', null, $session, [
                'topic_id' => $row['topic_id'],
                'label' => $row['label'],
            ]);

            $existingSet[$row['topic_id']] = true;
            $imported++;
        }

        $this->info("Sesi diimpor: {$imported}");
        $this->line("Sesi sudah ada (dilewati): {$existingCount}");

        return self::SUCCESS;
    }

    /**
     * @param  list<array{topic_id: string, label: string, notes: string|null}>  $rows
     * @return list<array{topic_id: string, label: string, notes: string|null}>|null
     */
    private function mergeExtraSessions(array $rows): ?array
    {
        $extra = $this->option('extra-sessions');
        if (! is_string($extra) || $extra === '') {
            return $rows;
        }

        $extraPath = $this->resolvePath($extra);
        if (! File::isFile($extraPath)) {
            $this->error("Berkas extra-sessions tidak ditemukan: {$extraPath}");

            return null;
        }

        $decoded = json_decode(File::get($extraPath), true);
        if (! is_array($decoded)) {
            $this->error('Berkas extra-sessions harus berisi larik JSON.');

            return null;
        }

        $topicIds = array_flip(array_column($rows, 'topic_id'));

        foreach ($decoded as $item) {
            if (! is_array($item)) {
                continue;
            }

            $topicId = $item['topic_id'] ?? null;
            $label = $item['label'] ?? null;
            if (! is_string($topicId) || $topicId === '' || ! is_string($label) || $label === '') {
                continue;
            }

            if (isset($topicIds[$topicId])) {
                continue;
            }

            $notes = $item['notes'] ?? null;
            $notes = is_string($notes) && $notes !== '' ? mb_substr($notes, 0, 500) : null;

            $rows[] = [
                'topic_id' => $topicId,
                'label' => $label,
                'notes' => $notes,
            ];
            $topicIds[$topicId] = true;
        }

        return $rows;
    }

    private function resolvePath(string $from): string
    {
        if (str_starts_with($from, '~/')) {
            $home = getenv('HOME') ?: '';
            if ($home !== '') {
                return $home.substr($from, 1);
            }
        }

        return $from;
    }

    /**
     * @param  array{topic_id: string, label: string, notes: string|null}  $row
     * @return array<string, string>
     */
    private function planTableRow(array $row, string $status, bool $verbose): array
    {
        $fullNotes = $row['notes'] ?? '';
        $plan = [
            'topic_id' => $row['topic_id'],
            'label' => $row['label'],
            'catatan' => $this->truncateForTable($fullNotes, 40),
            'status' => $status,
        ];

        if ($verbose) {
            $plan['catatan_penuh'] = $fullNotes;
        }

        return $plan;
    }

    /**
     * @param  list<array<string, string>>  $plan
     */
    private function renderImportPlanTable(array $plan, bool $verbose): void
    {
        $headers = ['topic_id', 'label', 'catatan', 'status'];
        if ($verbose) {
            $headers[] = 'catatan_penuh';
        }

        $this->table($headers, $plan);
    }

    private function truncateForTable(string $text, int $maxLength): string
    {
        if ($text === '') {
            return '';
        }

        if (mb_strlen($text) <= $maxLength) {
            return $text;
        }

        return mb_substr($text, 0, $maxLength).'…';
    }
}
