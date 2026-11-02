import {Controller} from '@hotwired/stimulus';
export default class extends Controller {
 static targets=['time']; static values={timezone:String};
 connect(){this.tick();this.timer=setInterval(()=>this.tick(),1000)}
 disconnect(){clearInterval(this.timer)}
 tick(){try{this.timeTarget.textContent=new Intl.DateTimeFormat('fr-FR',{timeZone:this.timezoneValue,hour:'2-digit',minute:'2-digit',second:'2-digit'}).format(new Date())}catch{this.timeTarget.textContent=new Date().toLocaleTimeString('fr-FR')}}
}
