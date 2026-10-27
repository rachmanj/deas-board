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

window.boardAutoRefresh = function (versionUrl, fragmentUrl) {
    const POLL_BASE_MS = 10000;
    const POLL_JITTER_MS = 3000;

    return {
        versionUrl,
        fragmentUrl,
        lastKnownHash: null,
        pollTimer: null,
        polling: false,
        init() {
            this.setStatus('Menunggu perubahan…');
            document.addEventListener('visibilitychange', () => this.schedulePoll());
            this.schedulePoll();
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

            try {
                const versionResponse = await fetch(this.versionUrl, {
                    credentials: 'same-origin',
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });

                if (!versionResponse.ok) {
                    this.setStatus('Gagal memperbarui, mencoba lagi…');
                    return;
                }

                const version = await versionResponse.json();
                const remoteHash = version.hash;

                if (this.lastKnownHash !== null && remoteHash !== this.lastKnownHash) {
                    const fragmentResponse = await fetch(this.fragmentUrl, {
                        credentials: 'same-origin',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                    });

                    if (!fragmentResponse.ok) {
                        this.setStatus('Gagal memperbarui, mencoba lagi…');
                        return;
                    }

                    const html = await fragmentResponse.text();
                    const container = this.$refs.columnsContainer;
                    if (container) {
                        const scrollY = window.scrollY;
                        container.innerHTML = html;
                        if (window.Alpine) {
                            window.Alpine.initTree(container);
                        }
                        window.scrollTo(0, scrollY);
                    }

                    const headerHash = fragmentResponse.headers.get('X-Board-Hash');
                    this.lastKnownHash = headerHash || remoteHash;
                } else if (this.lastKnownHash === null) {
                    this.lastKnownHash = remoteHash;
                }

                this.markUpdated();
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

window.sessionTable = function (initialEditingId = null) {
    return {
        editingId: initialEditingId ? Number(initialEditingId) : null,
        lastEditButton: null,
        isEditing(id) {
            return this.editingId === Number(id);
        },
        startEdit(id, event) {
            this.editingId = Number(id);
            this.lastEditButton = event.currentTarget;
        },
        cancelEdit() {
            this.editingId = null;
            this.$nextTick(() => this.lastEditButton?.focus());
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
