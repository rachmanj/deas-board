<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\CommentMention;
use App\Models\Notification;
use App\Models\Session;
use App\Models\Task;
use App\Models\TaskComment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsApiActor;
use Tests\TestCase;

class CommentApiTest extends TestCase
{
    use ActsAsApiActor;
    use RefreshDatabase;

    public function test_mention_in_body_creates_mentions_and_notifies_target_not_author(): void
    {
        $author = Session::create(['label' => 'Author', 'topic_id' => '500', 'is_active' => true]);
        $target = Session::create(['label' => 'Target', 'topic_id' => '501', 'is_active' => true]);
        $task = Task::create([
            'code' => 'CLK-1',
            'title' => 'Comment task',
            'status' => 'todo',
            'priority' => 'normal',
        ]);

        $response = $this->asSession($author)->postJson("/api/tasks/{$task->id}/comments", [
            'body_md' => 'Hello @501',
        ]);

        $response->assertCreated();

        $this->assertSame(1, CommentMention::query()->count());
        $mention = CommentMention::query()->first();
        $this->assertSame($target->id, $mention->mentionable_id);

        $this->assertSame(1, Notification::query()->where('type', 'comment.mention')->count());
        $notification = Notification::query()->first();
        $this->assertSame($target->id, $notification->notifiable_id);
    }

    public function test_edit_comment_does_not_create_duplicate_mention_notifications(): void
    {
        $author = Session::create(['label' => 'Author', 'topic_id' => '550', 'is_active' => true]);
        $target = Session::create(['label' => 'Target', 'topic_id' => '551', 'is_active' => true]);
        $task = Task::create([
            'code' => 'CLK-4',
            'title' => 'Mention edit',
            'status' => 'todo',
            'priority' => 'normal',
        ]);

        $this->asSession($author)->postJson("/api/tasks/{$task->id}/comments", [
            'body_md' => 'Hai @551',
        ])->assertCreated();

        $this->assertSame(1, Notification::query()->where('type', 'comment.mention')->count());

        $comment = TaskComment::query()->first();
        $this->asSession($author)->patchJson("/api/comments/{$comment->id}", [
            'body_md' => 'Hai lagi @551',
        ])->assertOk();

        $this->assertSame(1, Notification::query()->where('type', 'comment.mention')->count());
    }

    public function test_edit_comment_stores_old_body_in_activity_log(): void
    {
        $author = Session::create(['label' => 'Author', 'topic_id' => '600', 'is_active' => true]);
        $task = Task::create([
            'code' => 'CLK-2',
            'title' => 'Edit task',
            'status' => 'todo',
            'priority' => 'normal',
        ]);
        $comment = TaskComment::create([
            'task_id' => $task->id,
            'body_md' => 'Original body',
            'author_type' => Session::class,
            'author_id' => $author->id,
        ]);

        $this->asSession($author)->patchJson("/api/comments/{$comment->id}", [
            'body_md' => 'Updated body',
        ])->assertOk();

        $log = ActivityLog::query()->where('action', 'comment.edited')->first();
        $this->assertNotNull($log);
        $this->assertSame('Original body', $log->changes['body_md']['old']);
        $this->assertSame('Updated body', $log->changes['body_md']['new']);

        $comment->refresh();
        $this->assertNotNull($comment->edited_at);
    }

    public function test_delete_comment_is_soft_delete(): void
    {
        $author = Session::create(['label' => 'Author', 'topic_id' => '700', 'is_active' => true]);
        $task = Task::create([
            'code' => 'CLK-3',
            'title' => 'Delete task',
            'status' => 'todo',
            'priority' => 'normal',
        ]);
        $comment = TaskComment::create([
            'task_id' => $task->id,
            'body_md' => 'To delete',
            'author_type' => Session::class,
            'author_id' => $author->id,
        ]);

        $this->asSession($author)->deleteJson("/api/comments/{$comment->id}")->assertOk();

        $this->assertSoftDeleted('task_comments', ['id' => $comment->id]);
        $this->assertNotNull(ActivityLog::query()->where('action', 'comment.deleted')->first());
    }
}
