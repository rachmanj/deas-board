@php
    use App\Support\BoardHealth;
@endphp
<section id="{{ $id }}" class="rounded-xl border border-zinc-800 bg-zinc-900">
    <header class="border-b border-zinc-800 px-4 py-3">
        <h2 class="text-sm font-semibold text-zinc-100">{{ $title }}</h2>
        @if(! empty($subtitle))
            <p class="mt-0.5 text-xs text-zinc-500">{{ $subtitle }}</p>
        @endif
        <p class="mt-0.5 text-xs text-zinc-400">{{ $tasks->count() }} task</p>
    </header>
    @if($tasks->isEmpty())
        <p class="px-4 py-6 text-sm text-zinc-500">Tidak ada penyimpangan.</p>
    @else
        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead class="border-b border-zinc-800 bg-zinc-950/50 text-xs text-zinc-400">
                    <tr>
                        <th class="px-3 py-2 font-medium">Kode</th>
                        <th class="px-3 py-2 font-medium">Judul</th>
                        <th class="px-3 py-2 font-medium">Sesi</th>
                        <th class="px-3 py-2 font-medium">Umur</th>
                        <th class="px-3 py-2 font-medium">Detail</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-800">
                    @foreach($tasks as $task)
                        @php
                            $anchor = BoardHealth::taskActivityAnchor($task);
                        @endphp
                        <tr>
                            <td class="px-3 py-2 font-mono text-xs text-teal-500">{{ $task->code }}</td>
                            <td class="px-3 py-2 text-zinc-200">{{ $task->title }}</td>
                            <td class="px-3 py-2 text-zinc-400">
                                @if($task->assigneeSession)
                                    {{ $task->assigneeSession->label }} ({{ $task->assigneeSession->topic_id }})
                                @else
                                    —
                                @endif
                            </td>
                            <td class="px-3 py-2 text-zinc-400">{{ BoardHealth::formatAge($anchor) }}</td>
                            <td class="px-3 py-2">
                                <a href="{{ route('tasks.show', $task) }}"
                                   class="text-teal-500 hover:text-teal-400 focus:outline-none focus-visible:ring-2 focus-visible:ring-teal-500/60">
                                    Buka
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>
