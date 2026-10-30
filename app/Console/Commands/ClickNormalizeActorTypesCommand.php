<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ClickNormalizeActorTypesCommand extends Command
{
    protected $signature = 'click:normalize-actor-types
                            {--dry-run : Preview changes without writing}
                            {--apply : Apply normalization in a transaction}';

    protected $description = 'Rewrite legacy morph aliases (e.g. lead) to full model class names';

    /**
     * @var list<array{table: string, type_column: string, id_column: string}>
     */
    private const TARGETS = [
        ['table' => 'task_updates', 'type_column' => 'actor_type', 'id_column' => 'actor_id'],
        ['table' => 'activity_logs', 'type_column' => 'actor_type', 'id_column' => 'actor_id'],
        ['table' => 'task_comments', 'type_column' => 'author_type', 'id_column' => 'author_id'],
        ['table' => 'comment_mentions', 'type_column' => 'mentionable_type', 'id_column' => 'mentionable_id'],
    ];

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $apply = (bool) $this->option('apply');

        if ($dryRun === $apply) {
            $this->error('Pilih tepat satu: --dry-run atau --apply.');

            return self::FAILURE;
        }

        $plan = $this->buildPlan();
        $nullIdReports = [];

        foreach ($plan as $table => $rows) {
            if ($rows === []) {
                continue;
            }

            $this->line('');
            $this->info($table);
            $this->table(
                ['Nilai lama', 'Nilai baru', 'Baris'],
                array_map(
                    fn (array $row) => [$row['from'], $row['to'], (string) $row['count']],
                    $rows
                )
            );
        }

        if ($plan === [] || $this->planRowCount($plan) === 0) {
            $this->line('');
            $this->comment('Tidak ada baris yang perlu dinormalisasi.');

            return self::SUCCESS;
        }

        if ($dryRun) {
            return self::SUCCESS;
        }

        DB::transaction(function () use ($plan, &$nullIdReports) {
            foreach (self::TARGETS as $target) {
                $table = $target['table'];
                if (! Schema::hasTable($table)) {
                    continue;
                }

                $typeColumn = $target['type_column'];
                $idColumn = $target['id_column'];

                foreach ($plan[$table] ?? [] as $entry) {
                    $from = $entry['from'];
                    $to = $entry['to'];
                    $modelClass = $to;

                    $ids = DB::table($table)
                        ->where($typeColumn, $from)
                        ->pluck('id');

                    foreach ($ids as $rowId) {
                        $foreignId = DB::table($table)->where('id', $rowId)->value($idColumn);
                        $newForeignId = $foreignId;

                        if ($foreignId !== null && ! $modelClass::query()->whereKey($foreignId)->exists()) {
                            $newForeignId = null;
                            $nullIdReports[] = "{$table}#{$rowId} {$idColumn}={$foreignId} → NULL ({$to})";
                        }

                        DB::table($table)->where('id', $rowId)->update([
                            $typeColumn => $to,
                            $idColumn => $newForeignId,
                        ]);
                    }
                }
            }
        });

        if ($nullIdReports !== []) {
            $this->line('');
            $this->warn('ID tidak ditemukan di tabel tujuan (di-null-kan):');
            foreach ($nullIdReports as $line) {
                $this->line('  '.$line);
            }
        }

        $this->info('Normalisasi selesai.');

        return self::SUCCESS;
    }

    /**
     * @return array<string, list<array{from: string, to: string, count: int}>>
     */
    private function buildPlan(): array
    {
        $plan = [];

        foreach (self::TARGETS as $target) {
            $table = $target['table'];
            if (! Schema::hasTable($table)) {
                continue;
            }

            $typeColumn = $target['type_column'];
            $distinct = DB::table($table)
                ->select($typeColumn)
                ->whereNotNull($typeColumn)
                ->distinct()
                ->pluck($typeColumn);

            foreach ($distinct as $rawType) {
                if (! is_string($rawType) || $rawType === '') {
                    continue;
                }

                $targetClass = $this->normalizationTarget($rawType);
                if ($targetClass === null) {
                    continue;
                }

                $count = DB::table($table)->where($typeColumn, $rawType)->count();
                if ($count === 0) {
                    continue;
                }

                $plan[$table][] = [
                    'from' => $rawType,
                    'to' => $targetClass,
                    'count' => $count,
                ];
            }
        }

        return $plan;
    }

    private function normalizationTarget(string $type): ?string
    {
        if (str_contains($type, '\\') && class_exists($type)) {
            return null;
        }

        return Relation::getMorphedModel($type);
    }

    /**
     * @param  array<string, list<array{from: string, to: string, count: int}>>  $plan
     */
    private function planRowCount(array $plan): int
    {
        $total = 0;
        foreach ($plan as $rows) {
            foreach ($rows as $row) {
                $total += $row['count'];
            }
        }

        return $total;
    }
}
