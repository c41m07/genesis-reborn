import { Controller } from '@hotwired/stimulus';
export default class extends Controller {
    static targets=['result']; static values={url:String};
    disconnect(){ clearTimeout(this.timer); this.abort?.abort(); }
    schedule(){ clearTimeout(this.timer);this.timer=setTimeout(()=>this.estimate(),300); }
    async estimate(){
        this.abort?.abort();this.abort=new AbortController();
        try {
            const response=await fetch(this.urlValue,{method:'POST',body:new FormData(this.element),signal:this.abort.signal,headers:{Accept:'application/json'}});
            if(!response.headers.get('content-type')?.includes('application/json')) throw new Error('Session expirée. Reconnectez-vous.');
            const q=await response.json();if(!response.ok) throw new Error(q.error||'Estimation indisponible.');
            const n=x=>Math.ceil(x).toLocaleString('fr-FR');
            this.resultTarget.textContent=`${n(q.distance)} UA · ${Math.floor(q.seconds/3600)} h ${Math.floor(q.seconds%3600/60)} min ${q.seconds%60} s · Carburant aller-retour : ${n(q.total_fuel)} H · Fret disponible : ${n(q.freight_capacity)}`;
            this.resultTarget.classList.remove('error');
        }catch(e){ if(e.name!=='AbortError'){this.resultTarget.textContent=e.message;this.resultTarget.classList.add('error');} }
    }
}
