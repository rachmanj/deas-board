<?php

namespace App\Http\Controllers\Web\Concerns;

use App\Models\Project;
use App\Models\Session;
use App\Support\TaskRules;

trait PreparesTaskViews
{
    protected function filterOptions(): array
    {
        return [
            'sessions' => Session::query()->where('is_active', true)->orderBy('label')->get(['id', 'label']),
            'projects' => Project::query()->where('is_active', true)->orderBy('name')->get(['id', 'slug', 'name']),
            'priorities' => TaskRules::PRIORITIES,
        ];
    }

    protected function statusLabels(): array
    {
        return [
            'todo' => 'Belum dikerjakan',
            'in_progress' => 'Dikerjakan',
            'blocked' => 'Terhambat',
            'review' => 'Ditinjau',
            'done' => 'Selesai',
        ];
    }

    protected function priorityLabels(): array
    {
        return [
            'low' => 'Rendah',
            'normal' => 'Normal',
            'high' => 'Tinggi',
            'urgent' => 'Mendesak',
        ];
    }
}
