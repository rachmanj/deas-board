<?php

namespace App\Console\Commands;

use App\Models\Project;
use App\Models\Session;
use App\Models\Task;
use App\Support\SessionProjectSlug;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class ClickSeedProjectsCommand extends Command
{
    private const NEUTRAL_COLOR = '#64748b';

    protected $signature = 'click:seed-projects
                            {--dry-run : Preview seeding without writing}
                            {--apply : Apply projects and task project_id updates}
                            {--reslug : Normalize existing project slugs from their names}';

    protected $description = 'Create projects from session app labels and backfill task project_id';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $apply = (bool) $this->option('apply');
        $reslug = (bool) $this->option('reslug');

        if ($dryRun === $apply) {
            $this->error('Pilih tepat satu: --dry-run atau --apply.');

            return self::FAILURE;
        }

        if ($reslug) {
            return $this->handleReslug($dryRun);
        }

        $projectsBySlug = $this->projectsFromSessions();
        $existingSlugs = Project::query()
            ->whereIn('slug', array_keys($projectsBySlug))
            ->pluck('slug')
            ->all();
        $existingSlugSet = array_flip($existingSlugs);

        $newProjectCount = 0;
        foreach ($projectsBySlug as $slug => $name) {
            if (! isset($existingSlugSet[$slug])) {
                $newProjectCount++;
            }
        }

        if ($apply) {
            foreach ($projectsBySlug as $slug => $name) {
                if (isset($existingSlugSet[$slug])) {
                    continue;
                }

                Project::create([
                    'slug' => $slug,
                    'name' => $name,
                    'color' => self::NEUTRAL_COLOR,
                    'is_active' => true,
                ]);
                $existingSlugSet[$slug] = true;
            }
        }

        $slugToProjectId = Project::query()
            ->whereIn('slug', array_keys($projectsBySlug))
            ->pluck('id', 'slug')
            ->all();

        $tasksToFill = 0;
        $tasksSkipped = 0;

        $tasks = Task::query()->with('assigneeSession:id,label')->get(['id', 'project_id', 'assignee_session_id']);

        foreach ($tasks as $task) {
            if ($task->project_id !== null) {
                $tasksSkipped++;

                continue;
            }

            $label = $task->assigneeSession?->label;
            if (! is_string($label) || trim($label) === '') {
                $tasksSkipped++;

                continue;
            }

            $slug = SessionProjectSlug::fromLabel($label);
            if (! isset($projectsBySlug[$slug])) {
                $tasksSkipped++;

                continue;
            }

            if ($apply) {
                $projectId = $slugToProjectId[$slug] ?? null;
                if ($projectId === null) {
                    $tasksSkipped++;

                    continue;
                }

                $task->update(['project_id' => $projectId]);
            }

            $tasksToFill++;
        }

        if ($dryRun) {
            $this->line("Proyek baru: {$newProjectCount}");
            $this->line("Task akan diisi: {$tasksToFill}");
            $this->line("Task dilewati: {$tasksSkipped}");

            return self::SUCCESS;
        }

        $this->info("Proyek baru: {$newProjectCount}");
        $this->line("Task diisi: {$tasksToFill}");
        $this->line("Task dilewati: {$tasksSkipped}");

        return self::SUCCESS;
    }

    private function handleReslug(bool $dryRun): int
    {
        $projects = Project::query()->orderBy('id')->get();
        $assigned = $this->resolveProjectSlugs($projects);

        $updated = 0;
        $skippedConflict = 0;
        $alreadyCorrect = 0;

        $toApply = [];

        foreach ($projects as $project) {
            $target = $assigned[$project->id];
            $base = SessionProjectSlug::fromLabel($project->name);

            if ($target !== $base) {
                $skippedConflict++;
            }

            if ($project->slug === $target) {
                $alreadyCorrect++;

                continue;
            }

            $toApply[] = ['project' => $project, 'target' => $target];
            $updated++;
        }

        if (! $dryRun && $toApply !== []) {
            foreach ($toApply as $row) {
                $row['project']->update(['slug' => '__reslug-'.$row['project']->id]);
            }

            foreach ($toApply as $row) {
                $row['project']->update(['slug' => $row['target']]);
            }
        }

        $prefix = $dryRun ? 'akan ' : '';
        $this->line("Slug {$prefix}diperbarui: {$updated}");
        $this->line("Slug dilewati (bentrok): {$skippedConflict}");
        $this->line("Slug sudah benar: {$alreadyCorrect}");

        return self::SUCCESS;
    }

    /**
     * @return array<int, string> project id => unique slug
     */
    private function resolveProjectSlugs(Collection $projects): array
    {
        $assigned = [];
        $occupied = [];

        foreach ($projects as $project) {
            $base = SessionProjectSlug::fromLabel($project->name);
            $slug = $base;
            $suffix = 2;

            while (isset($occupied[$slug])) {
                $slug = $base.'-'.$suffix;
                $suffix++;
            }

            $occupied[$slug] = $project->id;
            $assigned[$project->id] = $slug;
        }

        return $assigned;
    }

    /**
     * @return array<string, string> slug => display name
     */
    private function projectsFromSessions(): array
    {
        $projects = [];

        $labels = Session::query()
            ->whereNotNull('label')
            ->where('label', '!=', '')
            ->orderBy('id')
            ->pluck('label');

        foreach ($labels as $label) {
            if (! is_string($label)) {
                continue;
            }

            $slug = SessionProjectSlug::fromLabel($label);
            if (isset($projects[$slug])) {
                continue;
            }

            $projects[$slug] = $label;
        }

        return $projects;
    }
}
