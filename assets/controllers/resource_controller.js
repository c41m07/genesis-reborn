import { Controller } from '@hotwired/stimulus';
export default class extends Controller {
    static targets = ['stock'];
    static values = { stock: Number, rate: Number, cap: Number };
    connect() { this.started = Date.now(); this.tick(); this.timer = setInterval(() => this.tick(), 1000); }
    disconnect() { clearInterval(this.timer); }
    tick() { const stock = Math.min(this.capValue, this.stockValue + Math.max(0, Date.now()-this.started)/1000*this.rateValue); this.stockTarget.textContent = Math.floor(stock).toLocaleString('fr-FR'); }
}
