import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['status', 'time'];
    static values = { start: String, end: String };

    connect() {
        this.tick();
        this.timer = setInterval(() => this.tick(), 1000);
    }

    disconnect() {
        clearInterval(this.timer);
    }

    tick() {
        const now = Date.now();
        const startsAt = Date.parse(this.startValue);
        const endsAt = Date.parse(this.endValue);
        if (!Number.isFinite(startsAt) || !Number.isFinite(endsAt)) return;

        if (now < startsAt) {
            this.statusTarget.textContent = 'Commence dans';
            this.timeTarget.textContent = this.format(startsAt - now);
            return;
        }

        if (now < endsAt) {
            this.statusTarget.textContent = 'Se termine dans';
            this.timeTarget.textContent = this.format(endsAt - now);
            return;
        }

        this.statusTarget.textContent = 'Événement terminé';
        this.timeTarget.textContent = '';
    }

    format(milliseconds) {
        const seconds = Math.max(0, Math.floor(milliseconds / 1000));
        const days = Math.floor(seconds / 86400);
        const hours = Math.floor((seconds % 86400) / 3600);
        const minutes = Math.floor((seconds % 3600) / 60);
        const remaining = seconds % 60;
        return [days ? `${days} j` : null, hours ? `${hours} h` : null, minutes ? `${minutes} min` : null, `${remaining} s`]
            .filter(Boolean)
            .join(' ');
    }
}
