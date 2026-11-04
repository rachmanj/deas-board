@if(($healthTotal ?? 0) > 0)
    <section class="mb-4 rounded-xl border border-amber-900/40 bg-amber-950/20 p-4" aria-label="Ringkasan kesehatan papan">
        <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
            <h2 class="text-sm font-semibold text-amber-200">Kesehatan papan</h2>
            <a href="{{ route('health') }}"
               class="text-xs font-medium text-teal-500 hover:text-teal-400 focus:outline-none focus-visible:ring-2 focus-visible:ring-teal-500/60">
                Lihat panel
            </a>
        </div>
        <dl class="grid grid-cols-2 gap-3 text-sm sm:grid-cols-4">
            <div>
                <dt class="text-xs text-zinc-400">In progress basi</dt>
                <dd>
                    <a href="{{ route('health') }}#in-progress-stale" class="text-lg font-semibold text-zinc-100 hover:text-teal-400">{{ $healthCounts['in_progress_stale'] }}</a>
                </dd>
            </div>
            <div>
                <dt class="text-xs text-zinc-400">Review basi</dt>
                <dd>
                    <a href="{{ route('health') }}#review-stale" class="text-lg font-semibold text-zinc-100 hover:text-teal-400">{{ $healthCounts['review_stale'] }}</a>
                </dd>
            </div>
            <div>
                <dt class="text-xs text-zinc-400">Done tanpa bukti</dt>
                <dd>
                    <a href="{{ route('health') }}#done-without-evidence" class="text-lg font-semibold text-zinc-100 hover:text-teal-400">{{ $healthCounts['done_without_evidence'] }}</a>
                </dd>
            </div>
            <div>
                <dt class="text-xs text-zinc-400">Todo indikasi commit</dt>
                <dd>
                    <a href="{{ route('health') }}#todo-commit-hint" class="text-lg font-semibold text-zinc-100 hover:text-teal-400">{{ $healthCounts['todo_commit_hint'] }}</a>
                </dd>
            </div>
        </dl>
    </section>
@endif
