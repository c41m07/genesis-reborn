import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['trigger', 'panel'];

    connect() {
        this.triggerTarget.setAttribute('aria-expanded', 'false');
        this.reposition = () => { if (this.isOpen()) this.position(); };
        window.addEventListener('resize', this.reposition);
        document.addEventListener('scroll', this.reposition, true);
        this.outsideFocus = event => {
            if (this.isOpen() && !this.element.contains(event.target) && !this.panelTarget.contains(event.target)) this.hide();
        };
        document.addEventListener('focusin', this.outsideFocus);
        if (document.activeElement === this.triggerTarget) this.show();
    }

    disconnect() {
        clearTimeout(this.timer);
        window.removeEventListener('resize', this.reposition);
        document.removeEventListener('scroll', this.reposition, true);
        document.removeEventListener('focusin', this.outsideFocus);
    }

    isOpen() { return typeof this.panelTarget.showPopover === 'function' && this.panelTarget.matches(':popover-open'); }

    show() {
        clearTimeout(this.timer);
        if (typeof this.panelTarget.showPopover !== 'function') return;
        if (!this.isOpen()) this.panelTarget.showPopover();
        this.triggerTarget.setAttribute('aria-expanded', 'true');
        this.position();
    }

    refresh() { if (this.isOpen()) this.position(); }

    anchor() {
        if (this.element.matches('.flight-operation')) return this.element;
        if (this.triggerTarget.getClientRects().length) return this.triggerTarget;
        return [...document.querySelectorAll('[popovertarget]')].find(trigger =>
            trigger.getAttribute('popovertarget') === this.panelTarget.id && trigger.getClientRects().length
        ) ?? this.triggerTarget;
    }

    position() {
        const anchor = this.anchor();
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

    leave(event) {
        if (event.pointerType !== 'mouse') return;
        clearTimeout(this.timer);
        this.timer = setTimeout(() => {
            const anchor = this.anchor();
            if (this.panelTarget.matches(':hover') || anchor.matches(':hover')) return;
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
            if (!this.panelTarget.contains(document.activeElement) && document.activeElement !== this.triggerTarget) this.hide();
        });
    }

    escape(event) {
        if (event.key !== 'Escape' || !this.isOpen()) return;
        event.preventDefault();
        event.stopPropagation();
        this.hide();
        this.restoringFocus = true;
        this.anchor().focus();
        this.restoringFocus = false;
    }
}
