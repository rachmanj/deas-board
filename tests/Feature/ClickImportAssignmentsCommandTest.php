<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Email;
use App\Models\EmailTaskLink;
use App\Models\Session;
use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ClickImportAssignmentsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_apply_creates_task_links_email_and_is_idempotent(): void
    {
        $session = Session::create([
            'label' => 'Assignee',
            'topic_id' => '88001',
            'is_active' => true,
        ]);

        $email = Email::create([
            'uid' => '5501',
            'mailbox' => 'INBOX',
            'from_email' => 'x@example.com',
            'status' => 'arsip',
        ]);

        $jsonPath = $this->writeAssignmentsJson([
            [
                'uid' => '5501',
                'title' => 'Tugas dari email',
                'description' => 'Detail tugas',
                'session_topic_id' => '88001',
                'status' => 'todo',
                'priority' => 'high',
                'external_ref' => 'ext-5501',
                'email_summary' => 'Ringkasan impor',
                'email_status' => 'baru',
            ],
        ]);

        $this->artisan('click:import-assignments', [
            '--from' => $jsonPath,
            '--apply' => true,
        ])->assertSuccessful();

        $task = Task::query()->where('external_ref', 'ext-5501')->first();
        $this->assertNotNull($task);
        $this->assertSame('email', $task->source);
        $this->assertSame($session->id, $task->assignee_session_id);
        $this->assertSame('Tugas dari email', $task->title);
        $this->assertTrue(str_starts_with($task->code, 'CLK-'));

        $this->assertSame(1, EmailTaskLink::query()->where('email_id', $email->id)->where('task_id', $task->id)->count());
        $email->refresh();
        $this->assertSame('Ringkasan impor', $email->summary);
        $this->assertSame('baru', $email->status);

        $this->assertNotNull(ActivityLog::query()->where('action', 'task.imported')->first());

        $this->artisan('click:import-assignments', [
            '--from' => $jsonPath,
            '--apply' => true,
        ])->assertSuccessful();

        $this->assertSame(1, Task::query()->where('external_ref', 'ext-5501')->count());
    }

    public function test_null_session_topic_id_creates_task_without_assignee(): void
    {
        Email::create([
            'uid' => '5503',
            'mailbox' => 'INBOX',
            'status' => 'arsip',
        ]);

        $jsonPath = $this->writeAssignmentsJson([
            [
                'uid' => '5503',
                'title' => 'Tanpa penanggung jawab',
                'session_topic_id' => null,
                'status' => 'todo',
                'priority' => 'normal',
                'external_ref' => 'ext-no-assignee',
            ],
        ]);

        $this->artisan('click:import-assignments', [
            '--from' => $jsonPath,
            '--apply' => true,
        ])
            ->assertSuccessful()
            ->expectsOutputToContain('Dibuat tanpa penanggung jawab: 1')
            ->expectsOutputToContain('Gagal: 0');

        $task = Task::query()->where('external_ref', 'ext-no-assignee')->first();
        $this->assertNotNull($task);
        $this->assertNull($task->assignee_session_id);
    }

    public function test_import_with_review_status_creates_task_as_todo(): void
    {
        Session::create([
            'label' => 'Assignee',
            'topic_id' => '88002',
            'is_active' => true,
        ]);

        Email::create([
            'uid' => '5510',
            'mailbox' => 'INBOX',
            'status' => 'arsip',
        ]);

        $jsonPath = $this->writeAssignmentsJson([
            [
                'uid' => '5510',
                'title' => 'Dari review',
                'session_topic_id' => '88002',
                'status' => 'review',
                'priority' => 'normal',
                'external_ref' => 'ext-review-downgrade',
            ],
        ]);

        $this->artisan('click:import-assignments', [
            '--from' => $jsonPath,
            '--apply' => true,
        ])->assertSuccessful();

        $task = Task::query()->where('external_ref', 'ext-review-downgrade')->first();
        $this->assertNotNull($task);
        $this->assertSame('todo', $task->status);
    }

    public function test_import_with_review_status_kept_when_keep_review_option(): void
    {
        Session::create([
            'label' => 'Assignee',
            'topic_id' => '88003',
            'is_active' => true,
        ]);

        Email::create([
            'uid' => '5511',
            'mailbox' => 'INBOX',
            'status' => 'arsip',
        ]);

        $jsonPath = $this->writeAssignmentsJson([
            [
                'uid' => '5511',
                'title' => 'Tetap review',
                'session_topic_id' => '88003',
                'status' => 'review',
                'priority' => 'normal',
                'external_ref' => 'ext-review-kept',
            ],
        ]);

        $this->artisan('click:import-assignments', [
            '--from' => $jsonPath,
            '--apply' => true,
            '--keep-review' => true,
        ])->assertSuccessful();

        $task = Task::query()->where('external_ref', 'ext-review-kept')->first();
        $this->assertNotNull($task);
        $this->assertSame('review', $task->status);
    }

    public function test_reimport_does_not_change_existing_task_status(): void
    {
        $session = Session::create([
            'label' => 'Assignee',
            'topic_id' => '88004',
            'is_active' => true,
        ]);

        $email = Email::create([
            'uid' => '5512',
            'mailbox' => 'INBOX',
            'status' => 'arsip',
        ]);

        $task = Task::create([
            'code' => 'CLK-99',
            'title' => 'Sudah ada',
            'status' => 'in_progress',
            'priority' => 'normal',
            'assignee_session_id' => $session->id,
            'source' => 'email',
            'external_ref' => 'ext-existing-status',
        ]);

        EmailTaskLink::create([
            'email_id' => $email->id,
            'task_id' => $task->id,
            'linked_at' => now(),
        ]);

        $jsonPath = $this->writeAssignmentsJson([
            [
                'uid' => '5512',
                'title' => 'Sudah ada',
                'session_topic_id' => '88004',
                'status' => 'review',
                'priority' => 'normal',
                'external_ref' => 'ext-existing-status',
            ],
        ]);

        $this->artisan('click:import-assignments', [
            '--from' => $jsonPath,
            '--apply' => true,
        ])
            ->assertSuccessful()
            ->expectsOutputToContain('Dilewati: 1');

        $task->refresh();
        $this->assertSame('in_progress', $task->status);
    }

    public function test_dry_run_shows_final_status_without_writing(): void
    {
        Session::create([
            'label' => 'Assignee',
            'topic_id' => '88005',
            'is_active' => true,
        ]);

        Email::create([
            'uid' => '5513',
            'mailbox' => 'INBOX',
            'status' => 'arsip',
        ]);

        $jsonPath = $this->writeAssignmentsJson([
            [
                'uid' => '5513',
                'title' => 'Dry run review',
                'session_topic_id' => '88005',
                'status' => 'review',
                'priority' => 'normal',
                'external_ref' => 'ext-dry-run-review',
            ],
        ]);

        $this->artisan('click:import-assignments', [
            '--from' => $jsonPath,
            '--dry-run' => true,
        ])
            ->assertSuccessful()
            ->expectsOutputToContain('todo (dari review)');

        $this->assertSame(0, Task::query()->where('external_ref', 'ext-dry-run-review')->count());
    }

    public function test_unknown_session_is_reported_as_failed(): void
    {
        Email::create([
            'uid' => '5502',
            'mailbox' => 'INBOX',
            'status' => 'arsip',
        ]);

        $jsonPath = $this->writeAssignmentsJson([
            [
                'uid' => '5502',
                'title' => 'Tanpa sesi',
                'session_topic_id' => 'missing-topic',
                'status' => 'todo',
                'priority' => 'normal',
                'external_ref' => 'ext-missing-session',
            ],
        ]);

        $this->artisan('click:import-assignments', [
            '--from' => $jsonPath,
            '--dry-run' => true,
        ])
            ->assertSuccessful()
            ->expectsOutputToContain('Gagal: 1');

        $this->assertSame(0, Task::count());
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function writeAssignmentsJson(array $rows): string
    {
        $dir = storage_path('framework/testing/assignments');
        File::ensureDirectoryExists($dir);
        $path = $dir.'/'.uniqid('assign-', true).'.json';
        File::put($path, json_encode($rows, JSON_PRETTY_PRINT));

        return $path;
    }
}
