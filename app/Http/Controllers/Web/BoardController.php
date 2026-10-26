<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\PreparesTaskViews;
use App\Models\Task;
use App\Support\TaskFilterQuery;
use App\Support\TaskRules;
use Illuminate\Http\Request;

class BoardController extends Controller
{
    use PreparesTaskViews;

    public function index(Request $request)
    {
        $tasks = TaskFilterQuery::fromRequest($request)->get();

        $columns = [];
        foreach (TaskRules::STATUSES as $status) {
            $columns[$status] = $tasks->where('status', $status)->values();
        }

        $totalTasks = Task::query()->count();
        $hasActiveFilters = TaskFilterQuery::hasActiveFilters($request);

        return view('board.index', [
            'columns' => $columns,
            'statusLabels' => $this->statusLabels(),
            'priorityLabels' => $this->priorityLabels(),
            'totalTasks' => $totalTasks,
            'hasActiveFilters' => $hasActiveFilters,
            'filters' => $this->filterOptions(),
            'pageTitle' => 'Papan',
        ]);
    }
}
