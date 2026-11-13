import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static values = { search: Boolean };
    connect() {
        this.chapters = [...this.element.querySelectorAll('.wiki-chapter')];
        this.picker = this.element.querySelector('[name="chapter"]');
        if (!this.chapters.length || !this.picker) return;
        this.element.classList.add('wiki-chapters-ready');
        this.follow = () => this.show(location.hash.slice(1));
        this.choose = event => {
            if (!event.target.value) return;
            const id = 'wiki-' + event.target.value;
            if (this.searchValue) { this.picker.form.requestSubmit(); return; }
            this.navigate(id);
        };
        this.click = event => {
            const link = event.target.closest('a[href^="#wiki-"]');
            if (!link || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
            const id = link.hash.slice(1);
            if (!this.chapters.some(chapter => chapter.id === id)) return;
            event.preventDefault();
            this.navigate(id);
        };
        this.element.addEventListener('click', this.click);
        this.picker.addEventListener('change', this.choose);
        window.addEventListener('hashchange', this.follow);
        window.addEventListener('popstate', this.follow);
        this.follow();
    }
    navigate(id) {
        history.pushState(null, '', '#' + id);
        this.show(id);
        const heading = document.getElementById(id)?.querySelector('h2');
        if (heading) { heading.tabIndex = -1; heading.focus({ preventScroll: true }); heading.scrollIntoView({ block: 'start' }); }
    }
    show(id) {
        if (this.searchValue) return;
        if (id && !this.chapters.some(chapter => chapter.id === id)) return;
        const current = this.chapters.find(chapter => chapter.id === id) || this.chapters[0];
        for (const chapter of this.chapters) chapter.hidden = chapter !== current;
        this.picker.value = current.id.slice(5);
        for (const link of this.element.querySelectorAll('.wiki-summary a')) {
            if (link.hash === '#' + current.id) link.setAttribute('aria-current', 'true');
            else link.removeAttribute('aria-current');
        }
    }
    disconnect() {
        this.element.removeEventListener('click', this.click);
        this.picker?.removeEventListener('change', this.choose);
        window.removeEventListener('hashchange', this.follow);
        window.removeEventListener('popstate', this.follow);
        for (const chapter of this.chapters || []) chapter.hidden = false;
        this.element.classList.remove('wiki-chapters-ready');
    }
}
