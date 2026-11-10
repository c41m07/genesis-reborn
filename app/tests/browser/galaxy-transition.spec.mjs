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
    toSystem(restoreChoice) { returnedToSystem = true; expect(restoreChoice).toBe(true); },
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


test('Seule la maison apparaît au survol des systèmes possédés et le point inconnu reste centré', () => {
  const calls = [];
  const state = {
    hiddenMarkers: new Set(), systemMarkers: GalaxyController.prototype.systemMarkers,
    drawMarkers(...args) { calls.push(args); },
  };
  const point = { x: 120, y: 90 };
  const unknown = { known: false, planets: [] };
  GalaxyController.prototype.drawSystemMarkers.call(state, null, unknown, point, false);
  expect(calls[0].slice(2)).toEqual([120, 90, true]);
  state.hiddenMarkers.add('unknown');
  GalaxyController.prototype.drawSystemMarkers.call(state, null, unknown, point, true);
  expect(calls).toHaveLength(1);

  const known = { known: true, planets: ['own', 'uninhabited', 'allied', 'neutral', 'npc', 'enemy'].map(relation => ({ relation })) };
  GalaxyController.prototype.drawSystemMarkers.call(state, null, known, point, false);
  expect(calls).toHaveLength(1);
  GalaxyController.prototype.drawSystemMarkers.call(state, null, known, point, true);
  expect(calls[1][1].map(marker => marker.symbol)).toEqual(['⌂']);
  expect(calls[1].slice(2)).toEqual([120, 69, false]);
  state.hiddenMarkers.add('own');
  GalaxyController.prototype.drawSystemMarkers.call(state, null, known, point, true);
  expect(calls).toHaveLength(2);
  expect(state.systemMarkers({ known: true, planets: [{ relation: 'enemy' }] })).toEqual([]);
});

test('Les quatre quarts fixes suivent les catégories découvertes, les modes et les filtres', () => {
  const gray = '#a4acb8';
  const state = { mapMode: 'exploration', hiddenMarkers: new Set(), npcMarker: GalaxyController.prototype.npcMarker };
  const system = { known: true, planets: ['npc', 'allied', 'neutral', 'enemy'].map(relation => ({ relation, conquestLevel: 5 })) };
  const quarters = () => GalaxyController.prototype.systemQuarters.call(state, system);
  expect(quarters()).toEqual(['#ff8a00', '#00e676', gray, '#ff3344']);
  expect(GalaxyController.prototype.systemQuarters.call(state, { ...system, known: false })).toEqual([gray, gray, gray, gray]);
  state.hiddenMarkers.add('allied');
  expect(quarters()).toEqual(['#ff8a00', gray, gray, '#ff3344']);
  state.mapMode = 'players';
  expect(quarters()).toEqual([gray, gray, gray, '#ff3344']);
  state.mapMode = 'npc';
  expect(quarters()).toEqual(['#9555ff', gray, gray, gray]);
  state.hiddenMarkers.add('npc-medium');
  expect(quarters()).toEqual([gray, gray, gray, gray]);
});

test('Le disque garde quatre secteurs égaux et un anneau extérieur détaché', () => {
  const arcs = [], fills = [];
  const ctx = {
    save() {}, restore() {}, beginPath() {}, closePath() {}, moveTo() {}, lineTo() {}, stroke() {},
    arc(...args) { arcs.push(args); }, fill() { fills.push(this.fillStyle); },
  };
  const colors = ['#ff8a00', '#00e676', '#a4acb8', '#ff3344'];
  GalaxyController.prototype.drawSystemDisk.call({ systemQuarters: () => colors }, ctx, { known: true }, { x: 25, y: 40 }, 11, false);
  expect(fills).toEqual(colors);
  expect(arcs).toHaveLength(6);
  expect(arcs[5].slice(0, 3)).toEqual([25, 40, 14]);
  for (const arc of arcs.slice(0, 5)) expect(arc.slice(0, 3)).toEqual([25, 40, 11]);
  for (const arc of arcs.slice(0, 4)) expect(arc[4] - arc[3]).toBeCloseTo(Math.PI / 2);
});

