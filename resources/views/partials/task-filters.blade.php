<form method="GET" action="{{ $filterAction }}" class="mb-4 flex flex-wrap items-end gap-3 rounded-xl border border-zinc-800 bg-zinc-900 p-4">
    <div>
        <label for="filter-assignee" class="mb-1 block text-xs text-zinc-400">Sesi</label>
        <select id="filter-assignee" name="assignee"
                class="min-h-11 rounded-lg border border-zinc-800 bg-zinc-950 px-3 text-sm text-zinc-100 focus:border-teal-500 focus:outline-none focus:ring-2 focus:ring-teal-500/40">
            <option value="">Semua sesi</option>
            @foreach($filters['sessions'] as $session)
                <option value="{{ $session->id }}" @selected(request('assignee') == $session->id)>{{ $session->label }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label for="filter-project" class="mb-1 block text-xs text-zinc-400">Proyek</label>
        <select id="filter-project" name="project"
                class="min-h-11 rounded-lg border border-zinc-800 bg-zinc-950 px-3 text-sm text-zinc-100 focus:border-teal-500 focus:outline-none focus:ring-2 focus:ring-teal-500/40">
            <option value="">Semua proyek</option>
            @foreach($filters['projects'] as $project)
                <option value="{{ $project->slug }}" @selected(request('project') == $project->slug || request('project') == $project->id)>{{ $project->name }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label for="filter-priority" class="mb-1 block text-xs text-zinc-400">Prioritas</label>
        <select id="filter-priority" name="priority"
                class="min-h-11 rounded-lg border border-zinc-800 bg-zinc-950 px-3 text-sm text-zinc-100 focus:border-teal-500 focus:outline-none focus:ring-2 focus:ring-teal-500/40">
            <option value="">Semua prioritas</option>
            @foreach($filters['priorities'] as $priority)
                <option value="{{ $priority }}" @selected(request('priority') === $priority)>{{ $priorityLabels[$priority] ?? $priority }}</option>
            @endforeach
        </select>
    </div>
    @isset($showStatusFilter)
        <div>
            <label for="filter-status" class="mb-1 block text-xs text-zinc-400">Status</label>
            <select id="filter-status" name="status"
                    class="min-h-11 rounded-lg border border-zinc-800 bg-zinc-950 px-3 text-sm text-zinc-100 focus:border-teal-500 focus:outline-none focus:ring-2 focus:ring-teal-500/40">
                <option value="">Semua status</option>
                @foreach($statusLabels as $value => $label)
                    <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
    @endisset
    <div class="flex min-h-11 items-center gap-2">
        <input id="filter-overdue" type="checkbox" name="overdue" value="1" @checked(request('overdue') === '1')
               class="size-4 rounded border-zinc-700 bg-zinc-950 text-teal-500 focus:ring-teal-500">
        <label for="filter-overdue" class="text-sm text-zinc-400">Hanya lewat tenggat</label>
    </div>
    <div class="min-w-0 flex-1 basis-full sm:basis-auto">
        <label for="filter-q" class="mb-1 block text-xs text-zinc-400">Cari</label>
        <input id="filter-q" name="q" type="search" value="{{ request('q') }}"
               class="min-h-11 w-full min-w-[12rem] rounded-lg border border-zinc-800 bg-zinc-950 px-3 text-sm text-zinc-100 focus:border-teal-500 focus:outline-none focus:ring-2 focus:ring-teal-500/40">
    </div>
    <button type="submit"
            class="min-h-11 rounded-lg bg-teal-500 px-4 text-sm font-medium text-zinc-950 hover:bg-teal-400 focus:outline-none focus-visible:ring-2 focus-visible:ring-teal-500 focus-visible:ring-offset-2 focus-visible:ring-offset-zinc-900">
        Terapkan
    </button>
</form>
