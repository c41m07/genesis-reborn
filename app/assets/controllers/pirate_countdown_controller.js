import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['remaining'];
    static values = { deadline: String, server: String };

    connect() {
        const due = Date.parse(this.deadlineValue);
        const serverAtRender = Date.parse(this.serverValue);
        if (!this.hasRemainingTarget || !Number.isFinite(due) || !Number.isFinite(serverAtRender)) return;

        const observed = performance.now();
        const run = () => {
            const seconds = Math.max(0, Math.ceil((due - serverAtRender - (performance.now() - observed)) / 1000));
            this.remainingTarget.hidden = false;
            if (seconds === 0) {
                this.remainingTarget.textContent = ' · Échéance atteinte, actualisez pour vérifier la confirmation.';
                this.stop();
                return;
            }
            const minutes = Math.floor(seconds / 60);
            this.remainingTarget.textContent = ' · Environ ' + (minutes ? minutes + ' min ' : '') + (seconds % 60) + ' s';
        };
        this.interval = window.setInterval(run, 1000);
        run();
    }

    disconnect() {
        this.stop();
    }

    stop() {
        if (this.interval === undefined) return;
        window.clearInterval(this.interval);
        this.interval = undefined;
    }
}
