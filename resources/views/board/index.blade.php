@extends('layouts.app')

@section('content')
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

    @if($totalTasks > 0 && $visibleCount > 0)
        <div class="grid gap-3 lg:grid-cols-5">
            @foreach($columns as $status => $items)
                <section class="flex min-w-0 flex-col rounded-xl border border-zinc-800 bg-zinc-900" aria-labelledby="col-{{ $status }}">
                    <header class="border-b border-zinc-800 px-3 py-3">
                        <h2 id="col-{{ $status }}" class="text-sm font-medium text-zinc-100">
                            {{ $statusLabels[$status] }}
                            <span class="ml-1 text-zinc-400">({{ $items->count() }})</span>
                        </h2>
                    </header>
                    <div class="flex flex-col gap-2 p-2">
                        @forelse($items as $task)
                            @include('partials.task-card', [
                                'task' => $task,
                                'statusLabels' => $statusLabels,
                                'priorityLabels' => $priorityLabels,
                            ])
                        @empty
                            <p class="px-2 py-4 text-center text-xs text-zinc-500">Kosong</p>
                        @endforelse
                    </div>
                </section>
            @endforeach
        </div>
    @endif
@endsection
