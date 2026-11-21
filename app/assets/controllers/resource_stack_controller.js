import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    connect() {
        this.frame = this.element.closest('.app-frame');
        this.observer = new ResizeObserver(() => {
            // Mesurer la bande seule. La topbar fixe son propre décalage sticky, même sur deux rangées.
            this.frame?.style.setProperty('--resource-stack-height', `${this.element.getBoundingClientRect().height}px`);
        });
        this.observer.observe(this.element);
    }

    disconnect() {
        this.observer?.disconnect();
        this.frame?.style.removeProperty('--resource-stack-height');
    }
}