test('Le résumé ne révèle aucun contenu inconnu et compte les planètes avec leurs niveaux PNJ', () => {
  const system = { known: true, planets: [
    { relation: 'own' }, { relation: 'npc', conquestLevel: 2 }, { relation: 'npc', conquestLevel: 5 },
    { relation: 'neutral' }, { relation: 'enemy' },
  ] };
  const summary = GalaxyController.prototype.systemSummary.call({}, system);
  expect(summary).toEqual([
    { type: 'own', label: 'Vos colonies', value: '1' },
    { type: 'npc', label: 'Planètes PNJ', value: '2 · niveaux 2 à 5' },
    { type: 'neutral', label: 'Planètes neutres', value: '1' },
    { type: 'enemy', label: 'Planètes ennemies', value: '1' },
  ]);
  expect(GalaxyController.prototype.systemSummary.call({}, { ...system, known: false })).toEqual([]);
});

test('Le clic utilise la position réellement dessinée, accepte le bord et ignore les planètes non dessinées', () => {
  const planet = { id: 2, visual: { sx: 0, sy: 40, screenRadius: 8 } };
  const state = { selectedSystem: { planets: [{ id: 1, visual: {} }, planet] } };
  expect(GalaxyController.prototype.nearestPlanet.call(state, { x: 0, y: 40 })).toBe(planet);
  expect(GalaxyController.prototype.nearestPlanet.call(state, { x: 20, y: 40 })).toBe(planet);
  expect(GalaxyController.prototype.nearestPlanet.call(state, { x: 50, y: 40 })).toBeNull();
});

test('Le premier clic ouvre directement la planète et un glissement ne déclenche aucune ouverture', () => {
  const opened = [];
  const state = {
    mode: 'system', drag: { pointerId: 1, moved: false },
    canvasPoint: () => ({ x: 20, y: 30 }), nearestPlanet: () => ({ id: 7 }),
    selectPlanetById(id) { opened.push(id); }, enterPlanetView() { opened.push('planet'); },
  };
  GalaxyController.prototype.pointerUp.call(state, { pointerId: 1 });
  expect(opened).toEqual([7, 'planet']);
  state.drag = { pointerId: 1, moved: true };
  GalaxyController.prototype.pointerUp.call(state, { pointerId: 1 });
  expect(opened).toEqual([7, 'planet']);
});

for (const width of [320, 390, 720]) {
  test('Neuf planètes restent espacées et dans la zone visible à ' + width + ' px', () => {
    const planets = Array.from({ length: 9 }, (_, index) => ({ id: index + 1, position: index + 1, visual: { angle: 0 } }));
    const bounds = { left: 24, right: width - 24, top: 150, bottom: 460 };
    const layout = GalaxyController.prototype.buildSolarLayout.call({}, planets, bounds);
    expect(layout.positions).toHaveLength(9);
    for (const [index, point] of layout.positions.entries()) {
      expect(point.x).toBeGreaterThanOrEqual(bounds.left + 18);
      expect(point.x).toBeLessThanOrEqual(bounds.right - 18);
      expect(point.y).toBeGreaterThanOrEqual(bounds.top + 18);
      expect(point.y).toBeLessThanOrEqual(bounds.bottom - 18);
      for (const other of layout.positions.slice(index + 1)) expect(Math.hypot(point.x - other.x, point.y - other.y)).toBeGreaterThan(32);
    }
  });
}

test('Les planètes avancent sur leurs orbites à leur vitesse et respectent la réduction des animations', () => {
  const planets = [1, 2].map(position => ({ position, visual: { angle: 0, speed: .055 / Math.sqrt(position) } }));
  const layout = GalaxyController.prototype.buildSolarLayout.call({}, planets, { left: 24, right: 366, top: 150, bottom: 460 });
  const before = layout.positions.map(point => ({ angle: point.angle, x: point.x, y: point.y }));
  GalaxyController.prototype.advanceSolarLayout.call({ motionReduced: false }, layout, 1);
  for (const [index, point] of layout.positions.entries()) {
    expect(point.angle - before[index].angle).toBeCloseTo(planets[index].visual.speed);
    expect(Math.hypot(point.x - before[index].x, point.y - before[index].y)).toBeGreaterThan(0);
    const ellipse = ((point.x - layout.centerX) / point.radius) ** 2 + ((point.y - layout.centerY) / (point.radius * point.aspect)) ** 2;
    expect(ellipse).toBeCloseTo(1);
  }
  const frozen = layout.positions.map(point => ({ x: point.x, y: point.y }));
  GalaxyController.prototype.advanceSolarLayout.call({ motionReduced: true }, layout, 1);
  expect(layout.positions.map(point => ({ x: point.x, y: point.y }))).toEqual(frozen);
  GalaxyController.prototype.advanceSolarLayout.call({ motionReduced: false }, layout, 0);
  expect(layout.positions.map(point => ({ x: point.x, y: point.y }))).toEqual(frozen);
});

