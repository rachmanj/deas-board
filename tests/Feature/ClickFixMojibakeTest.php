<?php

namespace Tests\Feature;

use App\Models\Task;
use App\Models\TaskComment;
use App\Models\User;
use App\Support\Mojibake;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClickFixMojibakeTest extends TestCase
{
    use RefreshDatabase;

    private function brokenUtf8(string $correct): string
    {
        return mb_convert_encoding($correct, 'UTF-8', 'ISO-8859-1');
    }

    private function cp1252Mojibake(string $correct): string
    {
        return mb_convert_encoding($correct, 'UTF-8', 'CP1252');
    }

    public function test_repair_production_cp1252_byte_patterns(): void
    {
        $cases = [
            ["\u{00E2}\u{2020}\u{2019}", '→'],
            ["\u{00E2}\u{20AC}\u{201D}", '—'],
            ["\u{00E2}\u{20AC}\u{2122}", '’'],
            ["\u{00E2}\u{20AC}\u{0153}", '“'],
            ["\u{00E2}\u{20AC}\u{009D}", '”'],
            ["\u{00E2}\u{20AC}\u{00A6}", '…'],
        ];

        foreach ($cases as [$broken, $expected]) {
            $this->assertSame($expected, Mojibake::repair($broken), 'hex before: '.bin2hex($broken));
            $fixed = Mojibake::repair($broken);
            $this->assertSame($expected, $fixed, 'hex after: '.bin2hex((string) $fixed));
        }
    }

    public function test_repair_production_mixed_sentence_with_emoji(): void
    {
        $input = 'Alur: A '.$this->cp1252Mojibake('→').' B '.$this->cp1252Mojibake('—').' selesai ✅';
        $expected = 'Alur: A → B — selesai ✅';

        $this->assertSame($expected, Mojibake::repair($input));
    }

    public function test_repair_leaves_correct_utf8_arrow_unchanged(): void
    {
        $value = 'Sudah benar → tanpa mojibake';

        $result = Mojibake::repairWithStatus($value);

        $this->assertSame('none', $result['action']);
        $this->assertSame($value, $result['value']);
    }

    public function test_repair_reverses_common_double_encoding_sequences(): void
    {
        $this->assertSame('→', Mojibake::repair($this->brokenUtf8('→')));
        $this->assertSame('→', Mojibake::repair($this->cp1252Mojibake('→')));
        $this->assertSame('—', Mojibake::repair($this->brokenUtf8('—')));
        $this->assertSame('’', Mojibake::repair($this->brokenUtf8('’')));
        $this->assertSame('“', Mojibake::repair($this->brokenUtf8('“')));
        $this->assertSame('”', Mojibake::repair($this->brokenUtf8('”')));

        $mixed = $this->brokenUtf8('Langkah → selesai — catatan ’');
        $this->assertSame('Langkah → selesai — catatan ’', Mojibake::repair($mixed));
    }

    public function test_repair_mixed_correct_and_broken_sequences_in_one_string(): void
    {
        $input = 'sudah benar → dan rusak '.$this->brokenUtf8('—').' juga '.$this->brokenUtf8('→');
        $expected = 'sudah benar → dan rusak — juga →';

        $this->assertSame($expected, Mojibake::repair($input));
    }

    public function test_repair_preserves_emoji_while_fixing_mojibake(): void
    {
        $broken = $this->brokenUtf8('→');
        $value = 'Emoji 🎉 '.$broken.' selesai';

        $result = Mojibake::repairWithStatus($value);

        $this->assertSame('repaired', $result['action']);
        $this->assertSame('Emoji 🎉 → selesai', $result['value']);
        $this->assertFalse($result['unrecognized_remainder']);
    }

    public function test_repair_fixes_mojibake_alongside_cjk_without_mutation(): void
    {
        $value = 'Catatan 中文 dengan '.$this->brokenUtf8('→');

        $result = Mojibake::repairWithStatus($value);

        $this->assertSame('repaired', $result['action']);
        $this->assertSame('Catatan 中文 dengan →', $result['value']);
    }

    public function test_second_repair_pass_makes_no_changes(): void
    {
        $fixed = Mojibake::repair($this->brokenUtf8('→ dan —'));
        $again = Mojibake::repairWithStatus($fixed);

        $this->assertSame('none', $again['action']);
        $this->assertSame($fixed, $again['value']);
    }

    public function test_dry_run_reports_without_writing_and_shows_real_characters(): void
    {
        $broken = $this->brokenUtf8('→ dan — faktur');
        Task::create([
            'code' => 'CLK-MJ',
            'title' => $broken,
            'status' => 'todo',
            'priority' => 'normal',
        ]);

        $this->artisan('click:fix-mojibake', ['--dry-run' => true])
            ->assertSuccessful()
            ->expectsOutputToContain('terdeteksi: 1, diperbaiki: 1, dilewati: 0, sisa tidak dikenali: 0')
            ->expectsOutputToContain('→ dan — faktur')
            ->doesntExpectOutputToContain('???');

        $this->assertSame($broken, Task::query()->value('title'));
    }

    public function test_dry_run_sample_shows_arrow_and_em_dash_fixed_not_question_marks(): void
    {
        $title = $this->brokenUtf8('A → B — selesai');
        Task::create([
            'code' => 'CLK-DR',
            'title' => $title,
            'status' => 'todo',
            'priority' => 'normal',
        ]);

        $this->artisan('click:fix-mojibake', ['--dry-run' => true])
            ->assertSuccessful()
            ->expectsOutputToContain('A → B — selesai')
            ->doesntExpectOutputToContain('???');
    }

    public function test_dry_run_mixed_emoji_mojibake_and_correct_characters(): void
    {
        $title = '🚀 sudah benar → dan rusak '
            .$this->brokenUtf8('—')
            .' juga '
            .$this->brokenUtf8('→')
            .' 🎉';

        Task::create([
            'code' => 'CLK-MIX',
            'title' => $title,
            'status' => 'todo',
            'priority' => 'normal',
        ]);

        $this->withoutMockingConsoleOutput();

        $exitCode = $this->artisan('click:fix-mojibake', ['--dry-run' => true]);
        $this->assertSame(0, $exitCode);

        $output = \Illuminate\Support\Facades\Artisan::output();

        $this->assertStringContainsString('🚀 sudah benar → dan rusak — juga → 🎉', $output);
        $this->assertStringContainsString('diperbaiki: 1', $output);
        $this->assertStringNotContainsString('???', $output);
    }

    public function test_apply_repairs_common_sequences_and_second_run_is_no_op(): void
    {
        $title = $this->brokenUtf8('A → B — C ’');
        $task = Task::create([
            'code' => 'CLK-FIX',
            'title' => $title,
            'description_md' => $this->brokenUtf8('Panah → seharusnya ok'),
            'status' => 'todo',
            'priority' => 'normal',
        ]);

        $this->artisan('click:fix-mojibake', ['--apply' => true])
            ->assertSuccessful()
            ->expectsOutputToContain('Diperbarui:');

        $task->refresh();
        $this->assertSame('A → B — C ’', $task->title);
        $this->assertStringContainsString('→', $task->description_md);
        $this->assertFalse(Mojibake::containsPattern($task->title));

        $this->artisan('click:fix-mojibake', ['--dry-run' => true])
            ->assertSuccessful()
            ->expectsOutputToContain('Tidak ada baris dengan pola mojibake terdeteksi.');
    }

    public function test_apply_twice_is_idempotent(): void
    {
        $title = $this->brokenUtf8('Satu → dua — tiga');
        Task::create([
            'code' => 'CLK-IDEM',
            'title' => $title,
            'status' => 'todo',
            'priority' => 'normal',
        ]);

        $this->artisan('click:fix-mojibake', ['--apply' => true])->assertSuccessful();
        $expected = Task::query()->value('title');

        $this->artisan('click:fix-mojibake', ['--apply' => true])
            ->assertSuccessful()
            ->expectsOutputToContain('Tidak ada baris dengan pola mojibake terdeteksi.');

        $this->assertSame($expected, Task::query()->value('title'));
    }

    public function test_apply_leaves_clean_text_unchanged(): void
    {
        $user = User::factory()->create();
        $task = Task::create([
            'code' => 'CLK-OK',
            'title' => 'Sudah benar →',
            'status' => 'todo',
            'priority' => 'normal',
        ]);
        TaskComment::create([
            'task_id' => $task->id,
            'body_md' => 'Komentar biasa tanpa mojibake',
            'author_type' => User::class,
            'author_id' => $user->id,
        ]);

        $this->artisan('click:fix-mojibake', ['--apply' => true])
            ->assertSuccessful()
            ->expectsOutputToContain('Tidak ada baris dengan pola mojibake terdeteksi.');

        $this->assertSame('Sudah benar →', Task::query()->value('title'));
    }

    public function test_dry_run_reports_skipped_rows_with_remaining_pattern_reason(): void
    {
        $unmapped = "\u{00E2}\u{0080}\u{00FF}";
        Task::create([
            'code' => 'CLK-SKIP',
            'title' => 'Campur 🎉 '.$unmapped.' teks',
            'status' => 'todo',
            'priority' => 'normal',
        ]);

        $this->withoutMockingConsoleOutput();

        $exitCode = $this->artisan('click:fix-mojibake', ['--dry-run' => true]);
        $this->assertSame(0, $exitCode);

        $output = \Illuminate\Support\Facades\Artisan::output();

        $this->assertStringContainsString('dilewati: 1', $output);
        $this->assertStringContainsString('poli mojibake tersisa', $output);
    }
}
