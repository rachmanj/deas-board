import './bootstrap';
import Alpine from 'alpinejs';

window.submitForm = function (processingText = 'Memproses...') {
    return {
        processing: false,
        processingText,
        defaultText: '',
        init() {
            const btn = this.$el.querySelector('button[type="submit"]');
            if (btn) {
                this.defaultText = btn.dataset.submitLabel || btn.textContent.trim();
            }
        },
        onFormSubmit() {
            this.processing = true;
        },
        buttonText() {
            return this.processing ? this.processingText : this.defaultText;
        },
    };
};

window.boardAutoRefresh = function (fragmentUrl) {
    const POLL_BASE_MS = 10000;
    const POLL_JITTER_MS = 3000;

    return {
        fragmentUrl,
        lastEtag: null,
        contentHash: null,
        pollTimer: null,
        polling: false,
        init() {
            const container = this.$refs.columnsContainer;
            if (container) {
                this.contentHash = this.hashString(container.innerHTML);
            }
            this.setStatus('Menunggu perubahan…');
            document.addEventListener('visibilitychange', () => this.schedulePoll());
            this.schedulePoll();
        },
        hashString(text) {
            let hash = 0;
            for (let i = 0; i < text.length; i++) {
                hash = (hash << 5) - hash + text.charCodeAt(i);
                hash |= 0;
            }
            return String(hash);
        },
        setStatus(text) {
            const el = document.getElementById('board-refresh-status');
            if (el) {
                el.textContent = text;
            }
        },
        shouldPause() {
            if (document.hidden) {
                return true;
            }
            const boardArea = document.getElementById('board-area');
            if (!boardArea) {
                return true;
            }
            const active = document.activeElement;
            if (active && boardArea.contains(active)) {
                const tag = active.tagName;
                if (tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT') {
                    return true;
                }
            }
            const textareas = boardArea.querySelectorAll('textarea');
            for (const textarea of textareas) {
                if (textarea.value.trim() !== '') {
                    return true;
                }
            }
            return false;
        },
        schedulePoll() {
            if (this.pollTimer) {
                clearTimeout(this.pollTimer);
            }
            const delay = POLL_BASE_MS + Math.floor(Math.random() * (POLL_JITTER_MS + 1));
            this.pollTimer = setTimeout(() => this.poll(), delay);
        },
        async poll() {
            if (this.shouldPause()) {
                this.schedulePoll();
                return;
            }

            this.polling = true;
            this.setStatus('Menunggu perubahan…');

            const headers = {
                'X-Requested-With': 'XMLHttpRequest',
            };
            if (this.lastEtag) {
                headers['If-None-Match'] = this.lastEtag;
            }

            try {
                const response = await fetch(this.fragmentUrl, {
                    credentials: 'same-origin',
                    headers,
                });

                if (response.status === 304) {
                    this.markUpdated();
                    return;
                }

                if (response.ok) {
                    const etag = response.headers.get('ETag');
                    if (etag) {
                        this.lastEtag = etag;
                    }
                    const html = await response.text();
                    const container = this.$refs.columnsContainer;
                    if (container) {
                        const nextHash = this.hashString(html);
                        if (nextHash !== this.contentHash) {
                            const scrollY = window.scrollY;
                            container.innerHTML = html;
                            if (window.Alpine) {
                                window.Alpine.initTree(container);
                            }
                            this.contentHash = nextHash;
                            window.scrollTo(0, scrollY);
                        }
                    }
                    this.markUpdated();
                    return;
                }
            } catch {
                this.setStatus('Gagal memperbarui, mencoba lagi…');
            } finally {
                this.polling = false;
                this.schedulePoll();
            }
        },
        markUpdated() {
            const now = new Date();
            const time = now.toLocaleTimeString('id-ID', {
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit',
                hour12: false,
            });
            this.setStatus(`Diperbarui ${time}`);
        },
    };
};

window.taskCardStatus = function () {
    return {
        saving: false,
        onStatusChange(event) {
            this.saving = true;
            event.target.form.submit();
        },
    };
};

window.taskLabelPicker = function (initialSelected, labelsUrl) {
    return {
        labels: [],
        selected: initialSelected.map((id) => Number(id)),
        loading: true,
        async load() {
            try {
                const response = await fetch(labelsUrl, {
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                });
                if (response.ok) {
                    this.labels = await response.json();
                }
            } finally {
                this.loading = false;
            }
        },
        isSelected(id) {
            return this.selected.includes(Number(id));
        },
        toggle(id) {
            const numericId = Number(id);
            const index = this.selected.indexOf(numericId);
            if (index === -1) {
                this.selected.push(numericId);
            } else {
                this.selected.splice(index, 1);
            }
        },
    };
};

window.Alpine = Alpine;
Alpine.start();
