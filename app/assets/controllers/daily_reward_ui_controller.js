import { Controller } from '@hotwired/stimulus';
import '../styles/daily-reward-ui.css';

export default class extends Controller {
    static targets = ['dialog', 'mount', 'gift', 'mail', 'wiki'];
    static values = { autoOpen: Boolean, panelUrl: String };

    connect() {
        if (!this.autoOpenValue || !this.hasGiftTarget || !this.canOpenDialog()) return;
        // La décision d’ouverture appartient au rendu serveur, pas à cet onglet.
        this.autoOpenValue = false;
        void this.loadPanel(this.giftTarget, false);
    }

    disconnect() {
        this.pending?.abort();
        this.pending = null;
    }

    canOpenDialog() {
        return this.hasPanelUrlValue && this.hasMountTarget
            && typeof window.HTMLDialogElement?.prototype.showModal === 'function';
    }

    openFromGift(event) {
        if (event.defaultPrevented || event.button > 0 || event.ctrlKey || event.metaKey || event.altKey || event.shiftKey
            || !this.canOpenDialog()) return;
        event.preventDefault();
        void this.loadPanel(event.currentTarget, true);
    }

    async loadPanel(link, explicit) {
        if (this.pending) return;
        const request = new AbortController();
        this.pending = request;
        try {
            // Chaque ouverture relit le jour civil et l’état du compte sur le serveur, sans scrutation.
            const response = await fetch(this.panelUrlValue, {
                headers: { Accept: 'text/html' },
                credentials: 'same-origin',
                cache: 'no-store',
                redirect: 'error',
                signal: request.signal,
            });
            if (!response.ok) throw new Error('Fenêtre indisponible');
            const html = new DOMParser().parseFromString(await response.text(), 'text/html');
            const dialog = html.querySelector('dialog[data-daily-reward-ui-target="dialog"]');
            if (!dialog) throw new Error('Réponse sans fenêtre de récompense');
            if (request.signal.aborted || !link.isConnected) return;

            if (!dialog.querySelector('button[type="submit"]')) {
                if (document.activeElement === link && this.hasWikiTarget) this.wikiTarget.focus();
                link.hidden = true;
                if (!explicit) return;
            }
            this.mountTarget.replaceChildren(dialog);
            dialog.showModal();
        } catch {
            // Une ouverture automatique ne doit jamais empêcher d’entrer sur la page de jeu.
            if (explicit && !request.signal.aborted && link.isConnected) window.location.assign(link.href);
        } finally {
            if (this.pending === request) this.pending = null;
        }
    }

    close() {
        if (this.hasDialogTarget) this.dialogTarget.close();
    }

    restoreFocus() {
        if (this.hasGiftTarget && !this.giftTarget.hidden) {
            this.giftTarget.focus();
            this.giftTarget.classList.add('is-notifying');
        } else if (this.hasWikiTarget) {
            this.wikiTarget.focus();
        }
    }

    updateMail(event) {
        if (!this.hasMailTarget) return;
        const unread = Number(event.detail?.count) || 0;
        if (unread <= 0 && document.activeElement === this.mailTarget && this.hasWikiTarget) this.wikiTarget.focus();
        this.mailTarget.hidden = unread <= 0;
        this.mailTarget.setAttribute('aria-description', unread === 1 ? '1 message non lu' : unread + ' messages non lus');
    }

    dismissTooltip() {
        if (this.hasDialogTarget && this.dialogTarget.open) return;
        for (const control of this.element.querySelectorAll('[data-header-tooltip]:hover, [data-header-tooltip]:focus-visible')) {
            control.setAttribute('data-header-tooltip-dismissed', '');
        }
    }

    resetTooltip(event) {
        const control = event.target.closest('[data-header-tooltip]');
        if (control && !control.contains(event.relatedTarget)) control.removeAttribute('data-header-tooltip-dismissed');
    }
}
