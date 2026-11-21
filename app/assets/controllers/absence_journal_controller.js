import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['entry', 'form', 'status'];

    connect() {
        if (!this.hasEntryTarget || !this.hasFormTarget || !window.IntersectionObserver) return;

        this.entryVisible = false;
        this.onVisibility = () => this.consultIfVisible();
        document.addEventListener('visibilitychange', this.onVisibility);
        window.addEventListener('focus', this.onVisibility);
        document.addEventListener('close', this.onVisibility, true);
        this.observer = new IntersectionObserver(entries => {
            this.entryVisible = entries.at(-1).intersectionRatio >= 0.5;
            this.consultIfVisible();
        }, { threshold: 0.5 });
        this.observer.observe(this.entryTarget);
    }

    disconnect() {
        this.observer?.disconnect();
        document.removeEventListener('visibilitychange', this.onVisibility);
        window.removeEventListener('focus', this.onVisibility);
        document.removeEventListener('close', this.onVisibility, true);
        this.request?.abort();
    }

    async consultIfVisible() {
        if (!this.entryVisible || document.visibilityState !== 'visible' || !document.hasFocus()
            || document.querySelector('dialog:modal') || this.request) return;

        const form = this.formTarget;
        const request = this.request = new AbortController();
        this.element.dataset.journalConsultation = 'pending';
        try {
            // Une récupération du fragment et un onglet en arrière-plan n’accusent jamais réception.
            const response = await fetch(form.action, {
                method: 'POST',
                body: new FormData(form),
                headers: { Accept: 'application/json' },
                signal: request.signal,
            });
            if (!response.ok || response.redirected) throw new Error('Consultation indisponible');
            if (request.signal.aborted || !this.element.isConnected) return;

            this.element.dataset.journalConsultation = 'read';
            this.statusTarget.textContent = 'Journal consulté.';
            if (!form.contains(document.activeElement)) form.hidden = true;
            // L’accusé du POST confirme l’écriture ; le voyant relit l’état courant par son canal habituel.
            window.dispatchEvent(new CustomEvent('genesis:journal-consulted'));
        } catch (error) {
            if (error.name === 'AbortError') return;
            delete this.element.dataset.journalConsultation;
            // L’action native reste possible, sans boucle automatique en cas d’échec.
            this.statusTarget.textContent = 'Le journal n’a pas pu être marqué automatiquement. Utilisez le bouton ou rechargez la page.';
        } finally {
            this.observer?.disconnect();
        }
    }
}
