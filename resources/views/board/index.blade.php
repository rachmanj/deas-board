@extends('layouts.app')

@push('page-title-meta')
    <span id="board-refresh-status" class="mt-0.5 block text-xs text-zinc-400" aria-live="polite"></span>
@endpush

@section('content')
    <div
        id="board-area"
        x-data="boardAutoRefresh(@js(route('board.version', request()->query())), @js(route('board.fragment', request()->query())))"
        x-init="init()"
    >
        @if($errors->any())
            <div class="mb-4 rounded-lg border border-red-900/50 bg-red-950/40 px-4 py-3 text-sm text-red-300" role="alert">
                <ul class="list-inside list-disc">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @include('partials.task-filters', [
            'filterAction' => route('board'),
            'filters' => $filters,
            'priorityLabels' => $priorityLabels,
            'statusLabels' => $statusLabels,
        ])

        @php
            $visibleCount = collect($columns)->flatten(1)->count();
        @endphp

        @include('partials.empty-states', [
            'totalTasks' => $totalTasks,
            'hasActiveFilters' => $hasActiveFilters,
            'isEmpty' => $visibleCount === 0,
            'clearFiltersUrl' => route('board'),
        ])

        <div id="board-columns-container" x-ref="columnsContainer">
            @if($totalTasks > 0 && $visibleCount > 0)
                @include('partials.board-columns', [
                    'columns' => $columns,
                    'statusLabels' => $statusLabels,
                    'priorityLabels' => $priorityLabels,
                ])
            @endif
        </div>
    </div>
@endsection
