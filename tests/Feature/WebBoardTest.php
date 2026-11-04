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

    public function test_board_shows_four_active_columns_not_done_kanban_column(): void
    {
        $user = $this->actingUser();

        Task::create(['code' => 'CLK-1', 'title' => 'Todo task', 'status' => 'todo', 'priority' => 'normal']);
        Task::create(['code' => 'CLK-2', 'title' => 'Progress task', 'status' => 'in_progress', 'priority' => 'normal']);
        Task::create(['code' => 'CLK-3', 'title' => 'Finished task', 'status' => 'done', 'priority' => 'normal']);

        $response = $this->actingAs($user)->get('/');

        $response->assertOk()
            ->assertSee('To do')
            ->assertSee('In progress')
            ->assertSee('Blocked')
            ->assertSee('Review')
            ->assertSee('id="col-todo"', false)
            ->assertSee('id="col-in_progress"', false)
            ->assertSee('id="col-blocked"', false)
            ->assertSee('id="col-review"', false)
            ->assertDontSee('id="col-done"', false)
            ->assertSee('Todo task')
            ->assertSee('Progress task')
            ->assertSee('Finished task');
    }

    public function test_done_strip_shows_at_most_eight_chips_total_count_and_view_all_link(): void
    {
        $user = $this->actingUser();

        for ($i = 1; $i <= 10; $i++) {
            Task::create([
                'code' => 'CLK-D'.$i,
                'title' => 'Done task '.$i,
                'status' => 'done',
                'priority' => 'normal',
            ]);
        }

        $response = $this->actingAs($user)->get('/');

        $response->assertOk()
            ->assertSee('id="board-done-strip"', false)
            ->assertSee('>(10)</span>', false)
            ->assertSee('View all', false)
            ->assertSee('href="'.route('tasks.index', ['status' => 'done']).'"', false);

        $html = $response->getContent();
        $this->assertSame(8, substr_count($html, 'data-done-chip'));
    }

    public function test_session_filter_limits_done_strip_content_and_total(): void
    {
        $user = $this->actingUser();
        $sessionA = Session::create(['label' => 'Sesi A', 'topic_id' => '10', 'is_active' => true]);
        $sessionB = Session::create(['label' => 'Sesi B', 'topic_id' => '11', 'is_active' => true]);

        Task::create([
            'code' => 'CLK-A1',
            'title' => 'Done A',
            'status' => 'done',
            'priority' => 'normal',
            'assignee_session_id' => $sessionA->id,
        ]);
        Task::create([
            'code' => 'CLK-B1',
            'title' => 'Done B',
            'status' => 'done',
            'priority' => 'normal',
            'assignee_session_id' => $sessionB->id,
        ]);

        $this->actingAs($user)
            ->get('/?session_id=10')
            ->assertOk()
            ->assertSee('>(1)</span>', false)
            ->assertSee('CLK-A1', false)
            ->assertDontSee('CLK-B1', false);
    }

    public function test_board_without_done_tasks_hides_done_strip(): void
    {
        $user = $this->actingUser();
        Task::create(['code' => 'CLK-1', 'title' => 'Todo task', 'status' => 'todo', 'priority' => 'normal']);

        $this->actingAs($user)
            ->get('/')
            ->assertOk()
            ->assertDontSee('id="board-done-strip"', false);
    }

    public function test_board_status_done_filter_shows_empty_columns_and_done_strip(): void
    {
        $user = $this->actingUser();
        Task::create(['code' => 'CLK-1', 'title' => 'Selesai', 'status' => 'done', 'priority' => 'normal']);

        $this->actingAs($user)
            ->get('/?status=done')
            ->assertOk()
            ->assertSee('Task selesai ditampilkan di strip Done di bawah.')
            ->assertSee('id="board-done-strip"', false)
            ->assertDontSee('id="col-done"', false);
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

    public function test_task_card_shows_assignee_session_label_and_topic_id(): void
    {
        $user = $this->actingUser();
        $session = Session::create(['label' => 'LAP-KEU', 'topic_id' => '34926', 'is_active' => true]);

        Task::create([
            'code' => 'CLK-50',
            'title' => 'Kartu sesi',
            'status' => 'todo',
            'priority' => 'normal',
            'assignee_session_id' => $session->id,
        ]);

        $this->actingAs($user)
            ->get('/')
            ->assertOk()
            ->assertSee('LAP-KEU', false)
            ->assertSee('(34926)', false)
            ->assertSee('text-zinc-400">(34926)', false);
    }

    public function test_board_session_filter_option_includes_topic_id(): void
    {
        $user = $this->actingUser();
        Session::create(['label' => 'FilterSesi', 'topic_id' => '77701', 'is_active' => true]);

        $this->actingAs($user)
            ->get('/')
            ->assertOk()
            ->assertSee('FilterSesi (77701)', false);
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

    public function test_board_card_and_filter_use_english_priority_labels(): void
    {
        $user = $this->actingUser();

        Task::create(['code' => 'CLK-H', 'title' => 'High card', 'status' => 'todo', 'priority' => 'high']);
        Task::create(['code' => 'CLK-U', 'title' => 'Urgent card', 'status' => 'todo', 'priority' => 'urgent']);

        $board = $this->actingAs($user)->get('/');
        $board->assertOk()
            ->assertSee('>High</dd>', false)
            ->assertSee('>Urgent</dd>', false)
            ->assertDontSee('>Tinggi</', false)
            ->assertDontSee('>Mendesak</', false)
            ->assertSee('>High</option>', false)
            ->assertSee('>Urgent</option>', false);
    }
}
