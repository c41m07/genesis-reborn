import { Controller } from '@hotwired/stimulus';
export default class extends Controller {
    static targets = ['bar', 'fill', 'remaining', 'total'];
    static values = {start:String, end:String, total:String};
    connect() { this.tick();this.timer=setInterval(()=>this.tick(),1000); }
    disconnect() { clearInterval(this.timer); }
    label(seconds) {
        if(seconds<=0)return 'Finalisation…';
        const h=Math.floor(seconds/3600),m=Math.floor(seconds%3600/60),s=seconds%60;
        return `${h?h+' h ':''}${m?m+' min ':''}${s} s`;
    }
    tick() {
        const now=Date.now(),start=Date.parse(this.startValue),end=Date.parse(this.endValue);
        if(!Number.isFinite(start)||!Number.isFinite(end))return;
        const percent=Math.max(0,Math.min(100,(now-start)/Math.max(1,end-start)*100));
        this.fillTarget.style.width=`${percent}%`;this.barTarget.setAttribute('aria-valuenow',String(Math.round(percent)));
        this.remainingTarget.textContent=this.label(Math.max(0,Math.ceil((end-now)/1000)));
        if(this.hasTotalTarget)this.totalTarget.textContent=this.label(Math.max(0,Math.ceil((Date.parse(this.totalValue)-now)/1000)));
        if(now>=end&&(!this.lastRefresh||now-this.lastRefresh>=5000)) {
            this.lastRefresh=now;window.dispatchEvent(new CustomEvent('genesis:refresh'));
        }
    }
}
