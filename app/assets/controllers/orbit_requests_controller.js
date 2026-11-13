import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['content', 'details', 'count'];
    static values = { now: String };

    connect() {
        this.offset = Date.parse(this.nowValue) - Date.now();
        this.receive = event => {
            this.offset = Date.parse(event.detail.serverNow) - Date.now();
            if (this.version !== event.detail.orbitVersion) {
                this.next = event.detail;
                this.update();
            }
            this.tick();
        };
        this.focusOut = () => { clearTimeout(this.defer); this.defer = setTimeout(() => this.update(), 0); };
        window.addEventListener('genesis:orbit-requests', this.receive);
        this.element.addEventListener('focusout', this.focusOut);
        this.timer = setInterval(() => this.tick(), 1000);
        this.tick();
    }

    disconnect() {
        clearInterval(this.timer); clearTimeout(this.defer);
        window.removeEventListener('genesis:orbit-requests', this.receive);
        this.element.removeEventListener('focusout', this.focusOut);
    }

    update() {
        if (!this.next || this.contentTarget.contains(document.activeElement)) return;
        this.contentTarget.innerHTML = this.next.orbitHtml;
        if (this.hasDetailsTarget) {
            const count = this.contentTarget.querySelectorAll('[data-orbit-request]').length;
            this.countTarget.textContent = count;
            if (count > 0) this.detailsTarget.open = true;
        }
        this.version = this.next.orbitVersion; this.next = null; this.tick();
    }

    tick() {
        for (const label of this.contentTarget.querySelectorAll('[data-orbit-deadline]')) {
            const seconds = Math.max(0, Math.ceil((Date.parse(label.dataset.orbitDeadline) - Date.now() - this.offset) / 1000));
            label.textContent = seconds ? Math.floor(seconds / 3600) + ' h ' + Math.floor(seconds % 3600 / 60) + ' min ' + seconds % 60 + ' s' : 'Échéance atteinte · arrivée à résoudre';
        }
    }
}
