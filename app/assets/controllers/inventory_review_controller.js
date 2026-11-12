import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['picker', 'selection', 'operation', 'dialog', 'title', 'copy', 'confirm'];

    connect() {
        if (typeof this.dialogTarget.showModal !== 'function') return;
        if (this.hasPickerTarget) {
            this.pickerTarget.hidden = false;
            this.choose();
        }
    }

    choose() {
        if (!this.hasSelectionTarget) return;
        for (const operation of this.operationTargets) {
            operation.hidden = operation.dataset.operation !== this.selectionTarget.value;
        }
    }

    review(event) {
        if (this.approvedForm === event.currentTarget) {
            this.approvedForm = null;
            return;
        }
        if (typeof this.dialogTarget.showModal !== 'function') return;
        event.preventDefault();
        this.pendingForm = event.currentTarget;
        this.launcher = event.submitter;
        this.titleTarget.textContent = this.pendingForm.dataset.reviewTitle;
        const destination = this.pendingForm.querySelector('select[name="planet_id"]');
        const colony = destination?.selectedOptions[0]?.textContent;
        this.copyTarget.textContent = this.pendingForm.dataset.reviewCopy + (colony ? ' Destination : ' + colony + '.' : '');
        this.confirmTarget.textContent = this.pendingForm.dataset.reviewConfirm;
        this.dialogTarget.showModal();
    }

    navigate(event) {
        if (event.key !== 'Tab') return;
        const buttons = [...this.dialogTarget.querySelectorAll('button:not([disabled])')];
        const first = buttons[0], last = buttons.at(-1);
        if (event.shiftKey && document.activeElement === first) {
            event.preventDefault();
            last?.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
            event.preventDefault();
            first?.focus();
        }
    }

    confirm() {
        const form = this.pendingForm;
        if (!form) return;
        this.pendingForm = null;
        this.approvedForm = form;
        this.dialogTarget.close();
        form.requestSubmit(this.launcher);
    }

    cancel(event) {
        event?.preventDefault();
        this.pendingForm = null;
        this.approvedForm = null;
        this.dialogTarget.close();
    }

    closed() {
        this.pendingForm = null;
        this.launcher?.focus();
    }
}
