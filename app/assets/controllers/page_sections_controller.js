import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    connect() {
        // Les anciens liens d’un écran composite gardent une destination après la séparation.
        if (['guilde', 'diplomatie'].includes(this.element.dataset.uiPage)) {
            const destinations = {
                '#guild-management': '/guildes/gestion', '#guild-management-title': '/guildes/gestion',
                '#guild-members-title': '/guildes/membres', '#guild-ranks-title': '/guildes/grades',
                '#guild-search': '/guildes', '#alliance-members': '/diplomatie/commandants',
            };
            const destination = destinations[window.location.hash];
            if (destination && window.location.pathname !== destination) {
                window.location.replace(destination + window.location.hash);
                return;
            }
        }
        if (this.element.dataset.uiPage === 'flottes') {
            const fleet = /^#fleet-(\d+)$/.exec(window.location.hash);
            if (window.location.pathname === '/flottes' && fleet && new URLSearchParams(window.location.search).get('fleet') !== fleet[1]) {
                const url = new URL(window.location.href);
                url.searchParams.set('fleet', fleet[1]);
                window.location.replace(url);
                return;
            }
            const destinations = { '#prepare-mission':'/flottes/preparer', '#active-missions':'/flottes', '#orbit-requests-title':'/flottes/orbites', '#held-fleets-title':'/flottes/quai' };
            const destination = destinations[window.location.hash];
            if (destination && window.location.pathname !== destination) {
                window.location.replace(destination + window.location.search + window.location.hash);
                return;
            }
        }
        const main = this.element.querySelector('main');
        if (!main || ['vue-generale', 'flottes', 'wiki', 'projet-detail', 'accueil-public', 'recherche-messages'].includes(this.element.dataset.uiPage)) return;
        if (main.querySelector('[data-page-sections="disabled"], .page-contents, nav a[href^="#"]')) return;
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
