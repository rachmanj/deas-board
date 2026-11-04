<?php

namespace App\Support;

use App\Models\DeviationNotification;
use App\Models\Task;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class HealthDeviationNotifier
{
    private ?int $lastTelegramHttpStatus = null;

    private ?string $lastTelegramError = null;

    public function __construct(
        private readonly ?string $botToken,
        private readonly ?string $chatId,
    ) {}

    public function isConfigured(): bool
    {
        return $this->botToken !== null
            && $this->botToken !== ''
            && $this->chatId !== null
            && $this->chatId !== '';
    }

    /**
     * @return list<array{category: string, message: string, tasks: Collection<int, Task>}>
     */
    public function pendingNotifications(Carbon $onDate, bool $force, int $limit, ?int $doneWithoutEvidenceDays = null): array
    {
        $remaining = $limit;
        $plans = [];

        foreach (HealthDeviationCategories::tasksByCategory($doneWithoutEvidenceDays) as $category => $tasks) {
            if ($remaining <= 0) {
                break;
            }

            $pending = $this->filterPending($category, $tasks, $onDate, $force);
            if ($pending->isEmpty()) {
                continue;
            }

            $batch = $pending->take($remaining)->values();
            $remaining -= $batch->count();

            $plans[] = [
                'category' => $category,
                'message' => $this->formatMessage($category, $batch->count(), $batch),
                'tasks' => $batch,
            ];
        }

        return $plans;
    }

    /**
     * @param  list<array{category: string, message: string, tasks: Collection<int, Task>}>  $plans
     */
    /**
     * @return array{sent: int, failed: int, skipped: int}
     */
    public function sendAndRecord(array $plans, Carbon $onDate, bool $force): array
    {
        $sent = 0;
        $failed = 0;
        $skipped = 0;

        foreach ($plans as $plan) {
            if (! $this->isConfigured()) {
                $skipped++;

                continue;
            }

            if ($this->sendTelegram($plan['message'])) {
                $sent++;
                if (! $force) {
                    $this->recordBatch($plan['category'], $plan['tasks'], $onDate);
                }

                continue;
            }

            $failed++;
            Log::warning('notifikasi Telegram gagal', [
                'category' => $plan['category'],
                'count' => $plan['tasks']->count(),
                'status' => $this->lastTelegramHttpStatus,
                'error' => $this->lastTelegramError,
            ]);
        }

        return compact('sent', 'failed', 'skipped');
    }

    public function sendTelegram(string $text): bool
    {
        $this->lastTelegramHttpStatus = null;
        $this->lastTelegramError = null;

        if (! $this->isConfigured()) {
            return false;
        }

        try {
            $response = Http::post("https://api.telegram.org/bot{$this->botToken}/sendMessage", [
                'chat_id' => $this->chatId,
                'text' => $text,
            ]);

            if ($response->successful() && $response->json('ok') === true) {
                return true;
            }

            $this->lastTelegramHttpStatus = $response->status();
            $this->lastTelegramError = is_string($response->json('description'))
                ? $response->json('description')
                : ($response->body() !== '' ? 'response not ok' : 'empty response');

            return false;
        } catch (Throwable $e) {
            $this->lastTelegramError = $e->getMessage();

            return false;
        }
    }

    /**
     * @param  Collection<int, Task>  $tasks
     */
    private function filterPending(string $category, Collection $tasks, Carbon $onDate, bool $force): Collection
    {
        if ($force) {
            return $tasks->values();
        }

        $notifiedIds = DeviationNotification::query()
            ->where('category', $category)
            ->where('subject_type', Task::class)
            ->whereDate('notified_on', $onDate)
            ->whereIn('subject_id', $tasks->pluck('id'))
            ->pluck('subject_id')
            ->all();

        $notifiedSet = array_flip($notifiedIds);

        return $tasks
            ->filter(fn (Task $task) => ! isset($notifiedSet[$task->id]))
            ->values();
    }

    /**
     * @param  Collection<int, Task>  $batch
     */
    public function formatMessage(string $category, int $totalCount, Collection $batch): string
    {
        $lines = [
            HealthDeviationCategories::titles()[$category] ?? $category,
            $totalCount.' penyimpangan',
        ];

        foreach ($batch->take(5) as $task) {
            $lines[] = $this->formatTaskLine($task);
        }

        $extra = max(0, $totalCount - 5);
        if ($extra > 0) {
            $lines[] = 'dan '.$extra.' lainnya';
        }

        return implode("\n", array_slice($lines, 0, 8));
    }

    private function formatTaskLine(Task $task): string
    {
        $anchor = BoardHealth::taskActivityAnchor($task);
        $title = Str::limit($task->title, 48, '…');

        return $task->code.' · '.$title.' · '.BoardHealth::formatAge($anchor);
    }

    /**
     * @param  Collection<int, Task>  $tasks
     */
    private function recordBatch(string $category, Collection $tasks, Carbon $onDate): void
    {
        $notifiedOn = $onDate->copy()->startOfDay();

        foreach ($tasks as $task) {
            DeviationNotification::query()->firstOrCreate(
                [
                    'category' => $category,
                    'subject_type' => Task::class,
                    'subject_id' => $task->id,
                    'notified_on' => $notifiedOn,
                ],
                [
                    'payload' => [
                        'code' => $task->code,
                        'title' => $task->title,
                    ],
                ],
            );
        }
    }
}
