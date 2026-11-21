import { Controller } from '@hotwired/stimulus';
import '../styles/first-steps-guide.css';

export default class extends Controller {
    static targets = ['summary', 'fallback', 'content', 'status', 'closeButton', 'slide', 'controls', 'previous', 'next', 'position'];
    static values = { scope: String, url: String };

    connect() {
        this.element.classList.add('first-steps-popup-enhanced');
        this.wasOpen = false;
        this.clearPanel();
        this.reflectDisclosure();
        if (this.element.open) this.rememberOpened();
    }

    disconnect() {
        this.cancelPending();
    }

    reflectDisclosure() {
        const label = this.element.open ? 'Fermer Premiers pas' : 'Ouvrir Premiers pas';
        this.summaryTarget.setAttribute('aria-expanded', String(this.element.open));
        this.summaryTarget.setAttribute('aria-label', label);
        this.summaryTarget.title = label;
    }

    rememberOpened() {
        this.reflectDisclosure();
        const open = this.element.open;
        if (open === this.wasOpen) return;
        this.wasOpen = open;

        if (!open) {
            this.cancelPending();
            this.clearPanel();
            return;
        }

        // Une autre page peut avoir fait progresser ou désactivé le guide pendant sa fermeture.
        if (!this.pending) this.loadPanel();
    }

    clearPanel() {
        this.contentTarget.replaceChildren();
        this.contentTarget.hidden = true;
        this.fallbackTarget.hidden = false;
        this.statusTarget.hidden = true;
        this.step = null;
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
                this.hideUnavailableGuide();
                return;
            }
            if (!response.ok) throw new Error('Guide indisponible');

            const template = document.createElement('template');
            template.innerHTML = await response.text();
            const panel = template.content.querySelector('[data-first-steps-guide-fragment]');
            if (request.signal.aborted || !this.element.open) return;
            if (!panel?.dataset.firstStepsGuideStep) throw new Error('Étape indisponible');

            this.step = panel.dataset.firstStepsGuideStep;
            const fallbackHadFocus = this.fallbackTarget.contains(document.activeElement);
            this.contentTarget.replaceChildren(panel);
            this.contentTarget.hidden = false;
            // Le lien natif reste le même choix si le joueur l’a déjà atteint au clavier.
            if (fallbackHadFocus) panel.querySelector('[data-first-steps-guide-return]')?.focus();
            this.fallbackTarget.hidden = true;
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

    hideUnavailableGuide() {
        if (this.element.contains(document.activeElement)) {
            const main = document.getElementById('main');
            if (main) {
                // Retirer le contrôle actif sans ajouter un arrêt permanent à la tabulation.
                if (!main.hasAttribute('tabindex')) main.tabIndex = -1;
                main.focus({ preventScroll: true });
            }
        }
        this.element.open = false;
        this.element.hidden = true;
        this.rememberOpened();
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
        this.summaryTarget.focus();
        this.rememberOpened();
    }

    closeFromKeyboard(event) {
        if (!this.element.open) return;
        event.stopPropagation();
        const slide = this.slideTargets[this.index];
        if (slide?.querySelector('[data-first-steps-zoom][aria-expanded="true"]')) {
            this.setZoom(slide, false)?.focus();
            return;
        }
        this.close();
    }

    toggleZoom(event) {
        const slide = this.slideTargets[this.index];
        if (!slide?.contains(event.currentTarget)) return;
        this.setZoom(slide, event.currentTarget.getAttribute('aria-expanded') !== 'true');
    }

    setZoom(slide, expanded) {
        const button = slide.querySelector('[data-first-steps-zoom]');
        const visual = slide.querySelector('[data-first-steps-visual]');
        if (!button || !visual) return null;
        // Le même visuel grandit dans la zone défilante : aucun dialogue ni nouvelle requête.
        visual.classList.toggle('first-steps-gesture-frame--expanded', expanded);
        button.setAttribute('aria-expanded', String(expanded));
        button.textContent = expanded ? 'Réduire l’image' : 'Voir en grand';
        return button;
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
        for (const [item, slide] of this.slideTargets.entries()) {
            slide.hidden = item !== index;
            if (slide.hidden) {
                this.setZoom(slide, false);
                // Débrancher l’image masquée même si sa réponse n’est pas encore arrivée.
                slide.querySelector('img[data-src]')?.removeAttribute('src');
            }
        }
        if (this.hasPositionTarget) this.positionTarget.textContent = index + 1 + ' sur ' + this.slideTargets.length;
        if (this.hasPreviousTarget) this.previousTarget.setAttribute('aria-disabled', String(index === 0));
        if (this.hasNextTarget) this.nextTarget.setAttribute('aria-disabled', String(index === this.slideTargets.length - 1));
        this.write(this.slideKey(), String(index));

        // Les commandes et le texte ne dépendent ni de la réponse de l’image ni de son décodage.
        const illustration = this.slideTargets[index].querySelector('img[data-src]');
        if (illustration && !illustration.hasAttribute('src')) illustration.src = illustration.dataset.src;
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
