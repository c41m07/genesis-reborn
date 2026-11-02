import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['dialog'];

    open() {
        this.dialogTarget.showModal();
    }

    outside(event) {
        const bounds = this.dialogTarget.getBoundingClientRect();
        const outside = event.clientX < bounds.left || event.clientX > bounds.right || event.clientY < bounds.top || event.clientY > bounds.bottom;
        if (outside) this.dialogTarget.close();
    }
}
