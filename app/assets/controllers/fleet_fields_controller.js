import {Controller} from '@hotwired/stimulus';
export default class extends Controller {
    static targets=['coordinate','coordinateHelp','cargo','orbit'];
    connect(){this.update();}
    dirty(){this.element.dataset.dirty='true';}
    update(event){
        if(event)this.dirty();
        const mission=this.element.elements.mission.value;
        const exploring=mission==='explore';
        const input=this.coordinateTarget.querySelector('input[name="coordinates"]');
        this.coordinateTarget.querySelector('label').textContent=exploring?'Système cible (GALAXIE:SYSTÈME)':'Planète cible (GALAXIE:SYSTÈME:PLANÈTE)';
        input.placeholder=exploring?'A:12':'A:12:3';
        this.coordinateHelpTarget.textContent=exploring?'Exemple : A:12':'Exemple : A:12:3';
        for(const [target,visible] of [[this.cargoTarget,mission==='transport'],[this.orbitTarget,mission==='orbit']]) {
            target.hidden=!visible;
            target.querySelectorAll('input,select').forEach(field=>{field.disabled=!visible});
        }
    }
}
