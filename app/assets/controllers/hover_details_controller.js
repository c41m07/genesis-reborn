import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['trigger', 'panel'];

    connect() {
        this.triggerTarget.setAttribute('aria-expanded', 'false');
        this.reposition = () => { if (this.isOpen()) this.position(); };
        window.addEventListener('resize', this.reposition);
        document.addEventListener('scroll', this.reposition, true);
    }

    disconnect() {
        clearTimeout(this.timer);
        window.removeEventListener('resize', this.reposition);
        document.removeEventListener('scroll', this.reposition, true);
    }

    isOpen() { return typeof this.panelTarget.showPopover === 'function' && this.panelTarget.matches(':popover-open'); }

    show() {
        clearTimeout(this.timer);
        if (typeof this.panelTarget.showPopover !== 'function') return;
        if (!this.isOpen()) this.panelTarget.showPopover();
        this.triggerTarget.setAttribute('aria-expanded', 'true');
        this.position();
    }

    position() {
        const anchor = this.element.matches('.flight-operation') ? this.element : this.triggerTarget;
        const rect = anchor.getBoundingClientRect(), panel = this.panelTarget;
        const width = panel.getBoundingClientRect().width, height = panel.getBoundingClientRect().height;
        const left = Math.max(12, Math.min(rect.left, innerWidth - width - 12));
        const below = rect.bottom + 8;
        const top = below + height <= innerHeight - 12 ? below : Math.max(12, rect.top - height - 8);
        panel.style.left = left + 'px';
        panel.style.top = top + 'px';
    }

    enter(event) {
        if (event.pointerType !== 'mouse') return;
        this.show();
    }

    focus() { if (!this.restoringFocus) this.show(); }

    leave() {
        clearTimeout(this.timer);
        this.timer = setTimeout(() => {
            if (this.pinned || this.panelTarget.matches(':hover') || this.triggerTarget.matches(':hover')) return;
            if (this.panelTarget.contains(document.activeElement) || document.activeElement === this.triggerTarget) return;
            this.hide();
        }, 180);
    }

    pin(event) {
        if (typeof this.panelTarget.showPopover !== 'function') return;
        event.preventDefault();
        if (this.pinned && this.isOpen()) this.hide();
        else { this.pinned = true; this.show(); }
    }

    card(event) {
        if (event.target.closest('a,button,input,select,summary')) return;
        if (this.panelTarget.contains(event.target)) return;
        this.pin(event);
    }

    hide() {
        clearTimeout(this.timer);
        this.pinned = false;
        if (typeof this.panelTarget.hidePopover === 'function' && this.isOpen()) this.panelTarget.hidePopover();
        this.triggerTarget.setAttribute('aria-expanded', 'false');
    }

    changed() {
        const open = this.isOpen();
        this.triggerTarget.setAttribute('aria-expanded', String(open));
        if (!open) this.pinned = false;
        else this.position();
    }

    blur() {
        queueMicrotask(() => {
            if (!this.pinned && !this.element.contains(document.activeElement)) this.hide();
        });
    }

    dismiss(event) {
        event.preventDefault();
        event.stopPropagation();
        this.hide();
        this.restoringFocus = true;
        this.triggerTarget.focus();
        this.restoringFocus = false;
    }

    escape(event) {
        if (event.key !== 'Escape' || !this.isOpen()) return;
        event.preventDefault();
        event.stopPropagation();
        this.hide();
        this.restoringFocus = true;
        this.triggerTarget.focus();
        this.restoringFocus = false;
    }
}
