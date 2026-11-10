import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    async connect() {
        this.active = true;
        await document.fonts.ready;
        if (!this.active) return;
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

    disconnect() {
        this.active = false;
        window.cancelAnimationFrame(this.frame);
    }
}
