import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    connect() {
        if (!window.IntersectionObserver) return;
        this.check = () => this.consult();
        this.observer = new IntersectionObserver(entries => {
            this.visible = entries.at(-1).intersectionRatio >= 0.5;
            this.consult();
        }, { threshold: 0.5 });
        this.observer.observe(this.element);
        document.addEventListener('visibilitychange', this.check);
        document.addEventListener('close', this.check, true);
        window.addEventListener('focus', this.check);
    }

    disconnect() {
        this.observer?.disconnect();
        document.removeEventListener('visibilitychange', this.check);
        document.removeEventListener('close', this.check, true);
        window.removeEventListener('focus', this.check);
        this.request?.abort();
    }

    async consult() {
        if (!this.visible || document.visibilityState !== 'visible' || !document.hasFocus()
            || document.querySelector('dialog:modal') || this.request) return;

        const request = this.request = new AbortController();
        this.observer.disconnect();
        try {
            // Une fiche préchargée ou ouverte à l’arrière-plan ne marque pas le rapport.
            const response = await fetch(this.element.action, {
                method: 'POST', body: new FormData(this.element), headers: { Accept: 'application/json' }, signal: request.signal,
            });
            if (!response.ok || response.redirected || request.signal.aborted) return;
            if (!this.element.contains(document.activeElement)) this.element.hidden = true;
            window.dispatchEvent(new CustomEvent('genesis:report-consulted'));
        } catch {
            // En cas d’échec, le formulaire natif reste utilisable.
        }
    }
}
