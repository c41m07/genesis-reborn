import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['field', 'row', 'status'];

    connect() {
        this.fieldTarget.hidden = false;
    }

    update(event) {
        const normalize = text => text.normalize('NFD').replace(/\p{Diacritic}/gu, '').trim().toLowerCase();
        const query = normalize(event.target.value);
        let count = 0;
        // Filtrer uniquement les libellés rendus par le serveur. Les sélecteurs
        // natifs et les vérifications d’accès ne dépendent pas de JavaScript.
        for (const row of this.rowTargets) {
            const visible = !query || normalize(row.dataset.systemSearch).includes(query);
            row.hidden = !visible;
            if (visible) ++count;
        }
        this.statusTarget.hidden = !query;
        this.statusTarget.textContent = count === 0 ? 'Aucun système trouvé.' : `${count} ${count === 1 ? 'système trouvé' : 'systèmes trouvés'}.`;
    }
}
