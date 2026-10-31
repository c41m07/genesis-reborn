import { Controller } from '@hotwired/stimulus';
export default class extends Controller {
    static targets = ['time'];
    static values = { end: String };
    connect() { this.tick(); this.timer=setInterval(()=>this.tick(),1000); }
    disconnect() { clearInterval(this.timer); }
    tick() { const seconds=Math.max(0,Math.ceil((Date.parse(this.endValue)-Date.now())/1000)); this.timeTarget.textContent=seconds ? `${Math.floor(seconds/3600)}h ${Math.floor(seconds%3600/60)}m ${seconds%60}s` : 'Finalisation…'; if(!seconds&&!this.requested){ this.requested=true;window.dispatchEvent(new CustomEvent('genesis:refresh')); } }
}
