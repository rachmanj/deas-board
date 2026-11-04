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
}
