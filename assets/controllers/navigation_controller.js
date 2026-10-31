import { Controller } from '@hotwired/stimulus';
export default class extends Controller {
    static targets = ['sidebar', 'toggle'];
    connect() {
        this.element.classList.add('nav-ready');
        if (!this.hasSidebarTarget) return;
        this.mobile = matchMedia('(max-width: 1020px)');
        this.onViewport = () => this.sync();
        this.mobile.addEventListener('change', this.onViewport);
        this.onKey = event => {
            if (!this.element.classList.contains('nav-open')) return;
            if (event.key === 'Escape') this.close();
            if (event.key === 'Tab') {
                const items = [...this.sidebarTarget.querySelectorAll('a,button')].filter(item => item.offsetParent !== null);
                const first = items[0], last = items.at(-1);
                if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
                else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
            }
        };
        document.addEventListener('keydown', this.onKey);
        this.sync();
    }
    disconnect() {
        this.mobile?.removeEventListener('change', this.onViewport);
        document.removeEventListener('keydown', this.onKey);
    }
    sync() {
        if (!this.mobile.matches) this.element.classList.remove('nav-open');
        const open = this.element.classList.contains('nav-open');
        this.sidebarTarget.inert = this.mobile.matches && !open;
        this.sidebarTarget.setAttribute('aria-hidden', String(this.mobile.matches && !open));
        this.toggleTarget.setAttribute('aria-expanded', String(open));
    }
    toggle() {
        this.element.classList.toggle('nav-open');
        this.sync();
        if (this.element.classList.contains('nav-open')) this.sidebarTarget.querySelector('a')?.focus();
    }
    close() {
        this.element.classList.remove('nav-open');
        this.sync();
        this.toggleTarget.focus();
    }
}
