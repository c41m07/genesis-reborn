import { Controller } from '@hotwired/stimulus';

const number = new Intl.NumberFormat('fr-FR', {maximumFractionDigits: 2});
const credits = new Intl.NumberFormat('fr-FR', {minimumFractionDigits: 2, maximumFractionDigits: 2});

export default class extends Controller {
    static targets = ['origin', 'form', 'step', 'indicator', 'control', 'notice', 'barter', 'deposit',
        'recap', 'count', 'ranks', 'mission', 'lot', 'capacity', 'fuel', 'arrival', 'estimate'];

    connect() {
        if (!this.hasFormTarget) return;
        this.controlTargets.forEach(button => { button.hidden = false; });
        this.recapTarget.hidden = false;
        this.show(0);
        this.update();
    }

    disconnect() {
        clearTimeout(this.timer);
        this.pending?.abort();
    }

    get ships() {
        return Array.from(this.formTarget.querySelectorAll('[data-ship-tier]:enabled'));
    }

    next() {
        const panel = this.stepTargets[this.index];
        for (const field of panel.querySelectorAll('input:enabled, select:enabled')) {
            if (!field.reportValidity()) return;
        }
        if (this.index === 0 && !this.ships.some(input => input.valueAsNumber > 0)) {
            this.noticeTarget.textContent = 'Sélectionnez au moins un cargo disponible.';
            this.ships[0]?.focus();
            return;
        }
        this.show(this.index + 1, true);
    }

    back() { this.show(this.index - 1, true); }

    enter(event) {
        if (event.target.matches('input') && this.index < this.stepTargets.length - 1) {
            event.preventDefault();
            this.next();
        }
    }

    show(index, focus = false) {
        this.index = index;
        if (this.hasOriginTarget) this.originTarget.hidden = index !== 0;
        this.stepTargets.forEach((panel, i) => { panel.hidden = i !== index; });
        this.indicatorTargets.forEach((item, i) => {
            item.classList.toggle('active', i === index);
            if (i === index) item.setAttribute('aria-current', 'step');
            else item.removeAttribute('aria-current');
        });
        if (focus) this.stepTargets[index].querySelector('legend').focus();
    }

    update() {
        clearTimeout(this.timer);
        this.pending?.abort();
        const fields = this.formTarget.elements;
        const selected = this.ships.filter(input => input.valueAsNumber > 0);
        const count = selected.reduce((sum, input) => sum + input.valueAsNumber, 0);
        const quantity = fields.quantity.valueAsNumber;
        const barter = fields.operation.value === 'barter';
        if (count && this.noticeTarget.textContent === 'Sélectionnez au moins un cargo disponible.') {
            this.noticeTarget.textContent = '';
        }
        this.countTarget.textContent = number.format(count);
        this.ranksTarget.textContent = selected.map(input => {
            const cell = input.closest('td');
            const rank = cell.closest('table').tHead.rows[0].cells[cell.cellIndex].textContent.trim();
            return number.format(input.valueAsNumber) + ' ' + rank;
        }).join(' · ');
        this.barterTarget.hidden = !barter;
        this.depositTarget.hidden = fields.operation.value !== 'collect';
        this.missionTarget.textContent = fields.operation.selectedOptions[0].text;
        this.lotTarget.textContent = quantity > 0 ? number.format(quantity) + ' ' + fields.pay.selectedOptions[0].text
            + (barter ? ' → ' + fields.receive.selectedOptions[0].text : '') : 'À renseigner';
        this.capacityTarget.textContent = '—';
        this.fuelTarget.textContent = '—';
        this.arrivalTarget.textContent = '—';
        this.estimateTarget.textContent = '';
        if (!count || (fields.quantity.value && !fields.quantity.validity.valid)) return;
        const body = new FormData(this.formTarget);
        body.set('action', 'estimate');
        body.set('quantity', String(quantity || 0));
        this.timer = setTimeout(() => this.estimate(body), 250);
    }

    async estimate(body) {
        const request = new AbortController();
        this.pending = request;
        try {
            const response = await fetch(this.formTarget.getAttribute('action'), {
                method: 'POST', body, signal: request.signal, headers: {Accept: 'application/json'},
            });
            const result = await response.json();
            if (request.signal.aborted) return;
            if (!response.ok) {
                this.estimateTarget.textContent = result.error || 'Estimation indisponible.';
                return;
            }
            this.capacityTarget.textContent = number.format(result.outCapacity) + ' / ' + number.format(result.returnCapacity);
            this.fuelTarget.textContent = number.format(result.oneWayFuel * 2) + ' H';
            this.arrivalTarget.textContent = new Intl.DateTimeFormat('fr-FR', {
                hour: '2-digit', minute: '2-digit', timeZoneName: 'short', timeZone: this.recapTarget.dataset.timezone,
            }).format(new Date(result.arrival));
            if (result.operation === 'buy' || result.operation === 'sell') {
                this.estimateTarget.textContent = (result.operation === 'buy' ? 'À payer : ' : 'À recevoir : ')
                    + credits.format(result.creditCents / 100) + ' crédits.';
            } else if (result.operation === 'barter') {
                this.estimateTarget.textContent = 'À recevoir : ' + number.format(result.received) + ' '
                    + this.formTarget.elements.receive.selectedOptions[0].text + '.';
            }
        } catch {
            if (!request.signal.aborted) this.estimateTarget.textContent = 'Estimation indisponible. Vous pouvez calculer le devis.';
        }
    }
}
