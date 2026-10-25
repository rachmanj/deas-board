@extends('layouts.app')

@php
    use App\Models\Session;
    use App\Models\User;

    $actorLabel = function ($actor) {
        if ($actor instanceof Session) {
            return $actor->label.' ('.$actor->topic_id.')';
        }
        if ($actor instanceof User) {
            return $actor->name;
        }

        return 'Sistem';
    };

    $formatLogChanges = function ($log) {
        if (! is_array($log->changes) || $log->changes === []) {
            return 'Belum diisi';
        }
        $parts = [];
        foreach ($log->changes as $key => $value) {
            if (is_array($value) && array_key_exists('old', $value) && array_key_exists('new', $value)) {
                $parts[] = $key.': '.$value['old'].' → '.$value['new'];
            } else {
                $parts[] = $key.': '.json_encode($value, JSON_UNESCAPED_UNICODE);
            }
        }

        return implode('; ', $parts);
    };
@endphp

@section('content')
    <div class="mb-4">
        <a href="{{ route('tasks.index') }}" class="text-sm text-teal-500 hover:text-teal-400 focus:outline-none focus-visible:ring-2 focus-visible:ring-teal-500">
            ← Kembali ke daftar
        </a>
    </div>

    <div class="flex flex-col gap-4 lg:flex-row lg:items-start">
        <div class="min-w-0 flex-1 space-y-4">
            <div class="rounded-xl border border-zinc-800 bg-zinc-900 p-4 sm:p-6">
                <p class="font-mono text-xs text-zinc-400">{{ $task->code }}</p>
                <h2 class="mt-1 text-xl font-semibold text-zinc-100">{{ $task->title }}</h2>

                @if($task->labels->isNotEmpty())
                    <div class="mt-3 flex flex-wrap gap-2">
                        @foreach($task->labels as $label)
                            <span class="rounded-full border border-zinc-700 bg-zinc-950 px-2.5 py-0.5 text-xs text-zinc-300">{{ $label->name }}</span>
                        @endforeach
                    </div>
                @endif

                @if($task->description_md)
                    <div class="prose prose-invert mt-4 max-w-none text-sm">
                        {!! \Illuminate\Support\Str::markdown($task->description_md) !!}
                    </div>
                @else
                    <p class="mt-4 text-sm text-zinc-400">Tidak ada deskripsi.</p>
                @endif
            </div>

            <div class="rounded-xl border border-zinc-800 bg-zinc-900" x-data="{ tab: 'diskusi' }" id="diskusi">
                <div class="flex flex-wrap border-b border-zinc-800" role="tablist">
                    <button type="button" role="tab" @click="tab = 'diskusi'"
                            :class="tab === 'diskusi' ? 'border-b-2 border-teal-500 text-teal-500' : 'text-zinc-400'"
                            class="min-h-11 px-4 text-sm font-medium focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-teal-500">
                        Diskusi
                    </button>
                    <button type="button" role="tab" @click="tab = 'status'"
                            :class="tab === 'status' ? 'border-b-2 border-teal-500 text-teal-500' : 'text-zinc-400'"
                            class="min-h-11 px-4 text-sm font-medium focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-teal-500">
                        Riwayat status
                    </button>
                    <button type="button" role="tab" @click="tab = 'aktivitas'"
                            :class="tab === 'aktivitas' ? 'border-b-2 border-teal-500 text-teal-500' : 'text-zinc-400'"
                            class="min-h-11 px-4 text-sm font-medium focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-teal-500">
                        Riwayat perubahan
                    </button>
                </div>

                <div class="p-4 sm:p-6">
                    <div x-show="tab === 'diskusi'" role="tabpanel" class="space-y-4">
                        @if($errors->any())
                            <div class="rounded-lg border border-red-900/50 bg-red-950/40 px-4 py-3 text-sm text-red-300" role="alert">
                                <ul class="list-inside list-disc">
                                    @foreach($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        @if($topLevelComments->isEmpty())
                            <p class="text-sm text-zinc-400">Belum ada komentar. Jadilah yang pertama menulis di diskusi ini.</p>
                        @else
                            <div class="space-y-4">
                                @foreach($topLevelComments as $comment)
                                    @include('partials.comment-item', [
                                        'comment' => $comment,
                                        'task' => $task,
                                        'allComments' => $allComments,
                                        'depth' => 0,
                                    ])
                                @endforeach
                            </div>
                        @endif

                        <form method="POST" action="{{ route('tasks.comments.store', $task) }}" class="space-y-2 border-t border-zinc-800 pt-4">
                            @csrf
                            <label for="new-comment" class="block text-sm font-medium text-zinc-300">Tulis komentar</label>
                            @include('partials.mention-textarea', [
                                'id' => 'new-comment',
                                'name' => 'body_md',
                                'rows' => 4,
                                'placeholder' => 'Markdown didukung. Ketik @ untuk menyebut sesi atau pengguna.',
                                'required' => true,
                                'value' => old('body_md'),
                            ])
                            <button type="submit"
                                    class="min-h-11 rounded-lg bg-teal-500 px-4 text-sm font-medium text-zinc-950 hover:bg-teal-400 focus:outline-none focus-visible:ring-2 focus-visible:ring-teal-500">
                                Kirim komentar
                            </button>
                        </form>
                    </div>

                    <div x-show="tab === 'status'" x-cloak role="tabpanel">
                        @if($updates->isEmpty())
                            <p class="text-sm text-zinc-400">Belum ada perubahan status.</p>
                        @else
                            <ul class="divide-y divide-zinc-800 text-sm">
                                @foreach($updates as $update)
                                    <li class="py-3">
                                        <div class="flex flex-wrap gap-2 text-zinc-300">
                                            <span>{{ $statusLabels[$update->from_status] ?? $update->from_status }}</span>
                                            <span class="text-zinc-400">→</span>
                                            <span>{{ $statusLabels[$update->to_status] ?? $update->to_status }}</span>
                                        </div>
                                        @if($update->note)
                                            <p class="mt-1 text-zinc-400">{{ $update->note }}</p>
                                        @endif
                                        @if($update->evidence)
                                            <p class="mt-1 text-xs text-zinc-400">Bukti: {{ $update->evidence }}</p>
                                        @endif
                                        <p class="mt-1 text-xs text-zinc-400">
                                            {{ $update->created_at?->format('d M Y H:i') }}
                                            · {{ $actorLabel($update->actor) }}
                                        </p>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>

                    <div x-show="tab === 'aktivitas'" x-cloak role="tabpanel">
                        @if($activityLogs->isEmpty())
                            <p class="text-sm text-zinc-400">Belum ada aktivitas tercatat.</p>
                        @else
                            <ul class="divide-y divide-zinc-800 text-sm">
                                @foreach($activityLogs as $log)
                                    <li class="py-3">
                                        <p class="text-zinc-300">
                                            <span class="font-mono text-xs text-teal-500/90">{{ $log->action }}</span>
                                            · {{ $formatLogChanges($log) }}
                                        </p>
                                        <p class="mt-1 text-xs text-zinc-400">
                                            {{ $log->created_at?->format('d M Y H:i') }}
                                            · {{ $actorLabel($log->actor) }}
                                        </p>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <aside class="w-full shrink-0 rounded-xl border border-zinc-800 bg-zinc-900 p-4 text-sm lg:w-72">
            <div class="mb-4 flex items-center justify-between gap-2">
                <h3 class="font-semibold text-zinc-100">Detail</h3>
                <a href="{{ route('tasks.edit', $task) }}"
                   class="min-h-9 rounded-lg border border-zinc-800 px-2 text-xs text-teal-500 hover:border-zinc-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-teal-500">
                    Ubah
                </a>
            </div>
            <dl class="space-y-3 text-zinc-400">
                <div>
                    <dt class="text-xs text-zinc-400">Status</dt>
                    <dd class="text-zinc-200">{{ $statusLabels[$task->status] ?? $task->status }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-zinc-400">Prioritas</dt>
                    <dd class="text-zinc-200">{{ $priorityLabels[$task->priority] ?? $task->priority }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-zinc-400">Sesi penanggung jawab</dt>
                    <dd class="text-zinc-200">
                        @if($task->assigneeSession)
                            {{ $task->assigneeSession->label }} ({{ $task->assigneeSession->topic_id }})
                        @else
                            Belum ada
                        @endif
                    </dd>
                </div>
                <div>
                    <dt class="text-xs text-zinc-400">Proyek</dt>
                    <dd class="text-zinc-200">{{ $task->project?->name ?? 'Tidak ada' }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-zinc-400">Tenggat</dt>
                    <dd class="text-zinc-200">{{ $task->due_date?->format('d M Y') ?? 'Tidak ada' }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-zinc-400">Mulai</dt>
                    <dd class="text-zinc-200">{{ $task->start_date?->format('d M Y') ?? 'Tidak ada' }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-zinc-400">Referensi eksternal</dt>
                    <dd class="break-all text-zinc-200">{{ $task->external_ref ?? 'Tidak ada' }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-zinc-400">Dibuat</dt>
                    <dd class="text-zinc-200">{{ $task->created_at?->format('d M Y H:i') }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-zinc-400">Diperbarui</dt>
                    <dd class="text-zinc-200">{{ $task->updated_at?->format('d M Y H:i') }}</dd>
                </div>
            </dl>
        </aside>
    </div>
@endsection
