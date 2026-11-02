import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['query', 'row', 'count', 'empty', 'filter'];

    connect() {
        this.filter();
    }

    filter() {
        const query = this.normalize(this.hasQueryTarget ? this.queryTarget.value : '');
        const selectedFilters = this.filterTargets
            .filter((select) => select.value !== '')
            .map((select) => ({ key: select.dataset.filterKey, value: select.value }));
        let visibleCount = 0;

        for (const row of this.rowTargets) {
            const textMatches = this.normalize(row.textContent).includes(query);
            const filtersMatch = selectedFilters.every(({ key, value }) => row.dataset['filter' + key[0].toUpperCase() + key.slice(1)] === value);
            row.hidden = !(textMatches && filtersMatch);
            if (!row.hidden) visibleCount += 1;
        }

        if (this.hasCountTarget) {
            this.countTarget.textContent = visibleCount + ' résultat' + (visibleCount === 1 ? '' : 's');
        }
        if (this.hasEmptyTarget) {
            this.emptyTarget.hidden = visibleCount !== 0;
        }
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
