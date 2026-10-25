<?php

namespace App\Console\Commands;

use App\Models\Email;
use App\Support\ActivityLogger;
use App\Support\EmlMetadataParser;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class ClickImportEmailsCommand extends Command
{
    protected $signature = 'click:import-emails
                            {--dir= : Directory containing uid* folders}
                            {--dry-run : Preview import without writing}
                            {--apply : Apply import to database}
                            {--limit=0 : Max .eml files to process (0 = no limit)}';

    protected $description = 'Import email metadata from .eml files in uid* folders';

    public function handle(EmlMetadataParser $parser): int
    {
        $dir = $this->option('dir');
        if (! is_string($dir) || $dir === '') {
            $this->error('Opsi --dir wajib diisi.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $apply = (bool) $this->option('apply');

        if ($dryRun === $apply) {
            $this->error('Pilih tepat satu: --dry-run atau --apply.');

            return self::FAILURE;
        }

        $path = $this->resolvePath($dir);
        if (! File::isDirectory($path)) {
            $this->error("Direktori tidak ditemukan: {$path}");

            return self::FAILURE;
        }

        $limit = (int) $this->option('limit');
        ['entries' => $entries, 'duplicates' => $duplicateMessageIds] = $this->collectEntries($path, $limit, $parser);

        $mailbox = 'INBOX';
        $uids = array_column($entries, 'uid');
        $existingUids = Email::query()
            ->where('mailbox', $mailbox)
            ->whereIn('uid', $uids)
            ->pluck('uid')
            ->all();
        $existingSet = array_flip($existingUids);

        $toImport = 0;
        $skipped = 0;
        $plan = [];

        foreach ($entries as $entry) {
            $exists = isset($existingSet[$entry['uid']]);
            if ($exists) {
                $skipped++;
            } else {
                $toImport++;
            }

            if (count($plan) < 5) {
                $meta = $parser->parse(File::get($entry['path']), filemtime($entry['path']));
                $plan[] = [
                    'uid' => $entry['uid'],
                    'from' => $meta['from_email'] ?? '-',
                    'subject' => $meta['subject'] ?? '-',
                    'status' => $exists ? 'ada' : 'baru',
                ];
            }
        }

        $folderCount = count(array_unique(array_column($entries, 'folder')));
        $emlCount = count($entries);

        if ($dryRun) {
            $this->table(['uid', 'from', 'subject', 'status'], $plan);
            $this->line("Folder terbaca: {$folderCount}");
            $this->line("Berkas .eml: {$emlCount}");
            $this->line("Akan diimpor: {$toImport}");
            $this->line("Sudah ada (dilewati): {$skipped}");
            $this->line("Duplikat Message-ID (dilewati): {$duplicateMessageIds}");

            return self::SUCCESS;
        }

        $imported = 0;
        foreach ($entries as $entry) {
            if (isset($existingSet[$entry['uid']])) {
                continue;
            }

            $raw = File::get($entry['path']);
            $meta = $parser->parse($raw, filemtime($entry['path']));

            $email = Email::create([
                'uid' => $entry['uid'],
                'mailbox' => $mailbox,
                'from_name' => $meta['from_name'],
                'from_email' => $meta['from_email'],
                'to' => $meta['to'],
                'cc' => $meta['cc'],
                'subject' => $meta['subject'],
                'received_at' => $meta['received_at'],
                'summary' => null,
                'has_full_body' => false,
                'body_text' => null,
                'status' => 'arsip',
            ]);

            ActivityLogger::log('email.imported', null, $email, [
                'uid' => $entry['uid'],
                'mailbox' => $mailbox,
            ]);

            $existingSet[$entry['uid']] = true;
            $imported++;
        }

        $this->info("Email diimpor: {$imported}");
        $this->line("Sudah ada (dilewati): {$skipped}");
        $this->line("Duplikat Message-ID (dilewati): {$duplicateMessageIds}");

        return self::SUCCESS;
    }

    /**
     * @return array{entries: list<array{uid: string, path: string, folder: string}>, duplicates: int}
     */
    private function collectEntries(string $dir, int $limit, EmlMetadataParser $parser): array
    {
        $entries = [];
        $duplicates = 0;
        $folders = File::directories($dir);

        foreach ($folders as $folder) {
            $folderName = basename($folder);
            if (! preg_match('/^uid/i', $folderName)) {
                continue;
            }

            if (! preg_match('/^uid([^-]+)-/i', $folderName, $match)) {
                continue;
            }

            $baseUid = $match[1];
            $emlFiles = collect(File::files($folder))
                ->filter(fn ($file) => strtolower($file->getExtension()) === 'eml')
                ->sortBy(fn ($file) => $file->getFilename())
                ->values();

            $count = $emlFiles->count();
            $index = 0;
            $seenMessageIds = [];
            $baseUidUsedForMessageId = false;

            foreach ($emlFiles as $file) {
                if ($limit > 0 && count($entries) >= $limit) {
                    return ['entries' => $entries, 'duplicates' => $duplicates];
                }

                $index++;
                $raw = File::get($file->getPathname());
                $messageId = $parser->parse($raw, filemtime($file->getPathname()))['message_id'] ?? null;
                $messageId = is_string($messageId) ? trim($messageId) : null;

                if ($messageId !== null && $messageId !== '') {
                    if (isset($seenMessageIds[$messageId])) {
                        $duplicates++;

                        continue;
                    }
                    $seenMessageIds[$messageId] = true;

                    if (! $baseUidUsedForMessageId) {
                        $uid = $baseUid;
                        $baseUidUsedForMessageId = true;
                    } else {
                        $uid = "{$baseUid}-{$index}";
                    }
                } else {
                    $uid = $count > 1 ? "{$baseUid}-{$index}" : $baseUid;
                }

                $entries[] = [
                    'uid' => $uid,
                    'path' => $file->getPathname(),
                    'folder' => $folderName,
                ];
            }
        }

        return ['entries' => $entries, 'duplicates' => $duplicates];
    }

    private function resolvePath(string $path): string
    {
        if (str_starts_with($path, '~/')) {
            $home = getenv('HOME') ?: '';
            if ($home !== '') {
                return $home.substr($path, 1);
            }
        }

        return $path;
    }
}
