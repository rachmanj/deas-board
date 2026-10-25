<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Session;
use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClickSeedProjectsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_apply_creates_projects_from_session_labels(): void
    {
        Session::create(['label' => 'AccountingOne', 'topic_id' => '1', 'is_active' => true]);
        Session::create(['label' => 'Arka Click', 'topic_id' => '2', 'is_active' => true]);

        $this->artisan('click:seed-projects', ['--apply' => true])->assertSuccessful();

        $this->assertSame(2, Project::count());
        $this->assertDatabaseHas('projects', [
            'slug' => 'accountingone',
            'name' => 'AccountingOne',
            'color' => '#64748b',
            'is_active' => true,
        ]);
        $this->assertDatabaseHas('projects', [
            'slug' => 'arka-click',
            'name' => 'Arka Click',
        ]);
    }

    public function test_apply_fills_task_project_id_without_touching_existing(): void
    {
        $session = Session::create(['label' => 'POS-ATK', 'topic_id' => '10', 'is_active' => true]);
        $other = Session::create(['label' => 'ARK-GS', 'topic_id' => '11', 'is_active' => true]);

        $existingProject = Project::create([
            'slug' => 'manual',
            'name' => 'Manual',
            'color' => '#111111',
            'is_active' => true,
        ]);

        $toFill = Task::create([
            'code' => 'CLK-100',
            'title' => 'Tanpa proyek',
            'status' => 'todo',
            'priority' => 'normal',
            'assignee_session_id' => $session->id,
        ]);

        $locked = Task::create([
            'code' => 'CLK-101',
            'title' => 'Sudah punya proyek',
            'status' => 'todo',
            'priority' => 'normal',
            'assignee_session_id' => $other->id,
            'project_id' => $existingProject->id,
        ]);

        $this->artisan('click:seed-projects', ['--apply' => true])->assertSuccessful();

        $toFill->refresh();
        $locked->refresh();

        $posProject = Project::query()->where('slug', 'pos-atk')->first();
        $this->assertNotNull($posProject);
        $this->assertSame($posProject->id, $toFill->project_id);
        $this->assertSame($existingProject->id, $locked->project_id);
    }

    public function test_one_project_per_duplicate_session_label(): void
    {
        Session::create(['label' => 'AccountingOne', 'topic_id' => '1', 'is_active' => true]);
        Session::create(['label' => 'AccountingOne', 'topic_id' => '99', 'is_active' => true]);

        $this->artisan('click:seed-projects', ['--apply' => true])->assertSuccessful();

        $this->assertSame(1, Project::query()->where('slug', 'accountingone')->count());
    }

    public function test_seed_is_idempotent(): void
    {
        $session = Session::create(['label' => 'AccountingOne', 'topic_id' => '1', 'is_active' => true]);
        Task::create([
            'code' => 'CLK-200',
            'title' => 'Backfill',
            'status' => 'todo',
            'priority' => 'normal',
            'assignee_session_id' => $session->id,
        ]);

        $this->artisan('click:seed-projects', ['--apply' => true])->assertSuccessful();
        $this->artisan('click:seed-projects', ['--apply' => true])->assertSuccessful();

        $this->assertSame(1, Project::count());
        $this->assertSame(1, Task::query()->whereNotNull('project_id')->count());
    }

    public function test_dry_run_reports_plan_without_writing(): void
    {
        $session = Session::create(['label' => 'AccountingOne', 'topic_id' => '1', 'is_active' => true]);
        Task::create([
            'code' => 'CLK-300',
            'title' => 'Akan diisi',
            'status' => 'todo',
            'priority' => 'normal',
            'assignee_session_id' => $session->id,
        ]);
        Task::create([
            'code' => 'CLK-301',
            'title' => 'Tanpa assignee',
            'status' => 'todo',
            'priority' => 'normal',
        ]);

        $this->artisan('click:seed-projects', ['--dry-run' => true])
            ->assertSuccessful()
            ->expectsOutputToContain('Proyek baru: 1')
            ->expectsOutputToContain('Task akan diisi: 1')
            ->expectsOutputToContain('Task dilewati: 1');

        $this->assertSame(0, Project::count());
        $this->assertNull(Task::query()->where('code', 'CLK-300')->value('project_id'));
    }

    public function test_apply_creates_clean_slug_from_em_dash_and_slash_labels(): void
    {
        Session::create(['label' => 'AccountingOne — LAP-KEU', 'topic_id' => '1', 'is_active' => true]);
        Session::create(['label' => 'Pratasaba/Hotel', 'topic_id' => '2', 'is_active' => true]);

        $this->artisan('click:seed-projects', ['--apply' => true])->assertSuccessful();

        $this->assertDatabaseHas('projects', ['slug' => 'accountingone-lap-keu']);
        $this->assertDatabaseHas('projects', ['slug' => 'pratasaba-hotel']);
    }

    public function test_reslug_fixes_messy_project_slugs(): void
    {
        Project::create([
            'slug' => 'accountingone-—-lap-keu',
            'name' => 'AccountingOne — LAP-KEU',
            'color' => '#64748b',
            'is_active' => true,
        ]);
        Project::create([
            'slug' => 'pratasaba/hotel',
            'name' => 'Pratasaba/Hotel',
            'color' => '#64748b',
            'is_active' => true,
        ]);

        $this->artisan('click:seed-projects', ['--reslug' => true, '--apply' => true])
            ->assertSuccessful()
            ->expectsOutputToContain('Slug diperbarui: 2');

        $this->assertDatabaseHas('projects', ['slug' => 'accountingone-lap-keu']);
        $this->assertDatabaseHas('projects', ['slug' => 'pratasaba-hotel']);
    }

    public function test_reslug_is_idempotent(): void
    {
        Project::create([
            'slug' => 'accountingone-lap-keu',
            'name' => 'AccountingOne — LAP-KEU',
            'color' => '#64748b',
            'is_active' => true,
        ]);

        $this->artisan('click:seed-projects', ['--reslug' => true, '--apply' => true])
            ->assertSuccessful()
            ->expectsOutputToContain('Slug sudah benar: 1')
            ->expectsOutputToContain('Slug diperbarui: 0');

        $this->artisan('click:seed-projects', ['--reslug' => true, '--apply' => true])
            ->assertSuccessful()
            ->expectsOutputToContain('Slug sudah benar: 1')
            ->expectsOutputToContain('Slug diperbarui: 0');
    }

    public function test_reslug_assigns_suffix_when_names_collide(): void
    {
        Project::create([
            'slug' => 'alpha',
            'name' => 'Alpha',
            'color' => '#64748b',
            'is_active' => true,
        ]);
        Project::create([
            'slug' => 'alpha-messy',
            'name' => 'Alpha',
            'color' => '#64748b',
            'is_active' => true,
        ]);

        $this->artisan('click:seed-projects', ['--reslug' => true, '--apply' => true])
            ->assertSuccessful();

        $this->assertDatabaseHas('projects', ['slug' => 'alpha']);
        $this->assertDatabaseHas('projects', ['slug' => 'alpha-2']);
    }
}
