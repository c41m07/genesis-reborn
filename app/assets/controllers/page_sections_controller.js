import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    connect() {
        if (['guilde', 'diplomatie', 'flottes', 'inventaire', 'profil', 'preferences', 'projets', 'evenements'].includes(this.element.dataset.uiPage)) {
            // Un changement de fragment peut conserver le document et ne reconnecte pas Stimulus.
            this.onHashChange = () => this.redirectLegacyFragment();
            window.addEventListener('hashchange', this.onHashChange);
            if (this.redirectLegacyFragment()) return;
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

    redirectLegacyFragment() {
        // Les anciens liens d’un écran composite gardent une destination après la séparation.
        if (this.element.dataset.uiPage === 'evenements' && window.location.pathname === '/evenements' && window.location.hash === '#page-section-0') {
            // Le premier titre sans identifiant de l’ancien écran était celui des défis.
            window.location.replace('/evenements/defis' + window.location.search + '#challenges-list');
            return true;
        }
        if (['guilde', 'diplomatie'].includes(this.element.dataset.uiPage)) {
            const destinations = {
                '#guild-management': '/guildes/gestion', '#guild-management-title': '/guildes/gestion',
                '#guild-members-title': '/guildes/membres', '#guild-ranks-title': '/guildes/grades',
                '#guild-search': '/guildes', '#alliance-members': '/diplomatie/commandants',
            };
            const destination = destinations[window.location.hash];
            if (destination && window.location.pathname !== destination) {
                window.location.replace(destination + window.location.hash);
                return true;
            }
        }
        if (this.element.dataset.uiPage === 'projets') {
            const destinations = {
                '#ongoing-projects-title': '/guildes/projets',
                '#available-projects-title': '/guildes/projets/disponibles',
                '#completed-projects-title': '/guildes/projets/historique',
            };
            const destination = destinations[window.location.hash];
            if (destination && window.location.pathname !== destination) {
                window.location.replace(destination + window.location.search + window.location.hash);
                return true;
            }
        }
        if (['profil', 'preferences'].includes(this.element.dataset.uiPage)) {
            const destinations = {
                '#vacances': '/profil/vacances', '#vacances-info': '/profil/vacances', '#vacation-heading': '/profil/vacances',
                '#account-delete-title': '/profil/supprimer',
            };
            const destination = destinations[window.location.hash];
            if (destination && window.location.pathname !== destination) {
                window.location.replace(destination + window.location.search + window.location.hash);
                return true;
            }
        }
        if (this.element.dataset.uiPage === 'inventaire') {
            const destinations = {
                '#inventory-boosts': '/boosts', '#available-bonuses-title': '/boosts', '#boost-use-title': '/boosts',
                '#inventory-boxes': '/inventaire/boites', '#inventory-boxes-title': '/inventaire/boites',
                '#inventory-pending': '/inventaire/recompenses', '#inventory-pending-title': '/inventaire/recompenses',
            };
            const destination = destinations[window.location.hash];
            if (destination && window.location.pathname !== destination) {
                window.location.replace(destination + window.location.search + window.location.hash);
                return true;
            }
        }
        if (this.element.dataset.uiPage === 'flottes') {
            const fleet = /^#fleet-(\d+)$/.exec(window.location.hash);
            if (window.location.pathname === '/flottes' && fleet && !document.getElementById(`fleet-${fleet[1]}`) && new URLSearchParams(window.location.search).get('fleet') !== fleet[1]) {
                const url = new URL(window.location.href);
                url.searchParams.set('fleet', fleet[1]);
                window.location.replace(url);
                return true;
            }
            const destinations = { '#prepare-mission':'/flottes/preparer', '#active-missions':'/flottes', '#orbit-requests-title':'/flottes/orbites', '#held-fleets-title':'/flottes/quai' };
            const destination = destinations[window.location.hash];
            if (destination && window.location.pathname !== destination) {
                const url = new URL(destination, window.location.origin);
                url.search = window.location.search;
                url.hash = window.location.hash;
                // Les paramètres d’envoi feraient revenir l’ancienne entrée /flottes vers la préparation.
                if (destination !== '/flottes/preparer') {
                    for (const parameter of ['mission', 'system', 'planet', 'composition', 'confirmation', 'adjust']) url.searchParams.delete(parameter);
                }
                if (destination === '/flottes' && url.searchParams.has('colony')) {
                    url.searchParams.set('origin', url.searchParams.get('colony'));
                    url.searchParams.delete('colony');
                }
                window.location.replace(url);
                return true;
            }
        }
        return false;
    }

    disconnect() {
        window.removeEventListener('hashchange', this.onHashChange);
        this.navigation?.remove();
    }
}
