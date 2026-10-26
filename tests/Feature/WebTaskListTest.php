<?php

namespace Tests\Feature;

use App\Models\Session;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebTaskListTest extends TestCase
{
    use RefreshDatabase;

    public function test_task_list_shows_assignee_session_label_and_topic_id(): void
    {
        $user = User::factory()->create();
        $session = Session::create(['label' => 'LAP-KEU', 'topic_id' => '34926', 'is_active' => true]);

        Task::create([
            'code' => 'CLK-10',
            'title' => 'Listed task',
            'status' => 'todo',
            'priority' => 'normal',
            'assignee_session_id' => $session->id,
        ]);

        $this->actingAs($user)
            ->get('/tasks')
            ->assertOk()
            ->assertSee('LAP-KEU', false)
            ->assertSee('(34926)', false);
    }

    public function test_task_list_filters_by_status(): void
    {
        $user = User::factory()->create();

        Task::create(['code' => 'CLK-1', 'title' => 'Open task', 'status' => 'todo', 'priority' => 'normal']);
        Task::create(['code' => 'CLK-2', 'title' => 'Done task', 'status' => 'done', 'priority' => 'normal']);

        $this->actingAs($user)
            ->get('/tasks?status=done')
            ->assertOk()
            ->assertSee('Done task')
            ->assertDontSee('Open task');
    }
}
