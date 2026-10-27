<form method="GET" action="{{ route('sessions') }}" class="mb-4 flex flex-wrap items-end gap-3 rounded-xl border border-zinc-800 bg-zinc-900 p-4">
    <div class="min-w-0 flex-1 basis-full sm:basis-auto">
        <label for="session-filter-q" class="mb-1 block text-xs text-zinc-400">Search</label>
        <input id="session-filter-q" name="q" type="search" value="{{ request('q') }}"
               placeholder="Name, topic ID, or chat ID"
               class="min-h-11 w-full min-w-[12rem] rounded-lg border border-zinc-800 bg-zinc-950 px-3 text-sm text-zinc-100 focus:border-teal-500 focus:outline-none focus:ring-2 focus:ring-teal-500/40">
    </div>
    <div>
        <label for="session-filter-status" class="mb-1 block text-xs text-zinc-400">Status</label>
        <select id="session-filter-status" name="status"
                class="min-h-11 rounded-lg border border-zinc-800 bg-zinc-950 px-3 text-sm text-zinc-100 focus:border-teal-500 focus:outline-none focus:ring-2 focus:ring-teal-500/40">
            <option value="">All</option>
            <option value="aktif" @selected(request('status') === 'aktif')>Active</option>
            <option value="nonaktif" @selected(request('status') === 'nonaktif')>Inactive</option>
        </select>
    </div>
    <div>
        <label for="session-filter-sort" class="mb-1 block text-xs text-zinc-400">Sort by</label>
        <select id="session-filter-sort" name="sort"
                class="min-h-11 rounded-lg border border-zinc-800 bg-zinc-950 px-3 text-sm text-zinc-100 focus:border-teal-500 focus:outline-none focus:ring-2 focus:ring-teal-500/40">
            <option value="label" @selected(request('sort', 'label') === 'label')>Name</option>
            <option value="topic" @selected(request('sort') === 'topic')>Topic ID</option>
            <option value="tasks" @selected(request('sort') === 'tasks')>Open tasks</option>
        </select>
    </div>
    <button type="submit"
            class="inline-flex min-h-11 items-center justify-center whitespace-nowrap rounded-lg bg-teal-500 px-4 text-center text-sm font-medium text-zinc-950 hover:bg-teal-400 focus:outline-none focus-visible:ring-2 focus-visible:ring-teal-500 focus-visible:ring-offset-2 focus-visible:ring-offset-zinc-900">
        Apply
    </button>
    @if($hasActiveFilters ?? false)
        <a href="{{ route('sessions') }}"
           class="inline-flex min-h-11 items-center justify-center whitespace-nowrap rounded-lg border border-zinc-800 px-4 text-center text-sm text-zinc-100 hover:border-zinc-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-teal-500">
            Clear filters
        </a>
    @endif
</form>
