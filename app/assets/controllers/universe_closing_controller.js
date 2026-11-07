import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
  static targets = ['label', 'time', 'date', 'error'];
  static values = { url: String, state: Object };

  connect() {
    this.active = true;
    this.apply(this.stateValue);
    this.onVisible = () => { if (!document.hidden) this.sync(); };
    document.addEventListener('visibilitychange', this.onVisible);
    this.timer = setInterval(() => this.tick(), 1000);
    this.poll = setInterval(() => this.sync(), 60000);
    this.sync();
  }

  disconnect() {
    this.active = false;
    clearInterval(this.timer);
    clearInterval(this.poll);
    document.removeEventListener('visibilitychange', this.onVisible);
    this.request?.abort();
    this.request = null;
  }

  apply(state) {
    const serverNow = Date.parse(state.serverNow);
    const endsAt = state.endsAt === null ? null : Date.parse(state.endsAt);
    if (!Number.isFinite(serverNow) || (endsAt !== null && !Number.isFinite(endsAt))) throw new Error('Date serveur invalide');
    this.state = state;
    this.serverNow = serverNow;
    this.endsAt = endsAt;
    this.observedAt = performance.now();
    this.dateTarget.textContent = endsAt === null ? '' : `Échéance : ${new Intl.DateTimeFormat('fr-FR', { timeZone: 'UTC', dateStyle: 'short', timeStyle: 'medium' }).format(new Date(endsAt))} UTC.`;
    this.tick();
  }

  tick() {
    const remaining = this.endsAt === null ? null : this.endsAt - (this.serverNow + performance.now() - this.observedAt);
    const visible = this.state.status === 'active' && remaining !== null && remaining <= 86400000;
    this.element.hidden = !visible;
    if (!visible) return;
    const seconds = Math.max(0, Math.ceil(remaining / 1000));
    this.labelTarget.textContent = seconds === 0 ? 'Échéance de fermeture atteinte' : 'Fermeture dans';
    this.timeTarget.textContent = seconds === 0 ? '' : `${Math.floor(seconds / 3600)} h ${Math.floor(seconds % 3600 / 60)} min ${seconds % 60} s`;
  }

  async sync() {
    if (this.request || !this.active) return;
    const request = new AbortController();
    this.request = request;
    try {
      const response = await fetch(this.urlValue, { signal: request.signal, cache: 'no-store', headers: { Accept: 'application/json' } });
      if (!response.ok) throw new Error('Actualisation indisponible');
      const state = await response.json();
      if (this.active && this.request === request) { this.apply(state); this.errorTarget.hidden = true; }
    } catch (error) {
      if (this.active && this.request === request && error.name !== 'AbortError') this.errorTarget.hidden = false;
    } finally {
      if (this.request === request) this.request = null;
    }
  }
}
