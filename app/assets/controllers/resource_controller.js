import { Controller } from '@hotwired/stimulus';
import { exactNumber, displayQuantity } from '../quantity.js';

export default class extends Controller {
    static targets = ['stock', 'capacity', 'rate', 'productivity', 'meter', 'fill', 'protectedFill', 'protectedLimit', 'economyStock', 'economyCapacity', 'gross', 'consumption', 'net', 'uncredited', 'uncreditedRow', 'estimate', 'productionSources', 'consumptionSources', 'potential', 'productionInputs'];
    static values = { stock: Number, rate: Number, cap: Number, protectedCap: Number, estimateSeconds: { type: Number, default: -1 }, vacation: Boolean, sourceLabels: Object };
    connect() { this.started = Date.now(); this.tick(); this.timer = setInterval(() => this.tick(), 1000); }
    disconnect() { clearInterval(this.timer); }
    update({ detail: resource }) {
        this.stockValue = resource.stock;
        this.rateValue = resource.rate;
        this.capValue = resource.capacity;
        this.protectedCapValue = resource.protectedCapacity;
        this.started = Date.now();
        if (resource.estimateSeconds !== undefined) this.estimateSecondsValue = resource.estimateSeconds ?? -1;
        if (resource.vacation !== undefined) this.vacationValue = resource.vacation;
        if (resource.gross !== undefined && this.hasGrossTarget) {
            if (this.hasPotentialTarget && resource.potentialGross !== undefined) this.potentialTarget.textContent = exactNumber(resource.potentialGross * 3600, 2);
            this.grossTarget.textContent = exactNumber(resource.gross * 3600, 2);
            this.consumptionTarget.textContent = exactNumber(resource.consumption * 3600, 2);
            this.uncreditedTarget.textContent = exactNumber(resource.uncredited * 3600, 2);
            this.uncreditedRowTarget.hidden = resource.uncredited <= 0;
            this.renderSources(this.productionSourcesTarget, resource.sources, false, resource.potentialSources);
            if (this.hasProductionInputsTarget && resource.productionInputs !== undefined) this.renderInputs(resource.productionInputs);
            this.renderSources(this.consumptionSourcesTarget, resource.consumptionSources, true);
        }
        if(this.hasCapacityTarget) displayQuantity(this.capacityTarget,this.capValue);
        const reduced = resource.productivity !== null && resource.productivity < 99.999999;
        this.productivityTarget.hidden = resource.productivity === null;
        this.productivityTarget.classList.toggle('resource-productivity--reduced', reduced);
        this.productivityTarget.textContent = `${reduced ? '↓ ' : ''}${(resource.productivity ?? 100).toLocaleString('fr-FR', { minimumFractionDigits: 1, maximumFractionDigits: 1 })} %`;
        this.element.classList.toggle('resource--deficit', ['electricity', 'hydrogen'].includes(this.element.dataset.resourceSlug) && resource.rate < 0);
        this.tick();
        this.dispatch('economy');
    }
    renderSources(target, sources, consumption, potentialSources = null) {
        const items = Object.entries(potentialSources ?? sources ?? {}).filter(([,rate]) => rate > 0).map(([source,rate]) => {
            const item = document.createElement('li');
            const label = source === 'base' ? 'Revenu de base' : (this.sourceLabelsValue[source] ?? source);
            item.textContent = `${label} : ${exactNumber(rate * 3600, 2)} / h${potentialSources ? ' brut' : ''}`;
            if (potentialSources && Math.abs(rate - (sources?.[source] ?? 0)) > 1e-9) item.textContent += ` · ${exactNumber((sources?.[source] ?? 0) * 3600, 2)} / h actuellement`;
            return item;
        });
        if (!items.length) {
            const item = document.createElement('li');
            item.textContent = consumption ? 'Aucune consommation continue.' : 'Aucune production.';
            items.push(item);
        }
        target.replaceChildren(...items);
    }
    renderInputs(inputs) {
        const items = Object.values(inputs).map(input => {
            const item = document.createElement('li');
            item.textContent = `${input.label} : ${exactNumber(input.gross * 3600, 2)} / h brut · ${exactNumber(input.actual * 3600, 2)} / h actuellement`;
            return item;
        });
        if (!items.length) {
            const item = document.createElement('li');item.textContent = 'Aucun besoin continu.';items.push(item);
        }
        this.productionInputsTarget.replaceChildren(...items);
    }
    tick() {
        const stock = Math.max(0, Math.min(Math.max(this.capValue, this.stockValue), this.stockValue + Math.max(0, Date.now() - this.started) / 1000 * this.rateValue));
        const saturated = stock >= this.capValue && this.rateValue > 0;
        const depleted = stock <= 0 && this.rateValue < 0;
        const bounded = saturated || depleted;
        const storedRate = bounded ? 0 : this.rateValue;
        if (this.displayedRate !== storedRate) {
            displayQuantity(this.rateTarget, Math.round(storedRate * 3600));
            this.rateTarget.textContent = `${storedRate >= 0 ? '+' : ''}${this.rateTarget.textContent} / h`;
            this.rateTarget.title += ' / h';this.rateTarget.setAttribute('aria-label', this.rateTarget.title);
            this.displayedRate = storedRate;
        }
        if (bounded && !this.boundaryNotified) { this.boundaryNotified = true;this.dispatch('boundary'); }
        if (!bounded) this.boundaryNotified = false;
        const format = value => exactNumber(Math.floor(value));
        displayQuantity(this.stockTarget, Math.floor(stock));
        this.dispatch('stock', { detail: { slug: this.element.dataset.resourceSlug, stock, deficit: this.element.classList.contains('resource--deficit') } });
        if (this.hasEconomyStockTarget) {
            this.economyStockTarget.textContent = format(stock);
            this.economyCapacityTarget.textContent = exactNumber(this.capValue);
            this.netTarget.textContent = exactNumber(storedRate * 3600, 2);
            let estimate = 'Stock stable';
            if (this.vacationValue) estimate = 'Production suspendue pendant les vacances';
            else if (stock >= this.capValue && this.rateValue >= 0) estimate = 'Stockage plein';
            else if (depleted) estimate = 'Stock épuisé';
            else if (this.estimateSecondsValue >= 0) {
                const seconds = Math.max(0, Math.ceil(this.estimateSecondsValue - (Date.now() - this.started) / 1000));
                const duration = `${seconds >= 3600 ? Math.floor(seconds / 3600) + ' h ' : ''}${seconds >= 60 ? Math.floor(seconds % 3600 / 60) + ' min ' : ''}${seconds % 60} s`;
                estimate = `${this.rateValue > 0 ? 'Saturation' : 'Épuisement'} dans ${duration}`;
            }
            this.estimateTarget.textContent = estimate;
        }
        if (this.hasFillTarget) this.fillTarget.style.width = `${this.capValue > 0 ? Math.min(100, stock / this.capValue * 100) : 0}%`;
        if (this.hasProtectedFillTarget) this.protectedFillTarget.style.width = `${this.capValue > 0 ? Math.min(stock, this.protectedCapValue) / this.capValue * 100 : 0}%`;
        if (this.hasProtectedLimitTarget) this.protectedLimitTarget.style.left = `${this.capValue > 0 ? this.protectedCapValue / this.capValue * 100 : 0}%`;
        if (this.hasMeterTarget) {
            const description = `Stock : ${format(stock)} / ${format(this.capValue)}. Repère : jusqu’à ${format(this.protectedCapValue)} protégés du pillage ; zone verte : ${format(Math.min(stock, this.protectedCapValue))} actuellement protégés.`;
            this.meterTarget.title = description;
            this.meterTarget.setAttribute('aria-label', description);
        }
    }
}
