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
                    <p class="px-2 py-4 text-center text-xs text-zinc-400">Kosong</p>
                @endforelse
            </div>
        </section>
    @endforeach
</div>
