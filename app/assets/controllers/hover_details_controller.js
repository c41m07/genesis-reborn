import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    enter(event) {
        this.hovering = event.pointerType === 'mouse';
        if (this.hovering && !this.element.open) {
            this.hoverOpened = true;
            this.element.open = true;
        }
    }

    leave() {
        this.hovering = false;
        this.closeHover();
    }

    pin(event) {
        if (!this.hoverOpened) return;
        event.preventDefault();
        this.hoverOpened = false;
    }

    closeHover() {
        if (this.hoverOpened && !this.element.contains(document.activeElement)) {
            this.element.open = false;
            this.hoverOpened = false;
        }
    }

    blur() {
        queueMicrotask(() => { if (!this.hovering) this.closeHover(); });
    }

    escape(event) {
        if (event.key !== 'Escape' || !this.element.open) return;
        event.preventDefault();
        event.stopPropagation();
        this.element.open = false;
        this.hoverOpened = false;
        this.element.querySelector('summary').focus();
    }
}
