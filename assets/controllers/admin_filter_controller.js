import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static values = { includeAncestors: Boolean };

    static targets = ['query', 'row', 'count', 'empty', 'filter', 'group', 'groupLink', 'tools', 'resetPath'];

    connect() {
        this.toolsTargets.forEach((tools) => { tools.hidden = false; });
        this.filter();
    }

    filter(event) {
        if (event) this.selectedNode = null;
        const query = this.normalize(this.hasQueryTarget ? this.queryTarget.value : '');
        const selectedFilters = this.filterTargets
            .filter((select) => select.value !== '')
            .map((select) => ({ key: select.dataset.filterKey, value: select.value }));
        let visibleCount = 0;
        const matches = new Set();

        for (const row of this.rowTargets) {
            const textMatches = this.normalize(row.dataset.searchText ?? row.textContent).includes(query);
            const filtersMatch = selectedFilters.every(({ key, value }) => row.dataset['filter' + key[0].toUpperCase() + key.slice(1)] === value);
            if (this.selectedNode ? row === this.selectedNode : textMatches && filtersMatch) matches.add(row);
        }

        const visible = new Set(matches);
        if ((query || this.selectedNode) && this.includeAncestorsValue) {
            const byKey = new Map(this.rowTargets.map((row) => [row.dataset.nodeKey, row]));
            const pending = [...matches];
            while (pending.length) {
                const row = pending.pop();
                for (const key of JSON.parse(row.dataset.parents || '[]')) {
                    const parent = byKey.get(key);
                    if (parent && !visible.has(parent)) {
                        visible.add(parent);
                        pending.push(parent);
                    }
                }
            }
        }
        for (const row of this.rowTargets) {
            row.hidden = !visible.has(row);
            row.classList.toggle('is-search-match', Boolean((query || this.selectedNode) && this.includeAncestorsValue && matches.has(row)));
            if (!row.hidden) visibleCount += 1;
        }

        this.resetPathTargets.forEach((button) => { button.hidden = !this.selectedNode; });

        this.groupTargets.forEach((group) => {
            group.hidden = ![...group.querySelectorAll('[data-admin-filter-target~="row"]')].some((row) => !row.hidden);
        });
        this.groupLinkTargets.forEach((link) => {
            link.hidden = this.groupTargets.find((group) => group.id === link.hash.slice(1))?.hidden ?? false;
        });

        if (this.hasCountTarget) {
            this.countTarget.textContent = (query || this.selectedNode) && this.includeAncestorsValue
                ? `${matches.size} résultat${matches.size === 1 ? '' : 's'} · ${visibleCount - matches.size} prérequis affiché${visibleCount - matches.size === 1 ? '' : 's'}`
                : visibleCount + ' résultat' + (visibleCount === 1 ? '' : 's');
        }
        if (this.hasEmptyTarget) {
            if (this.emptyTarget.dataset.defaultMessage) {
                this.emptyTarget.textContent = query
                    ? this.emptyTarget.dataset.noMatchesMessage
                    : this.emptyTarget.dataset.defaultMessage;
            }
            this.emptyTarget.hidden = visibleCount !== 0;
        }
    }

    selectPath(event) {
        if (!this.includeAncestorsValue) return;
        this.selectedNode = event.currentTarget.closest('[data-node-key]');
        this.filter();
    }

    clear() {
        this.selectedNode = null;
        if (this.hasQueryTarget) this.queryTarget.value = '';
        this.filterTargets.forEach((select) => { select.value = ''; });
        this.filter();
        if (this.hasQueryTarget) this.queryTarget.focus();
    }

    exportCsv() {
        const table = this.element.querySelector('table');
        if (!table) return;

        const headers = Array.from(table.querySelectorAll('thead th'), (cell) => cell.innerText.trim());
        const rows = this.rowTargets.filter((row) => !row.hidden).map((row) =>
            Array.from(row.cells, (cell) => this.csvCell(cell.innerText)),
        );
        const csv = [headers.map((cell) => this.csvCell(cell)), ...rows].map((row) => row.join(';')).join('\r\n');
        const blob = new Blob(['\ufeff', csv], { type: 'text/csv;charset=utf-8' });
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = 'genesis-admin-journal-' + new Date().toISOString().slice(0, 10) + '.csv';
        link.click();
        URL.revokeObjectURL(url);
    }

    normalize(value) {
        return value.toLocaleLowerCase('fr').normalize('NFD').replace(/[\u0300-\u036f]/g, '');
    }

    csvCell(value) {
        const cleaned = String(value).replace(/[\r\n]+/g, ' ').trim();
        const safe = /^[=+\-@]/.test(cleaned) ? "'" + cleaned : cleaned;
        return '"' + safe.replace(/"/g, '""') + '"';
    }
}
