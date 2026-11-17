import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static values = { destinations: Object };

    connect() {
        this.picker = this.element.querySelector('[name="chapter"]');
        this.follow = () => this.redirectLegacyFragment();
        this.choose = () => { if (this.picker.value) this.picker.form.requestSubmit(); };
        window.addEventListener('hashchange', this.follow);
        this.picker?.addEventListener('change', this.choose);
        if (this.redirectLegacyFragment()) return;
        this.element.classList.add('wiki-chapters-ready');
    }

    redirectLegacyFragment() {
        // Le serveur ne reçoit pas le fragment des anciennes URL ; seuls les identifiants du manuel sont acceptés.
        const match = window.location.hash.match(/^#wiki-(?:title-)?([a-z0-9-]+)$/);
        const destination = match && Object.prototype.hasOwnProperty.call(this.destinationsValue, match[1]) ? this.destinationsValue[match[1]] : null;
        if (!destination || window.location.pathname === destination) return false;
        // Remplacer l’ancienne entrée évite une boucle au retour arrière. Les liens serveur restent le parcours natif.
        window.location.replace(destination + window.location.search + window.location.hash);
        return true;
    }

    disconnect() {
        window.removeEventListener('hashchange', this.follow);
        this.picker?.removeEventListener('change', this.choose);
        this.element.classList.remove('wiki-chapters-ready');
    }
}
