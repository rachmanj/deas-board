import './bootstrap';
import Alpine from 'alpinejs';

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
