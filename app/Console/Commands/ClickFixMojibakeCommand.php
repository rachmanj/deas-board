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

        $updates = $this->collectUpdates();

        if ($updates === []) {
            $this->comment('Tidak ada baris dengan pola mojibake terdeteksi.');

            return self::SUCCESS;
        }

        foreach ($updates as $table => $rows) {
            $this->line('');
            $this->info($table.' ('.count($rows).' baris)');

            $samples = array_slice($rows, 0, 3);
            foreach ($samples as $row) {
                foreach ($row['changes'] as $column => $change) {
                    $this->line(sprintf(
                        '  [%s id=%s] %s',
                        $column,
                        (string) $row['id'],
                        Mojibake::sample($change['before']).' → '.Mojibake::sample($change['after'])
                    ));
                }
            }
        }

        if ($dryRun) {
            return self::SUCCESS;
        }

        $written = 0;

        DB::transaction(function () use ($updates, &$written) {
            foreach ($updates as $table => $rows) {
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
     * @return array<string, list<array{id: int|string, changes: array<string, array{before: string, after: string}>, changes_flat: array<string, string>}>>
     */
    private function collectUpdates(): array
    {
        $updates = [];

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

            $query = DB::table($table)->select(array_merge(['id'], $columns));

            foreach ($query->cursor() as $record) {
                $changes = [];
                $changesFlat = [];

                foreach ($columns as $column) {
                    $value = $record->{$column};
                    if (! is_string($value) || ! Mojibake::containsPattern($value)) {
                        continue;
                    }

                    $repaired = Mojibake::repair($value);
                    if ($repaired === $value) {
                        continue;
                    }

                    $changes[$column] = ['before' => $value, 'after' => $repaired];
                    $changesFlat[$column] = $repaired;
                }

                if ($changes === []) {
                    continue;
                }

                $updates[$table][] = [
                    'id' => $record->id,
                    'changes' => $changes,
                    'changes_flat' => $changesFlat,
                ];
            }
        }

        return $updates;
    }
}
