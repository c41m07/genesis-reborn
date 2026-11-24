import { test, expect } from '@playwright/test';
import { readFileSync } from 'node:fs';

// Charger le contrôleur réel en isolant uniquement sa classe de base Stimulus.
const controllerSource = readFileSync('app/assets/controllers/galaxy_v2_controller.js', 'utf8')
  .replace("import { Controller } from '@hotwired/stimulus';", 'class Controller {}');
const { default: GalaxyController } = await import('data:text/javascript;base64,' + Buffer.from(controllerSource).toString('base64'));

// Le vrai retour Galaxie synchronise le lien natif, même dans ces tests sans Stimulus ni DOM.
function listNavigationFixture() {
  const listLink = {
    href: 'https://genesis.example.test/carte?galaxy=1&system=2',
    setAttribute(name, value) {
      expect(name).toBe('href');
      this.href = new URL(value, this.href).href;
    },
  };
  return {
    listLink,
    controllerTargets: {
      element: {
        parentElement: { querySelector: selector => selector === '[data-galaxy-list-link]' ? listLink : null },
      },
      syncListLink: GalaxyController.prototype.syncListLink,
    },
  };
}

// Coordonnées exportées de GalaxyGeometry (spiral-v1), indépendantes du dessin JavaScript.
const referenceSystems = JSON.parse(readFileSync('app/tests/Fixtures/galaxy-spiral-reference.json', 'utf8'));

// Simulation de l'horloge : une animation ancienne ne doit pas annuler un retour plus récent.
test('Les fins d’animation de la carte respectent une navigation plus récente', () => {
  const previousWindow = globalThis.window;
  const callbacks = [];
  globalThis.window = { setTimeout: callback => callbacks.push(callback) };
  const target = () => ({ hidden: false, textContent: '' });
  const { listLink, controllerTargets } = listNavigationFixture();
  const state = {
    ...controllerTargets,
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
    expect(listLink.href).toBe('https://genesis.example.test/carte?galaxy=1');
    state.mode = 'system';
    state.selectedSystem = { name: 'Autre système', galaxy: '1', number: 2 };
    state.selectedPlanet = { id: 2, name: 'Autre planète', coordinates: '1:2:1' };
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
      const { listLink, controllerTargets } = listNavigationFixture();
      const state = {
        ...controllerTargets,
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
      expect(listLink.href).toBe('https://genesis.example.test/carte?galaxy=1');
      expect(state.camera).toEqual({ x: 12, y: 24 });
      expect(state.targetZoom).toBe(0.8);
    });
  }
}


