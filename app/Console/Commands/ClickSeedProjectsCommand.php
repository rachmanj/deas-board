<?php

namespace App\Console\Commands;

use App\Models\Project;
use App\Models\Session;
use App\Models\Task;
use App\Support\SessionProjectSlug;
use Illuminate\Console\Command;

class ClickSeedProjectsCommand extends Command
{
    private const NEUTRAL_COLOR = '#64748b';

    protected $signature = 'click:seed-projects
                            {--dry-run : Preview seeding without writing}
                            {--apply : Apply projects and task project_id updates}';

    protected $description = 'Create projects from session app labels and backfill task project_id';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $apply = (bool) $this->option('apply');

        if ($dryRun === $apply) {
            $this->error('Pilih tepat satu: --dry-run atau --apply.');

            return self::FAILURE;
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
            if ($slug === '' || isset($projects[$slug])) {
                continue;
            }

            $projects[$slug] = $label;
        }

        return $projects;
    }
}
