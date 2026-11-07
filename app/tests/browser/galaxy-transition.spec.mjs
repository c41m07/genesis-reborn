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

test('Échap conserve le retour au système depuis la fiche planète', () => {
  let returnedToSystem = false;
  let prevented = false;
  const state = {
    planetBackTarget: { hidden: true }, planetPanelTarget: { hidden: false },
    toSystem() { returnedToSystem = true; },
  };
  GalaxyController.prototype.navigateBack.call(state, {
    key: 'Escape', defaultPrevented: false, target: { closest: () => null },
    preventDefault() { prevented = true; },
  });
  expect(returnedToSystem).toBe(true);
  expect(prevented).toBe(true);
});

for (const motionReduced of [false, true]) {
  for (const mode of ['system', 'planet', 'transition-to-planet']) {
    test(`La croix planète revient à la galaxie depuis ${mode}, mouvement réduit ${motionReduced}`, () => {
      const target = () => ({ hidden: false, textContent: '' });
      const state = {
        mode, motionReduced, selectedPlanet: { id: 1 }, selectedSystem: { id: 2 },
        systemPanelTarget: target(), planetPanelTarget: target(), systemBackTarget: target(), planetBackTarget: target(),
        minimapWrapTarget: { hidden: true }, fleetLogTarget: { parentElement: { hidden: true } },
        locationTarget: target(), coordinatesTarget: target(), mapHelpTarget: target(),
        gameMap: { galaxy: '1' }, searchInputTarget: { focus() {} },
        baseCamera: { x: 0, y: 0 }, baseZoom: 1, savedCamera: { camera: { x: 12, y: 24 }, zoom: 0.8 },
        setCanvasMode(value) { this.canvasMode = value; }, drawFrame() {},
        animateCamera(x, y, zoom, duration, done) { this.camera = { x, y }; this.targetZoom = zoom; done(); },
        toGalaxy: GalaxyController.prototype.toGalaxy,
      };
      GalaxyController.prototype.closePlanet.call(state);
      expect(state.mode).toBe('galaxy');
      expect(state.canvasMode).toBe('galaxy');
      expect(state.planetPanelTarget.hidden).toBe(true);
      expect(state.systemPanelTarget.hidden).toBe(true);
      expect(state.minimapWrapTarget.hidden).toBe(false);
      expect(state.selectedPlanet).toBeNull();
      expect(state.selectedSystem).toBeNull();
      expect(state.camera).toEqual({ x: 12, y: 24 });
      expect(state.targetZoom).toBe(0.8);
    });
  }
}
