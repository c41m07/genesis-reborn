import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['panel', 'mode', 'choice', 'search', 'searchLabel', 'searchHelp', 'results', 'searchStatus', 'choicesStatus', 'coordinates'];
    static values = { url: String };

    connect() {
        this.mission = this.element.elements.mission.value;
        this.items = [];
        this.active = -1;
        this.selectMode();
    }

    disconnect() {
        clearTimeout(this.timer);
        clearTimeout(this.blurTimer);
        this.choicesAbort?.abort();
        this.searchAbort?.abort();
    }

    get mode() { return this.element.querySelector('[name="destination_mode"]:checked')?.value || 'custom'; }
    get selectedField() { return this.mode === 'custom' ? this.coordinatesTarget : this.choiceTargets.find(field => field.name === this.mode + '_destination'); }

    changed(event) {
        if (event.target.name === 'destination_mode') this.selectMode();
        if (event.target.name === 'mission' && event.target.value !== this.mission) {
            const previousType = this.mission === 'explore';
            this.mission = event.target.value;
            if (previousType !== (this.mission === 'explore') || this.coordinatesTarget.value === this.suggestedCoordinates) {
                this.coordinatesTarget.value = '';
            }
            if (previousType !== (this.mission === 'explore')) this.searchTarget.value = '';
            this.suggestedCoordinates = null;
            this.items = [];
            this.searchStatusTarget.textContent = '';
            this.close();
            this.searchAbort?.abort();
            this.loadChoices();
        }
        if (event.target.name === 'coordinates' && event.target.value !== this.suggestedCoordinates) this.suggestedCoordinates = null;
    }

    selectMode(resetSuggestions = true) {
        if (resetSuggestions) {
            this.items = [];
            this.close();
            this.searchAbort?.abort();
        }
        for (const panel of this.panelTargets) {
            const visible = panel.dataset.mode === this.mode;
            panel.hidden = !visible;
            panel.querySelectorAll('input,select').forEach(field => { field.disabled = !visible; field.required = visible && field.name !== ''; });
        }
        const exploring = this.mission === 'explore';
        this.searchLabelTarget.textContent = exploring ? 'Rechercher un système par coordonnées' : 'Pseudonyme du commandant';
        this.searchHelpTarget.textContent = exploring
            ? 'Deux caractères minimum. Seules les coordonnées des systèmes non explorés sont proposées.'
            : 'Deux caractères minimum. Seules les planètes découvertes et compatibles avec la mission sont proposées.';
        this.resultsTarget.setAttribute('aria-label', exploring ? 'Systèmes non explorés proposés' : 'Planètes découvertes proposées');
        const field = this.selectedField;
        this.choicesStatusTarget.textContent = this.mode !== 'custom' && field && field.options.length <= 1
            ? 'Aucune destination compatible dans ce mode. Choisissez un autre type de destination.'
            : '';
    }

    async loadChoices() {
        this.choicesAbort?.abort();
        this.choicesAbort = new AbortController();
        const mission = this.mission;
        const previous = Object.fromEntries(this.choiceTargets.map(field => [field.name, field.value]));
        for (const field of this.choiceTargets) this.fill(field, []);
        this.choicesStatusTarget.textContent = 'Chargement des destinations…';
        try {
            const url = new URL(this.urlValue, location.origin);
            url.searchParams.set('mission', mission);
            const response = await fetch(url, { signal: this.choicesAbort.signal, headers: { Accept: 'application/json' } });
            if (!response.ok || !response.headers.get('content-type')?.includes('application/json')) throw new Error();
            const data = await response.json();
            if (this.mission !== mission) return;
            for (const field of this.choiceTargets) {
                const group = field.name.replace('_destination', '');
                this.fill(field, data.groups[group] ?? []);
                if ([...field.options].some(option => option.value === previous[field.name])) field.value = previous[field.name];
            }
            for (const label of this.modeTargets) {
                const input = label.querySelector('input');
                const available = input.value === 'custom' || data.modes.includes(input.value);
                label.hidden = !available;
                input.disabled = !available;
                if (!available) input.checked = false;
            }
            if (!this.element.querySelector('[name="destination_mode"]:checked')) {
                this.element.querySelector('[name="destination_mode"][value="' + data.modes[0] + '"]').checked = true;
            }
            this.selectMode(false);
            this.selectedField.dispatchEvent(new Event('change', { bubbles: true }));
        } catch (error) {
            if (error.name !== 'AbortError') this.choicesStatusTarget.textContent = 'Les listes sont indisponibles. Vous pouvez utiliser Destination personnalisée.';
        }
    }

    fill(select, rows) {
        select.replaceChildren(new Option(this.mission === 'explore' ? 'Choisir un système non exploré' : 'Choisir une planète découverte compatible', ''));
        for (const row of rows) {
            if (row.type !== 'system' && Number(row.id) === Number(this.element.elements.origin.value)) continue;
            const option = new Option(row.label, String(row.id));
            option.dataset.systemCoordinates = row.systemCoordinates;
            select.append(option);
        }
    }

    search() {
        clearTimeout(this.timer);
        this.searchAbort?.abort();
        this.close();
        this.items = [];
        if (this.suggestedCoordinates && this.coordinatesTarget.value === this.suggestedCoordinates) {
            this.coordinatesTarget.value = '';
            this.suggestedCoordinates = null;
            this.coordinatesTarget.dispatchEvent(new Event('change', { bubbles: true }));
        }
        const query = this.searchTarget.value.trim();
        this.searchStatusTarget.textContent = query.length < 2 ? '' : (this.mission === 'explore' ? 'Recherche des systèmes non explorés…' : 'Recherche des planètes découvertes…');
        if (query.length >= 2) this.timer = setTimeout(() => this.loadSuggestions(), 250);
    }

    async loadSuggestions() {
        if (this.mode !== 'custom') return;
        this.searchAbort?.abort();
        this.searchAbort = new AbortController();
        const query = this.searchTarget.value.trim(), mission = this.mission;
        try {
            const url = new URL(this.urlValue, location.origin);
            url.searchParams.set('mission', mission);
            url.searchParams.set('q', query);
            const response = await fetch(url, { signal: this.searchAbort.signal, headers: { Accept: 'application/json' } });
            if (!response.ok || !response.headers.get('content-type')?.includes('application/json')) throw new Error();
            const data = await response.json();
            if (this.mode !== 'custom' || this.mission !== mission || this.searchTarget.value.trim() !== query) return;
            this.items = data.suggestions.filter(row => row.type === 'system' || Number(row.id) !== Number(this.element.elements.origin.value));
            this.resultsTarget.replaceChildren();
            this.active = -1;
            for (const [index, row] of this.items.entries()) {
                const item = document.createElement('li');
                item.id = this.resultsTarget.id + '-' + index;
                item.setAttribute('role', 'option');
                item.setAttribute('aria-selected', 'false');
                item.textContent = row.label;
                item.addEventListener('mousedown', event => event.preventDefault());
                item.addEventListener('click', () => this.choose(row));
                this.resultsTarget.append(item);
            }
            this.resultsTarget.hidden = this.items.length === 0;
            this.searchTarget.setAttribute('aria-expanded', String(this.items.length > 0));
            this.searchStatusTarget.textContent = this.items.length
                ? this.items.length + ' destination(s) proposée(s). Flèches puis Entrée pour choisir.'
                : (this.mission === 'explore' ? 'Aucun système non exploré correspondant.' : 'Aucune planète découverte compatible pour ce pseudonyme.');
        } catch (error) {
            if (error.name !== 'AbortError') this.searchStatusTarget.textContent = 'Suggestions indisponibles. Vous pouvez saisir les coordonnées.';
        }
    }

    navigate(event) {
        if (event.key === 'Escape' || event.key === 'Tab') {
            this.close();
            if (event.key === 'Escape') event.preventDefault();
            return;
        }
        if (event.key === 'Enter') {
            event.preventDefault();
            if (this.items.length) this.choose(this.items[Math.max(0, this.active)]);
            else if (this.searchTarget.value.trim().length >= 2) this.loadSuggestions();
            return;
        }
        if (!['ArrowDown', 'ArrowUp'].includes(event.key) || !this.items.length) return;
        event.preventDefault();
        this.resultsTarget.hidden = false;
        this.searchTarget.setAttribute('aria-expanded', 'true');
        this.active = (this.active + (event.key === 'ArrowDown' ? 1 : -1) + this.items.length) % this.items.length;
        [...this.resultsTarget.children].forEach((item, index) => item.setAttribute('aria-selected', String(index === this.active)));
        const option = this.resultsTarget.children[this.active];
        this.searchTarget.setAttribute('aria-activedescendant', option.id);
        option.scrollIntoView({ block: 'nearest' });
    }

    choose(row) {
        this.coordinatesTarget.value = this.mission === 'explore' ? row.systemCoordinates : row.coordinates;
        this.suggestedCoordinates = this.coordinatesTarget.value;
        this.searchTarget.value = row.owner ?? row.coordinates;
        this.searchStatusTarget.textContent = 'Destination sélectionnée : ' + row.label;
        this.close();
        this.items = [];
        this.coordinatesTarget.dispatchEvent(new Event('change', { bubbles: true }));
    }

    blur() { this.blurTimer = setTimeout(() => this.close(), 150); }

    close() {
        if (!this.hasResultsTarget) return;
        this.resultsTarget.hidden = true;
        this.searchTarget.setAttribute('aria-expanded', 'false');
        this.searchTarget.removeAttribute('aria-activedescendant');
        this.active = -1;
    }
}
