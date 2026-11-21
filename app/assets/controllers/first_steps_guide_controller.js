import { Controller } from '@hotwired/stimulus';
import '../styles/first-steps-guide.css';

export default class extends Controller {
    static targets = ['summary', 'mount', 'status', 'closeButton', 'slide', 'illustration', 'controls', 'previous', 'next', 'position'];
    static values = { scope: String, url: String, autoOpen: Boolean, defer: Boolean };

    connect() {
        this.element.classList.add('first-steps-popup-enhanced');
        this.reflectDisclosure();
        if (this.element.open) {
            this.rememberOpened();
        } else if (this.autoOpenValue && !this.read(this.openKey())) {
            const daily = document.querySelector('[data-controller~="daily-reward-ui"]');
            if (this.deferValue && daily && daily.dataset.dailyRewardUiInitialPrompt !== 'finished') {
                // Attendre la fin de l’essai, même si aucune fenêtre n’a pu être ouverte.
                this.afterDaily = (event) => {
                    if (event.target !== daily) return;
                    document.removeEventListener('genesis:daily-reward-prompt-finished', this.afterDaily);
                    this.showFirstVisit();
                };
                document.addEventListener('genesis:daily-reward-prompt-finished', this.afterDaily);
            } else {
                this.showFirstVisit();
            }
        }
    }

    disconnect() {
        if (this.afterDaily) document.removeEventListener('genesis:daily-reward-prompt-finished', this.afterDaily);
        this.afterDaily = null;
        this.cancelPending();
    }

    showFirstVisit() {
        if (!this.element.isConnected || this.read(this.openKey()) || document.getElementById('daily-reward-dialog')?.open) return;
        // Ne jamais déplacer le focus lors d’une ouverture proposée.
        this.element.open = true;
        this.rememberOpened();
    }

    reflectDisclosure() {
        const label = this.element.open ? 'Fermer Premiers pas' : 'Ouvrir Premiers pas';
        this.summaryTarget.setAttribute('aria-expanded', String(this.element.open));
        this.summaryTarget.setAttribute('aria-label', label);
        this.summaryTarget.title = label;
    }

    rememberOpened() {
        this.reflectDisclosure();
        if (!this.element.open) {
            this.cancelPending();
            return;
        }

        this.write(this.openKey(), '1');
        if (!this.step && !this.pending) this.loadPanel();
    }

    async loadPanel() {
        const request = new AbortController();
        this.pending = request;
        this.setStatus('Chargement de l’étape…');

        try {
            const response = await fetch(this.urlValue, {
                signal: request.signal,
                credentials: 'same-origin',
                redirect: 'error',
                cache: 'no-store',
            });
            if (request.signal.aborted || !this.element.open) return;
            if (response.status === 204) {
                this.setStatus('Aucune étape à afficher. Le guide complet reste accessible.');
                return;
            }
            if (!response.ok) throw new Error('Guide indisponible');

            const template = document.createElement('template');
            template.innerHTML = await response.text();
            const panel = template.content.querySelector('[data-first-steps-guide-fragment]');
            if (request.signal.aborted || !this.element.open) return;
            if (!panel?.dataset.firstStepsGuideStep) throw new Error('Étape indisponible');

            this.step = panel.dataset.firstStepsGuideStep;
            const fallbackHadFocus = this.mountTarget.contains(document.activeElement);
            this.mountTarget.replaceChildren(panel);
            // Le lien natif reste le même choix si le joueur l’a déjà atteint au clavier.
            if (fallbackHadFocus) panel.querySelector('[data-first-steps-guide-return]')?.focus();
            this.closeButtonTarget.hidden = false;
            const count = this.slideTargets.length;
            if (this.hasControlsTarget) this.controlsTarget.hidden = count < 2;
            // Le stockage est distinct par empire et par étape, jamais d’un univers à l’autre.
            this.switchSlide(Math.max(0, Math.min(count - 1, Number(this.read(this.slideKey())) || 0)));
        } catch (error) {
            if (!request.signal.aborted) this.setStatus('Impossible de charger l’étape. Vous pouvez réessayer ou ouvrir le guide complet.');
        } finally {
            if (this.pending === request) this.pending = null;
        }
    }

    setStatus(message) {
        if (!this.hasStatusTarget) return;
        this.statusTarget.hidden = false;
        this.statusTarget.textContent = message;
    }

    cancelPending() {
        this.pending?.abort();
        this.pending = null;
    }

    close() {
        this.element.open = false;
        this.cancelPending();
        this.reflectDisclosure();
        this.summaryTarget.focus();
    }

    closeFromKeyboard(event) {
        if (!this.element.open) return;
        event.stopPropagation();
        this.close();
    }

    previousSlide() {
        this.switchSlide(this.index - 1);
    }

    nextSlide() {
        this.switchSlide(this.index + 1);
    }

    switchSlide(index) {
        if (index < 0 || index >= this.slideTargets.length) return;
        this.index = index;
        for (const [item, slide] of this.slideTargets.entries()) slide.hidden = item !== index;
        const illustration = this.illustrationTargets[index];
        // Les autres images restent sans source jusqu’à une demande du joueur.
        if (!illustration.hasAttribute('src')) illustration.src = illustration.dataset.src;
        if (this.hasPositionTarget) this.positionTarget.textContent = index + 1 + ' sur ' + this.slideTargets.length;
        if (this.hasPreviousTarget) this.previousTarget.setAttribute('aria-disabled', String(index === 0));
        if (this.hasNextTarget) this.nextTarget.setAttribute('aria-disabled', String(index === this.slideTargets.length - 1));
        this.write(this.slideKey(), String(index));
    }

    openKey() {
        return 'genesis:first-steps:seen:' + this.scopeValue;
    }

    slideKey() {
        return 'genesis:first-steps:slide:' + this.scopeValue + ':' + this.step;
    }

    read(key) {
        try { return window.localStorage.getItem(key); } catch { return null; }
    }

    write(key, value) {
        try { window.localStorage.setItem(key, value); } catch { /* Le panneau reste utilisable sans stockage. */ }
    }
}
