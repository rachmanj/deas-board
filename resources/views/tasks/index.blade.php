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
        'filterAction' => route('tasks.index'),
        'filters' => $filters,
        'priorityLabels' => $priorityLabels,
        'statusLabels' => $statusLabels,
        'showStatusFilter' => true,
    ])

    @php
        $visibleCount = $tasks->count();
    @endphp

    @include('partials.empty-states', [
        'totalTasks' => $totalTasks,
        'hasActiveFilters' => $hasActiveFilters,
        'isEmpty' => $visibleCount === 0,
        'clearFiltersUrl' => route('tasks.index'),
    ])

    @if($totalTasks > 0 && $visibleCount > 0)
        <div class="overflow-x-auto rounded-xl border border-zinc-800 bg-zinc-900">
            <table class="w-full min-w-[48rem] text-left text-sm">
                <thead class="border-b border-zinc-800 text-xs text-zinc-400">
                    <tr>
                        <th class="px-3 py-2 font-medium">Kode</th>
                        <th class="px-3 py-2 font-medium">Judul</th>
                        <th class="px-3 py-2 font-medium">Status</th>
                        <th class="px-3 py-2 font-medium">Prioritas</th>
                        <th class="px-3 py-2 font-medium">Sesi</th>
                        <th class="px-3 py-2 font-medium">Proyek</th>
                        <th class="px-3 py-2 font-medium">Tenggat</th>
                        <th class="px-3 py-2 font-medium">Umur (hari)</th>
                        <th class="px-3 py-2 font-medium">Komentar</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-800">
                    @foreach($tasks as $task)
                        @php
                            $priorityClass = match ($task->priority) {
                                'urgent' => 'text-red-400',
                                'high' => 'text-amber-400',
                                'low' => 'text-zinc-500',
                                default => 'text-zinc-400',
                            };
                            $ageDays = $task->created_at?->startOfDay()->diffInDays(now()->startOfDay());
                        @endphp
                        <tr class="cursor-pointer hover:bg-zinc-950/80 focus-within:bg-zinc-950/80 focus-within:ring-2 focus-within:ring-inset focus-within:ring-teal-500"
                            tabindex="0" role="link"
                            onclick="window.location='{{ route('tasks.show', $task) }}'"
                            onkeydown="if (event.key === 'Enter') { window.location='{{ route('tasks.show', $task) }}'; }">
                            <td class="px-3 py-2 font-mono text-xs text-zinc-500">{{ $task->code }}</td>
                            <td class="px-3 py-2 text-zinc-100">{{ $task->title }}</td>
                            <td class="px-3 py-2 text-zinc-400">{{ $statusLabels[$task->status] ?? $task->status }}</td>
                            <td class="px-3 py-2 {{ $priorityClass }}">{{ $priorityLabels[$task->priority] ?? $task->priority }}</td>
                            <td class="px-3 py-2 text-zinc-400">{{ $task->assigneeSession?->label ?? 'Belum ada' }}</td>
                            <td class="px-3 py-2 text-zinc-400">{{ $task->project?->name ?? 'Tidak ada' }}</td>
                            <td class="px-3 py-2 text-zinc-400">{{ $task->due_date?->format('d M Y') ?? 'Tidak ada' }}</td>
                            <td class="px-3 py-2 text-zinc-400">{{ $ageDays }}</td>
                            <td class="px-3 py-2 text-zinc-400">{{ $task->comments_count }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
@endsection
