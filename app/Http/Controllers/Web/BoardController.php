<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\PreparesTaskViews;
use App\Models\Task;
use App\Support\BoardHealth;
use App\Support\BoardVersion;
use App\Support\TaskFilterQuery;
use App\Support\TaskRules;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class BoardController extends Controller
{
    use PreparesTaskViews;

    public function index(Request $request)
    {
        $board = $this->boardPayload($request);
        $totalTasks = Task::query()->count();
        $hasActiveFilters = TaskFilterQuery::hasActiveFilters($request);
        $healthCounts = BoardHealth::counts();

        return view('board.index', [
            ...$board,
            'statusLabels' => $this->statusLabels(),
            'priorityLabels' => $this->priorityLabels(),
            'totalTasks' => $totalTasks,
            'hasActiveFilters' => $hasActiveFilters,
            'filters' => $this->filterOptions(),
            'healthCounts' => $healthCounts,
            'healthTotal' => BoardHealth::totalDeviations($healthCounts),
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
        $board = $this->boardPayload($request);
        $totalTasks = Task::query()->count();

        $html = '';
        if ($this->shouldRenderBoard($request, $totalTasks, $board)) {
            $html = view('partials.board-fragment', [
                'columns' => $board['columns'],
                'doneStrip' => $board['doneStrip'],
                'statusDoneFilter' => $board['statusDoneFilter'],
                'viewAllQuery' => $board['viewAllQuery'],
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

    /**
     * @return array{
     *     columns: array<string, Collection<int, Task>>,
     *     doneStrip: array{total: int, tasks: Collection<int, Task>},
     *     statusDoneFilter: bool,
     *     viewAllQuery: array<string, mixed>,
     *     activeVisibleCount: int,
     * }
     */
    private function boardPayload(Request $request): array
    {
        $tasks = TaskFilterQuery::fromRequest($request)->get();

        $columns = [];
        foreach (TaskRules::BOARD_COLUMN_STATUSES as $status) {
            $columns[$status] = $tasks->where('status', $status)->values();
        }

        $doneTasks = $tasks->where('status', 'done')
            ->sortByDesc(fn (Task $task) => ($task->updated_at?->getTimestamp() ?? 0) * 1_000_000 + $task->id)
            ->values();

        $viewAllQuery = $request->query();
        unset($viewAllQuery['status']);
        $viewAllQuery['status'] = 'done';

        return [
            'columns' => $columns,
            'doneStrip' => [
                'total' => $doneTasks->count(),
                'tasks' => $doneTasks->take(8),
            ],
            'statusDoneFilter' => $request->filled('status') && (string) $request->string('status') === 'done',
            'viewAllQuery' => $viewAllQuery,
            'activeVisibleCount' => collect($columns)->flatten(1)->count(),
        ];
    }

    /**
     * @param  array{
     *     columns: array<string, Collection<int, Task>>,
     *     doneStrip: array{total: int, tasks: Collection<int, Task>},
     *     statusDoneFilter: bool,
     *     viewAllQuery: array<string, mixed>,
     *     activeVisibleCount: int,
     * }  $board
     */
    private function shouldRenderBoard(Request $request, int $totalTasks, array $board): bool
    {
        if ($totalTasks === 0) {
            return false;
        }

        if ($board['activeVisibleCount'] > 0 || $board['doneStrip']['total'] > 0) {
            return true;
        }

        return $board['statusDoneFilter'];
    }
}
