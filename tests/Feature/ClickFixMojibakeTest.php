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

    public function test_dry_run_reports_without_writing(): void
    {
        $broken = $this->brokenUtf8('Langkah → berikut');
        Task::create([
            'code' => 'CLK-MJ',
            'title' => $broken,
            'status' => 'todo',
            'priority' => 'normal',
        ]);

        $this->artisan('click:fix-mojibake', ['--dry-run' => true])
            ->assertSuccessful()
            ->expectsOutputToContain('tasks (1 baris)');

        $this->assertSame($broken, Task::query()->value('title'));
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
            'body_md' => 'Komentar biasa',
            'author_type' => User::class,
            'author_id' => $user->id,
        ]);

        $this->artisan('click:fix-mojibake', ['--apply' => true])
            ->assertSuccessful()
            ->expectsOutputToContain('Tidak ada baris dengan pola mojibake terdeteksi.');

        $this->assertSame('Sudah benar →', Task::query()->value('title'));
    }
}
