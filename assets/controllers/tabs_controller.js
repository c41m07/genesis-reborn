import { Controller } from '@hotwired/stimulus';
export default class extends Controller {
    static targets = ['tab','panel'];
    connect() {
        this.element.classList.add('tabs-ready');
        const section = new URL(location.href).searchParams.get('section');
        this.activate(Math.max(0, this.tabTargets.findIndex(tab => tab.id === `tab-${section}`)), false);
        this.onHistory = () => this.activate(Math.max(0, this.tabTargets.findIndex(tab => tab.id === `tab-${new URL(location.href).searchParams.get('section')}`)), false);
        window.addEventListener('popstate', this.onHistory);
    }
    disconnect() { window.removeEventListener('popstate', this.onHistory); }
    select(event) { this.activate(Number(event.currentTarget.dataset.index)); }
    activate(index, update = true) {
        this.tabTargets.forEach((tab,i) => { tab.setAttribute('aria-selected',String(i===index));tab.tabIndex=i===index?0:-1;this.panelTargets[i].hidden=i!==index; });
        if (update) {
            const section = this.tabTargets[index].id.replace('tab-', ''), url = new URL(location.href);
            url.searchParams.set('section', section);history.replaceState(null,'',url);
            for (const link of document.querySelectorAll('[data-colony-section]')) {
                const active = link.dataset.colonySection === section;link.classList.toggle('active', active);
                if (active) link.setAttribute('aria-current','page');else link.removeAttribute('aria-current');
            }
            const input = document.querySelector('.sidebar-colonies input[name="section"]');if (input) input.value = section;
        }
    }
    keydown(event) {
        const i=Number(event.currentTarget.dataset.index),n=this.tabTargets.length;let next;
        if(event.key==='ArrowRight')next=(i+1)%n;if(event.key==='ArrowLeft')next=(i+n-1)%n;
        if(event.key==='Home')next=0;if(event.key==='End')next=n-1;
        if(next!==undefined){event.preventDefault();this.activate(next);this.tabTargets[next].focus();}
    }
}
