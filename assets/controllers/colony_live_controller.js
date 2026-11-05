import { Controller } from '@hotwired/stimulus';
export default class extends Controller {
    static values={url:String};
    connect(){ this.pendingRefresh=false;this.onRefresh=()=>{clearTimeout(this.timer);this.timer=setTimeout(()=>this.refresh(),200)};this.onFocusOut=()=>{if(this.pendingRefresh){clearTimeout(this.timer);this.timer=setTimeout(()=>this.refresh(),200)}};window.addEventListener('genesis:refresh',this.onRefresh);document.addEventListener('focusout',this.onFocusOut); }
    disconnect(){window.removeEventListener('genesis:refresh',this.onRefresh);document.removeEventListener('focusout',this.onFocusOut);clearTimeout(this.timer);this.abort?.abort();}
    async refresh(){
        this.abort?.abort();this.abort=new AbortController();
        try {
            const r=await fetch((location.pathname.startsWith('/planetes/')||location.pathname==='/flottes') ? location.href : this.urlValue,{signal:this.abort.signal});if(!r.ok) return;
            const doc=new DOMParser().parseFromString(await r.text(),'text/html');
            let deferred=false;
            for(const region of document.querySelectorAll('[data-live-region]')) {
                if(region.querySelector('form[data-dirty="true"]')) continue;
                if(region.contains(document.activeElement)){deferred=true;continue;}
                const next=doc.querySelector(`[data-live-region="${region.dataset.liveRegion}"]`);if(next) { const left=region.scrollLeft;region.replaceWith(next);next.scrollLeft=left; }
            }
            this.pendingRefresh=deferred;
        }catch(e){if(e.name!=='AbortError') this.element.dispatchEvent(new CustomEvent('genesis:offline',{bubbles:true}));}
    }
}
