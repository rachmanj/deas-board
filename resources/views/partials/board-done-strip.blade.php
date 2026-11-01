@if($doneStrip['total'] > 0)
    <section
        id="board-done-strip"
        class="mt-4 rounded-xl border border-zinc-800 bg-zinc-900 px-3 py-3"
        aria-labelledby="board-done-strip-heading"
    >
        <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
            <h2 id="board-done-strip-heading" class="text-sm font-medium text-zinc-100">
                Done
                <span class="ml-1 text-zinc-400">({{ $doneStrip['total'] }})</span>
            </h2>
            <a
                href="{{ route('tasks.index', $viewAllQuery) }}"
                class="text-xs text-teal-500 hover:text-teal-400 focus:outline-none focus-visible:ring-2 focus-visible:ring-teal-500/60"
            >
                View all
            </a>
        </div>
        <div class="flex max-h-[5.25rem] flex-wrap gap-2 overflow-hidden">
            @foreach($doneStrip['tasks'] as $task)
                <a
                    href="{{ route('tasks.show', $task) }}"
                    class="inline-flex max-w-full min-w-0 items-center gap-1.5 rounded-lg border border-zinc-800 bg-zinc-950 px-2.5 py-1.5 text-xs text-zinc-300 hover:border-zinc-700 hover:text-zinc-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-teal-500/60"
                    data-done-chip
                >
                    <span class="shrink-0 font-mono text-zinc-400">{{ $task->code }}</span>
                    <span class="min-w-0 truncate text-zinc-100">{{ $task->title }}</span>
                    <span class="hidden shrink-0 text-zinc-500 sm:inline">·</span>
                    <span class="hidden min-w-0 truncate text-zinc-400 sm:inline">
                        @if($task->assigneeSession)
                            {{ $task->assigneeSession->label }} ({{ $task->assigneeSession->topic_id }})
                        @else
                            Belum ada
                        @endif
                    </span>
                </a>
            @endforeach
        </div>
    </section>
@endif
