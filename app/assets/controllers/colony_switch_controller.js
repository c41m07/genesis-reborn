import { Controller } from '@hotwired/stimulus';
export default class extends Controller {
    change() {
        const section = this.element.form.querySelector('[name="section"]');
        if (section) section.value = new URL(location.href).searchParams.get('section') || section.value;
        this.element.form.requestSubmit();
    }
}
