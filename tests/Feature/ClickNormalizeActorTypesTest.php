<?php

namespace Tests\Feature;

use App\Models\Session;
use App\Models\Task;
use App\Models\TaskComment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClickNormalizeActorTypesTest extends TestCase
{
    use RefreshDatabase;

    public function test_dry_run_reports_plan_without_mutating_rows(): void
    {
        $session = Session::create(['label' => 'S', 'topic_id' => '1', 'is_active' => true]);
        $task = Task::create([
            'code' => 'CLK-DR',
            'title' => 'Dry',
            'status' => 'todo',
            'priority' => 'normal',
        ]);
        TaskComment::create([
            'task_id' => $task->id,
            'body_md' => 'x',
            'author_type' => 'lead',
            'author_id' => $session->id,
        ]);

        $this->artisan('click:normalize-actor-types', ['--dry-run' => true])
            ->assertSuccessful()
            ->expectsOutputToContain('task_comments');

        $this->assertSame('lead', TaskComment::query()->value('author_type'));
    }

    public function test_apply_rewrites_lead_and_second_run_is_no_op(): void
    {
        $session = Session::create(['label' => 'S', 'topic_id' => '2', 'is_active' => true]);
        $task = Task::create([
            'code' => 'CLK-N',
            'title' => 'Norm',
            'status' => 'todo',
            'priority' => 'normal',
        ]);
        TaskComment::create([
            'task_id' => $task->id,
            'body_md' => 'legacy',
            'author_type' => 'lead',
            'author_id' => $session->id,
        ]);

        $this->artisan('click:normalize-actor-types', ['--apply' => true])
            ->assertSuccessful();

        $this->assertSame(Session::class, TaskComment::query()->value('author_type'));

        $this->artisan('click:normalize-actor-types', ['--dry-run' => true])
            ->assertSuccessful()
            ->expectsOutputToContain('Tidak ada baris yang perlu dinormalisasi.');
    }

    public function test_apply_leaves_full_class_names_unchanged(): void
    {
        $session = Session::create(['label' => 'S', 'topic_id' => '3', 'is_active' => true]);
        $task = Task::create([
            'code' => 'CLK-OK',
            'title' => 'OK',
            'status' => 'todo',
            'priority' => 'normal',
        ]);
        TaskComment::create([
            'task_id' => $task->id,
            'body_md' => 'ok',
            'author_type' => Session::class,
            'author_id' => $session->id,
        ]);

        $this->artisan('click:normalize-actor-types', ['--apply' => true])
            ->assertSuccessful()
            ->expectsOutputToContain('Tidak ada baris yang perlu dinormalisasi.');

        $this->assertSame(Session::class, TaskComment::query()->value('author_type'));
    }
}
