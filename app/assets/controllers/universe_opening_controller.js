import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['time'];
    static values = { opensAt: String };

    connect() {
        this.tick();
        this.interval = window.setInterval(() => this.tick(), 1000);
    }

    disconnect() {
        window.clearInterval(this.interval);
        window.clearTimeout(this.reloadTimeout);
    }

    tick() {
        const seconds = Math.max(0, Math.ceil((Date.parse(this.opensAtValue) - Date.now()) / 1000));
        if (seconds === 0) {
            this.timeTarget.textContent = 'Ouverture…';
            if (!this.reloading) {
                this.reloading = true;
                this.reloadTimeout = window.setTimeout(() => window.location.reload(), 1000);
            }
            return;
        }

        const days = Math.floor(seconds / 86400);
        const hours = Math.floor((seconds % 86400) / 3600);
        const minutes = Math.floor((seconds % 3600) / 60);
        const remainingSeconds = seconds % 60;
        const parts = [];
        if (days > 0) parts.push(days + ' j');
        parts.push(String(hours).padStart(2, '0') + ' h');
        parts.push(String(minutes).padStart(2, '0') + ' min');
        parts.push(String(remainingSeconds).padStart(2, '0') + ' s');
        this.timeTarget.textContent = parts.join(' ');
    }
}
