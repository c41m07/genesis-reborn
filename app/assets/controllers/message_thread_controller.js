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
        const resources = document.querySelector('.resource-event-stack');
        if (resources) this.resizeObserver.observe(resources);
        this.fitViewport();
        this.frame = window.requestAnimationFrame(() => {
            this.element.scrollTop = this.element.scrollHeight;
            if (window.matchMedia('(max-width: 740px)').matches) {
                this.frame = window.requestAnimationFrame(() => {
                    const pane = this.element.closest('.message-pane');
                    (pane?.querySelector('.message-compose') ?? this.element).scrollIntoView({ block: 'end', behavior: 'instant' });
                });
            }
        });
    }

    fitViewport() {
        const pane = this.element.closest('.message-pane');
        if (!pane) return;
        if (window.matchMedia('(max-width: 740px)').matches) {
            this.element.style.maxHeight = '';
            return;
        }
        const thread = this.element.getBoundingClientRect();
        const bottom = pane.getBoundingClientRect().bottom;
        const available = Math.max(120, Math.floor(window.innerHeight - thread.top - (bottom - thread.bottom) - 16));
        const height = `${available}px`;
        if (this.element.style.maxHeight === height) return;
        const atBottom = this.element.scrollHeight - this.element.scrollTop - this.element.clientHeight < 2;
        this.element.style.maxHeight = height;
        if (atBottom) this.element.scrollTop = this.element.scrollHeight;
    }

    disconnect() {
        this.active = false;
        window.removeEventListener('resize', this.onResize);
        this.resizeObserver?.disconnect();
        window.cancelAnimationFrame(this.frame);
    }
}
