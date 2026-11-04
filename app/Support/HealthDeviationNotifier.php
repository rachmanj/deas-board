<?php

namespace App\Support;

use App\Models\DeviationNotification;
use App\Models\Task;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class HealthDeviationNotifier
{
    public function __construct(
        private readonly ?string $botToken,
        private readonly ?string $chatId,
    ) {}

    /**
     * @return list<array{category: string, message: string, tasks: Collection<int, Task>}>
     */
    public function pendingNotifications(Carbon $onDate, bool $force, int $limit): array
    {
        $remaining = $limit;
        $plans = [];

        foreach (HealthDeviationCategories::tasksByCategory() as $category => $tasks) {
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
    public function sendAndRecord(array $plans, Carbon $onDate, bool $force): void
    {
        foreach ($plans as $plan) {
            $this->sendTelegram($plan['message']);
            if (! $force) {
                $this->recordBatch($plan['category'], $plan['tasks'], $onDate);
            }
        }
    }

    public function sendTelegram(string $text): void
    {
        if ($this->botToken === null || $this->botToken === '' || $this->chatId === null || $this->chatId === '') {
            return;
        }

        Http::post("https://api.telegram.org/bot{$this->botToken}/sendMessage", [
            'chat_id' => $this->chatId,
            'text' => $text,
        ]);
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
