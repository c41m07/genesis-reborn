import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['system'];

    change(event) {
        if (event.target.name !== 'galaxy') return;
        this.systemTarget.disabled = true;
        this.element.requestSubmit();
    }
}
