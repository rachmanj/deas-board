<?php

namespace App\Support;

use App\Models\Task;
use App\Models\TaskUpdate;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
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
     * @return array{ok: bool, sources: list<array{type: string, id: int}>, reason: string, last_in_progress_at: ?Carbon}
     */
    public static function forDone(Task $task): array
    {
        $sources = [];
        $window = self::evidenceWindow($task);

        $task->loadMissing(['comments', 'updates']);

        foreach ($task->comments as $comment) {
            if (! self::isRelevantAfter($comment->created_at, $window)) {
                continue;
            }
            if (self::hasEvidence((string) $comment->body_md)) {
                $sources[] = ['type' => 'comment', 'id' => $comment->id];
            }
        }

        foreach ($task->updates as $update) {
            if (! self::isRelevantAfter($update->created_at, $window)) {
                continue;
            }
            if ($update->evidence !== null && self::hasEvidence((string) $update->evidence)) {
                $sources[] = ['type' => 'task_update', 'id' => $update->id];
            }
        }

        $ok = $sources !== [];

        return [
            'ok' => $ok,
            'sources' => $sources,
            'reason' => $ok ? '' : self::missingEvidenceReason($window),
            'last_in_progress_at' => $window['last_in_progress_at'],
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

    public static function doneRejectionMessage(Task $task): string
    {
        $check = self::forDone($task);
        if ($check['reason'] === '') {
            return self::DONE_MESSAGE;
        }

        return self::DONE_MESSAGE.' '.$check['reason'];
    }

    public static function rejectDone(Task $task, object $actor, Request $request): never
    {
        $message = self::doneRejectionMessage($task);
        self::logRejection($task, $actor, 'done', $message);

        if ($request->is('api/*')) {
            throw new HttpResponseException(response()->json(['message' => $message], 422));
        }

        throw ValidationException::withMessages([
            'status' => [$message],
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

    /**
     * @return array{
     *     cutoff: Carbon,
     *     had_in_progress_transition: bool,
     *     last_in_progress_at: ?Carbon,
     * }
     */
    private static function evidenceWindow(Task $task): array
    {
        $task->loadMissing('updates');

        /** @var TaskUpdate|null $lastInProgress */
        $lastInProgress = $task->updates
            ->where('to_status', 'in_progress')
            ->sortByDesc(fn (TaskUpdate $update) => [$update->created_at, $update->id])
            ->first();

        if ($lastInProgress !== null && $lastInProgress->created_at !== null) {
            return [
                'cutoff' => $lastInProgress->created_at,
                'had_in_progress_transition' => true,
                'last_in_progress_at' => $lastInProgress->created_at,
            ];
        }

        return [
            'cutoff' => now()->subDays(7),
            'had_in_progress_transition' => false,
            'last_in_progress_at' => null,
        ];
    }

    /**
     * @param  array{
     *     cutoff: Carbon,
     *     had_in_progress_transition: bool,
     *     last_in_progress_at: ?Carbon,
     * }  $window
     */
    private static function isRelevantAfter(?Carbon $timestamp, array $window): bool
    {
        if ($timestamp === null) {
            return false;
        }

        return $timestamp->gt($window['cutoff']);
    }

    /**
     * @param  array{
     *     cutoff: Carbon,
     *     had_in_progress_transition: bool,
     *     last_in_progress_at: ?Carbon,
     * }  $window
     */
    private static function missingEvidenceReason(array $window): string
    {
        if ($window['had_in_progress_transition'] && $window['last_in_progress_at'] !== null) {
            return 'belum ada bukti setelah status in_progress terakhir ('.$window['last_in_progress_at']->format('Y-m-d H:i').').';
        }

        return 'belum ada bukti dalam 7 hari terakhir.';
    }

    private static function logRejection(Task $task, object $actor, string $requestedStatus, string $reason): void
    {
        ActivityLogger::log('task.evidence_rejected', $actor, $task, [
            'requested_status' => $requestedStatus,
            'reason' => $reason,
        ]);
    }
}
