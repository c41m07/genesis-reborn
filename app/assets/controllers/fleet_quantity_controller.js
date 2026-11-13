import { Controller } from '@hotwired/stimulus';
import { exactNumber } from '../quantity.js';

export default class extends Controller {
    static targets = ['ship', 'cargo', 'cargoMax', 'status'];
    static values = { limit: Number };

    connect() { this.updateShipBounds(); }
    field(event) { return this.element.querySelector('#' + CSS.escape(event.currentTarget.dataset.inputId)); }
    shipMaximum(input) {
        const others = this.shipTargets.reduce((sum, field) => sum + (field === input ? 0 : Math.max(0, Number(field.value) || 0)), 0);
        return Math.max(0, Math.min(Number(input.dataset.available), this.limitValue - others));
    }
    cargoMaximum(input) {
        if (this.capacity === undefined) return 0;
        const others = this.cargoTargets.reduce((sum, field) => sum + (field === input ? 0 : Math.max(0, Number(field.value) || 0)), 0);
        return Math.max(0, Math.floor(Math.min(this.available[input.dataset.resource] || 0, this.capacity - others)));
    }
    updateShipBounds() {
        for (const input of this.shipTargets) input.max = String(this.shipMaximum(input));
    }
    clampShip(event) {
        this.clamp(event.target, this.shipMaximum(event.target));
        this.updateShipBounds();
    }
    clampCargo(event) {
        if (this.capacity !== undefined) this.clamp(event.target, this.cargoMaximum(event.target));
    }
    clamp(input, maximum) {
        const value = Number(input.value);
        if (Number.isInteger(value) && value > maximum) {
            input.value = String(maximum);
            this.statusTarget.textContent = 'Quantité ajustée au maximum disponible : ' + exactNumber(maximum) + '.';
        }
    }
    maximumShip(event) {
        const input = this.field(event);
        input.value = String(this.shipMaximum(input));
        input.dispatchEvent(new Event('input', { bubbles: true }));
    }
    maximumCargo(event) {
        if (this.capacity === undefined) return;
        const input = this.field(event);
        input.value = String(this.cargoMaximum(input));
        input.dispatchEvent(new Event('input', { bubbles: true }));
    }
    invalidate(event) {
        if (this.cargoTargets.includes(event.target)) return;
        this.capacity = undefined;
        for (const button of this.cargoMaxTargets) button.disabled = true;
    }
    quote({ detail }) {
        if (this.element.querySelector('[name="require_exact_composition"][value="1"]') && detail.ship_adjusted) {
            this.statusTarget.textContent = 'Composition devenue indisponible : vérifiez les quantités avant envoi.';
            return;
        }
        this.capacity = detail.freight_capacity;
        this.available = detail.cargo_available;
        for (const input of this.shipTargets) {
            const match = input.name.match(/^ships\[([^\]]+)\](?:\[([^\]]+)\])?$/);
            if (match) {
                input.value = String(detail.composition[match[1]]?.[match[2] || 'basic'] || 0);
                input.dataset.available = String(detail.ship_available[match[1]]?.[match[2] || 'basic'] || 0);
            }
        }
        this.updateShipBounds();
        for (const input of this.cargoTargets) {
            input.value = String(detail.freight[input.dataset.resource] || 0);
        }
        for (const input of this.cargoTargets) input.max = String(this.cargoMaximum(input));
        for (const button of this.cargoMaxTargets) button.disabled = false;
        if (detail.adjusted) this.statusTarget.textContent = 'Quantités ajustées aux stocks et à la capacité disponibles.';
    }
}
