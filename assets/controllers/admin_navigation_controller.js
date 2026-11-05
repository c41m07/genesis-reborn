import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['panel', 'toggle'];

    connect() {
        this.viewport = window.matchMedia('(max-width: 760px)');
        this.onViewport = () => this.sync();
        this.viewport.addEventListener('change', this.onViewport);
        this.sync();
    }

    disconnect() {
        this.viewport.removeEventListener('change', this.onViewport);
    }

    sync() {
        const toggleFocused = document.activeElement === this.toggleTarget;
        this.toggleTarget.hidden = !this.viewport.matches;
        this.setOpen(!this.viewport.matches);
        if (toggleFocused && !this.viewport.matches) {
            this.panelTarget.querySelector('[aria-current="page"], a[href]')?.focus();
        }
    }

    toggle() {
        this.setOpen(this.panelTarget.hidden);
    }

    close(event) {
        if (!this.viewport.matches || this.panelTarget.hidden) return;
        event?.preventDefault();
        this.setOpen(false);
        this.toggleTarget.focus();
    }

    navigate(event) {
        const link = event.target.closest('a[href]');
        if (!this.viewport.matches || !link || !this.panelTarget.contains(link)) return;
        this.setOpen(false);
        const target = new URL(link.href);
        if (target.pathname === window.location.pathname && target.hash) {
            document.getElementById('admin-main')?.focus({ preventScroll: true });
        }
    }

    setOpen(open) {
        const focusInside = this.panelTarget.contains(document.activeElement);
        this.panelTarget.hidden = !open;
        this.toggleTarget.setAttribute('aria-expanded', String(open));
        if (!open && focusInside) this.toggleTarget.focus();
    }
}
