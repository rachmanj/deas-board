@extends('layouts.app')

@section('content')
    <div class="space-y-6">
        <section class="rounded-xl border border-zinc-800 bg-zinc-900 p-4 sm:p-6">
            <h2 class="text-base font-semibold text-zinc-100">Tambah sesi</h2>
            <form method="POST" action="{{ route('sessions.store') }}" class="mt-4 grid gap-4 sm:grid-cols-2">
                @csrf
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
                    <button type="submit"
                            class="min-h-11 rounded-lg bg-teal-500 px-4 text-sm font-medium text-zinc-950 hover:bg-teal-400 focus:outline-none focus-visible:ring-2 focus-visible:ring-teal-500">
                        Simpan sesi
                    </button>
                </div>
            </form>
        </section>

        <section class="overflow-hidden rounded-xl border border-zinc-800 bg-zinc-900">
            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="border-b border-zinc-800 bg-zinc-950/50 text-zinc-400">
                        <tr>
                            <th class="px-3 py-2 font-medium">Label</th>
                            <th class="px-3 py-2 font-medium">Topic ID</th>
                            <th class="px-3 py-2 font-medium">Chat ID</th>
                            <th class="px-3 py-2 font-medium">Host</th>
                            <th class="px-3 py-2 font-medium">Aktif</th>
                            <th class="px-3 py-2 font-medium">Token aktif</th>
                            <th class="px-3 py-2 font-medium">Task terbuka</th>
                            <th class="px-3 py-2 font-medium">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-800">
                        @forelse($sessions as $session)
                            <tr class="align-top">
                                <td class="px-3 py-3 text-zinc-100">{{ $session->label }}</td>
                                <td class="px-3 py-3 font-mono text-xs text-zinc-400">{{ $session->topic_id }}</td>
                                <td class="px-3 py-3 text-zinc-400">{{ $session->chat_id ?? '—' }}</td>
                                <td class="px-3 py-3 text-zinc-400">{{ $session->host ?? '—' }}</td>
                                <td class="px-3 py-3">
                                    @if($session->is_active)
                                        <span class="text-teal-500">Ya</span>
                                    @else
                                        <span class="text-zinc-500">Tidak</span>
                                    @endif
                                </td>
                                <td class="px-3 py-3 text-zinc-300">{{ $session->active_tokens_count }}</td>
                                <td class="px-3 py-3 text-zinc-300">{{ $session->open_tasks_count }}</td>
                                <td class="px-3 py-3">
                                    <div class="flex flex-wrap gap-2">
                                        @if($session->is_active)
                                            <form method="POST" action="{{ route('sessions.deactivate', $session) }}">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit"
                                                        class="min-h-9 rounded-lg border border-zinc-700 px-2 text-xs text-zinc-300 hover:border-zinc-600 focus:outline-none focus-visible:ring-2 focus-visible:ring-teal-500">
                                                    Nonaktifkan
                                                </button>
                                            </form>
                                        @else
                                            <form method="POST" action="{{ route('sessions.activate', $session) }}">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit"
                                                        class="min-h-9 rounded-lg border border-teal-500/40 px-2 text-xs text-teal-500 hover:bg-teal-500/10 focus:outline-none focus-visible:ring-2 focus-visible:ring-teal-500">
                                                    Aktifkan
                                                </button>
                                            </form>
                                        @endif
                                        <form method="POST" action="{{ route('sessions.tokens.issue', $session) }}">
                                            @csrf
                                            <button type="submit"
                                                    class="min-h-9 rounded-lg bg-teal-500/15 px-2 text-xs font-medium text-teal-500 hover:bg-teal-500/25 focus:outline-none focus-visible:ring-2 focus-visible:ring-teal-500">
                                                Terbitkan token
                                            </button>
                                        </form>
                                    </div>
                                    @if($session->tokens->isNotEmpty())
                                        <div class="mt-3 space-y-2 border-t border-zinc-800 pt-3">
                                            <p class="text-xs font-medium text-zinc-500">Token</p>
                                            @foreach($session->tokens as $token)
                                                <div class="flex flex-wrap items-center justify-between gap-2 text-xs">
                                                    <div class="min-w-0 text-zinc-400">
                                                        <span class="text-zinc-300">{{ $token->name }}</span>
                                                        <span class="text-zinc-600">·</span>
                                                        dibuat {{ $token->created_at->format('Y-m-d H:i') }}
                                                        @if($token->last_used_at)
                                                            <span class="text-zinc-600">·</span>
                                                            terakhir {{ $token->last_used_at->format('Y-m-d H:i') }}
                                                        @endif
                                                    </div>
                                                    <form method="POST" action="{{ route('sessions.tokens.revoke', [$session, $token]) }}">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit"
                                                                class="min-h-8 rounded border border-red-500/30 px-2 text-red-400 hover:bg-red-500/10 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-500">
                                                            Cabut
                                                        </button>
                                                    </form>
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-3 py-6 text-center text-zinc-500">Belum ada sesi.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
@endsection
