<?php

namespace App\Console\Commands;

use App\Support\Mojibake;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ClickFixMojibakeCommand extends Command
{
    protected $signature = 'click:fix-mojibake
                            {--dry-run : Report affected rows without writing}
                            {--apply : Repair mojibake in a transaction}';

    protected $description = 'Repair double-encoded UTF-8 (mojibake) in text columns';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $apply = (bool) $this->option('apply');

        if ($dryRun === $apply) {
            $this->error('Pilih tepat satu: --dry-run atau --apply.');

            return self::FAILURE;
        }

        $scan = $this->scan();

        if ($scan['updates'] === [] && $scan['skipped'] === []) {
            $this->comment('Tidak ada baris dengan pola mojibake terdeteksi.');

            return self::SUCCESS;
        }

        foreach ($scan['table_stats'] as $table => $stats) {
            $this->line('');
            $this->info($table.sprintf(
                ' — terdeteksi: %d, akan diperbaiki: %d, dilewati: %d',
                $stats['detected'],
                $stats['will_fix'],
                $stats['skipped']
            ));

            $samples = array_slice($scan['updates'][$table] ?? [], 0, 3);
            foreach ($samples as $row) {
                foreach ($row['changes'] as $column => $change) {
                    $this->line(sprintf(
                        '  [%s id=%s] %s → %s',
                        $column,
                        (string) $row['id'],
                        Mojibake::sample($change['before']),
                        Mojibake::sample($change['after'])
                    ));
                }
            }

            $skippedSamples = array_slice($scan['skipped'][$table] ?? [], 0, 3);
            foreach ($skippedSamples as $row) {
                foreach ($row['columns'] as $column => $meta) {
                    $this->line(sprintf(
                        '  dilewati [%s id=%s] alasan: %s',
                        $column,
                        (string) $row['id'],
                        $meta['skip_reason']
                    ));
                    $this->line('    cuplikan: '.Mojibake::sample($meta['value']));
                }
            }
        }

        if ($dryRun) {
            return self::SUCCESS;
        }

        if ($scan['updates'] === []) {
            $this->line('');
            $this->comment('Tidak ada baris yang aman untuk diperbaiki.');

            return self::SUCCESS;
        }

        $written = 0;

        DB::transaction(function () use ($scan, &$written) {
            foreach ($scan['updates'] as $table => $rows) {
                foreach ($rows as $row) {
                    DB::table($table)->where('id', $row['id'])->update($row['changes_flat']);
                    $written++;
                }
            }
        });

        $this->line('');
        $this->info("Diperbarui: {$written} baris.");

        return self::SUCCESS;
    }

    /**
     * @return array{
     *     updates: array<string, list<array{id: int|string, changes: array<string, array{before: string, after: string}>, changes_flat: array<string, string>}>>,
     *     skipped: array<string, list<array{id: int|string, columns: array<string, array{value: string, skip_reason: string}>}>>,
     *     table_stats: array<string, array{detected: int, will_fix: int, skipped: int}>
     * }
     */
    private function scan(): array
    {
        $updates = [];
        $skipped = [];
        $tableStats = [];

        foreach (Mojibake::SCAN_TARGETS as $target) {
            $table = $target['table'];
            if (! Schema::hasTable($table)) {
                continue;
            }

            $columns = array_values(array_filter(
                $target['columns'],
                fn (string $column) => Schema::hasColumn($table, $column)
            ));

            if ($columns === []) {
                continue;
            }

            $tableStats[$table] = ['detected' => 0, 'will_fix' => 0, 'skipped' => 0];

            $query = DB::table($table)->select(array_merge(['id'], $columns));

            foreach ($query->cursor() as $record) {
                $changes = [];
                $changesFlat = [];
                $skippedColumns = [];
                $rowDetected = false;

                foreach ($columns as $column) {
                    $value = $record->{$column};
                    if (! is_string($value) || ! Mojibake::containsPattern($value)) {
                        continue;
                    }

                    $rowDetected = true;
                    $result = Mojibake::repairWithStatus($value);

                    if ($result['action'] === 'repaired' && $result['value'] !== $value) {
                        $changes[$column] = ['before' => $value, 'after' => $result['value']];
                        $changesFlat[$column] = $result['value'];
                    } elseif ($result['action'] === 'skipped') {
                        $skippedColumns[$column] = [
                            'value' => $value,
                            'skip_reason' => $result['skip_reason'] ?? 'tidak bisa dibalik dengan aman',
                        ];
                    }
                }

                if (! $rowDetected) {
                    continue;
                }

                $tableStats[$table]['detected']++;

                if ($changes !== []) {
                    $tableStats[$table]['will_fix']++;
                    $updates[$table][] = [
                        'id' => $record->id,
                        'changes' => $changes,
                        'changes_flat' => $changesFlat,
                    ];
                }

                if ($skippedColumns !== []) {
                    $tableStats[$table]['skipped']++;
                    $skipped[$table][] = [
                        'id' => $record->id,
                        'columns' => $skippedColumns,
                    ];
                }
            }
        }

        return [
            'updates' => $updates,
            'skipped' => $skipped,
            'table_stats' => $tableStats,
        ];
    }
}
