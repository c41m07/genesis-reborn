import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['input', 'preview', 'image', 'filename', 'remove', 'status', 'dialog', 'enlarged'];
    static values = { maxSize: Number };

    connect() {
        // Conserver la génération : un décodage antérieur peut finir après une reconnexion.
        this.version = this.version ?? 0;
        this.update();
    }

    disconnect() {
        this.resetPreview();
        this.removeTarget.hidden = true;
    }

    update() {
        this.resetPreview();
        const file = this.inputTarget.files[0];
        this.removeTarget.hidden = !file;
        if (!file) return;

        // Limiter le décodage local ; l’acceptation du fichier reste contrôlée par le serveur.
        const acceptedTypes = this.inputTarget.accept.split(',').map(type => type.trim());
        if (!acceptedTypes.includes(file.type) || file.size > this.maxSizeValue) {
            this.statusTarget.textContent = 'Aperçu indisponible. La capture sera vérifiée à l’envoi.';
            return;
        }

        const version = this.version;
        this.previewUrl = URL.createObjectURL(file);
        this.imageTarget.src = this.previewUrl;
        this.imageTarget.decode().then(() => {
            // Une sélection plus récente ou une déconnexion rend le décodage précédent obsolète.
            if (version !== this.version || !this.element.isConnected) return;
            this.filenameTarget.textContent = file.name;
            this.enlargedTarget.src = this.previewUrl;
            this.previewTarget.hidden = false;
        }).catch(() => {
            if (version !== this.version || !this.element.isConnected) return;
            this.resetPreview();
            this.statusTarget.textContent = 'Impossible d’afficher cet aperçu. La capture sera vérifiée à l’envoi.';
        });
    }

    clear() {
        this.inputTarget.value = '';
        this.update();
        this.inputTarget.focus();
    }

    open() {
        if (!this.previewTarget.hidden && !this.dialogTarget.open) this.dialogTarget.showModal();
    }

    close() {
        this.dialogTarget.close();
    }

    resetPreview() {
        this.version += 1;
        if (this.dialogTarget.open) this.dialogTarget.close();
        this.previewTarget.hidden = true;
        this.statusTarget.textContent = '';
        this.imageTarget.removeAttribute('src');
        this.enlargedTarget.removeAttribute('src');
        this.filenameTarget.textContent = '';
        if (this.previewUrl) URL.revokeObjectURL(this.previewUrl);
        this.previewUrl = null;
    }
}
