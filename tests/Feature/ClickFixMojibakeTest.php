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

    public function test_repair_reverses_common_double_encoding_sequences(): void
    {
        $this->assertSame('→', Mojibake::repair($this->brokenUtf8('→')));
        $this->assertSame('—', Mojibake::repair($this->brokenUtf8('—')));
        $this->assertSame('’', Mojibake::repair($this->brokenUtf8('’')));
        $this->assertSame('“', Mojibake::repair($this->brokenUtf8('“')));
        $this->assertSame('”', Mojibake::repair($this->brokenUtf8('”')));

        $mixed = $this->brokenUtf8('Langkah → selesai — catatan ’');
        $this->assertSame('Langkah → selesai — catatan ’', Mojibake::repair($mixed));
    }

    public function test_repair_skips_values_with_non_latin1_characters(): void
    {
        $broken = $this->brokenUtf8('→');
        $value = 'Emoji 🎉 '.$broken;

        $result = Mojibake::repairWithStatus($value);

        $this->assertSame('skipped', $result['action']);
        $this->assertSame($value, $result['value']);
        $this->assertStringContainsString('255', $result['skip_reason'] ?? '');
    }

    public function test_repair_skips_cjk_without_mutation(): void
    {
        $value = 'Catatan 中文 dengan '.$this->brokenUtf8('→');

        $result = Mojibake::repairWithStatus($value);

        $this->assertSame('skipped', $result['action']);
        $this->assertSame($value, $result['value']);
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
            ->expectsOutputToContain('terdeteksi: 1, akan diperbaiki: 1, dilewati: 0')
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

    public function test_dry_run_reports_skipped_rows_with_reason(): void
    {
        $broken = $this->brokenUtf8('→');
        Task::create([
            'code' => 'CLK-SKIP',
            'title' => 'Campur 🎉 '.$broken,
            'status' => 'todo',
            'priority' => 'normal',
        ]);

        $this->withoutMockingConsoleOutput();

        $exitCode = $this->artisan('click:fix-mojibake', ['--dry-run' => true]);
        $this->assertSame(0, $exitCode);

        $output = \Illuminate\Support\Facades\Artisan::output();

        $this->assertStringContainsString('dilewati: 1', $output);
        $this->assertStringContainsString('alasan: mengandung karakter di luar Latin-1', $output);
    }
}
