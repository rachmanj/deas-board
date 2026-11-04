<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Email;
use App\Models\Session;
use App\Models\Task;
use App\Models\TaskComment;
use App\Models\TaskUpdate;
use App\Models\User;
use App\Support\EvidenceRule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Tests\Concerns\ActsAsApiActor;
use Tests\TestCase;

class EvidenceRuleTest extends TestCase
{
    use ActsAsApiActor;
    use RefreshDatabase;

    public function test_patch_done_without_evidence_returns_422_and_logs_rejection(): void
    {
        $session = Session::create(['label' => 'S', 'topic_id' => '901', 'is_active' => true]);
        $task = Task::create([
            'code' => 'CLK-1',
            'title' => 'Need proof',
            'status' => 'in_progress',
            'priority' => 'normal',
        ]);

        $response = $this->asSession($session)->patchJson("/api/tasks/{$task->id}", [
            'status' => 'done',
        ]);

        $response->assertStatus(422);
        $this->assertStringContainsString(EvidenceRule::DONE_MESSAGE, (string) $response->json('message'));
        $this->assertStringContainsString('7 hari terakhir', (string) $response->json('message'));

        $task->refresh();
        $this->assertSame('in_progress', $task->status);
        $this->assertSame(0, TaskUpdate::query()->count());

        $log = ActivityLog::query()->where('action', 'task.evidence_rejected')->first();
        $this->assertNotNull($log);
        $this->assertSame('done', $log->changes['requested_status']);
    }

    public function test_patch_done_after_commit_comment_succeeds_and_records_update(): void
    {
        $session = Session::create(['label' => 'S', 'topic_id' => '902', 'is_active' => true]);
        $task = Task::create([
            'code' => 'CLK-2',
            'title' => 'With comment proof',
            'status' => 'review',
            'priority' => 'normal',
        ]);

        $this->asSession($session)->postJson("/api/tasks/{$task->id}/comments", [
            'body_md' => 'Selesai, commit deadbeef1234567',
        ])->assertCreated();

        $response = $this->asSession($session)->patchJson("/api/tasks/{$task->id}", [
            'status' => 'done',
        ]);

        $response->assertOk();
        $task->refresh();
        $this->assertSame('done', $task->status);
        $this->assertSame(1, TaskUpdate::query()->where('to_status', 'done')->count());
    }

    public function test_post_update_to_done_requires_evidence_field(): void
    {
        $session = Session::create(['label' => 'S', 'topic_id' => '903', 'is_active' => true]);
        $task = Task::create([
            'code' => 'CLK-3',
            'title' => 'Update path',
            'status' => 'in_progress',
            'priority' => 'normal',
        ]);

        $this->asSession($session)->postJson("/api/tasks/{$task->id}/updates", [
            'status' => 'done',
        ])->assertStatus(422);

        $this->asSession($session)->postJson("/api/tasks/{$task->id}/updates", [
            'status' => 'done',
            'evidence' => 'commit abc1234',
        ])->assertCreated();

        $task->refresh();
        $this->assertSame('done', $task->status);
    }

    public function test_review_transition_requires_evidence(): void
    {
        $session = Session::create(['label' => 'S', 'topic_id' => '904', 'is_active' => true]);
        $task = Task::create([
            'code' => 'CLK-4',
            'title' => 'Review gate',
            'status' => 'in_progress',
            'priority' => 'normal',
        ]);

        $this->asSession($session)->patchJson("/api/tasks/{$task->id}", [
            'status' => 'review',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['evidence']);

        $this->asSession($session)->patchJson("/api/tasks/{$task->id}", [
            'status' => 'review',
            'evidence' => 'PR siap dicek',
        ])->assertOk();

        $task->refresh();
        $this->assertSame('review', $task->status);
    }

    public function test_web_board_status_done_without_evidence_keeps_status_and_shows_error(): void
    {
        $user = User::factory()->create();
        $task = Task::create([
            'code' => 'CLK-5',
            'title' => 'Kartu web',
            'status' => 'todo',
            'priority' => 'normal',
        ]);

        $this->actingAs($user)
            ->from('/')
            ->patch(route('tasks.status', $task), [
                'status' => 'done',
                'redirect' => '/',
            ])
            ->assertRedirect('/')
            ->assertSessionHasErrors('status');

        $task->refresh();
        $this->assertSame('todo', $task->status);
    }

    public function test_import_can_create_task_with_done_status(): void
    {
        Session::create([
            'label' => 'Assignee',
            'topic_id' => '90501',
            'is_active' => true,
        ]);

        Email::create([
            'uid' => '9051',
            'mailbox' => 'INBOX',
            'status' => 'arsip',
        ]);

        $dir = storage_path('framework/testing/assignments');
        File::ensureDirectoryExists($dir);
        $path = $dir.'/evidence-import-'.uniqid('', true).'.json';
        File::put($path, json_encode([
            [
                'uid' => '9051',
                'title' => 'Sudah selesai di sumber',
                'session_topic_id' => '90501',
                'status' => 'done',
                'priority' => 'normal',
                'external_ref' => 'ext-import-done',
            ],
        ], JSON_PRETTY_PRINT));

        $this->artisan('click:import-assignments', [
            '--from' => $path,
            '--apply' => true,
        ])->assertSuccessful();

        $task = Task::query()->where('external_ref', 'ext-import-done')->first();
        $this->assertNotNull($task);
        $this->assertSame('done', $task->status);
    }

    public function test_old_bukti_comment_before_last_in_progress_does_not_allow_done(): void
    {
        Carbon::setTestNow('2026-06-10 12:00:00');
        $session = Session::create(['label' => 'S', 'topic_id' => '906', 'is_active' => true]);
        $task = Task::create([
            'code' => 'CLK-83',
            'title' => 'Stale bukti',
            'status' => 'todo',
            'priority' => 'normal',
            'created_at' => Carbon::parse('2026-06-01 08:00:00'),
        ]);

        $stale = TaskComment::create([
            'task_id' => $task->id,
            'body_md' => 'Ini bukti lama sebelum mulai kerja',
            'author_type' => Session::class,
            'author_id' => $session->id,
        ]);
        $stale->created_at = Carbon::parse('2026-06-01 09:00:00');
        $stale->saveQuietly();

        $inProgressAt = Carbon::parse('2026-06-10 12:00:00');
        TaskUpdate::create([
            'task_id' => $task->id,
            'from_status' => 'todo',
            'to_status' => 'in_progress',
            'created_at' => $inProgressAt,
        ]);
        $task->update(['status' => 'in_progress']);

        $reject = $this->asSession($session)->patchJson("/api/tasks/{$task->id}", [
            'status' => 'done',
        ]);
        $reject->assertStatus(422);
        $this->assertStringContainsString('in_progress terakhir (2026-06-10 12:00)', (string) $reject->json('message'));

        Carbon::setTestNow('2026-06-10 13:00:00');
        $this->asSession($session)->postJson("/api/tasks/{$task->id}/comments", [
            'body_md' => 'Selesai commit cafebabe1234567',
        ])->assertCreated();

        $this->asSession($session)->patchJson("/api/tasks/{$task->id}", [
            'status' => 'done',
        ])->assertOk();

        $task->refresh();
        $this->assertSame('done', $task->status);

        Carbon::setTestNow();
    }
}
