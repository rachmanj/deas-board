<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Notification;
use App\Models\Session;
use App\Models\Task;
use App\Models\TaskComment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebTaskDetailTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        return User::factory()->create();
    }

    public function test_guest_is_redirected_to_login_from_task_detail(): void
    {
        $task = Task::create([
            'code' => 'CLK-1',
            'title' => 'Private',
            'status' => 'todo',
            'priority' => 'normal',
        ]);

        $this->get(route('tasks.show', $task))->assertRedirect('/login');
    }

    public function test_detail_page_shows_title_and_existing_comment(): void
    {
        $user = $this->user();
        $task = Task::create([
            'code' => 'CLK-2',
            'title' => 'Tampil di detail',
            'status' => 'todo',
            'priority' => 'normal',
        ]);
        TaskComment::create([
            'task_id' => $task->id,
            'body_md' => 'Komentar awal',
            'author_type' => User::class,
            'author_id' => $user->id,
        ]);

        $this->actingAs($user)
            ->get(route('tasks.show', $task))
            ->assertOk()
            ->assertSee('Tampil di detail')
            ->assertSee('Komentar awal');
    }

    public function test_new_comment_is_saved_and_shown(): void
    {
        $user = $this->user();
        $task = Task::create([
            'code' => 'CLK-3',
            'title' => 'Diskusi',
            'status' => 'todo',
            'priority' => 'normal',
        ]);

        $this->actingAs($user)
            ->post(route('tasks.comments.store', $task), [
                'body_md' => 'Komentar baru dari web',
            ])
            ->assertRedirect(route('tasks.show', $task).'#diskusi');

        $this->actingAs($user)
            ->get(route('tasks.show', $task))
            ->assertSee('Komentar baru dari web');
    }

    public function test_reply_is_nested_under_parent(): void
    {
        $user = $this->user();
        $task = Task::create([
            'code' => 'CLK-4',
            'title' => 'Balasan',
            'status' => 'todo',
            'priority' => 'normal',
        ]);
        $parent = TaskComment::create([
            'task_id' => $task->id,
            'body_md' => 'Induk',
            'author_type' => Session::class,
            'author_id' => Session::create(['label' => 'Sesi', 'topic_id' => '99', 'is_active' => true])->id,
        ]);

        $this->actingAs($user)
            ->post(route('tasks.comments.reply', [$task, $parent]), [
                'body_md' => 'Balasan web',
            ])
            ->assertRedirect();

        $response = $this->actingAs($user)->get(route('tasks.show', $task));
        $response->assertSee('Induk');
        $response->assertSee('Balasan web');
        $response->assertSee('ml-4 border-l border-zinc-800', false);
    }

    public function test_edit_comment_stores_old_body_in_activity_log(): void
    {
        $user = $this->user();
        $task = Task::create([
            'code' => 'CLK-5',
            'title' => 'Edit',
            'status' => 'todo',
            'priority' => 'normal',
        ]);
        $comment = TaskComment::create([
            'task_id' => $task->id,
            'body_md' => 'Isi lama',
            'author_type' => User::class,
            'author_id' => $user->id,
        ]);

        $this->actingAs($user)
            ->patch(route('tasks.comments.update', [$task, $comment]), [
                'body_md' => 'Isi baru',
            ])
            ->assertRedirect();

        $log = ActivityLog::query()->where('action', 'comment.edited')->first();
        $this->assertNotNull($log);
        $this->assertSame('Isi lama', $log->changes['body_md']['old']);
        $this->assertSame('Isi baru', $log->changes['body_md']['new']);
    }

    public function test_deleted_comment_still_visible_with_marker(): void
    {
        $user = $this->user();
        $task = Task::create([
            'code' => 'CLK-6',
            'title' => 'Hapus',
            'status' => 'todo',
            'priority' => 'normal',
        ]);
        $comment = TaskComment::create([
            'task_id' => $task->id,
            'body_md' => 'Akan dihapus',
            'author_type' => User::class,
            'author_id' => $user->id,
        ]);

        $this->actingAs($user)
            ->delete(route('tasks.comments.destroy', [$task, $comment]))
            ->assertRedirect();

        $this->actingAs($user)
            ->get(route('tasks.show', $task))
            ->assertSee('dihapus')
            ->assertDontSee('Akan dihapus');
    }

    public function test_mention_in_comment_notifies_session(): void
    {
        $user = $this->user();
        $target = Session::create(['label' => 'Target', 'topic_id' => '888', 'is_active' => true]);
        $task = Task::create([
            'code' => 'CLK-7',
            'title' => 'Mention',
            'status' => 'todo',
            'priority' => 'normal',
        ]);

        $this->actingAs($user)
            ->post(route('tasks.comments.store', $task), [
                'body_md' => 'Halo @888',
            ])
            ->assertRedirect();

        $this->assertSame(1, Notification::query()->where('type', 'comment.mention')->count());
        $notification = Notification::query()->first();
        $this->assertSame($target->id, $notification->notifiable_id);
    }
}
