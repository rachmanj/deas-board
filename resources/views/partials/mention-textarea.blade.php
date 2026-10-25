@php
    $fieldId = $id ?? 'mention-'.uniqid();
    $fieldName = $name ?? 'body_md';
@endphp
<div x-data="mentionBox('{{ route('mentions.json') }}')" class="relative">
    <textarea
        id="{{ $fieldId }}"
        name="{{ $fieldName }}"
        rows="{{ $rows ?? 4 }}"
        @if(!empty($placeholder)) placeholder="{{ $placeholder }}" @endif
        @if(!empty($required)) required @endif
        x-ref="textarea"
        @input="onInput($event)"
        @keydown="onKeydown($event)"
        class="w-full rounded-lg border border-zinc-800 bg-zinc-950 px-3 py-2 text-sm focus:border-teal-500 focus:outline-none focus:ring-2 focus:ring-teal-500/40"
    >@if(!empty($value)){{ $value }}@endif</textarea>

    <ul x-show="open && suggestions.length" x-cloak
        class="absolute z-10 mt-1 max-h-48 w-full overflow-auto rounded-lg border border-zinc-700 bg-zinc-900 py-1 text-sm shadow-lg"
        role="listbox">
        <template x-for="(item, index) in suggestions" :key="item.type + '-' + item.id">
            <li>
                <button type="button"
                        @click="pick(item)"
                        :class="index === activeIndex ? 'bg-teal-500/15 text-teal-400' : 'text-zinc-200 hover:bg-zinc-800'"
                        class="flex w-full min-h-10 items-center gap-2 px-3 text-left focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-teal-500"
                        role="option">
                    <span x-text="item.label" class="font-medium"></span>
                    <span x-show="item.type === 'session'" class="text-xs text-zinc-400" x-text="'@' + item.topic"></span>
                    <span x-show="item.type === 'user'" class="text-xs text-zinc-400">pengguna</span>
                </button>
            </li>
        </template>
    </ul>
</div>

@once
    @push('scripts')
        <script>
            document.addEventListener('alpine:init', () => {
                Alpine.data('mentionBox', (endpoint) => ({
                    endpoint,
                    open: false,
                    suggestions: [],
                    activeIndex: 0,
                    mentionStart: null,
                    mentionQuery: '',
                    async onInput(event) {
                        const el = event.target;
                        const pos = el.selectionStart;
                        const text = el.value.slice(0, pos);
                        const at = text.lastIndexOf('@');
                        if (at === -1 || (at > 0 && /\S/.test(text.charAt(at - 1)))) {
                            this.open = false;
                            return;
                        }
                        const query = text.slice(at + 1);
                        if (/\s/.test(query)) {
                            this.open = false;
                            return;
                        }
                        this.mentionStart = at;
                        this.mentionQuery = query;
                        const res = await fetch(this.endpoint + '?q=' + encodeURIComponent(query));
                        this.suggestions = await res.json();
                        this.activeIndex = 0;
                        this.open = this.suggestions.length > 0;
                    },
                    onKeydown(event) {
                        if (!this.open) return;
                        if (event.key === 'ArrowDown') {
                            event.preventDefault();
                            this.activeIndex = (this.activeIndex + 1) % this.suggestions.length;
                        } else if (event.key === 'ArrowUp') {
                            event.preventDefault();
                            this.activeIndex = (this.activeIndex - 1 + this.suggestions.length) % this.suggestions.length;
                        } else if (event.key === 'Enter' && this.open) {
                            event.preventDefault();
                            this.pick(this.suggestions[this.activeIndex]);
                        } else if (event.key === 'Escape') {
                            this.open = false;
                        }
                    },
                    pick(item) {
                        const el = this.$refs.textarea;
                        const insert = item.type === 'session'
                            ? '@' + item.topic
                            : '@' + item.label.replace(/\s+/g, '');
                        const before = el.value.slice(0, this.mentionStart);
                        const after = el.value.slice(el.selectionStart);
                        el.value = before + insert + ' ' + after;
                        this.open = false;
                        el.focus();
                    },
                }));
            });
        </script>
    @endpush
@endonce
