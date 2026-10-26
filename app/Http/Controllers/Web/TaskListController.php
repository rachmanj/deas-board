<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\PreparesTaskViews;
use App\Models\Task;
use App\Support\TaskFilterQuery;
use Illuminate\Http\Request;

class TaskListController extends Controller
{
    use PreparesTaskViews;

    public function index(Request $request)
    {
        $tasks = TaskFilterQuery::fromRequest($request)->get();

        $totalTasks = Task::query()->count();
        $hasActiveFilters = $request->hasAny(['assignee', 'project', 'priority', 'overdue', 'q', 'session_id', 'status']);

        return view('tasks.index', [
            'tasks' => $tasks,
            'statusLabels' => $this->statusLabels(),
            'priorityLabels' => $this->priorityLabels(),
            'totalTasks' => $totalTasks,
            'hasActiveFilters' => $hasActiveFilters,
            'filters' => $this->filterOptions(),
            'pageTitle' => 'Daftar task',
        ]);
    }
}
