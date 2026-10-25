<?php

namespace Tests\Feature;

use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebTaskListTest extends TestCase
{
    use RefreshDatabase;

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
