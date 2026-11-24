import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static values = { end: String, server: String, return: String };

    connect() {
        // Partir de l’heure du serveur même si l’horloge du joueur est décalée.
        this.deadline ??= Date.now() + Date.parse(this.endValue) - Date.parse(this.serverValue);
        this.refresh();
    }

    refresh() {
        clearTimeout(this.timer);
        const remaining = this.deadline - Date.now();
        if (remaining > 0) {
            // setTimeout utilise un entier signé sur 32 bits.
            this.timer = setTimeout(() => this.refresh(), Math.min(remaining, 2147483647));
        } else {
            this.element.hidden = true;
            if (this.hasReturnValue) window.location.replace(this.returnValue);
        }
    }

    disconnect() {
        clearTimeout(this.timer);
    }
}
