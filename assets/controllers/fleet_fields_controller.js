import {Controller} from '@hotwired/stimulus';
export default class extends Controller {
    static targets=['system','planet','cargo'];
    connect(){this.update();}
    dirty(){this.element.dataset.dirty='true';}
    update(event){
        if(event)this.dirty();
        const mission=this.element.elements.mission.value;
        for(const [target,visible] of [[this.systemTarget,mission==='explore'],[this.planetTarget,mission!=='explore'],[this.cargoTarget,mission==='transport']]) {
            target.hidden=!visible;target.querySelectorAll('input,select').forEach(input=>{input.disabled=!visible});
        }
    }
}
