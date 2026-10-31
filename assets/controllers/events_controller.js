import { Controller } from '@hotwired/stimulus';
export default class extends Controller {
    static targets=['message']; static values={url:String};
    connect() { this.active=true; this.seen=new Set(); this.open(); this.renew=setInterval(()=>this.open(),600000); }
    disconnect() { this.active=false; this.source?.close(); clearInterval(this.renew); }
    async open() { try { const r=await fetch(this.urlValue,{headers:{Accept:'application/json'}}); if(!r.ok || !r.headers.get('content-type')?.includes('application/json')) return; const data=await r.json(); if(!this.active) return; this.source?.close(); this.source=new EventSource(data.url,{withCredentials:true}); this.source.onmessage=event=>{ const data=JSON.parse(event.data); if(this.seen.has(data.id)) return; this.seen.add(data.id); this.messageTarget.textContent=data.message; this.element.hidden=false; window.dispatchEvent(new CustomEvent('genesis:refresh')); }; } catch {} }
    refresh() { window.location.reload(); }
}
