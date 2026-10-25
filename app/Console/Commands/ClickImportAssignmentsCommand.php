<?php

namespace App\Console\Commands;

use App\Models\Email;
use App\Models\EmailTaskLink;
use App\Models\Session;
use App\Models\Task;
use App\Support\ActivityLogger;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class ClickImportAssignmentsCommand extends Command
{
    protected $signature = 'click:import-assignments
                            {--from= : Path to JSON assignments file}
                            {--dry-run : Preview import without writing}
                            {--apply : Apply import to database}';

    protected $description = 'Import tasks from JSON and link them to emails';

    public function handle(): int
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

        $items = json_decode(File::get($path), true);
        if (! is_array($items)) {
            $this->error('Berkas JSON tidak valid.');

            return self::FAILURE;
        }

        $created = 0;
        $skipped = 0;
        $failed = 0;
        $planRows = [];

        foreach ($items as $item) {
            if (! is_array($item)) {
                $failed++;
                $planRows[] = ['uid' => '-', 'external_ref' => '-', 'hasil' => 'gagal (format)'];

                continue;
            }

            $uid = (string) ($item['uid'] ?? '');
            $externalRef = $item['external_ref'] ?? null;
            $sessionTopicId = isset($item['session_topic_id']) ? (string) $item['session_topic_id'] : null;

            $email = $uid !== ''
                ? Email::query()->where('mailbox', 'INBOX')->where('uid', $uid)->first()
                : null;

            $session = $sessionTopicId !== null
                ? Session::query()->where('topic_id', $sessionTopicId)->first()
                : null;

            if ($email === null || $session === null) {
                $failed++;
                $reason = $email === null ? 'email tidak ditemukan' : 'sesi tidak ditemukan';
                $planRows[] = [
                    'uid' => $uid,
                    'external_ref' => (string) ($externalRef ?? '-'),
                    'hasil' => "gagal ({$reason})",
                ];

                continue;
            }

            $alreadyLinked = $externalRef !== null && $externalRef !== ''
                && Task::query()
                    ->where('external_ref', $externalRef)
                    ->whereHas('emails', fn ($q) => $q->where('emails.id', $email->id))
                    ->exists();

            if ($alreadyLinked) {
                $skipped++;
                $planRows[] = [
                    'uid' => $uid,
                    'external_ref' => (string) $externalRef,
                    'hasil' => 'dilewati',
                ];

                continue;
            }

            if ($dryRun) {
                $created++;
                $planRows[] = [
                    'uid' => $uid,
                    'external_ref' => (string) ($externalRef ?? '-'),
                    'hasil' => 'dibuat',
                ];

                continue;
            }

            DB::transaction(function () use ($item, $email, $session, &$created) {
                $placeholder = 'tmp-'.uniqid();

                $task = Task::create([
                    'code' => $placeholder,
                    'title' => (string) ($item['title'] ?? 'Tanpa judul'),
                    'description_md' => $item['description'] ?? null,
                    'status' => (string) ($item['status'] ?? 'todo'),
                    'priority' => (string) ($item['priority'] ?? 'normal'),
                    'assignee_session_id' => $session->id,
                    'source' => 'email',
                    'external_ref' => $item['external_ref'] ?? null,
                ]);

                $task->update(['code' => 'CLK-'.$task->id]);

                EmailTaskLink::create([
                    'email_id' => $email->id,
                    'task_id' => $task->id,
                    'linked_at' => now(),
                ]);

                if (! empty($item['email_summary']) && $email->summary === null) {
                    $email->summary = (string) $item['email_summary'];
                }

                if (! empty($item['email_status'])) {
                    $email->status = (string) $item['email_status'];
                }

                $email->save();

                ActivityLogger::log('task.imported', null, $task, [
                    'uid' => $email->uid,
                    'external_ref' => $task->external_ref,
                ]);

                $created++;
            });

            $planRows[] = [
                'uid' => $uid,
                'external_ref' => (string) ($externalRef ?? '-'),
                'hasil' => 'dibuat',
            ];
        }

        if ($dryRun) {
            $this->table(['uid', 'external_ref', 'hasil'], $planRows);
        }

        $this->line("Dibuat: {$created}");
        $this->line("Dilewati: {$skipped}");
        $this->line("Gagal: {$failed}");

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
