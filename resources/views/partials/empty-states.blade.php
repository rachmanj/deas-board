@if($totalTasks === 0)
    <div class="rounded-xl border border-zinc-800 bg-zinc-900 px-6 py-10 text-center text-sm text-zinc-400">
        <p class="mb-2 text-zinc-100">Belum ada task.</p>
        <p>Buat task pertama lewat API POST /api/tasks dengan judul dan penanggung jawab sesi.</p>
    </div>
@elseif($hasActiveFilters && $isEmpty)
    <div class="rounded-xl border border-zinc-800 bg-zinc-900 px-6 py-10 text-center text-sm text-zinc-400">
        <p class="mb-4">Tidak ada task yang cocok dengan filter.</p>
        <a href="{{ $clearFiltersUrl }}"
           class="inline-flex min-h-11 items-center rounded-lg border border-zinc-800 px-4 text-zinc-100 hover:border-zinc-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-teal-500">
            Hapus filter
        </a>
    </div>
@endif
