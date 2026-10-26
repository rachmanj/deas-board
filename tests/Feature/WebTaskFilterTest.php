<?php

namespace Tests\Feature;

use App\Models\Session;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebTaskFilterTest extends TestCase
{
    use RefreshDatabase;

    private function seedTasksWithSessions(): array
    {
        $user = User::factory()->create();
        $sessionKeu = Session::create(['label' => 'LAP-KEU', 'topic_id' => '34926', 'is_active' => true]);
        $sessionOther = Session::create(['label' => 'Lain', 'topic_id' => '99999', 'is_active' => true]);

        Task::create([
            'code' => 'CLK-1',
            'title' => 'Task keu',
            'status' => 'todo',
            'priority' => 'normal',
            'assignee_session_id' => $sessionKeu->id,
        ]);
        Task::create([
            'code' => 'CLK-2',
            'title' => 'Task lain',
            'status' => 'todo',
            'priority' => 'normal',
            'assignee_session_id' => $sessionOther->id,
        ]);

        return [$user, $sessionKeu, $sessionOther];
    }

    public function test_q_matches_assignee_session_topic_id(): void
    {
        [$user] = $this->seedTasksWithSessions();

        $this->actingAs($user)
            ->get('/tasks?q=34926')
            ->assertOk()
            ->assertSee('Task keu')
            ->assertDontSee('Task lain');
    }

    public function test_q_matches_assignee_session_label(): void
    {
        [$user] = $this->seedTasksWithSessions();

        $this->actingAs($user)
            ->get('/tasks?q=LAP-KEU')
            ->assertOk()
            ->assertSee('Task keu')
            ->assertDontSee('Task lain');
    }

    public function test_session_id_filter_matches_topic_id_exactly(): void
    {
        [$user] = $this->seedTasksWithSessions();

        $this->actingAs($user)
            ->get('/tasks?session_id=34926')
            ->assertOk()
            ->assertSee('Task keu')
            ->assertDontSee('Task lain');
    }

    public function test_invalid_session_id_is_rejected_on_web(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from('/tasks')
            ->get('/tasks?session_id=abc')
            ->assertRedirect('/tasks')
            ->assertSessionHasErrors('session_id');
    }

    public function test_header_search_preserves_session_id_filter(): void
    {
        [$user] = $this->seedTasksWithSessions();

        $this->actingAs($user)
            ->get('/tasks?session_id=34926&q=keu')
            ->assertOk()
            ->assertSee('name="session_id"', false)
            ->assertSee('value="34926"', false);
    }
}
