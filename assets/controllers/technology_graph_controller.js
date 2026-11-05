import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['links', 'node'];

    connect() {
        this.observer = new ResizeObserver(() => this.draw());
        this.observer.observe(this.element);
        this.nodeTargets.forEach((node) => this.observer.observe(node));
        this.filterObserver = new MutationObserver(() => {
            this.element.scrollTo({ left: 0, top: 0, behavior: 'instant' });
            window.cancelAnimationFrame(this.resetFrame);
            this.resetFrame = window.requestAnimationFrame(() => this.element.scrollTo({ left: 0, top: 0, behavior: 'instant' }));
            this.draw();
        });
        this.filterObserver.observe(this.element, { attributes: true, attributeFilter: ['hidden'], subtree: true });
        this.draw();
    }

    disconnect() {
        this.observer?.disconnect();
        this.filterObserver?.disconnect();
        window.cancelAnimationFrame(this.resetFrame);
    }

    draw() {
        const svg = this.linksTarget;
        const bounds = this.element.getBoundingClientRect();
        svg.setAttribute('width', this.element.scrollWidth);
        svg.setAttribute('height', this.element.scrollHeight);
        svg.setAttribute('viewBox', `0 0 ${this.element.scrollWidth} ${this.element.scrollHeight}`);
        svg.replaceChildren();

        const visibleNodes = this.nodeTargets.filter((node) => node.getClientRects().length > 0);
        const byKey = new Map(visibleNodes.map((node) => [node.dataset.nodeKey, node]));
        visibleNodes.forEach((child) => {
            JSON.parse(child.dataset.parents || '[]').forEach((parentKey) => {
                const parent = byKey.get(parentKey);
                if (!parent) return;
                const from = parent.getBoundingClientRect();
                const to = child.getBoundingClientRect();
                const x1 = from.right - bounds.left + this.element.scrollLeft;
                const y1 = from.top + from.height / 2 - bounds.top + this.element.scrollTop;
                const x2 = to.left - bounds.left + this.element.scrollLeft;
                const y2 = to.top + to.height / 2 - bounds.top + this.element.scrollTop;
                const bend = Math.max(28, (x2 - x1) / 2);
                const path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
                path.setAttribute('d', `M ${x1} ${y1} C ${x1 + bend} ${y1}, ${x2 - bend} ${y2}, ${x2} ${y2}`);
                path.classList.add('technology-link', child.classList.contains('available') ? 'is-available' : 'is-locked');
                svg.append(path);
            });
        });
    }
}
