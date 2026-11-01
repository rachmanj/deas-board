@include('partials.board-columns', [
    'columns' => $columns,
    'statusLabels' => $statusLabels,
    'priorityLabels' => $priorityLabels,
    'statusDoneFilter' => $statusDoneFilter,
])

@include('partials.board-done-strip', [
    'doneStrip' => $doneStrip,
    'viewAllQuery' => $viewAllQuery,
])
