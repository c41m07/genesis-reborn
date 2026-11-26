import { Controller } from '@hotwired/stimulus';
import { compactNumber, exactNumber } from '../quantity.js';

export default class extends Controller {
    static targets = ['input', 'max', 'submit', 'status', 'label', 'arrow', 'form', 'cost', 'costHeading', 'duration', 'wait', 'waitText'];
    static values = { url: String };

    connect() {
        this.maximumUnavailable = this.maxTarget.disabled;
        this.maxTarget.hidden = false;
    }
    disconnect() { this.abort?.abort(); }
    invalidate() {
        // Une ancienne estimation ne doit plus être présentée dès que la quantité change.
        this.abort?.abort();
        this.inputTarget.removeAttribute('max');
        this.formTarget.dataset.dirty = 'true';
        this.updateWait(null);
    }
    change() {
        this.invalidate();
        this.refresh(false);
    }
    maximum() { this.refresh(true); }

    async refresh(maximize) {
        this.abort?.abort();
        const abort = this.abort = new AbortController();
        this.maxTarget.disabled = true;
        this.submitTarget.disabled = true;
        this.statusTarget.textContent = 'Calcul du coût et du maximum…';
        this.updateWait(null);
        try {
            const url = new URL(this.urlValue, window.location.origin);
            for (const name of ['group', 'slug', 'tier']) url.searchParams.set(name, this.formTarget.elements[name].value);
            url.searchParams.set('quantity', maximize ? '2147483647' : String(Math.max(1,Number(this.inputTarget.value) || 1)));
            const response = await fetch(url, { signal: abort.signal, headers: { Accept: 'application/json' } });
            const data = await response.json();
            if (!response.ok) throw new Error(data.error || 'Maximum indisponible. Réessayez.');
            if (abort.signal.aborted || this.abort !== abort || !this.element.isConnected) return false;
            const requested = Number(this.inputTarget.value);
            const maximum = data.maximum;
            this.maximumUnavailable = maximum === 0;
            this.inputTarget.max = String(maximum);
            this.inputTarget.min = maximum > 0 ? '1' : '0';
            if (maximize || requested > maximum || maximum === 0) this.inputTarget.value = String(maximum);
            this.statusTarget.textContent = maximum === 0 ? data.reason :
                (requested > maximum && !maximize ? 'Quantité ajustée. ' : '') + 'Maximum constructible : ' + exactNumber(maximum) + '.';
            this.renderPreview(data);
            this.updateWait(data.paymentWait, data.paymentEstimate);
            this.submitTarget.disabled = maximum === 0;
            this.submitTarget.classList.toggle('primary', maximum > 0);
            this.submitTarget.classList.toggle('secondary', maximum === 0);
            // Le devis garde l’ordre des cellules de la carte, lui-même calé sur celui du bandeau.
            const missing = this.costTargets.map(cell => cell.dataset.resource)
                .filter(resource => data.missingResources?.[resource]);
            this.submitTarget.classList.toggle('has-missing-resources', missing.length > 0);
            this.labelTarget.replaceChildren(document.createTextNode(maximum === 0 ? (missing.length ? 'Il manque :' : data.unavailableLabel) : this.orderLabel(data.quantity)));
            for (const key of missing) {
                const resource = data.missingResources[key];
                const line = document.createElement('span');
                line.className = 'catalog-missing-resource';
                line.dataset.resource = key;
                line.textContent = `${resource.label} : ${exactNumber(resource.amount)}`;
                this.labelTarget.append(line);
            }
            this.arrowTarget.hidden = maximum === 0;
            // Aucun lot sélectionné à zéro : le fragment de stock peut réactiver Max.
            if (maximum === 0) delete this.formTarget.dataset.dirty;
            else this.formTarget.dataset.dirty = 'true';
            return true;
        } catch (error) {
            if (error.name !== 'AbortError' && !abort.signal.aborted && this.abort === abort && this.element.isConnected) {
                this.statusTarget.textContent = error.message;
            }
            return false;
        } finally {
            if (this.abort === abort) this.maxTarget.disabled = this.maximumUnavailable;
        }
    }

    orderLabel(quantity) {
        // Le bandeau de la colonie est rafraîchi indépendamment d’un formulaire modifié.
        const queue = this.element.closest('.catalog-workspace')?.querySelector('[data-queue-type="shipyard"]');
        const occupied = (queue?.dataset.queueOccupied ?? this.submitTarget.dataset.queueOccupied) === 'true';
        return (occupied ? 'Ajouter à la file' : 'Construire') + ' : ' + this.submitTarget.dataset.orderName + ' × ' + exactNumber(quantity);
    }

    updateWait(text, estimate = null) {
        const timer = this.waitTextTarget;
        timer.textContent = text ?? '';
        if (estimate) {
            timer.dataset.observedAt = String(estimate.observedAt);
            timer.dataset.deadline = String(estimate.deadline);
            timer.dataset.limitingResource = estimate.resource;
            timer.setAttribute('role', 'timer');
            timer.setAttribute('aria-live', 'off');
        } else {
            delete timer.dataset.observedAt;
            delete timer.dataset.deadline;
            delete timer.dataset.limitingResource;
            timer.removeAttribute('role');
            timer.removeAttribute('aria-live');
        }
        this.waitTarget.hidden = !text;
        timer.dispatchEvent(new CustomEvent('resource-eta:change', { bubbles: true }));
    }

    renderPreview(data) {
        const costs = data.displayCosts ?? data.costs;
        for (const cost of this.costTargets) {
            const resource = cost.dataset.resource;
            const amount = Number(costs[resource] ?? 0);
            const missing = data.missingResources?.[resource];
            cost.hidden = amount === 0;
            cost.classList.toggle('insufficient', !!missing);
            cost.querySelector('[data-cost-amount]').textContent = compactNumber(amount);
            const description = `${cost.dataset.resourceLabel} : ${exactNumber(amount)}`;
            cost.title = description + (missing ? ` · Manque ${exactNumber(missing.amount)} ${missing.label}` : '');
            cost.setAttribute('aria-label', cost.title);
        }
        this.costHeadingTarget.textContent = data.quantity ? `Coût du lot × ${exactNumber(data.quantity)}` : 'Coût d’une unité';
        const seconds = data.displayDuration ?? data.duration;
        this.durationTarget.textContent = (seconds >= 3600 ? `${Math.floor(seconds / 3600)} h ` : '')
            + (seconds >= 60 ? `${Math.floor(seconds % 3600 / 60)} min ` : '') + `${seconds % 60} s`;
    }
}
