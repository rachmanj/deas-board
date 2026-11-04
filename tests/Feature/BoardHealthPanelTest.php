<?php

namespace Tests\Feature;

use App\Models\Session;
use App\Models\Task;
use App\Models\TaskUpdate;
use App\Models\User;
use App\Support\HealthThresholds;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class BoardHealthPanelTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        return User::factory()->create();
    }

    public function test_health_page_lists_each_deviation_category(): void
    {
        Carbon::setTestNow('2026-11-04 12:00:00');

        $session = Session::create(['label' => 'H', 'topic_id' => '801', 'is_active' => true]);

        $staleProgress = Task::create([
            'code' => 'CLK-IP',
            'title' => 'Progress basi',
            'status' => 'in_progress',
            'priority' => 'normal',
            'assignee_session_id' => $session->id,
        ]);
        $staleProgress->forceFill(['updated_at' => now()->subHours(HealthThresholds::IN_PROGRESS_STALE_HOURS + 1)])->save();

        $staleReview = Task::create([
            'code' => 'CLK-RV',
            'title' => 'Review basi',
            'status' => 'review',
            'priority' => 'normal',
            'assignee_session_id' => $session->id,
        ]);
        $staleReview->forceFill(['updated_at' => now()->subHours(HealthThresholds::REVIEW_STALE_HOURS + 1)])->save();

        $doneNoProof = Task::create([
            'code' => 'CLK-DN',
            'title' => 'Done tanpa bukti',
            'status' => 'done',
            'priority' => 'normal',
            'assignee_session_id' => $session->id,
            'done_at' => now(),
        ]);

        TaskUpdate::create([
            'task_id' => $doneNoProof->id,
            'from_status' => 'in_progress',
            'to_status' => 'done',
            'actor_type' => Session::class,
            'actor_id' => $session->id,
        ]);

        $todoCommit = Task::create([
            'code' => 'CLK-TC',
            'title' => 'Sudah commit belum naik',
            'status' => 'todo',
            'priority' => 'normal',
            'external_ref' => 'abc1234567890deadbeef',
        ]);

        TaskUpdate::create([
            'task_id' => $staleProgress->id,
            'from_status' => 'todo',
            'to_status' => 'in_progress',
            'actor_type' => Session::class,
            'actor_id' => $session->id,
        ])->forceFill([
            'created_at' => now()->subHours(HealthThresholds::IN_PROGRESS_STALE_HOURS + 2),
            'updated_at' => now()->subHours(HealthThresholds::IN_PROGRESS_STALE_HOURS + 2),
        ])->save();

        TaskUpdate::create([
            'task_id' => $staleReview->id,
            'from_status' => 'in_progress',
            'to_status' => 'review',
            'actor_type' => Session::class,
            'actor_id' => $session->id,
        ])->forceFill([
            'created_at' => now()->subHours(HealthThresholds::REVIEW_STALE_HOURS + 2),
            'updated_at' => now()->subHours(HealthThresholds::REVIEW_STALE_HOURS + 2),
        ])->save();

        $response = $this->actingAs($this->user())->get('/health');

        $response->assertOk()
            ->assertSee('Progress basi')
            ->assertSee('Review basi')
            ->assertSee('Done tanpa bukti')
            ->assertSee('Sudah commit belum naik')
            ->assertSee('CLK-IP')
            ->assertSee('H (801)', false);
    }

    public function test_board_health_summary_shows_counts_and_hides_when_all_zero(): void
    {
        Carbon::setTestNow('2026-11-04 12:00:00');
        $user = $this->user();

        Task::create([
            'code' => 'CLK-OK',
            'title' => 'Normal todo',
            'status' => 'todo',
            'priority' => 'normal',
        ]);

        $cleanBoard = $this->actingAs($user)->get('/');
        $cleanBoard->assertOk()
            ->assertDontSee('Kesehatan papan', false);

        Task::create([
            'code' => 'CLK-BAD',
            'title' => 'Commit di judul',
            'status' => 'todo',
            'priority' => 'normal',
        ]);

        $dirtyBoard = $this->actingAs($user)->get('/');
        $dirtyBoard->assertOk()
            ->assertSee('Kesehatan papan', false)
            ->assertSee('Todo indikasi commit')
            ->assertSee(route('health').'#todo-commit-hint', false);
        $this->assertMatchesRegularExpression('/Todo indikasi commit[\s\S]*?>\s*1\s*</', $dirtyBoard->getContent());
    }

    public function test_done_without_evidence_respects_days_and_session_trace(): void
    {
        Carbon::setTestNow('2026-11-04 12:00:00');
        $session = Session::create(['label' => 'H', 'topic_id' => '801', 'is_active' => true]);

        $oldDone = Task::create([
            'code' => 'CLK-OLD-D',
            'title' => 'Done lama',
            'status' => 'done',
            'priority' => 'normal',
            'assignee_session_id' => $session->id,
            'done_at' => now()->subDays(10),
        ]);
        TaskUpdate::create([
            'task_id' => $oldDone->id,
            'from_status' => 'in_progress',
            'to_status' => 'done',
            'actor_type' => Session::class,
            'actor_id' => $session->id,
        ]);

        $recentDone = Task::create([
            'code' => 'CLK-REC-D',
            'title' => 'Done baru',
            'status' => 'done',
            'priority' => 'normal',
            'assignee_session_id' => $session->id,
            'done_at' => now()->subDays(3),
        ]);
        TaskUpdate::create([
            'task_id' => $recentDone->id,
            'from_status' => 'review',
            'to_status' => 'done',
            'actor_type' => Session::class,
            'actor_id' => $session->id,
        ]);

        Task::create([
            'code' => 'CLK-IMP',
            'title' => 'Impor tanpa sesi',
            'status' => 'done',
            'priority' => 'normal',
            'source' => 'email',
            'done_at' => now()->subDays(2),
        ]);

        $withinSeven = \App\Support\BoardHealth::doneWithoutEvidence(7);
        $this->assertSame(1, $withinSeven->count());
        $this->assertTrue($withinSeven->contains(fn (Task $t) => $t->code === 'CLK-REC-D'));
        $this->assertFalse($withinSeven->contains(fn (Task $t) => $t->code === 'CLK-OLD-D'));
        $this->assertFalse($withinSeven->contains(fn (Task $t) => $t->code === 'CLK-IMP'));
        $this->assertSame(2, \App\Support\BoardHealth::doneWithoutEvidence(30)->count());
    }

    public function test_health_page_days_query_changes_done_section_row_count(): void
    {
        Carbon::setTestNow('2026-11-04 12:00:00');
        $session = Session::create(['label' => 'H', 'topic_id' => '801', 'is_active' => true]);

        foreach (['CLK-7D' => 6, 'CLK-12D' => 12] as $code => $ageDays) {
            $task = Task::create([
                'code' => $code,
                'title' => 'Done '.$ageDays.' hari',
                'status' => 'done',
                'priority' => 'normal',
                'assignee_session_id' => $session->id,
                'done_at' => now()->subDays($ageDays),
            ]);
            TaskUpdate::create([
                'task_id' => $task->id,
                'from_status' => 'in_progress',
                'to_status' => 'done',
                'actor_type' => Session::class,
                'actor_id' => $session->id,
            ]);
        }

        $user = $this->user();

        $this->actingAs($user)->get('/health')
            ->assertOk()
            ->assertSee('CLK-7D')
            ->assertDontSee('CLK-12D');

        $html14 = $this->actingAs($user)->get('/health?days=14')->assertOk()->getContent();
        $this->assertMatchesRegularExpression(
            '/id="done-without-evidence"[\s\S]*?>\s*2\s*task\s*</',
            $html14
        );
        $this->assertStringContainsString('CLK-7D', $html14);
        $this->assertStringContainsString('CLK-12D', $html14);
    }

    public function test_health_page_shows_done_without_evidence_subtitle(): void
    {
        Carbon::setTestNow('2026-11-04 12:00:00');

        $this->actingAs($this->user())->get('/health')
            ->assertOk()
            ->assertSee('hanya 7 hari terakhir, task hasil impor tanpa aktivitas sesi dikecualikan', false);
    }
}
