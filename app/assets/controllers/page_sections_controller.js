import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    connect() {
        const main = this.element.querySelector('main');
        if (!main || ['vue-generale', 'flottes', 'wiki', 'projet-detail', 'accueil-public', 'recherche-messages'].includes(this.element.dataset.uiPage)) return;
        if (main.querySelector('.page-contents, nav a[href^="#"]')) return;
        const seen = new Set();
        const headings = [...main.querySelectorAll('h2')].filter(heading => {
            const label = heading.textContent.trim();
            if (!label || label.length > 70 || seen.has(label) || heading.closest('[popover], dialog, [hidden]') || !heading.getClientRects().length) return false;
            seen.add(label);
            return true;
        }).slice(0, 8);
        if (headings.length < 3) return;
        this.navigation = document.createElement('nav');
        this.navigation.className = 'page-contents';
        this.navigation.setAttribute('aria-label', 'Sections de cette page');
        for (const [index, heading] of headings.entries()) {
            if (!heading.id) heading.id = 'page-section-' + index;
            const link = document.createElement('a');
            link.href = '#' + heading.id;
            link.textContent = heading.textContent.trim();
            this.navigation.append(link);
        }
        (main.querySelector('.page-heading') || main.querySelector('h1'))?.after(this.navigation);
    }

    disconnect() { this.navigation?.remove(); }
}
