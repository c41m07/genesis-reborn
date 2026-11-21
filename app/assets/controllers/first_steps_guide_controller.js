import { Controller } from '@hotwired/stimulus';
import '../styles/first-steps-guide.css';

export default class extends Controller {
    static targets = ['summary', 'closeButton', 'slide', 'illustration', 'controls', 'previous', 'next', 'position'];
    static values = { key: String, scope: String, defer: Boolean };

    connect() {
        this.element.classList.add('first-steps-popup-enhanced');
        this.closeButtonTarget.hidden = false;
        this.controlsTarget.hidden = false;
        this.index = Math.max(0, Math.min(this.slideTargets.length - 1, Number(this.read(this.slideKey())) || 0));
        if (this.element.open) {
            this.rememberOpened();
        } else if (!this.read(this.openKey())) {
            if (this.deferValue) {
                // Le premier aperçu attend la fenêtre quotidienne si celle-ci a priorité.
                this.afterDaily = (event) => {
                    if (event.target.id !== 'daily-reward-dialog') return;
                    document.removeEventListener('close', this.afterDaily, true);
                    this.showFirstVisit();
                };
                document.addEventListener('close', this.afterDaily, true);
            } else {
                this.showFirstVisit();
            }
        }
    }

    disconnect() {
        if (this.afterDaily) document.removeEventListener('close', this.afterDaily, true);
        this.afterDaily = null;
    }

    showFirstVisit() {
        if (!this.element.isConnected || this.read(this.openKey())) return;
        // Ne jamais déplacer le focus lors d’une ouverture proposée.
        this.element.open = true;
        this.rememberOpened();
    }

    rememberOpened() {
        if (!this.element.open) return;
        this.write(this.openKey(), '1');
        this.switchSlide(this.index);
    }

    close() {
        this.element.open = false;
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
