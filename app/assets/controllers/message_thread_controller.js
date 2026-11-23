import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    async connect() {
        this.active = true;
        await document.fonts.ready;
        if (!this.active) return;
        this.onResize = () => this.fitViewport();
        window.addEventListener('resize', this.onResize);
        const pane = this.element.closest('.message-pane');
        this.resizeObserver = new ResizeObserver(this.onResize);
        if (pane) this.resizeObserver.observe(pane);
        const composer = pane?.querySelector('.message-composer');
        if (composer) this.resizeObserver.observe(composer);
        const resources = document.querySelector('.resource-event-stack');
        if (resources) this.resizeObserver.observe(resources);
        this.fitViewport();
        this.frame = window.requestAnimationFrame(() => {
            this.element.scrollTop = this.element.scrollHeight;
            // Le seuil suit la disposition empilée contacts/conversation du lot L7E.
            this.frame = window.requestAnimationFrame(() => {
                const form = pane?.querySelector('.message-compose');
                if (!form) return;
                const rect = form.getBoundingClientRect();
                if (window.matchMedia('(max-width: 1050px)').matches || rect.bottom > window.innerHeight) {
                    form.scrollIntoView({ block: 'end', behavior: 'instant' });
                }
            });
        });
    }

    fitViewport() {
        const pane = this.element.closest('.message-pane');
        if (!pane) return;
        if (window.matchMedia('(max-width: 1050px)').matches) {
            pane.style.removeProperty('--message-pane-available');
            this.element.style.removeProperty('--message-thread-available');
            this.element.style.removeProperty('max-height');
            return;
        }
        const composer = pane.querySelector('.message-composer');
        const paneTop = pane.getBoundingClientRect().top + window.scrollY;
        const threadTop = this.element.getBoundingClientRect().top + window.scrollY;
        // Exclure la marge automatique du composeur : elle sert à l'aligner,
        // mais ne doit pas être comptée comme une partie incompressible.
        const available = Math.floor(window.innerHeight - 16 - paneTop);
        const reservedHeight = (threadTop - paneTop) + (composer?.offsetHeight ?? 0) + 2;
        const threadAvailable = `${Math.max(120, Math.floor(available - reservedHeight))}px`;
        const paneAvailable = `${Math.max(0, available)}px`;
        if (pane.style.getPropertyValue('--message-pane-available') === paneAvailable
            && this.element.style.getPropertyValue('--message-thread-available') === threadAvailable) return;
        const atBottom = this.element.scrollHeight - this.element.scrollTop - this.element.clientHeight < 2;
        pane.style.setProperty('--message-pane-available', paneAvailable);
        this.element.style.setProperty('--message-thread-available', threadAvailable);
        this.element.style.maxHeight = threadAvailable;
        if (atBottom) this.element.scrollTop = this.element.scrollHeight;
    }

    disconnect() {
        this.active = false;
        window.removeEventListener('resize', this.onResize);
        this.resizeObserver?.disconnect();
        window.cancelAnimationFrame(this.frame);
    }
}
