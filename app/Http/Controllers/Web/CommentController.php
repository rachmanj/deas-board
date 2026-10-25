<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Task;
use App\Models\TaskComment;
use App\Models\User;
use App\Support\ActivityLogger;
use App\Support\Actor;
use App\Support\MentionParser;
use Illuminate\Http\Request;

class CommentController extends Controller
{
    public function store(Request $request, Task $task)
    {
        $validated = $request->validate([
            'body_md' => ['required', 'string'],
        ]);

        $actor = Actor::from($request);

        $comment = TaskComment::create([
            'task_id' => $task->id,
            'parent_id' => null,
            'body_md' => $validated['body_md'],
            'author_type' => Actor::morphType($actor),
            'author_id' => $actor->getKey(),
        ]);

        MentionParser::sync($comment->id, $comment->body_md, $actor);

        ActivityLogger::log('comment.created', $actor, $comment, [
            'task_id' => $task->id,
        ]);

        return redirect()->route('tasks.show', $task)->withFragment('diskusi');
    }

    public function reply(Request $request, Task $task, TaskComment $comment)
    {
        if ($comment->task_id !== $task->id) {
            abort(404);
        }

        $validated = $request->validate([
            'body_md' => ['required', 'string'],
        ]);

        $actor = Actor::from($request);

        $reply = TaskComment::create([
            'task_id' => $task->id,
            'parent_id' => $comment->id,
            'body_md' => $validated['body_md'],
            'author_type' => Actor::morphType($actor),
            'author_id' => $actor->getKey(),
        ]);

        MentionParser::sync($reply->id, $reply->body_md, $actor);

        ActivityLogger::log('comment.created', $actor, $reply, [
            'task_id' => $task->id,
            'parent_id' => $comment->id,
        ]);

        return redirect()->route('tasks.show', $task)->withFragment('diskusi');
    }

    public function update(Request $request, Task $task, TaskComment $comment)
    {
        if ($comment->task_id !== $task->id) {
            abort(404);
        }

        $validated = $request->validate([
            'body_md' => ['required', 'string'],
        ]);

        $actor = Actor::from($request);
        $this->ensureUserAuthor($actor, $comment);

        $oldBody = $comment->body_md;

        ActivityLogger::log('comment.edited', $actor, $comment, [
            'body_md' => ['old' => $oldBody, 'new' => $validated['body_md']],
        ]);

        $comment->body_md = $validated['body_md'];
        $comment->edited_at = now();
        $comment->save();

        MentionParser::sync($comment->id, $comment->body_md, $actor, false);

        return redirect()->route('tasks.show', $task)->withFragment('diskusi');
    }

    public function destroy(Request $request, Task $task, TaskComment $comment)
    {
        if ($comment->task_id !== $task->id) {
            abort(404);
        }

        $actor = Actor::from($request);
        $this->ensureUserAuthor($actor, $comment);

        ActivityLogger::log('comment.deleted', $actor, $comment, [
            'task_id' => $task->id,
        ]);

        $comment->delete();

        return redirect()->route('tasks.show', $task)->withFragment('diskusi');
    }

    private function ensureUserAuthor(object $actor, TaskComment $comment): void
    {
        if (! $actor instanceof User) {
            abort(403);
        }

        if ($comment->author_type !== User::class || $comment->author_id !== $actor->getKey()) {
            abort(403);
        }
    }
}