for (const [width, height] of [[320, 548], [390, 548], [1052, 454]]) {
  test('Le cadrage rend les dix systèmes d’un même bras lisibles à ' + width + ' px sans changer leurs coordonnées', () => {
    const state = {
      width, height, galaxyExtentX: 1470, galaxyExtentY: 1470,
      galaxyArmPoint: GalaxyController.prototype.galaxyArmPoint,
    };
    state.systems = Array.from({ length: 10 }, (_, index) => ({
      x: index * 50, y: -index * 20, known: true, planets: index === 0 ? [{ ownedByCurrentPlayer: true }] : [],
      ...GalaxyController.prototype.galaxySystemPosition.call(state, Math.floor(index / 10), index % 10),
    }));
    GalaxyController.prototype.fitGalaxy.call(state);
    const points = state.systems.map(system => ({ x: width / 2 + (system.wx - state.camera.x) * state.zoom, y: height / 2 + (system.wy - state.camera.y) * state.zoom }));
    for (const [index, point] of points.entries()) {
      expect(point.x).toBeGreaterThan(12);
      expect(point.x).toBeLessThan(width - 12);
      expect(point.y).toBeGreaterThan(width <= 720 ? 134 : 76);
      expect(point.y).toBeLessThan(height - 64);
      expect(state.systems[index].x).toBe(index * 50);
      expect(state.systems[index].y).toBe(-index * 20);
      for (const other of points.slice(index + 1)) expect(Math.hypot(point.x - other.x, point.y - other.y)).toBeGreaterThanOrEqual(31.99);
    }
    const camera = { x: 17, y: 29 };
    state.hasUserCamera = true; state.camera = camera; state.zoom = 1.3; state.targetZoom = 1.4;
    GalaxyController.prototype.fitGalaxy.call(state);
    expect(state.camera).toBe(camera);
    expect(state.zoom).toBe(1.3);
    expect(state.targetZoom).toBe(1.4);
  });
}

test('Tous les systèmes restent sur le tracé réellement dessiné des huit bras', () => {
  const paths = [];
  let path;
  const ctx = {
    save() {}, restore() {}, beginPath() { path = []; },
    moveTo(x, y) { path.push({ x, y }); }, lineTo(x, y) { path.push({ x, y }); },
    stroke() { if (!paths.includes(path)) paths.push(path); },
  };
  const state = {
    galaxyExtentX: 1470, galaxyExtentY: 1470, zoom: 1,
    galaxyArmPoint: GalaxyController.prototype.galaxyArmPoint,
    galaxyPoint: point => ({ x: point.wx, y: point.wy }),
  };
  GalaxyController.prototype.drawSpiralArms.call(state, ctx);
  expect(paths).toHaveLength(8);
  paths.forEach((points, branch) => {
    for (let slot = 0; slot < 10; slot += 1) {
      const system = GalaxyController.prototype.galaxySystemPosition.call(state, branch, slot);
      let distance = Infinity;
      for (let index = 1; index < points.length; index += 1) {
        const a = points[index - 1], b = points[index];
        const dx = b.x - a.x, dy = b.y - a.y;
        const t = Math.max(0, Math.min(1, ((system.wx - a.x) * dx + (system.wy - a.y) * dy) / (dx * dx + dy * dy)));
        distance = Math.min(distance, Math.hypot(system.wx - (a.x + t * dx), system.wy - (a.y + t * dy)));
      }
      expect(distance).toBeLessThan(1);
    }
  });
});

test('Les dizaines consécutives sont projetées sur leur propre bras', () => {
  const state = {
    systems: Array.from({ length: 80 }, (_, index) => ({ id: index + 1, number: index + 1, x: index * 10, y: -index, planets: [] })),
    flights: [],
    seededRandom: GalaxyController.prototype.seededRandom,
    galaxyArmPoint: GalaxyController.prototype.galaxyArmPoint,
    galaxySystemPosition: GalaxyController.prototype.galaxySystemPosition,
  };
  GalaxyController.prototype.projectSystems.call(state);
  for (const number of [1, 10, 11, 20, 21, 30, 71, 80]) {
    const system = state.systems[number - 1];
    const expected = state.galaxyArmPoint(Math.floor((number - 1) / 10), .26 + ((number - 1) % 10) / 9 * .74);
    expect(system.wx).toBeCloseTo(expected.wx);
    expect(system.wy).toBeCloseTo(expected.wy);
    expect(system.x).toBe((number - 1) * 10);
    expect(system.y).toBe(-(number - 1));
  }
});
