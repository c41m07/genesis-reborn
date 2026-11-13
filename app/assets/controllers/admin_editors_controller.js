import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    connect() {
        let index = 0;
        const focused = document.activeElement;
        const autofocus = this.element.querySelector('main [autofocus]');
        for (const form of this.element.querySelectorAll('main form')) {
            if (form.closest('details') || form.method.toLowerCase() === 'get' || form.querySelector('input[type="search"]')) continue;
            const context = form.closest('article, section')?.querySelector('h2, h3')?.textContent.trim();
            const action = form.querySelector('button[type="submit"], button:not([type])')?.textContent.trim();
            const details = document.createElement('details');
            details.className = 'page-editor';
            details.open = index++ === 0 || form.contains(focused) || form.contains(autofocus);
            const summary = document.createElement('summary');
            summary.textContent = [context, action].filter(Boolean).join(' · ') || 'Réglages';
            form.before(details);
            details.append(summary, form);
        }
        const target = autofocus || (focused !== document.body ? focused : null);
        if (target?.isConnected) target.focus({ preventScroll: true });
    }
}
