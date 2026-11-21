import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['timer'];

    initialize() {
        this.timers = new Map();
    }

    connect() {
        this.active = true;
        this.timers.clear();
        for (const timer of this.timerTargets) this.observe(timer);
        this.resume();
    }

    disconnect() {
        this.active = false;
        clearTimeout(this.timeout);
        this.timers.clear();
    }

    timerTargetConnected(timer) {
        if (!this.active) return;
        this.observe(timer);
        this.resume();
    }

    timerTargetDisconnected(timer) {
        this.timers.delete(timer);
    }

    changed({ target }) {
        this.observe(target);
        this.resume();
    }

    observe(timer) {
        const duration = (Number(timer.dataset.deadline) - Number(timer.dataset.observedAt)) * 1000;
        if (!Number.isFinite(duration) || duration <= 0 || !timer.dataset.limitingResource) {
            this.timers.delete(timer);
            return;
        }

        // Caler le décompte sur la durée fournie par le serveur, pas sur
        // l'heure absolue du poste. Les ancres survivent aux suspensions d'onglet.
        this.timers.set(timer, { duration, monotonic: performance.now(), wall: Date.now(), elapsed: 0 });
    }

    resume() {
        clearTimeout(this.timeout);
        if (!this.active || document.hidden || this.timers.size === 0) return;

        const monotonic = performance.now(), wall = Date.now();
        for (const [timer, estimate] of this.timers) {
            // L'horloge monotone ne recule jamais. L'horloge civile couvre aussi
            // la veille sur les navigateurs où l'horloge monotone est suspendue.
            estimate.elapsed = Math.max(estimate.elapsed, monotonic - estimate.monotonic, wall - estimate.wall);
            const seconds = Math.max(0, Math.ceil((estimate.duration - estimate.elapsed) / 1000));
            const label = seconds === 0
                ? 'Estimation écoulée. Actualisez pour vérifier les ressources.'
                : `Délai estimé : ${this.duration(seconds)} (${timer.dataset.limitingResource}).`;
            if (timer.textContent !== label) timer.textContent = label;
            if (seconds === 0) this.timers.delete(timer);
        }
        if (this.timers.size > 0) this.timeout = setTimeout(() => this.resume(), 1000);
    }

    duration(seconds) {
        const parts = [];
        for (const [unit, label] of [[86400, 'j'], [3600, 'h'], [60, 'min'], [1, 's']]) {
            if (seconds >= unit) {
                parts.push(`${Math.floor(seconds / unit)} ${label}`);
                seconds %= unit;
            }
        }
        return parts.join(' ');
    }
}
