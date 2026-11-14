import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    connect() {
        this.reset();
    }

    reset() {
        this.sending = false;
        this.element.removeAttribute('aria-busy');
        this.element.querySelector('button[type="submit"]').disabled = false;
    }

    keydown(event) {
        if (event.target.tagName !== 'TEXTAREA' || event.key !== 'Enter' || !event.ctrlKey || event.altKey || event.metaKey || event.isComposing) return;
        event.preventDefault();
        if (event.repeat || this.sending) return;
        this.element.requestSubmit(this.element.querySelector('button[type="submit"]'));
    }

    submit(event) {
        if (this.sending) {
            event.preventDefault();
            return;
        }
        if (event.defaultPrevented) return;
        this.sending = true;
        this.element.setAttribute('aria-busy', 'true');
        this.element.querySelector('button[type="submit"]').disabled = true;
    }
}
