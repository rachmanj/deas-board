<?php

namespace App\Support;

use App\Models\CommentMention;
use App\Models\Notification;
use App\Models\Session;
use App\Models\TaskComment;
use App\Models\User;
use Illuminate\Support\Str;

class MentionParser
{
    public static function extract(string $bodyMd): array
    {
        if (! preg_match_all('/@([0-9]{2,6}|[a-zA-Z0-9.\-]+)/', $bodyMd, $matches)) {
            return [];
        }

        return array_values(array_unique($matches[1]));
    }

    public static function resolve(array $tokens): array
    {
        $resolved = [];

        foreach ($tokens as $token) {
            $session = Session::query()->where('topic_id', $token)->first();
            if ($session) {
                $resolved[] = $session;

                continue;
            }

            $normalizedToken = Str::lower($token);
            $user = User::query()
                ->get()
                ->first(fn (User $user) => Str::lower(str_replace(' ', '', $user->name)) === $normalizedToken);

            if ($user) {
                $resolved[] = $user;
            }
        }

        $unique = [];
        $seen = [];
        foreach ($resolved as $model) {
            $key = $model::class.':'.$model->getKey();
            if (! isset($seen[$key])) {
                $seen[$key] = true;
                $unique[] = $model;
            }
        }

        return $unique;
    }

    public static function sync(int $commentId, string $bodyMd, object $author): void
    {
        CommentMention::query()->where('comment_id', $commentId)->delete();

        $comment = TaskComment::query()->findOrFail($commentId);
        $targets = self::resolve(self::extract($bodyMd));

        foreach ($targets as $target) {
            CommentMention::create([
                'comment_id' => $commentId,
                'mentionable_type' => $target::class,
                'mentionable_id' => $target->getKey(),
            ]);

            if (self::isSameEntity($author, $target)) {
                continue;
            }

            Notification::create([
                'notifiable_type' => $target::class,
                'notifiable_id' => $target->getKey(),
                'type' => 'comment.mention',
                'subject_type' => TaskComment::class,
                'subject_id' => $commentId,
                'payload' => [
                    'task_id' => $comment->task_id,
                    'comment_id' => $commentId,
                ],
            ]);
        }
    }

    private static function isSameEntity(object $a, object $b): bool
    {
        return $a::class === $b::class && $a->getKey() === $b->getKey();
    }
}
