<?php

namespace Tests\Feature;

use App\Models\Email;
use App\Models\Session;
use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Tests\Concerns\ActsAsApiActor;
use Tests\TestCase;

class TaskDoneAtTest extends TestCase
{
    use ActsAsApiActor;
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_redone_after_reopen_sets_done_at_to_latest_transition(): void
    {
        $session = Session::create(['label' => 'S', 'topic_id' => '901', 'is_active' => true]);

        Carbon::setTestNow('2026-11-02 15:34:51');
        $task = Task::create([
            'code' => 'CLK-83',
            'title' => 'Reopened task',
            'status' => 'in_progress',
            'priority' => 'normal',
            'assignee_session_id' => $session->id,
        ]);

        $this->asSession($session)->patchJson("/api/tasks/{$task->id}", [
            'status' => 'done',
            'evidence' => 'Selesai commit cafebabe1234567',
        ])->assertOk();

        $task->refresh();
        $this->assertSame('2026-11-02T15:34:51+00:00', $task->done_at?->toIso8601String());

        Carbon::setTestNow('2026-11-03 08:00:00');
        $this->asSession($session)->patchJson("/api/tasks/{$task->id}", [
            'status' => 'todo',
        ])->assertOk();

        $task->refresh();
        $this->assertNull($task->done_at);

        Carbon::setTestNow('2026-11-03 14:00:00');
        $this->asSession($session)->patchJson("/api/tasks/{$task->id}", [
            'status' => 'in_progress',
        ])->assertOk();

        Carbon::setTestNow('2026-11-04 09:15:22');
        $this->asSession($session)->patchJson("/api/tasks/{$task->id}", [
            'status' => 'done',
            'evidence' => 'Selesai commit deadbeef1234567',
        ])->assertOk();

        $task->refresh();
        $this->assertSame('2026-11-04T09:15:22+00:00', $task->done_at?->toIso8601String());
    }

    public function test_leaving_done_clears_done_at_via_post_update(): void
    {
        $session = Session::create(['label' => 'S', 'topic_id' => '902', 'is_active' => true]);
        $task = Task::create([
            'code' => 'CLK-1',
            'title' => 'Was done',
            'status' => 'done',
            'priority' => 'normal',
            'done_at' => now(),
            'assignee_session_id' => $session->id,
        ]);

        $this->asSession($session)->postJson("/api/tasks/{$task->id}/updates", [
            'status' => 'blocked',
            'note' => 'Menunggu dependency',
        ])->assertCreated();

        $task->refresh();
        $this->assertSame('blocked', $task->status);
        $this->assertNull($task->done_at);
    }

    public function test_import_uses_source_done_at_when_present(): void
    {
        Session::create(['label' => 'Assignee', 'topic_id' => '903', 'is_active' => true]);
        Email::create(['uid' => '9031', 'mailbox' => 'INBOX', 'status' => 'arsip']);

        $dir = storage_path('framework/testing/assignments');
        File::ensureDirectoryExists($dir);
        $path = $dir.'/done-at-'.uniqid('', true).'.json';
        File::put($path, json_encode([
            [
                'uid' => '9031',
                'title' => 'Imported done',
                'session_topic_id' => '903',
                'status' => 'done',
                'priority' => 'normal',
                'external_ref' => 'ext-done-at-source',
                'done_at' => '2026-10-15T08:30:00+00:00',
            ],
        ], JSON_PRETTY_PRINT));

        $this->artisan('click:import-assignments', [
            '--from' => $path,
            '--apply' => true,
        ])->assertSuccessful();

        $task = Task::query()->where('external_ref', 'ext-done-at-source')->first();
        $this->assertNotNull($task);
        $this->assertSame('2026-10-15T08:30:00+00:00', $task->done_at?->toIso8601String());
    }

    public function test_import_without_source_done_at_uses_now(): void
    {
        Carbon::setTestNow('2026-11-04 12:00:00');
        Session::create(['label' => 'Assignee', 'topic_id' => '904', 'is_active' => true]);
        Email::create(['uid' => '9041', 'mailbox' => 'INBOX', 'status' => 'arsip']);

        $dir = storage_path('framework/testing/assignments');
        File::ensureDirectoryExists($dir);
        $path = $dir.'/done-at-now-'.uniqid('', true).'.json';
        File::put($path, json_encode([
            [
                'uid' => '9041',
                'title' => 'Imported done tanpa done_at',
                'session_topic_id' => '904',
                'status' => 'done',
                'priority' => 'normal',
                'external_ref' => 'ext-done-at-now',
            ],
        ], JSON_PRETTY_PRINT));

        $this->artisan('click:import-assignments', [
            '--from' => $path,
            '--apply' => true,
        ])->assertSuccessful();

        $task = Task::query()->where('external_ref', 'ext-done-at-now')->first();
        $this->assertNotNull($task);
        $this->assertSame('2026-11-04T12:00:00+00:00', $task->done_at?->toIso8601String());
    }

    public function test_fix_done_at_dry_run_reports_candidates_without_writing(): void
    {
        $task = Task::create([
            'code' => 'CLK-fix',
            'title' => 'Stale done_at',
            'status' => 'done',
            'priority' => 'normal',
            'done_at' => Carbon::parse('2026-11-02 15:34:51'),
        ]);
        $task->forceFill(['updated_at' => Carbon::parse('2026-11-04 09:15:22')])->saveQuietly();

        $this->artisan('click:fix-done-at', ['--dry-run' => true])
            ->expectsOutputToContain('Kandidat perbaikan: 1')
            ->assertSuccessful();

        $task->refresh();
        $this->assertSame('2026-11-02T15:34:51+00:00', $task->done_at?->toIso8601String());
    }
}
