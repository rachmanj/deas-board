<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\PreparesTaskViews;
use App\Models\Task;
use App\Support\BoardVersion;
use App\Support\TaskFilterQuery;
use App\Support\TaskRules;
use Illuminate\Http\Request;

class BoardController extends Controller
{
    use PreparesTaskViews;

    public function index(Request $request)
    {
        $columns = $this->columnsForRequest($request);
        $totalTasks = Task::query()->count();
        $hasActiveFilters = TaskFilterQuery::hasActiveFilters($request);

        return view('board.index', [
            'columns' => $columns,
            'statusLabels' => $this->statusLabels(),
            'priorityLabels' => $this->priorityLabels(),
            'totalTasks' => $totalTasks,
            'hasActiveFilters' => $hasActiveFilters,
            'filters' => $this->filterOptions(),
            'pageTitle' => 'Board',
        ]);
    }

    public function version(Request $request)
    {
        return response()
            ->json(BoardVersion::forRequest($request))
            ->header('Cache-Control', 'no-store');
    }

    public function fragment(Request $request)
    {
        $boardVersion = BoardVersion::forRequest($request);
        $columns = $this->columnsForRequest($request);
        $totalTasks = Task::query()->count();
        $visibleCount = collect($columns)->flatten(1)->count();

        $html = '';
        if ($totalTasks > 0 && $visibleCount > 0) {
            $html = view('partials.board-columns', [
                'columns' => $columns,
                'statusLabels' => $this->statusLabels(),
                'priorityLabels' => $this->priorityLabels(),
            ])->render();
        }

        $etag = '"'.hash('sha256', $html).'"';

        if ($request->header('If-None-Match') === $etag) {
            return response('', 304)
                ->header('ETag', $etag)
                ->header('X-Board-Hash', $boardVersion['hash'])
                ->header('Cache-Control', 'no-store');
        }

        return response($html, 200)
            ->header('ETag', $etag)
            ->header('X-Board-Hash', $boardVersion['hash'])
            ->header('Cache-Control', 'no-store');
    }

    private function columnsForRequest(Request $request): array
    {
        $tasks = TaskFilterQuery::fromRequest($request)->get();

        $columns = [];
        foreach (TaskRules::STATUSES as $status) {
            $columns[$status] = $tasks->where('status', $status)->values();
        }

        return $columns;
    }
}
