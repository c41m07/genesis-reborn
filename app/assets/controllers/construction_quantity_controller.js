import { Controller } from '@hotwired/stimulus';
import { exactNumber } from '../quantity.js';

export default class extends Controller {
    static targets = ['input', 'max', 'submit', 'status'];
    static values = { url: String };

    connect() { this.maxTarget.hidden = false; }
    disconnect() { this.abort?.abort(); }
    change() {
        this.inputTarget.removeAttribute('max');
        this.element.dataset.dirty = 'true';
        this.refresh(false);
    }
    maximum() { this.refresh(true); }

    async refresh(maximize) {
        this.abort?.abort();
        const abort = this.abort = new AbortController();
        this.maxTarget.disabled = true;
        if (maximize) this.submitTarget.disabled = true;
        this.statusTarget.textContent = 'Calcul du maximum…';
        try {
            const url = new URL(this.urlValue, window.location.origin);
            for (const name of ['group', 'slug', 'tier']) url.searchParams.set(name, this.element.elements[name].value);
            const response = await fetch(url, { signal: abort.signal, headers: { Accept: 'application/json' } });
            const data = await response.json();
            if (!response.ok) throw new Error(data.error || 'Maximum indisponible. Réessayez.');
            if (abort.signal.aborted) return false;
            const requested = Number(this.inputTarget.value);
            const maximum = data.maximum;
            this.inputTarget.max = String(maximum);
            this.inputTarget.min = maximum > 0 ? '1' : '0';
            if (maximize || requested > maximum || maximum === 0) this.inputTarget.value = String(maximum);
            this.statusTarget.textContent = maximum === 0 ? 'Aucune unité constructible actuellement.' :
                (requested > maximum && !maximize ? 'Quantité ajustée. ' : '') + 'Maximum constructible : ' + exactNumber(maximum) + '.';
            this.submitTarget.disabled = maximum === 0;
            this.element.dataset.dirty = 'true';
            return true;
        } catch (error) {
            if (error.name !== 'AbortError') this.statusTarget.textContent = error.message;
            return false;
        } finally {
            if (this.abort === abort) this.maxTarget.disabled = false;
        }
    }
}
