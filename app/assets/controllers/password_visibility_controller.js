import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['input', 'button'];

    connect() {
        this.buttonTarget.hidden = false;
        this.mask();
    }

    disconnect() {
        this.mask();
        this.buttonTarget.hidden = true;
    }

    toggle() {
        const visible = this.inputTarget.type === 'password';
        this.inputTarget.type = visible ? 'text' : 'password';
        this.buttonTarget.setAttribute('aria-pressed', String(visible));
        this.buttonTarget.setAttribute('aria-label', visible ? 'Masquer le mot de passe' : 'Afficher le mot de passe');
    }

    mask() {
        this.inputTarget.type = 'password';
        this.buttonTarget.setAttribute('aria-pressed', 'false');
        this.buttonTarget.setAttribute('aria-label', 'Afficher le mot de passe');
    }
}
