<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\Session;
use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsApiActor;
use Tests\TestCase;

class NotificationApiTest extends TestCase
{
    use ActsAsApiActor;
    use RefreshDatabase;

    public function test_other_session_cannot_mark_notification_read(): void
    {
        $owner = Session::create(['label' => 'Owner', 'topic_id' => '800', 'is_active' => true]);
        $other = Session::create(['label' => 'Other', 'topic_id' => '801', 'is_active' => true]);
        $task = Task::create([
            'code' => 'CLK-1',
            'title' => 'N',
            'status' => 'todo',
            'priority' => 'normal',
        ]);

        $notification = Notification::create([
            'notifiable_type' => Session::class,
            'notifiable_id' => $owner->id,
            'type' => 'task.status_changed',
            'subject_type' => Task::class,
            'subject_id' => $task->id,
            'payload' => ['task_id' => $task->id],
        ]);

        $this->asSession($other)
            ->postJson("/api/notifications/{$notification->id}/read")
            ->assertNotFound();

        $this->assertNull($notification->fresh()->read_at);
    }

    public function test_owner_can_mark_notification_read(): void
    {
        $owner = Session::create(['label' => 'Owner', 'topic_id' => '900', 'is_active' => true]);
        $task = Task::create([
            'code' => 'CLK-2',
            'title' => 'N2',
            'status' => 'todo',
            'priority' => 'normal',
        ]);

        $notification = Notification::create([
            'notifiable_type' => Session::class,
            'notifiable_id' => $owner->id,
            'type' => 'comment.mention',
            'subject_type' => Task::class,
            'subject_id' => $task->id,
            'payload' => [],
        ]);

        $this->asSession($owner)
            ->postJson("/api/notifications/{$notification->id}/read")
            ->assertOk();

        $this->assertNotNull($notification->fresh()->read_at);
    }
}
