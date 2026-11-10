import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static values = { url: String, token: String, membership: Number };
    connect() {
        this.lastInteraction = Date.now();
        this.interact = () => { if (!document.hidden) this.lastInteraction = Date.now(); };
        this.events = ['pointerdown', 'keydown', 'touchstart', 'scroll'];
        for (const event of this.events) window.addEventListener(event, this.interact, {passive:true});
        this.timer = window.setInterval(() => this.sample(), 60000);
    }
    disconnect() {
        window.clearInterval(this.timer);
        for (const event of this.events) window.removeEventListener(event, this.interact);
        this.abort?.abort();
    }
    async sample() {
        const idle = Math.floor((Date.now() - this.lastInteraction) / 1000);
        if (document.hidden || idle >= 300 || this.loading || this.stopped) return;
        this.loading = true;
        this.abort = new AbortController();
        try {
            const response = await fetch(this.urlValue, {method:'POST', signal:this.abort.signal, headers:{'X-Requested-With':'XMLHttpRequest'}, body:new URLSearchParams({_token:this.tokenValue, membership:String(this.membershipValue), idle_seconds:String(idle)})});
            if ([401,403,409].includes(response.status)) this.stopped = true;
        } catch { /* Le suivi reprend au prochain échantillon. */ }
        finally { this.loading = false; }
    }
}
