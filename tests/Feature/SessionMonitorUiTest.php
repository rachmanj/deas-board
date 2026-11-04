<?php

namespace Tests\Feature;

use App\Models\Session;
use App\Models\Task;
use App\Models\TaskComment;
use App\Models\TaskUpdate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class SessionMonitorUiTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        return User::factory()->create();
    }

    public function test_sessions_page_shows_idle_presence_and_task_counts(): void
    {
        Carbon::setTestNow('2026-11-04 12:00:00');

        $session = Session::create([
            'label' => 'IdleSesi',
            'topic_id' => '710',
            'is_active' => true,
            'last_seen_at' => now()->subMinutes(30),
        ]);

        Task::create([
            'code' => 'CLK-IDLE',
            'title' => 'Kerja idle',
            'status' => 'todo',
            'priority' => 'normal',
            'assignee_session_id' => $session->id,
        ]);
        Task::create([
            'code' => 'CLK-PROG',
            'title' => 'Sedang jalan',
            'status' => 'in_progress',
            'priority' => 'normal',
            'assignee_session_id' => $session->id,
        ]);

        $this->actingAs($this->user())
            ->get('/sessions')
            ->assertOk()
            ->assertSee('Idle')
            ->assertSee('todo 1')
            ->assertSee('prog 1')
            ->assertSee('Sedang jalan');
    }

    public function test_sessions_page_marks_menggantung_and_filter_works(): void
    {
        Carbon::setTestNow('2026-11-04 12:00:00');

        $stale = Session::create([
            'label' => 'Macet',
            'topic_id' => '711',
            'is_active' => true,
            'last_seen_at' => now()->subHours(3),
        ]);
        $fresh = Session::create([
            'label' => 'Aman',
            'topic_id' => '712',
            'is_active' => true,
            'last_seen_at' => now()->subMinutes(5),
        ]);

        Task::create([
            'code' => 'CLK-M',
            'title' => 'T macet',
            'status' => 'in_progress',
            'priority' => 'normal',
            'assignee_session_id' => $stale->id,
        ]);
        Task::create([
            'code' => 'CLK-OK',
            'title' => 'T ok',
            'status' => 'in_progress',
            'priority' => 'normal',
            'assignee_session_id' => $fresh->id,
        ]);

        $this->actingAs($this->user())
            ->get('/sessions')
            ->assertOk()
            ->assertSee('Menggantung')
            ->assertSee('Macet');

        $this->actingAs($this->user())
            ->get('/sessions?status_kerja=menggantung')
            ->assertOk()
            ->assertSee('Macet')
            ->assertDontSee('Aman');
    }
}
