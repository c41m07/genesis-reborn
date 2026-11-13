import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    connect() {
        this.element.classList.add('comparison-ready');
        this.restore = () => this.update(new URL(location.href).searchParams.get('resource') || 'metal');
        window.addEventListener('popstate', this.restore);
    }
    select(event) {
        const resource = event.target.value;
        const url = new URL(location.href);
        url.searchParams.set('resource', resource);
        history.pushState(null, '', url);
        this.update(resource);
    }
    update(resource) {
        const select = this.element.querySelector('select');
        if (![...select.options].some(option => option.value === resource)) resource = 'metal';
        select.value = resource;
        for (const cell of document.querySelectorAll('.colony-comparison [data-resource]')) cell.classList.toggle('is-selected-resource', cell.dataset.resource === resource);
    }
    disconnect() {
        window.removeEventListener('popstate', this.restore);
        this.element.classList.remove('comparison-ready');
    }
}
