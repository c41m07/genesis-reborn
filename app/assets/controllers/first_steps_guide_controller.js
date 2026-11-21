import { Controller } from '@hotwired/stimulus';
import '../styles/first-steps-guide.css';

export default class extends Controller {
    static targets = ['summary', 'closeButton', 'slide', 'illustration', 'controls', 'previous', 'next', 'position'];
    static values = { key: String, scope: String, defer: Boolean };

    connect() {
        this.element.classList.add('first-steps-popup-enhanced');
        this.closeButtonTarget.hidden = false;
        this.controlsTarget.hidden = false;
        this.reflectDisclosure();
        this.index = Math.max(0, Math.min(this.slideTargets.length - 1, Number(this.read(this.slideKey())) || 0));
        if (this.element.open) {
            this.rememberOpened();
        } else if (!this.read(this.openKey())) {
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
        if (!this.element.open) return;
        this.write(this.openKey(), '1');
        this.switchSlide(this.index);
    }

    close() {
        this.element.open = false;
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
        this.positionTarget.textContent = index + 1 + ' sur ' + this.slideTargets.length;
        this.previousTarget.setAttribute('aria-disabled', String(index === 0));
        this.nextTarget.setAttribute('aria-disabled', String(index === this.slideTargets.length - 1));
        this.write(this.slideKey(), String(index));
    }

    openKey() {
        return 'genesis:first-steps:seen:' + this.scopeValue;
    }

    slideKey() {
        return 'genesis:first-steps:slide:' + this.keyValue;
    }

    read(key) {
        try { return window.localStorage.getItem(key); } catch { return null; }
    }

    write(key, value) {
        try { window.localStorage.setItem(key, value); } catch { /* Le panneau reste utilisable sans stockage. */ }
    }
}
