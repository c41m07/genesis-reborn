import { Controller } from '@hotwired/stimulus';
export default class extends Controller {
    static values={url:String};
    connect(){ this.generation=(this.generation??0)+1;this.pendingRefresh=false;this.onRefresh=()=>{clearTimeout(this.timer);this.timer=setTimeout(()=>this.refresh(),200)};this.onFocusOut=()=>{if(this.pendingRefresh){clearTimeout(this.timer);this.timer=setTimeout(()=>this.refresh(),200)}};window.addEventListener('genesis:refresh',this.onRefresh);document.addEventListener('focusout',this.onFocusOut); }
    disconnect(){++this.generation;window.removeEventListener('genesis:refresh',this.onRefresh);document.removeEventListener('focusout',this.onFocusOut);clearTimeout(this.timer);this.abort?.abort();}
    async refresh(){
        this.abort?.abort();this.abort=new AbortController();
        const generation=++this.generation;
        const contextUrl=location.href;
        try {
            const r=await fetch((location.pathname.startsWith('/planetes/')||location.pathname==='/flottes') ? contextUrl : this.urlValue,{signal:this.abort.signal,headers:{"X-Genesis-Live":"1",Accept:'application/json'}});if(!r.ok || r.redirected) return;
            const html=r.headers.get('content-type')?.includes('application/json')
                ? Object.values((await r.json()).regions).join('\n') : await r.text();
            // AbortController ne suffit pas si une réponse a déjà été reçue ou décodée.
            if(generation!==this.generation || location.href!==contextUrl || !this.element.isConnected) return;
            const doc=new DOMParser().parseFromString(html,'text/html');
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
