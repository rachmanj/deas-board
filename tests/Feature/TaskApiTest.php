<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\Session;
use App\Models\Task;
use App\Models\TaskUpdate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsApiActor;
use Tests\TestCase;

class TaskApiTest extends TestCase
{
    use ActsAsApiActor;
    use RefreshDatabase;

    public function test_create_task_then_list_includes_it(): void
    {
        $session = Session::create(['label' => 'Creator', 'topic_id' => '100', 'is_active' => true]);

        $create = $this->asSession($session)->postJson('/api/tasks', [
            'title' => 'First task',
            'status' => 'todo',
        ]);

        $create->assertCreated()
            ->assertJsonPath('data.title', 'First task')
            ->assertJsonPath('data.code', 'CLK-1');

        $list = $this->asSession($session)->getJson('/api/tasks');
        $list->assertOk()
            ->assertJsonPath('meta.per_page', 25)
            ->assertJsonCount(1, 'data');
    }

    public function test_status_change_creates_update_and_notification(): void
    {
        $assignee = Session::create(['label' => 'Assignee', 'topic_id' => '200', 'is_active' => true]);
        $actor = Session::create(['label' => 'Actor', 'topic_id' => '201', 'is_active' => true]);

        $task = Task::create([
            'code' => 'CLK-1',
            'title' => 'Status task',
            'status' => 'todo',
            'priority' => 'normal',
            'assignee_session_id' => $assignee->id,
        ]);

        $response = $this->asSession($actor)->patchJson("/api/tasks/{$task->id}", [
            'status' => 'in_progress',
        ]);

        $response->assertOk();

        $this->assertSame(1, TaskUpdate::query()->count());
        $update = TaskUpdate::query()->first();
        $this->assertSame('todo', $update->from_status);
        $this->assertSame('in_progress', $update->to_status);

        $this->assertSame(1, Notification::query()->where('type', 'task.status_changed')->count());
        $notification = Notification::query()->first();
        $this->assertSame($assignee->id, $notification->notifiable_id);
    }

    public function test_unknown_status_returns_422(): void
    {
        $session = Session::create(['label' => 'S', 'topic_id' => '300', 'is_active' => true]);
        $task = Task::create([
            'code' => 'CLK-2',
            'title' => 'T',
            'status' => 'todo',
            'priority' => 'normal',
        ]);

        $response = $this->asSession($session)->patchJson("/api/tasks/{$task->id}", [
            'status' => 'invalid_status',
        ]);

        $response->assertStatus(422);
    }
}
