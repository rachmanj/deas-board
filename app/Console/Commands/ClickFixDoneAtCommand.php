<?php

namespace App\Console\Commands;

use App\Models\Task;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ClickFixDoneAtCommand extends Command
{
    protected $signature = 'click:fix-done-at
                            {--dry-run : Report candidate rows without writing}
                            {--apply : Set done_at to updated_at for mismatched done tasks}';

    protected $description = 'Fix done tasks whose done_at is earlier than updated_at';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $apply = (bool) $this->option('apply');

        if ($dryRun === $apply) {
            $this->error('Pilih tepat satu: --dry-run atau --apply.');

            return self::FAILURE;
        }

        $query = Task::query()
            ->where('status', 'done')
            ->whereNotNull('done_at')
            ->whereColumn('done_at', '<', 'updated_at');

        $count = (clone $query)->count();

        $this->line("Kandidat perbaikan: {$count}");

        if ($count === 0) {
            return self::SUCCESS;
        }

        $samples = (clone $query)->orderBy('id')->limit(5)->get(['id', 'code', 'done_at', 'updated_at']);
        foreach ($samples as $task) {
            $this->line(sprintf(
                '  [%s] done_at=%s updated_at=%s',
                $task->code,
                $task->done_at?->toIso8601String(),
                $task->updated_at?->toIso8601String()
            ));
        }

        if ($dryRun) {
            return self::SUCCESS;
        }

        $fixed = DB::transaction(function () use ($query) {
            $ids = (clone $query)->pluck('id');

            return Task::query()
                ->whereIn('id', $ids)
                ->update([
                    'done_at' => DB::raw('updated_at'),
                ]);
        });

        $this->info("Diperbaiki: {$fixed}");

        return self::SUCCESS;
    }
}
