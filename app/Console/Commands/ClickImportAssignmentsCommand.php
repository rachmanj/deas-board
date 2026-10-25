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
        $createdWithoutAssignee = 0;
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
            $hasSessionTopic = array_key_exists('session_topic_id', $item)
                && $item['session_topic_id'] !== null
                && (string) $item['session_topic_id'] !== '';
            $sessionTopicId = $hasSessionTopic ? (string) $item['session_topic_id'] : null;

            $email = $uid !== ''
                ? Email::query()->where('mailbox', 'INBOX')->where('uid', $uid)->first()
                : null;

            if ($email === null) {
                $failed++;
                $planRows[] = [
                    'uid' => $uid,
                    'external_ref' => (string) ($externalRef ?? '-'),
                    'hasil' => 'gagal (email tidak ditemukan)',
                ];

                continue;
            }

            $session = $hasSessionTopic
                ? Session::query()->where('topic_id', $sessionTopicId)->first()
                : null;

            if ($hasSessionTopic && $session === null) {
                $failed++;
                $planRows[] = [
                    'uid' => $uid,
                    'external_ref' => (string) ($externalRef ?? '-'),
                    'hasil' => 'gagal (sesi tidak ditemukan)',
                ];

                continue;
            }

            $createdLabel = $hasSessionTopic ? 'dibuat' : 'dibuat (tanpa penanggung jawab)';

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
                if ($hasSessionTopic) {
                    $created++;
                } else {
                    $createdWithoutAssignee++;
                }
                $planRows[] = [
                    'uid' => $uid,
                    'external_ref' => (string) ($externalRef ?? '-'),
                    'hasil' => $createdLabel,
                ];

                continue;
            }

            DB::transaction(function () use ($item, $email, $session, $hasSessionTopic, &$created, &$createdWithoutAssignee) {
                $placeholder = 'tmp-'.uniqid();

                $task = Task::create([
                    'code' => $placeholder,
                    'title' => (string) ($item['title'] ?? 'Tanpa judul'),
                    'description_md' => $item['description'] ?? null,
                    'status' => (string) ($item['status'] ?? 'todo'),
                    'priority' => (string) ($item['priority'] ?? 'normal'),
                    'assignee_session_id' => $session?->id,
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

                if ($hasSessionTopic) {
                    $created++;
                } else {
                    $createdWithoutAssignee++;
                }
            });

            $planRows[] = [
                'uid' => $uid,
                'external_ref' => (string) ($externalRef ?? '-'),
                'hasil' => $createdLabel,
            ];
        }

        if ($dryRun) {
            $this->table(['uid', 'external_ref', 'hasil'], $planRows);
        }

        $this->line("Dibuat: {$created}");
        $this->line("Dibuat tanpa penanggung jawab: {$createdWithoutAssignee}");
        $this->line("Gagal: {$failed}");
        $this->line("Dilewati: {$skipped}");

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
