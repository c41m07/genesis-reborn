import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    connect() {
        this.frame = this.element.closest('.app-frame');
        this.observer = new ResizeObserver(() => {
            // Mesurer une seule fois le rail et la bande ; le tiroir ouvert est hors flux.
            this.frame?.style.setProperty('--resource-stack-height', `${this.element.getBoundingClientRect().height}px`);
        });
        this.observer.observe(this.element);
    }

    disconnect() {
        this.observer?.disconnect();
        this.frame?.style.removeProperty('--resource-stack-height');
    }
}