test('Le système possédé garde son anneau vert et son libellé, sans maison ; le point inconnu reste centré', () => {
  const text = [];
  const strokes = [];
  const ctx = {
    clearRect() {}, save() {}, restore() {}, translate() {}, scale() {}, setLineDash() {},
    fillRect() {}, beginPath() {}, moveTo() {}, lineTo() {}, arc() {}, closePath() {}, fill() {},
    createRadialGradient: () => ({ addColorStop() {} }),
    measureText: () => ({ width: 10 }),
    strokeText() {},
    fillText(value, x, y) { text.push({ value, x, y, color: this.fillStyle }); },
    stroke() { strokes.push(this.strokeStyle); },
  };
  const known = {
    known: true, galaxy: 1, number: 8, starRadius: 1, wx: 120, wy: 90,
    planets: ['own', 'uninhabited', 'allied', 'neutral', 'npc', 'enemy'].map(relation => ({
      relation, ownedByCurrentPlayer: relation === 'own',
    })),
  };
  const unknown = { known: false, planets: [], starRadius: 1, wx: 185, wy: 110 };
  const state = Object.assign(Object.create(GalaxyController.prototype), {
    galaxyCanvasTarget: { getContext: () => ctx }, width: 330, height: 210, zoom: 1, galaxyExtentX: 200, galaxyExtentY: 100,
    layout: 'grid', backgroundStars: [], nebulae: [], dust: [],
    systems: [known, unknown], hiddenMarkers: new Set(), mapMode: 'exploration',
    galaxyPoint: ({ wx, wy }) => ({ x: wx, y: wy }),
    drawBackdrop() {}, drawGalaxyCore() {}, drawFlights() {}, drawSelection() {},
  });
  const draw = () => {
    text.length = 0;
    strokes.length = 0;
    state.drawGalaxy(0);
    expect(strokes).toContain('#00e676');
  };
  const unknownMarker = { value: '?', x: 185, y: 110, color: '#ffda66' };
  const ownedLabel = { value: '1:8', x: 120, y: 112, color: '#e4fff4' };

  draw();
  expect(text).toEqual([unknownMarker]);

  state.focusedSystem = known;
  draw();
  expect(text).toEqual([ownedLabel, unknownMarker]);

  state.focusedSystem = null;
  state.hoveredSystem = known;
  draw();
  expect(text).toEqual([ownedLabel, unknownMarker]);

  state.hiddenMarkers.add('unknown');
  draw();
  expect(text).toEqual([ownedLabel]);
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
  GalaxyController.prototype.drawSystemDisk.call({ systemQuarters: () => colors }, ctx, { known: true, planets: [] }, { x: 25, y: 40 }, 11, false);
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
    mode: 'system', drag: { pointerId: 1, moved: false }, pointers: new Map([[1, { x: 20, y: 30 }]]),
    canvasPoint: () => ({ x: 20, y: 30 }), nearestPlanet: () => ({ id: 7 }),
    selectPlanetById(id) { opened.push(id); }, enterPlanetView() { opened.push('planet'); },
  };
  GalaxyController.prototype.pointerUp.call(state, { pointerId: 1 });
  expect(opened).toEqual([7, 'planet']);
  expect(state.pointers.size).toBe(0);
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
  test('Le cadrage conserve la géographie réelle et le zoom permet de sélectionner chaque système à ' + width + ' px', () => {
    const state = {
      width, height, galaxyExtentX: 1500, galaxyExtentY: 1500, mode: 'galaxy', layout: 'spiral',
      projectLayout: GalaxyController.prototype.projectLayout,
      galaxyPoint: GalaxyController.prototype.galaxyPoint,
      drawFrame() {}, startLoop() {},
      systems: structuredClone(referenceSystems.slice(0, 10)).map(system => ({ ...system, known: true, planets: [] })),
    };
    state.projectLayout();
    GalaxyController.prototype.fitGalaxy.call(state);
    for (const [index, system] of state.systems.entries()) {
      const point = state.galaxyPoint(system);
      expect(point.x).toBeGreaterThan(28);
      expect(point.x).toBeLessThan(width - 28);
      expect(point.y).toBeGreaterThan(32);
      expect(point.y).toBeLessThan(height - 44);
      expect(system.x).toBe(referenceSystems[index].x);
      expect(system.y).toBe(referenceSystems[index].y);
      expect(system.physical).toEqual(referenceSystems[index].physical);
    }
    // Le zoom rapproche les systèmes sans déplacer le point visé ni altérer les distances.
    const anchor = state.galaxyPoint(state.systems[5]);
    const before = Math.hypot(state.systems[1].wx - state.systems[0].wx, state.systems[1].wy - state.systems[0].wy) * state.zoom;
    GalaxyController.prototype.zoomAt.call(state, 4, anchor);
    expect(state.galaxyPoint(state.systems[5]).x).toBeCloseTo(anchor.x, 8);
    expect(state.galaxyPoint(state.systems[5]).y).toBeCloseTo(anchor.y, 8);
    expect(Math.hypot(state.systems[1].wx - state.systems[0].wx, state.systems[1].wy - state.systems[0].wy) * state.zoom).toBeCloseTo(before * 4);
    for (const system of state.systems) {
      expect(GalaxyController.prototype.nearestSystem.call(state, state.galaxyPoint(system))).toBe(system);
    }
    const camera = state.camera, zoom = state.zoom;
    GalaxyController.prototype.fitGalaxy.call(state);
    expect(state.camera).toBe(camera);
    expect(state.zoom).toBe(zoom);
    expect(state.targetZoom).toBe(zoom);
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
    galaxyExtentX: 1500, galaxyExtentY: 1500, zoom: 1,
    galaxyArmPoint: GalaxyController.prototype.galaxyArmPoint,
    galaxyPoint: point => ({ x: point.wx, y: point.wy }),
  };
  GalaxyController.prototype.drawSpiralArms.call(state, ctx);
  expect(paths).toHaveLength(8);
  paths.forEach((points, branch) => {
    for (let slot = 0; slot < 10; slot += 1) {
      const physical = referenceSystems[branch * 10 + slot].physical;
      const system = { wx: physical.x * 100, wy: physical.y * 100 };
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

test('Les deux vues utilisent les coordonnées du serveur sans modifier la géographie ni les identifiants', () => {
  const state = {
    systems: structuredClone(referenceSystems).map(system => ({ ...system, planets: [] })),
    flights: [], gameMap: { geometry: { extent: 15 } }, layout: 'spiral',
    seededRandom: GalaxyController.prototype.seededRandom,
    projectLayout: GalaxyController.prototype.projectLayout,
  };
  GalaxyController.prototype.projectSystems.call(state);
  expect(state.decorativeSystems).toEqual([]);
  expect(state.galaxyExtentX).toBe(1500);
  for (const layout of ['spiral', 'grid', 'spiral']) {
    state.layout = layout;
    state.projectLayout();
    for (const [index, system] of state.systems.entries()) {
      const reference = referenceSystems[index];
      expect(system.wx).toBeCloseTo((layout === 'spiral' ? reference.physical.x : reference.x - 13.5) * 100);
      expect(system.wy).toBeCloseTo((layout === 'spiral' ? reference.physical.y : reference.y - 10.5) * 100);
      expect(system.x).toBe(reference.x);
      expect(system.y).toBe(reference.y);
      expect(system.physical).toEqual(reference.physical);
    }
  }
});


test('Le cercle extérieur des systèmes possédés reste vert sans modifier leurs quarts', () => {
  for (const owned of [false, true]) for (const hovered of [false, true]) {
    const fills = [], strokes = [];
    const ctx = {
      save() {}, restore() {}, beginPath() {}, moveTo() {}, lineTo() {}, arc() {}, closePath() {},
      fill() { fills.push(this.fillStyle); }, stroke() { strokes.push(this.strokeStyle); },
    };
    const colors = ['#ff8a00', '#00e676', '#b4c2d2', '#ff3344'];
    const state = { systemQuarters: () => colors };
    GalaxyController.prototype.drawSystemDisk.call(state, ctx, { known: true, planets: [{ ownedByCurrentPlayer: owned }] }, { x: 20, y: 30 }, 10, hovered);
    expect(fills).toEqual(colors);
    expect(strokes.at(-1)).toBe(owned ? '#00e676' : (hovered ? 'rgba(202,232,255,.9)' : 'rgba(157,202,239,.5)'));
  }
});

test('La sélection et le retour d’un aperçu préservent la caméra ; seule l’entrée la déplace', () => {
  const { listLink, controllerTargets } = listNavigationFixture();
  const target = () => ({ hidden: false, textContent: '' });
  const system = { id: 7, name: 'Système', galaxy: '1', number: 7, known: true, wx: 180, wy: 220 };
  const camera = { x: 12, y: 24 };
  const state = {
    ...controllerTargets,
    element: { ...controllerTargets.element, querySelector: () => null },
    mode: 'galaxy', motionReduced: true, hasUserCamera: true,
    camera: { ...camera }, zoom: .8, targetZoom: .8, baseZoom: .25,
    tooltipTarget: target(), systemPanelTarget: target(), planetPanelTarget: target(),
    systemBackTarget: target(), planetBackTarget: target(), minimapWrapTarget: target(),
    fleetLogTarget: { parentElement: target() }, locationTarget: target(), coordinatesTarget: target(),
    mapHelpTarget: target(), systemContentTarget: { replaceChildren() {} },
    searchInputTarget: { focus() {} },
    setCanvasMode(value) { this.view = value; }, drawFrame() {}, focusPanel() {},
    gameMap: { galaxy: '1' },
    prepareSystemPanel: GalaxyController.prototype.prepareSystemPanel,
    showSystemPreview: GalaxyController.prototype.showSystemPreview,
  };
  const preview = () => GalaxyController.prototype.previewSystem.call(state, system);
  const back = () => GalaxyController.prototype.toGalaxy.call(state);
  preview();
  expect(state.mode).toBe('galaxy');
  expect(state.systemPanelTarget.hidden).toBe(false);
  expect(listLink.href).toBe('https://genesis.example.test/carte?galaxy=1&system=7');
  expect(state.camera).toEqual(camera);
  expect(state.zoom).toBe(.8);
  expect(state.savedCamera).toBeUndefined();
  back();
  expect(state.selectedSystem).toBeNull();
  expect(state.camera).toEqual(camera);
  expect(state.zoom).toBe(.8);
  preview();
  GalaxyController.prototype.enterSelectedSystem.call(state);
  expect(state.mode).toBe('system');
  expect(state.view).toBe('system');
  expect(state.camera).toEqual({ x: 180, y: 220 });
  expect(state.zoom).toBeCloseTo(.725);
  back();
  expect(state.camera).toEqual(camera);
  expect(state.zoom).toBe(.8);
  expect(state.hasUserCamera).toBe(true);
});

test('Revenir depuis une planète consultée en aperçu restaure la fiche, sans entrer dans le système', () => {
  let focused = false;
  const state = {
    mode: 'planet', planetReturnView: 'galaxy', selectedSystem: { id: 1 }, selectedPlanet: { id: 2 },
    camera: { x: 50, y: 90 }, zoom: .6,
    toSystem: GalaxyController.prototype.toSystem,
    setCanvasMode(value) { this.view = value; },
    showSystemPreview() {},
    systemContentTarget: { querySelector: selector => selector === '[data-planet-id="2"]' ? { focus() { focused = true; } } : null },
  };
  GalaxyController.prototype.returnToPlanetSystem.call(state);
  expect(state.mode).toBe('galaxy');
  expect(state.view).toBe('galaxy');
  expect(state.planetReturnView).toBeNull();
  expect(state.camera).toEqual({ x: 50, y: 90 });
  expect(state.zoom).toBe(.6);
  expect(focused).toBe(true);
});
