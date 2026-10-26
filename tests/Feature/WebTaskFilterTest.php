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

    public function test_apply_filter_with_all_empty_query_params_returns_ok_without_validation_errors(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/tasks?assignee=&project=&priority=&overdue=&session_id=&q=&status=')
            ->assertOk()
            ->assertDontSee('The selected priority is invalid', false)
            ->assertDontSee('must be a string', false)
            ->assertDontSee('session id field', false);
    }

    public function test_single_empty_filter_params_return_ok(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/tasks?priority=')->assertOk();
        $this->actingAs($user)->get('/tasks?session_id=')->assertOk();
        $this->actingAs($user)->get('/tasks?q=')->assertOk();
    }

    public function test_duplicate_q_array_param_does_not_trigger_string_validation_error(): void
    {
        [$user] = $this->seedTasksWithSessions();

        $this->actingAs($user)
            ->get('/tasks?q[]=satu&q[]=dua')
            ->assertOk()
            ->assertDontSee('must be a string', false);
    }

    public function test_session_id_filter_still_works_and_empty_q_does_not_change_result_count(): void
    {
        [$user, $sessionKeu] = $this->seedTasksWithSessions();

        $unfiltered = $this->actingAs($user)->get('/tasks')->assertOk();
        $unfilteredCount = substr_count($unfiltered->getContent(), 'CLK-');

        $this->actingAs($user)
            ->get('/tasks?q=')
            ->assertOk()
            ->assertSee('CLK-1')
            ->assertSee('CLK-2');

        $this->assertSame(2, $unfilteredCount);

        $this->actingAs($user)
            ->get('/tasks?session_id='.$sessionKeu->topic_id)
            ->assertOk()
            ->assertSee('Task keu')
            ->assertDontSee('Task lain');
    }
}
