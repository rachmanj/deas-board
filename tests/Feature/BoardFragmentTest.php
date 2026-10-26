<?php

namespace Tests\Feature;

use App\Models\Session;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BoardFragmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_fragment_requires_auth(): void
    {
        $this->get('/board/fragment')->assertRedirect(route('login'));
    }

    public function test_fragment_returns_task_codes_when_authenticated(): void
    {
        $user = User::factory()->create();
        Task::create(['code' => 'CLK-1', 'title' => 'Todo task', 'status' => 'todo', 'priority' => 'normal']);

        $this->actingAs($user)
            ->get('/board/fragment')
            ->assertOk()
            ->assertSee('CLK-1', false);
    }

    public function test_fragment_session_id_filter_matches_board(): void
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

        $this->actingAs($user)
            ->get('/board/fragment?session_id=10')
            ->assertOk()
            ->assertSee('CLK-1', false)
            ->assertDontSee('CLK-2', false);
    }

    public function test_fragment_returns_304_when_etag_matches(): void
    {
        $user = User::factory()->create();
        Task::create(['code' => 'CLK-1', 'title' => 'Todo task', 'status' => 'todo', 'priority' => 'normal']);

        $first = $this->actingAs($user)->get('/board/fragment');
        $first->assertOk();
        $etag = $first->headers->get('ETag');
        $this->assertNotEmpty($etag);

        $this->actingAs($user)
            ->withHeaders(['If-None-Match' => $etag])
            ->get('/board/fragment')
            ->assertStatus(304)
            ->assertHeader('ETag', $etag);
    }

    public function test_fragment_etag_changes_after_task_status_update(): void
    {
        $user = User::factory()->create();
        $task = Task::create(['code' => 'CLK-1', 'title' => 'Todo task', 'status' => 'todo', 'priority' => 'normal']);

        $first = $this->actingAs($user)->get('/board/fragment');
        $etagBefore = $first->headers->get('ETag');
        $bodyBefore = $first->getContent();

        $task->status = 'in_progress';
        $task->save();

        $second = $this->actingAs($user)
            ->withHeaders(['If-None-Match' => $etagBefore])
            ->get('/board/fragment');
        $second->assertOk();
        $etagAfter = $second->headers->get('ETag');
        $this->assertNotSame($etagBefore, $etagAfter);
        $this->assertNotSame($bodyBefore, $second->getContent());
    }
}
