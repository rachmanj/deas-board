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
        $chatId = $this->option('chat-id');
        $chatId = is_string($chatId) && $chatId !== '' ? $chatId : null;

        $existingTopicIds = Session::query()
            ->whereIn('topic_id', array_column($rows, 'topic_id'))
            ->pluck('topic_id')
            ->all();
        $existingSet = array_flip($existingTopicIds);

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

            $plan[] = [
                'topic_id' => $row['topic_id'],
                'label' => $row['label'],
                'status' => $status,
            ];
        }

        if ($dryRun) {
            $this->table(['topic_id', 'label', 'status'], $plan);
            $this->line("Baris terbaca: ".count($rows));
            $this->line("Sesi baru: {$newCount}");
            $this->line("Sesi sudah ada (dilewati): {$existingCount}");

            return self::SUCCESS;
        }

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
}
