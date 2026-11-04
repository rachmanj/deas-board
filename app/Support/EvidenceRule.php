<?php

namespace App\Support;

use App\Models\Task;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class EvidenceRule
{
    public const DONE_MESSAGE = 'Status done butuh bukti: sertakan komentar berisi hash commit / kata bukti / nomor dokumen / angka uji.';

    public const REVIEW_EVIDENCE_MESSAGE = 'Transisi ke review wajib mengisi kolom evidence.';

    private static bool $enforce = true;

    public static function withoutEnforcement(callable $callback): mixed
    {
        $previous = self::$enforce;
        self::$enforce = false;

        try {
            return $callback();
        } finally {
            self::$enforce = $previous;
        }
    }

    public static function enforcing(): bool
    {
        return self::$enforce;
    }

    public static function hasEvidence(string $text): bool
    {
        if ($text === '') {
            return false;
        }

        if (preg_match('/\b[0-9a-f]{7,40}\b/i', $text)) {
            return true;
        }

        if (preg_match('/bukti/ui', $text)) {
            return true;
        }

        if (preg_match('/\b\d{6,15}\b/', $text)) {
            return true;
        }

        if (preg_match('/\b[0-9A-Za-z]{2,}-\d+\b/', $text)) {
            return true;
        }

        if (preg_match('/\b(?:tes|lulus|passed|assertion)\b/ui', $text) && preg_match('/\d/', $text)) {
            return true;
        }

        return false;
    }

    /**
     * @return array{ok: bool, sources: list<array{type: string, id: int}>, reason: string}
     */
    public static function forDone(Task $task): array
    {
        $sources = [];

        $task->loadMissing(['comments', 'updates']);

        foreach ($task->comments as $comment) {
            if (self::hasEvidence((string) $comment->body_md)) {
                $sources[] = ['type' => 'comment', 'id' => $comment->id];
            }
        }

        foreach ($task->updates as $update) {
            if ($update->evidence !== null && self::hasEvidence((string) $update->evidence)) {
                $sources[] = ['type' => 'task_update', 'id' => $update->id];
            }
        }

        $ok = $sources !== [];

        return [
            'ok' => $ok,
            'sources' => $sources,
            'reason' => $ok ? '' : 'Tidak ada bukti pada komentar atau riwayat update.',
        ];
    }

    public static function allowsDoneTransition(Task $task, ?string $requestEvidence = null): bool
    {
        if (! self::$enforce) {
            return true;
        }

        if ($requestEvidence !== null && self::hasEvidence($requestEvidence)) {
            return true;
        }

        return self::forDone($task)['ok'];
    }

    public static function allowsReviewTransition(?string $requestEvidence): bool
    {
        if (! self::$enforce) {
            return true;
        }

        return $requestEvidence !== null && trim($requestEvidence) !== '';
    }

    public static function rejectDone(Task $task, object $actor, Request $request): never
    {
        self::logRejection($task, $actor, 'done', self::DONE_MESSAGE);

        if ($request->is('api/*')) {
            throw new HttpResponseException(response()->json(['message' => self::DONE_MESSAGE], 422));
        }

        throw ValidationException::withMessages([
            'status' => [self::DONE_MESSAGE],
        ]);
    }

    public static function rejectReview(Task $task, object $actor, Request $request): never
    {
        self::logRejection($task, $actor, 'review', self::REVIEW_EVIDENCE_MESSAGE);

        if ($request->is('api/*')) {
            throw ValidationException::withMessages([
                'evidence' => [self::REVIEW_EVIDENCE_MESSAGE],
            ]);
        }

        throw ValidationException::withMessages([
            'evidence' => [self::REVIEW_EVIDENCE_MESSAGE],
        ]);
    }

    private static function logRejection(Task $task, object $actor, string $requestedStatus, string $reason): void
    {
        ActivityLogger::log('task.evidence_rejected', $actor, $task, [
            'requested_status' => $requestedStatus,
            'reason' => $reason,
        ]);
    }
}
