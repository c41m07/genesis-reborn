import { test, expect } from '@playwright/test';
import { readFileSync } from 'node:fs';

// Charger le contrôleur réel en isolant uniquement sa classe de base Stimulus.
const controllerSource = readFileSync('app/assets/controllers/galaxy_v2_controller.js', 'utf8')
  .replace("import { Controller } from '@hotwired/stimulus';", 'class Controller {}');
const { default: GalaxyController } = await import('data:text/javascript;base64,' + Buffer.from(controllerSource).toString('base64'));

// Simulation de l'horloge : une animation ancienne ne doit pas annuler un retour plus récent.
test('Les fins d’animation de la carte respectent une navigation plus récente', () => {
  const previousWindow = globalThis.window;
  const callbacks = [];
  globalThis.window = { setTimeout: callback => callbacks.push(callback) };
  const target = () => ({ hidden: false, textContent: '' });
  const state = {
    motionReduced: false, selectedPlanet: { id: 1, name: 'Planète', coordinates: '1:1:1' },
    selectedSystem: { name: 'Système', galaxy: '1', number: 1 },
    systemPanelTarget: target(), planetPanelTarget: target(), systemBackTarget: target(), planetBackTarget: target(),
    minimapWrapTarget: target(), fleetLogTarget: { parentElement: target() }, locationTarget: target(),
    coordinatesTarget: target(), mapHelpTarget: target(), systemContentTarget: { querySelector: () => null },
    setCanvasMode() {}, drawFrame() {}, focusPanel() {}, animateCamera() {},
    gameMap: { galaxy: '1' }, searchInputTarget: { focus() {} }, baseCamera: { x: 0, y: 0 }, baseZoom: 1,
  };
  try {
    GalaxyController.prototype.enterPlanetView.call(state);
    GalaxyController.prototype.toSystem.call(state);
    callbacks.shift()();
    expect(state.mode).toBe('transition-to-system-from-planet');
    GalaxyController.prototype.toGalaxy.call(state);
    expect(state.systemPanelTarget.hidden).toBe(true);
    callbacks.shift()();
    expect(state.mode).toBe('transition-to-galaxy');
    expect(state.systemPanelTarget.hidden).toBe(true);
    GalaxyController.prototype.enterPlanetView.call(state);
    callbacks.shift()();
    expect(state.mode).toBe('planet');
    GalaxyController.prototype.toSystem.call(state);
    callbacks.shift()();
    expect(state.mode).toBe('system');
  } finally {
    if (previousWindow === undefined) delete globalThis.window;
    else globalThis.window = previousWindow;
  }
});
