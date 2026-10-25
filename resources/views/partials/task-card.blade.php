@php
    $priorityClass = match ($task->priority) {
        'urgent' => 'text-red-400',
        'high' => 'text-amber-400',
        'low' => 'text-zinc-400',
        default => 'text-zinc-400',
    };
@endphp
<article class="rounded-lg border border-zinc-800 bg-zinc-950 p-3 text-sm">
    <div class="mb-1 font-mono text-xs text-zinc-400">{{ $task->code }}</div>
    <h3 class="mb-2 font-medium text-zinc-100">
        <a href="{{ route('tasks.show', $task) }}" class="hover:text-teal-400 focus:outline-none focus-visible:ring-2 focus-visible:ring-teal-500">
            {{ $task->title }}
        </a>
    </h3>
    <dl class="mb-3 space-y-1 text-xs text-zinc-400">
        <div class="flex justify-between gap-2">
            <dt>Penanggung</dt>
            <dd class="text-right text-zinc-300">{{ $task->assigneeSession?->label ?? 'Belum ada' }}</dd>
        </div>
        <div class="flex justify-between gap-2">
            <dt>Tenggat</dt>
            <dd class="text-right">{{ $task->due_date?->format('d M Y') ?? 'Tidak ada' }}</dd>
        </div>
        <div class="flex justify-between gap-2">
            <dt>Prioritas</dt>
            <dd class="text-right font-medium {{ $priorityClass }}">{{ $priorityLabels[$task->priority] ?? $task->priority }}</dd>
        </div>
        <div class="flex justify-between gap-2">
            <dt>Komentar</dt>
            <dd class="text-right">{{ $task->comments_count }}</dd>
        </div>
    </dl>
    <form method="POST" action="{{ route('tasks.status', $task) }}" class="flex items-center gap-2">
        @csrf
        @method('PATCH')
        <input type="hidden" name="redirect" value="{{ url()->current() }}">
        @foreach(request()->query() as $key => $value)
            @if(!is_array($value))
                <input type="hidden" name="{{ $key }}" value="{{ $value }}">
            @endif
        @endforeach
        <label for="status-{{ $task->id }}" class="sr-only">Status</label>
        <select id="status-{{ $task->id }}" name="status" onchange="this.form.submit()"
                class="min-h-11 w-full rounded-lg border border-zinc-800 bg-zinc-900 px-2 text-xs text-zinc-100 focus:border-teal-500 focus:outline-none focus:ring-2 focus:ring-teal-500/40">
            @foreach($statusLabels as $value => $label)
                <option value="{{ $value }}" @selected($task->status === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </form>
</article>
