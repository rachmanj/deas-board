<?php

namespace Tests\Feature;

use App\Models\Session;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebBoardTest extends TestCase
{
    use RefreshDatabase;

    private function actingUser(): User
    {
        return User::factory()->create();
    }

    public function test_board_shows_five_columns_and_tasks_in_correct_status(): void
    {
        $user = $this->actingUser();

        Task::create(['code' => 'CLK-1', 'title' => 'Todo task', 'status' => 'todo', 'priority' => 'normal']);
        Task::create(['code' => 'CLK-2', 'title' => 'Progress task', 'status' => 'in_progress', 'priority' => 'normal']);

        $response = $this->actingAs($user)->get('/');

        $response->assertOk()
            ->assertSee('Belum dikerjakan')
            ->assertSee('Dikerjakan')
            ->assertSee('Terhambat')
            ->assertSee('Ditinjau')
            ->assertSee('Selesai')
            ->assertSee('Todo task')
            ->assertSee('Progress task');
    }

    public function test_empty_board_directs_to_task_baru_not_api_endpoint(): void
    {
        $user = $this->actingUser();

        $this->actingAs($user)
            ->get('/')
            ->assertOk()
            ->assertSee('Task baru')
            ->assertDontSee('POST /api/tasks');
    }

    public function test_task_card_includes_status_saving_indicator(): void
    {
        $user = $this->actingUser();
        Task::create(['code' => 'CLK-99', 'title' => 'Kartu', 'status' => 'todo', 'priority' => 'normal']);

        $this->actingAs($user)
            ->get('/')
            ->assertOk()
            ->assertSee('taskCardStatus()', false)
            ->assertSee('Menyimpan...');
    }

    public function test_session_filter_shows_only_assignee_tasks_on_board(): void
    {
        $user = $this->actingUser();
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

        $this->actingAs($user)
            ->get('/?assignee='.$sessionA->id)
            ->assertOk()
            ->assertSee('Task sesi A')
            ->assertDontSee('Task sesi B');
    }
}
