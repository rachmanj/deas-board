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

    public function test_task_list_table_headers_are_english(): void
    {
        $user = User::factory()->create();
        Task::create(['code' => 'CLK-1', 'title' => 'Row', 'status' => 'todo', 'priority' => 'normal']);

        $response = $this->actingAs($user)->get('/tasks');

        $response->assertOk()
            ->assertSee('>Code</th>', false)
            ->assertSee('>Title</th>', false)
            ->assertSee('>Status</th>', false)
            ->assertSee('>Priority</th>', false)
            ->assertSee('>Assignee</th>', false)
            ->assertSee('>Project</th>', false)
            ->assertSee('>Due</th>', false)
            ->assertSee('>Age</th>', false)
            ->assertSee('>Comments</th>', false)
            ->assertDontSee('>Kode</th>', false)
            ->assertDontSee('>Judul</th>', false)
            ->assertDontSee('>Prioritas</th>', false)
            ->assertDontSee('>Sesi</th>', false)
            ->assertDontSee('>Proyek</th>', false)
            ->assertDontSee('>Tenggat</th>', false)
            ->assertDontSee('>Umur (hari)</th>', false)
            ->assertDontSee('>Komentar</th>', false);
    }
}
