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
