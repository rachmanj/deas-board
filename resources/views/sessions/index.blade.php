@extends('layouts.app')

@section('content')
    @php
        $editingSessionId = (int) old('_editing_session', 0);
        $inlineInputClass = 'min-h-11 w-full rounded-lg border border-zinc-800 bg-zinc-950 px-3 text-sm text-zinc-100 focus:border-teal-500 focus:outline-none focus:ring-2 focus:ring-teal-500/40';
    @endphp
    <div class="space-y-6">
        <section class="rounded-xl border border-zinc-800 bg-zinc-900 p-4 sm:p-6">
            <h2 class="text-base font-semibold text-zinc-100">Tambah sesi</h2>
            <form method="POST" action="{{ route('sessions.store') }}" class="mt-4 grid gap-4 sm:grid-cols-2"
                  x-data="submitForm()" @submit="onFormSubmit">
                @csrf
                @foreach($filterQuery as $filterKey => $filterValue)
                    <input type="hidden" name="{{ $filterKey }}" value="{{ $filterValue }}">
                @endforeach
                <div>
                    <label for="label" class="mb-1 block text-sm text-zinc-400">Label</label>
                    <input id="label" name="label" type="text" value="{{ old('label') }}" required
                           class="min-h-11 w-full rounded-lg border border-zinc-800 bg-zinc-950 px-3 text-sm focus:border-teal-500 focus:outline-none focus:ring-2 focus:ring-teal-500/40">
                    @error('label')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="topic_id" class="mb-1 block text-sm text-zinc-400">Topic ID</label>
                    <input id="topic_id" name="topic_id" type="text" value="{{ old('topic_id') }}" required
                           class="min-h-11 w-full rounded-lg border border-zinc-800 bg-zinc-950 px-3 text-sm focus:border-teal-500 focus:outline-none focus:ring-2 focus:ring-teal-500/40">
                    @error('topic_id')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="chat_id" class="mb-1 block text-sm text-zinc-400">Chat ID</label>
                    <input id="chat_id" name="chat_id" type="text" value="{{ old('chat_id') }}"
                           class="min-h-11 w-full rounded-lg border border-zinc-800 bg-zinc-950 px-3 text-sm focus:border-teal-500 focus:outline-none focus:ring-2 focus:ring-teal-500/40">
                </div>
                <div>
                    <label for="host" class="mb-1 block text-sm text-zinc-400">Host</label>
                    <input id="host" name="host" type="text" value="{{ old('host') }}"
                           class="min-h-11 w-full rounded-lg border border-zinc-800 bg-zinc-950 px-3 text-sm focus:border-teal-500 focus:outline-none focus:ring-2 focus:ring-teal-500/40">
                </div>
                <div class="sm:col-span-2">
                    <label for="notes" class="mb-1 block text-sm text-zinc-400">Catatan</label>
                    <textarea id="notes" name="notes" rows="2"
                              class="w-full rounded-lg border border-zinc-800 bg-zinc-950 px-3 py-2 text-sm focus:border-teal-500 focus:outline-none focus:ring-2 focus:ring-teal-500/40">{{ old('notes') }}</textarea>
                </div>
                <div class="sm:col-span-2">
                    <button type="submit" :disabled="processing"
                            class="inline-flex min-h-11 items-center justify-center rounded-lg bg-teal-500 px-4 text-center text-sm font-medium text-zinc-950 hover:bg-teal-400 focus:outline-none focus-visible:ring-2 focus-visible:ring-teal-500 disabled:opacity-60"
                            data-submit-label="Simpan sesi"
                            x-text="buttonText()">
                        Simpan sesi
                    </button>
                </div>
            </form>
        </section>

        @if($totalSessions > 0)
            @include('partials.session-filters', ['hasActiveFilters' => $hasActiveFilters])

            @if($hasActiveFilters && $sessions->isEmpty())
                <div class="rounded-xl border border-zinc-800 bg-zinc-900 px-6 py-10 text-center text-sm text-zinc-400">
                    <p class="mb-4">Tidak ada sesi yang cocok dengan filter</p>
                    <a href="{{ route('sessions') }}"
                       class="inline-flex min-h-11 items-center justify-center whitespace-nowrap rounded-lg border border-zinc-800 px-4 text-center text-zinc-100 hover:border-zinc-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-teal-500">
                        Clear filters
                    </a>
                </div>
            @else
                <section class="overflow-hidden rounded-xl border border-zinc-800 bg-zinc-900">
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-left text-sm">
                            <thead class="border-b border-zinc-800 bg-zinc-950/50 text-zinc-400">
                                <tr>
                                    <th class="px-3 py-2 font-medium">Label</th>
                                    <th class="px-3 py-2 font-medium">Topic ID</th>
                                    <th class="px-3 py-2 font-medium">Chat ID</th>
                                    <th class="px-3 py-2 font-medium">Host</th>
                                    <th class="px-3 py-2 font-medium">Catatan</th>
                                    <th class="px-3 py-2 font-medium">Aktif</th>
                                    <th class="px-3 py-2 font-medium">Token aktif</th>
                                    <th class="px-3 py-2 font-medium">Task terbuka</th>
                                    <th class="px-3 py-2 font-medium">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-zinc-800" x-data="sessionTable(@js($editingSessionId ?: null))">
                                @foreach($sessions as $session)
                                    @php
                                        $editFormId = 'session-edit-'.$session->id;
                                        $isRowEditing = $editingSessionId === $session->id;
                                    @endphp
                                    <tr class="align-top">
                                        <td class="px-3 py-3 text-zinc-100">
                                            <span x-show="!isEditing({{ $session->id }})">{{ $session->label }}</span>
                                            <div x-show="isEditing({{ $session->id }})" x-cloak>
                                                <input form="{{ $editFormId }}" name="label" type="text" required
                                                       value="{{ $isRowEditing ? old('label', $session->label) : $session->label }}"
                                                       class="{{ $inlineInputClass }}">
                                                @if($isRowEditing)
                                                    @error('label')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
                                                @endif
                                            </div>
                                        </td>
                                        <td class="px-3 py-3 font-mono text-xs text-zinc-400">{{ $session->topic_id }}</td>
                                        <td class="px-3 py-3 text-zinc-400">
                                            <span x-show="!isEditing({{ $session->id }})">{{ $session->chat_id ?? 'Belum diisi' }}</span>
                                            <div x-show="isEditing({{ $session->id }})" x-cloak>
                                                <input form="{{ $editFormId }}" name="chat_id" type="text"
                                                       value="{{ $isRowEditing ? old('chat_id', $session->chat_id) : $session->chat_id }}"
                                                       class="{{ $inlineInputClass }}">
                                                @if($isRowEditing)
                                                    @error('chat_id')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
                                                @endif
                                            </div>
                                        </td>
                                        <td class="px-3 py-3 text-zinc-400">
                                            <span x-show="!isEditing({{ $session->id }})">{{ $session->host ?? 'Belum diisi' }}</span>
                                            <div x-show="isEditing({{ $session->id }})" x-cloak>
                                                <input form="{{ $editFormId }}" name="host" type="text"
                                                       value="{{ $isRowEditing ? old('host', $session->host) : $session->host }}"
                                                       class="{{ $inlineInputClass }}">
                                                @if($isRowEditing)
                                                    @error('host')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
                                                @endif
                                            </div>
                                        </td>
                                        <td class="px-3 py-3 text-zinc-400">
                                            <span x-show="!isEditing({{ $session->id }})" class="line-clamp-2 max-w-[12rem]">{{ $session->notes ?: '—' }}</span>
                                            <div x-show="isEditing({{ $session->id }})" x-cloak>
                                                <textarea form="{{ $editFormId }}" name="notes" rows="2"
                                                          class="w-full min-w-[10rem] rounded-lg border border-zinc-800 bg-zinc-950 px-3 py-2 text-sm text-zinc-100 focus:border-teal-500 focus:outline-none focus:ring-2 focus:ring-teal-500/40">{{ $isRowEditing ? old('notes', $session->notes) : $session->notes }}</textarea>
                                                @if($isRowEditing)
                                                    @error('notes')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
                                                @endif
                                            </div>
                                        </td>
                                        <td class="px-3 py-3">
                                            @if($session->is_active)
                                                <span class="text-teal-500">Ya</span>
                                            @else
                                                <span class="text-zinc-400">Tidak</span>
                                            @endif
                                        </td>
                                        <td class="px-3 py-3 text-zinc-300">{{ $session->active_tokens_count }}</td>
                                        <td class="px-3 py-3 text-zinc-300">{{ $session->open_tasks_count }}</td>
                                        <td class="px-3 py-3">
                                            <form id="{{ $editFormId }}" method="POST"
                                                  action="{{ route('sessions.update', $session) }}"
                                                  class="hidden">
                                                @csrf
                                                @method('PATCH')
                                                <input type="hidden" name="_editing_session" value="{{ $session->id }}">
                                                @foreach($filterQuery as $filterKey => $filterValue)
                                                    <input type="hidden" name="{{ $filterKey }}" value="{{ $filterValue }}">
                                                @endforeach
                                            </form>
                                            <div class="flex flex-wrap gap-2">
                                                <button type="button"
                                                        x-show="!isEditing({{ $session->id }})"
                                                        @click="startEdit({{ $session->id }}, $event)"
                                                        :aria-expanded="isEditing({{ $session->id }})"
                                                        class="inline-flex min-h-9 items-center justify-center whitespace-nowrap rounded-lg border border-zinc-700 px-2 text-center text-xs text-zinc-300 hover:border-zinc-600 focus:outline-none focus-visible:ring-2 focus-visible:ring-teal-500">
                                                    Ubah
                                                </button>
                                                <div x-show="isEditing({{ $session->id }})" x-cloak class="flex flex-wrap gap-2">
                                                    <button type="submit" form="{{ $editFormId }}"
                                                            class="inline-flex min-h-9 items-center justify-center whitespace-nowrap rounded-lg bg-teal-500 px-2 text-center text-xs font-medium text-zinc-950 hover:bg-teal-400 focus:outline-none focus-visible:ring-2 focus-visible:ring-teal-500">
                                                        Simpan
                                                    </button>
                                                    <button type="button" @click="cancelEdit()"
                                                            class="inline-flex min-h-9 items-center justify-center whitespace-nowrap rounded-lg border border-zinc-700 px-2 text-center text-xs text-zinc-300 hover:border-zinc-600 focus:outline-none focus-visible:ring-2 focus-visible:ring-teal-500">
                                                        Batal
                                                    </button>
                                                </div>
                                                <div x-show="!isEditing({{ $session->id }})" class="flex flex-wrap gap-2">
                                                    @if($session->is_active)
                                                        <form method="POST" action="{{ route('sessions.deactivate', $session) }}">
                                                            @csrf
                                                            @method('PATCH')
                                                            @foreach($filterQuery as $filterKey => $filterValue)
                                                                <input type="hidden" name="{{ $filterKey }}" value="{{ $filterValue }}">
                                                            @endforeach
                                                            <button type="submit"
                                                                    class="inline-flex min-h-9 items-center justify-center whitespace-nowrap rounded-lg border border-zinc-700 px-2 text-center text-xs text-zinc-300 hover:border-zinc-600 focus:outline-none focus-visible:ring-2 focus-visible:ring-teal-500">
                                                                Nonaktifkan
                                                            </button>
                                                        </form>
                                                    @else
                                                        <form method="POST" action="{{ route('sessions.activate', $session) }}">
                                                            @csrf
                                                            @method('PATCH')
                                                            @foreach($filterQuery as $filterKey => $filterValue)
                                                                <input type="hidden" name="{{ $filterKey }}" value="{{ $filterValue }}">
                                                            @endforeach
                                                            <button type="submit"
                                                                    class="inline-flex min-h-9 items-center justify-center whitespace-nowrap rounded-lg border border-teal-500/40 px-2 text-center text-xs text-teal-500 hover:bg-teal-500/10 focus:outline-none focus-visible:ring-2 focus-visible:ring-teal-500">
                                                                Aktifkan
                                                            </button>
                                                        </form>
                                                    @endif
                                                    <form method="POST" action="{{ route('sessions.tokens.issue', $session) }}"
                                                          x-data="submitForm()" @submit="onFormSubmit">
                                                        @csrf
                                                        @foreach($filterQuery as $filterKey => $filterValue)
                                                            <input type="hidden" name="{{ $filterKey }}" value="{{ $filterValue }}">
                                                        @endforeach
                                                        <button type="submit" :disabled="processing"
                                                                class="inline-flex min-h-9 items-center justify-center whitespace-nowrap rounded-lg bg-teal-500/15 px-2 text-center text-xs font-medium text-teal-500 hover:bg-teal-500/25 focus:outline-none focus-visible:ring-2 focus-visible:ring-teal-500 disabled:opacity-60"
                                                                data-submit-label="Terbitkan token"
                                                                x-text="buttonText()">
                                                            Terbitkan token
                                                        </button>
                                                    </form>
                                                </div>
                                            </div>
                                            @if($session->tokens->isNotEmpty())
                                                <div class="mt-3 space-y-2 border-t border-zinc-800 pt-3" x-show="!isEditing({{ $session->id }})">
                                                    <p class="text-xs font-medium text-zinc-400">Token</p>
                                                    @foreach($session->tokens as $token)
                                                        <div class="flex flex-wrap items-center justify-between gap-2 text-xs">
                                                            <div class="min-w-0 text-zinc-400">
                                                                <span class="text-zinc-300">{{ $token->name }}</span>
                                                                <span class="text-zinc-400">·</span>
                                                                dibuat {{ $token->created_at->format('Y-m-d H:i') }}
                                                                @if($token->last_used_at)
                                                                    <span class="text-zinc-400">·</span>
                                                                    terakhir {{ $token->last_used_at->format('Y-m-d H:i') }}
                                                                @endif
                                                            </div>
                                                            <form method="POST" action="{{ route('sessions.tokens.revoke', [$session, $token]) }}">
                                                                @csrf
                                                                @method('DELETE')
                                                                @foreach($filterQuery as $filterKey => $filterValue)
                                                                    <input type="hidden" name="{{ $filterKey }}" value="{{ $filterValue }}">
                                                                @endforeach
                                                                <button type="submit"
                                                                        class="inline-flex min-h-8 items-center justify-center whitespace-nowrap rounded border border-red-500/30 px-2 text-center text-red-400 hover:bg-red-500/10 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-500">
                                                                    Cabut
                                                                </button>
                                                            </form>
                                                        </div>
                                                    @endforeach
                                                </div>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </section>
            @endif
        @else
            <section class="overflow-hidden rounded-xl border border-zinc-800 bg-zinc-900">
                <div class="px-3 py-6 text-center text-sm text-zinc-400">Belum ada sesi.</div>
            </section>
        @endif
    </div>
@endsection
