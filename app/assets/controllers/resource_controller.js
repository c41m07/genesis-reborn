import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['stock', 'rate', 'productivity', 'meter', 'fill', 'protectedFill', 'protectedLimit'];
    static values = { stock: Number, rate: Number, cap: Number, protectedCap: Number };
    connect() { this.started = Date.now(); this.tick(); this.timer = setInterval(() => this.tick(), 1000); }
    disconnect() { clearInterval(this.timer); }
    update({ detail: resource }) {
        this.stockValue = resource.stock;
        this.rateValue = resource.rate;
        this.capValue = resource.capacity;
        this.protectedCapValue = resource.protectedCapacity;
        this.started = Date.now();
        this.rateTarget.textContent = `${resource.rate >= 0 ? '+' : ''}${Math.round(resource.rate * 3600).toLocaleString('fr-FR')} / h`;
        const reduced = resource.productivity !== null && resource.productivity < 99.999999;
        this.productivityTarget.hidden = resource.productivity === null;
        this.productivityTarget.classList.toggle('resource-productivity--reduced', reduced);
        this.productivityTarget.textContent = `${reduced ? '↓ ' : ''}${(resource.productivity ?? 100).toLocaleString('fr-FR', { minimumFractionDigits: 1, maximumFractionDigits: 1 })} %`;
        this.element.classList.toggle('resource--deficit', ['electricity', 'hydrogen'].includes(this.element.dataset.resourceSlug) && resource.rate < 0);
        this.tick();
    }
    tick() {
        const stock = Math.max(0, Math.min(this.capValue, this.stockValue + Math.max(0, Date.now() - this.started) / 1000 * this.rateValue));
        const format = value => Math.floor(value).toLocaleString('fr-FR');
        this.stockTarget.textContent = format(stock);
        if (this.hasFillTarget) this.fillTarget.style.width = `${this.capValue > 0 ? stock / this.capValue * 100 : 0}%`;
        if (this.hasProtectedFillTarget) this.protectedFillTarget.style.width = `${this.capValue > 0 ? Math.min(stock, this.protectedCapValue) / this.capValue * 100 : 0}%`;
        if (this.hasProtectedLimitTarget) this.protectedLimitTarget.style.left = `${this.capValue > 0 ? this.protectedCapValue / this.capValue * 100 : 0}%`;
        if (this.hasMeterTarget) {
            const description = `Stock : ${format(stock)} / ${format(this.capValue)}. Repère : jusqu’à ${format(this.protectedCapValue)} protégés du pillage ; zone verte : ${format(Math.min(stock, this.protectedCapValue))} actuellement protégés.`;
            this.meterTarget.title = description;
            this.meterTarget.setAttribute('aria-label', description);
        }
    }
}
