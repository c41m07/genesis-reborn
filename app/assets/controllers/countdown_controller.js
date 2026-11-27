import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['time', 'dial'];
    static values = { end: String, now: String, duration: Number, hideOnEnd: Boolean, refresh: { type: Boolean, default: true } };

    initialize() {
        // Pour un bonus, conserver le décalage serveur même si Stimulus reconnecte le même élément.
        this.offset = this.hasNowValue ? Date.parse(this.nowValue) - Date.now() : 0;
    }

    connect() {
        this.timer = setInterval(() => this.tick(), 1000);
        this.tick();
    }

    disconnect() {
        clearInterval(this.timer);
    }

    tick() {
        const seconds = Math.max(0, Math.ceil((Date.parse(this.endValue) - Date.now() - this.offset) / 1000));
        if (!seconds && this.hideOnEndValue) {
            this.element.hidden = true;
            clearInterval(this.timer);
            return;
        }
        // Le cadran du bonus partage la même échéance que le temps affiché.
        if (this.hasDialTarget && this.durationValue > 0) {
            const percent = Math.min(100, 100 * seconds / this.durationValue);
            this.dialTarget.style.setProperty('--remaining', percent + '%');
        }
        this.timeTarget.textContent = seconds ? `${Math.floor(seconds / 3600)}h ${Math.floor(seconds % 3600 / 60)}m ${seconds % 60}s` : 'Finalisation…';
        if (!seconds && this.refreshValue && !this.requested) {
            this.requested = true;
            window.dispatchEvent(new CustomEvent('genesis:refresh'));
        }
    }
}
