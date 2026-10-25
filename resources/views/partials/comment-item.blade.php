@php
    use App\Models\Session;
    use App\Models\User;

    $author = $comment->author;
    $authorLabel = $author instanceof Session
        ? $author->label.' ('.$author->topic_id.')'
        : ($author?->name ?? 'Tidak dikenal');
    $isDeleted = $comment->trashed();
    $canManage = ! $isDeleted
        && $author instanceof User
        && auth()->id() === $author->id;
    $replies = $allComments->where('parent_id', $comment->id)->values();
@endphp

<article class="{{ $depth > 0 ? 'ml-4 border-l border-zinc-800 pl-4' : '' }}" id="comment-{{ $comment->id }}">
    <header class="mb-1 flex flex-wrap items-center gap-2 text-xs text-zinc-400">
        <span class="font-medium text-zinc-300">{{ $authorLabel }}</span>
        <time datetime="{{ $comment->created_at?->toIso8601String() }}">{{ $comment->created_at?->format('d M Y H:i') }}</time>
        @if($comment->edited_at)
            <span class="text-zinc-400">diedit {{ $comment->edited_at->format('d M Y H:i') }}</span>
        @endif
        @if($isDeleted)
            <span class="rounded bg-zinc-800 px-1.5 py-0.5 text-zinc-400">dihapus</span>
        @endif
    </header>

    @if($isDeleted)
        <p class="text-sm italic text-zinc-400">Komentar ini telah dihapus.</p>
    @else
        <div class="prose prose-invert prose-sm max-w-none text-sm text-zinc-200">
            {!! \Illuminate\Support\Str::markdown($comment->body_md) !!}
        </div>

        @if($canManage)
            <div class="mt-2 flex flex-wrap gap-2">
                <button type="button"
                        class="min-h-11 rounded border border-zinc-800 px-3 text-xs text-zinc-400 hover:text-zinc-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-teal-500"
                        onclick="document.getElementById('edit-comment-{{ $comment->id }}').classList.toggle('hidden')">
                    Ubah
                </button>
                <form method="POST" action="{{ route('tasks.comments.destroy', [$task, $comment]) }}" class="inline"
                      onsubmit="return confirm('Hapus komentar ini?')">
                    @csrf
                    @method('DELETE')
                    <button type="submit"
                            class="min-h-11 rounded border border-zinc-800 px-3 text-xs text-red-400 hover:text-red-300 focus:outline-none focus-visible:ring-2 focus-visible:ring-teal-500">
                        Hapus
                    </button>
                </form>
            </div>
            <form id="edit-comment-{{ $comment->id }}" method="POST" action="{{ route('tasks.comments.update', [$task, $comment]) }}"
                  class="mt-2 hidden space-y-2" x-data="submitForm()" @submit="onFormSubmit">
                @csrf
                @method('PATCH')
                <textarea name="body_md" rows="3" required
                          class="w-full rounded-lg border border-zinc-800 bg-zinc-950 px-3 py-2 text-sm focus:border-teal-500 focus:outline-none focus:ring-2 focus:ring-teal-500/40">{{ $comment->body_md }}</textarea>
                <button type="submit" :disabled="processing"
                        class="min-h-11 rounded-lg bg-teal-500 px-3 text-xs font-medium text-zinc-950 focus:outline-none focus-visible:ring-2 focus-visible:ring-teal-500 disabled:opacity-60"
                        data-submit-label="Simpan"
                        x-text="buttonText()">
                    Simpan
                </button>
            </form>
        @else
            <div class="mt-2">
                <button type="button"
                        class="min-h-11 rounded border border-zinc-800 px-3 text-xs text-zinc-400 hover:text-zinc-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-teal-500"
                        onclick="document.getElementById('reply-comment-{{ $comment->id }}').classList.toggle('hidden')">
                    Balas
                </button>
            </div>
            <form id="reply-comment-{{ $comment->id }}" method="POST" action="{{ route('tasks.comments.reply', [$task, $comment]) }}"
                  class="mt-2 hidden space-y-2" x-data="submitForm('Mengirim...')" @submit="onFormSubmit">
                @csrf
                @include('partials.mention-textarea', ['name' => 'body_md', 'id' => 'reply-body-'.$comment->id, 'rows' => 3, 'placeholder' => 'Tulis balasan…', 'required' => true])
                <button type="submit" :disabled="processing"
                        class="min-h-11 rounded-lg bg-teal-500 px-3 text-xs font-medium text-zinc-950 focus:outline-none focus-visible:ring-2 focus-visible:ring-teal-500 disabled:opacity-60"
                        data-submit-label="Kirim balasan"
                        x-text="buttonText()">
                    Kirim balasan
                </button>
            </form>
        @endif
    @endif

    @foreach($replies as $reply)
        @include('partials.comment-item', [
            'comment' => $reply,
            'task' => $task,
            'allComments' => $allComments,
            'depth' => ($depth ?? 0) + 1,
        ])
    @endforeach
</article>
