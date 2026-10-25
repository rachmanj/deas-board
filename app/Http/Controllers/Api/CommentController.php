<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CommentMention;
use App\Models\Notification;
use App\Models\Session;
use App\Models\Task;
use App\Models\TaskComment;
use App\Models\User;
use App\Support\ActivityLogger;
use App\Support\Actor;
use App\Support\ApiResponse;
use App\Support\MentionParser;
use Illuminate\Http\Request;

class CommentController extends Controller
{
    public function store(Request $request, Task $task)
    {
        $validated = $request->validate([
            'body_md' => ['required', 'string'],
            'parent_id' => ['sometimes', 'nullable', 'integer', 'exists:task_comments,id'],
            'mentions' => ['sometimes', 'array'],
            'mentions.*' => ['string'],
        ]);

        $actor = Actor::from($request);

        $comment = TaskComment::create([
            'task_id' => $task->id,
            'parent_id' => $validated['parent_id'] ?? null,
            'body_md' => $validated['body_md'],
            'author_type' => Actor::morphType($actor),
            'author_id' => $actor->getKey(),
        ]);

        MentionParser::sync($comment->id, $comment->body_md, $actor);
        $this->syncManualMentions($comment, $validated['mentions'] ?? [], $actor);

        ActivityLogger::log('comment.created', $actor, $comment, [
            'task_id' => $task->id,
        ]);

        return ApiResponse::data($this->commentPayload($comment->fresh(['mentions'])), 201);
    }

    public function reply(Request $request, TaskComment $comment)
    {
        $validated = $request->validate([
            'body_md' => ['required', 'string'],
            'mentions' => ['sometimes', 'array'],
            'mentions.*' => ['string'],
        ]);

        $actor = Actor::from($request);

        $reply = TaskComment::create([
            'task_id' => $comment->task_id,
            'parent_id' => $comment->id,
            'body_md' => $validated['body_md'],
            'author_type' => Actor::morphType($actor),
            'author_id' => $actor->getKey(),
        ]);

        MentionParser::sync($reply->id, $reply->body_md, $actor);
        $this->syncManualMentions($reply, $validated['mentions'] ?? [], $actor);

        ActivityLogger::log('comment.created', $actor, $reply, [
            'task_id' => $comment->task_id,
            'parent_id' => $comment->id,
        ]);

        return ApiResponse::data($this->commentPayload($reply->fresh(['mentions'])), 201);
    }

    public function update(Request $request, TaskComment $comment)
    {
        $validated = $request->validate([
            'body_md' => ['required', 'string'],
        ]);

        $actor = Actor::from($request);
        $this->ensureAuthor($actor, $comment);

        $oldBody = $comment->body_md;

        ActivityLogger::log('comment.edited', $actor, $comment, [
            'body_md' => ['old' => $oldBody, 'new' => $validated['body_md']],
        ]);

        $comment->body_md = $validated['body_md'];
        $comment->edited_at = now();
        $comment->save();

        MentionParser::sync($comment->id, $comment->body_md, $actor, false);

        return ApiResponse::data($this->commentPayload($comment->fresh(['mentions'])));
    }

    public function destroy(Request $request, TaskComment $comment)
    {
        $actor = Actor::from($request);
        $this->ensureAuthor($actor, $comment);

        ActivityLogger::log('comment.deleted', $actor, $comment, [
            'task_id' => $comment->task_id,
        ]);

        $comment->delete();

        return ApiResponse::data(['id' => $comment->id, 'deleted' => true]);
    }

    private function ensureAuthor(object $actor, TaskComment $comment): void
    {
        if ($comment->author_type !== Actor::morphType($actor) || $comment->author_id !== $actor->getKey()) {
            abort(403);
        }
    }

    private function syncManualMentions(TaskComment $comment, array $mentionIds, object $author): void
    {
        $existingKeys = $comment->mentions()
            ->get()
            ->map(fn (CommentMention $m) => $m->mentionable_type.':'.$m->mentionable_id)
            ->all();

        foreach ($mentionIds as $token) {
            $target = $this->resolveMentionTarget($token);
            if ($target === null) {
                continue;
            }

            $key = $target::class.':'.$target->getKey();
            if (in_array($key, $existingKeys, true)) {
                continue;
            }

            CommentMention::create([
                'comment_id' => $comment->id,
                'mentionable_type' => $target::class,
                'mentionable_id' => $target->getKey(),
            ]);
            $existingKeys[] = $key;

            if ($this->isSameEntity($author, $target)) {
                continue;
            }

            Notification::create([
                'notifiable_type' => $target::class,
                'notifiable_id' => $target->getKey(),
                'type' => 'comment.mention',
                'subject_type' => TaskComment::class,
                'subject_id' => $comment->id,
                'payload' => [
                    'task_id' => $comment->task_id,
                    'comment_id' => $comment->id,
                ],
            ]);
        }
    }

    private function resolveMentionTarget(string $token): ?object
    {
        $session = Session::query()->where('topic_id', $token)->first();
        if ($session) {
            return $session;
        }

        if (is_numeric($token)) {
            $user = User::query()->find($token);
            if ($user) {
                return $user;
            }
        }

        return null;
    }

    private function isSameEntity(object $a, object $b): bool
    {
        return $a::class === $b::class && $a->getKey() === $b->getKey();
    }

    private function commentPayload(TaskComment $comment): array
    {
        return [
            'id' => $comment->id,
            'task_id' => $comment->task_id,
            'parent_id' => $comment->parent_id,
            'body_md' => $comment->body_md,
            'edited_at' => $comment->edited_at?->toIso8601String(),
            'author_type' => $comment->author_type,
            'author_id' => $comment->author_id,
            'mentions' => $comment->mentions->map(fn ($m) => [
                'mentionable_type' => $m->mentionable_type,
                'mentionable_id' => $m->mentionable_id,
            ]),
        ];
    }
}
