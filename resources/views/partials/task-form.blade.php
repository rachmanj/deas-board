@php
    $isEdit = $task !== null;
    $selectedLabels = $isEdit ? $task->labels->pluck('id')->all() : [];
@endphp

<form method="POST" action="{{ $isEdit ? route('tasks.update', $task) : route('tasks.store') }}" class="space-y-4"
      x-data="submitForm()" @submit="onFormSubmit">
    @csrf
    @if($isEdit)
        @method('PATCH')
    @endif

    <div>
        <label for="title" class="mb-1 block text-sm text-zinc-400">Judul</label>
        <input id="title" name="title" type="text" value="{{ old('title', $task?->title) }}" required
               class="min-h-11 w-full rounded-lg border border-zinc-800 bg-zinc-950 px-3 text-sm focus:border-teal-500 focus:outline-none focus:ring-2 focus:ring-teal-500/40">
    </div>

    <div>
        <label for="description_md" class="mb-1 block text-sm text-zinc-400">Deskripsi (Markdown)</label>
        <textarea id="description_md" name="description_md" rows="6"
                  class="w-full rounded-lg border border-zinc-800 bg-zinc-950 px-3 py-2 text-sm focus:border-teal-500 focus:outline-none focus:ring-2 focus:ring-teal-500/40">{{ old('description_md', $task?->description_md) }}</textarea>
    </div>

    <div class="grid gap-4 sm:grid-cols-2">
        <div>
            <label for="status" class="mb-1 block text-sm text-zinc-400">Status</label>
            <select id="status" name="status"
                    class="min-h-11 w-full rounded-lg border border-zinc-800 bg-zinc-950 px-3 text-sm focus:border-teal-500 focus:outline-none focus:ring-2 focus:ring-teal-500/40">
                @foreach($statusLabels as $value => $label)
                    <option value="{{ $value }}" @selected(old('status', $task?->status ?? 'todo') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="priority" class="mb-1 block text-sm text-zinc-400">Prioritas</label>
            <select id="priority" name="priority"
                    class="min-h-11 w-full rounded-lg border border-zinc-800 bg-zinc-950 px-3 text-sm focus:border-teal-500 focus:outline-none focus:ring-2 focus:ring-teal-500/40">
                @foreach($priorityLabels as $value => $label)
                    <option value="{{ $value }}" @selected(old('priority', $task?->priority ?? 'normal') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="grid gap-4 sm:grid-cols-2">
        <div>
            <label for="assignee_session_id" class="mb-1 block text-sm text-zinc-400">Sesi penanggung jawab</label>
            <select id="assignee_session_id" name="assignee_session_id"
                    class="min-h-11 w-full rounded-lg border border-zinc-800 bg-zinc-950 px-3 text-sm focus:border-teal-500 focus:outline-none focus:ring-2 focus:ring-teal-500/40">
                <option value="">Tanpa sesi</option>
                @foreach($sessions as $session)
                    <option value="{{ $session->id }}" @selected((string) old('assignee_session_id', $task?->assignee_session_id) === (string) $session->id)>
                        {{ $session->label }} ({{ $session->topic_id }})
                    </option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="project_id" class="mb-1 block text-sm text-zinc-400">Proyek</label>
            <select id="project_id" name="project_id"
                    class="min-h-11 w-full rounded-lg border border-zinc-800 bg-zinc-950 px-3 text-sm focus:border-teal-500 focus:outline-none focus:ring-2 focus:ring-teal-500/40">
                <option value="">Tanpa proyek</option>
                @foreach($projects as $project)
                    <option value="{{ $project->id }}" @selected((string) old('project_id', $task?->project_id) === (string) $project->id)>
                        {{ $project->name }}
                    </option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="grid gap-4 sm:grid-cols-2">
        <div>
            <label for="due_date" class="mb-1 block text-sm text-zinc-400">Tenggat</label>
            <input id="due_date" name="due_date" type="date" value="{{ old('due_date', $task?->due_date?->format('Y-m-d')) }}"
                   class="min-h-11 w-full rounded-lg border border-zinc-800 bg-zinc-950 px-3 text-sm focus:border-teal-500 focus:outline-none focus:ring-2 focus:ring-teal-500/40">
        </div>
        <div>
            <label for="start_date" class="mb-1 block text-sm text-zinc-400">Mulai</label>
            <input id="start_date" name="start_date" type="date" value="{{ old('start_date', $task?->start_date?->format('Y-m-d')) }}"
                   class="min-h-11 w-full rounded-lg border border-zinc-800 bg-zinc-950 px-3 text-sm focus:border-teal-500 focus:outline-none focus:ring-2 focus:ring-teal-500/40">
        </div>
    </div>

    <div>
        <label for="external_ref" class="mb-1 block text-sm text-zinc-400">Referensi eksternal</label>
        <input id="external_ref" name="external_ref" type="text" value="{{ old('external_ref', $task?->external_ref) }}"
               class="min-h-11 w-full rounded-lg border border-zinc-800 bg-zinc-950 px-3 text-sm focus:border-teal-500 focus:outline-none focus:ring-2 focus:ring-teal-500/40">
    </div>

    <div x-data="taskLabelPicker(@js(old('label_ids', $selectedLabels)), @js(route('labels.json')))" x-init="load()">
        <span class="mb-2 block text-sm text-zinc-400">Label</span>
        <div class="flex flex-wrap gap-2" x-show="labels.length" x-cloak>
            <template x-for="label in labels" :key="label.id">
                <label class="inline-flex min-h-11 cursor-pointer items-center gap-2 rounded-lg border border-zinc-800 px-3 text-sm has-[:checked]:border-teal-500/50 has-[:checked]:bg-teal-500/10"
                       :class="isSelected(label.id) ? 'border-teal-500/50 bg-teal-500/10' : ''">
                    <input type="checkbox" name="label_ids[]" :value="label.id"
                           :checked="isSelected(label.id)"
                           @change="toggle(label.id)">
                    <span x-text="label.name"></span>
                </label>
            </template>
        </div>
        <p class="text-sm text-zinc-400" x-show="!loading && labels.length === 0">Belum ada label.</p>
        <noscript>
            <div class="flex flex-wrap gap-2">
                @forelse($labels as $label)
                    <label class="inline-flex min-h-11 cursor-pointer items-center gap-2 rounded-lg border border-zinc-800 px-3 text-sm has-[:checked]:border-teal-500/50 has-[:checked]:bg-teal-500/10">
                        <input type="checkbox" name="label_ids[]" value="{{ $label->id }}"
                               @checked(in_array($label->id, old('label_ids', $selectedLabels)))>
                        <span>{{ $label->name }}</span>
                    </label>
                @empty
                    <p class="text-sm text-zinc-400">Belum ada label.</p>
                @endforelse
            </div>
        </noscript>
    </div>

    <div class="flex flex-wrap gap-2">
                <button type="submit" :disabled="processing"
                        class="inline-flex min-h-11 sm:min-h-10 items-center justify-center rounded-xl bg-teal-500/90 px-3.5 text-center text-sm font-medium text-zinc-950 shadow-sm hover:bg-teal-500 active:bg-teal-600 focus:outline-none focus-visible:ring-2 focus-visible:ring-teal-500/60 focus-visible:ring-offset-1 focus-visible:ring-offset-zinc-900 disabled:opacity-60"
                data-submit-label="{{ $isEdit ? 'Simpan perubahan' : 'Buat task' }}"
                x-text="buttonText()">
            {{ $isEdit ? 'Simpan perubahan' : 'Buat task' }}
        </button>
        @if($isEdit)
            <a href="{{ route('tasks.show', $task) }}"
               class="inline-flex min-h-11 sm:min-h-10 items-center justify-center rounded-xl border border-zinc-800/80 px-3.5 text-center text-sm font-medium text-zinc-300 hover:bg-zinc-800/60 hover:text-zinc-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-teal-500/60 focus-visible:ring-offset-1 focus-visible:ring-offset-zinc-900">
                Batal
            </a>
        @else
            <a href="{{ route('board') }}"
               class="inline-flex min-h-11 sm:min-h-10 items-center justify-center rounded-xl border border-zinc-800/80 px-3.5 text-center text-sm font-medium text-zinc-300 hover:bg-zinc-800/60 hover:text-zinc-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-teal-500/60 focus-visible:ring-offset-1 focus-visible:ring-offset-zinc-900">
                Batal
            </a>
        @endif
    </div>
</form>
