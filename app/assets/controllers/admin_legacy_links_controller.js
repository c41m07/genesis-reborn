import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static values = { routes: Object };
    connect() {
        this.follow = this.follow.bind(this);
        window.addEventListener('hashchange', this.follow);
        this.follow();
    }
    disconnect() { window.removeEventListener('hashchange', this.follow); }
    follow() {
        const anchor = window.location.hash.slice(1);
        const section = anchor.startsWith('admin-universe') ? 'universes' : anchor.replace(/^admin-/, '');
        if (this.routesValue[section]) window.location.replace(this.routesValue[section] + '#' + anchor);
    }
}
