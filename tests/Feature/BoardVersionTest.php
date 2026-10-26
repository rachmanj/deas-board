<?php

namespace Tests\Feature;

use App\Models\Session;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BoardVersionTest extends TestCase
{
    use RefreshDatabase;

    public function test_version_requires_auth(): void
    {
        $this->get('/board/version')->assertRedirect(route('login'));
    }

    public function test_version_returns_json_when_authenticated(): void
    {
        $user = User::factory()->create();
        Task::create(['code' => 'CLK-1', 'title' => 'Todo task', 'status' => 'todo', 'priority' => 'normal']);

        $this->actingAs($user)
            ->get('/board/version')
            ->assertOk()
            ->assertJsonStructure(['hash', 'tasks', 'updated_at']);
    }

    public function test_version_hash_matches_fragment_x_board_hash(): void
    {
        $user = User::factory()->create();
        Task::create(['code' => 'CLK-1', 'title' => 'Todo task', 'status' => 'todo', 'priority' => 'normal']);

        $version = $this->actingAs($user)->get('/board/version');
        $fragment = $this->actingAs($user)->get('/board/fragment');

        $this->assertSame($version->json('hash'), $fragment->headers->get('X-Board-Hash'));
    }

    public function test_version_hash_changes_on_status_update_without_changing_task_count(): void
    {
        $user = User::factory()->create();
        $task = Task::create(['code' => 'CLK-1', 'title' => 'Todo task', 'status' => 'todo', 'priority' => 'normal']);

        $before = $this->actingAs($user)->get('/board/version');

        $task->status = 'in_progress';
        $task->save();

        $after = $this->actingAs($user)->get('/board/version');

        $this->assertNotSame($before->json('hash'), $after->json('hash'));
        $this->assertSame($before->json('tasks'), $after->json('tasks'));
    }

    public function test_version_session_id_filter_affects_hash(): void
    {
        $user = User::factory()->create();
        $sessionA = Session::create(['label' => 'Sesi A', 'topic_id' => '10', 'is_active' => true]);
        $sessionB = Session::create(['label' => 'Sesi B', 'topic_id' => '11', 'is_active' => true]);

        Task::create([
            'code' => 'CLK-1',
            'title' => 'Task sesi A',
            'status' => 'todo',
            'priority' => 'normal',
            'assignee_session_id' => $sessionA->id,
        ]);
        Task::create([
            'code' => 'CLK-2',
            'title' => 'Task sesi B',
            'status' => 'todo',
            'priority' => 'normal',
            'assignee_session_id' => $sessionB->id,
        ]);

        $hashA = $this->actingAs($user)->get('/board/version?session_id=10')->json('hash');
        $hashB = $this->actingAs($user)->get('/board/version?session_id=11')->json('hash');

        $this->assertNotSame($hashA, $hashB);
    }
}
