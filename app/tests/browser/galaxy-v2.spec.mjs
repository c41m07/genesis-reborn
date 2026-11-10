import { test, expect } from './support/isolated-test.mjs';

test.use({ hasTouch: true });

test('GalaxieV2 relie la galaxie, le système et la planète sans casser la carte classique', async ({ page }) => {
  await page.emulateMedia({ reducedMotion: 'reduce' });
  // Observer les coordonnées réellement dessinées pour exercer le clic natif sur le canvas.
  await page.addInitScript(() => {
    window.galaxyDrawnLabels = {};
    window.galaxyCoordinateLabels = [];
    const circles = new WeakMap();
    const arc = CanvasRenderingContext2D.prototype.arc;
    const fillText = CanvasRenderingContext2D.prototype.fillText;
    const clearRect = CanvasRenderingContext2D.prototype.clearRect;
    CanvasRenderingContext2D.prototype.clearRect = function(...args) {
      if (this.canvas.classList.contains('gv2-canvas--galaxy')) { window.galaxyUnknownMarkers = [];window.galaxyCoordinateLabels = []; }
      return clearRect.apply(this, args);
    };
    CanvasRenderingContext2D.prototype.arc = function(x, y, radius, ...args) {
      circles.set(this, { x, y, radius });
      return arc.call(this, x, y, radius, ...args);
    };
    CanvasRenderingContext2D.prototype.fillText = function(text, ...args) {
      const circle = circles.get(this);
      if (this.canvas.classList.contains('gv2-canvas--galaxy') && /^[^:]+:\d+$/.test(text)) window.galaxyCoordinateLabels.push(text);
      if (circle) window.galaxyDrawnLabels[text] = { ...circle, canvas: this.canvas.className };
      if (circle && text === '?' && this.canvas.classList.contains('gv2-canvas--galaxy')) window.galaxyUnknownMarkers.push({ ...circle });

      return fillText.call(this, text, ...args);
    };
  });
  const pageErrors = [];
  page.on('pageerror', error => pageErrors.push(error.message));
  const id = crypto.randomUUID().slice(0, 8);
  const email = 'galaxy-v2-' + id + '@example.test';
  const password = 'Galaxy-test-2026!';

  await page.goto('/inscription');
  await page.getByLabel('Adresse email').fill(email);
  await page.getByLabel('Nom de commandant').fill('Galaxy-' + id);
  await page.getByLabel('Mot de passe (12 caractères minimum)', { exact: true }).fill(password);
  await page.getByLabel('Confirmer le mot de passe').fill(password);
  await page.getByRole('button', { name: 'Créer mon compte' }).click();
  await expect(page).toHaveURL(/connexion/);
  await page.getByLabel('Adresse email').fill(email);
  await page.getByLabel('Mot de passe', { exact: true }).fill(password);
  await page.getByRole('button', { name: /Connexion/ }).click();
  await page.locator('article.universe-card').filter({ has: page.getByRole('heading', { name: /Turbo/ }) }).getByRole('button').click();

  await expect(page.getByRole('navigation', {name:'Navigation principale'}).getByRole('link', {name:'Espionnage', exact:true})).toHaveCount(0);
  const mainNavigation=page.getByRole('navigation', {name:'Navigation principale'});
  await expect(mainNavigation.getByRole('link', {name:'Paramètres', exact:true})).toHaveCount(0);
  await expect(mainNavigation.getByRole('link', {name:'Inventaire', exact:true})).toBeVisible();
  const menuLabels=await mainNavigation.locator('a').allTextContents();
  await expect(mainNavigation.getByRole('link', {name:'Technologie', exact:true})).toBeVisible();
  await expect(page.getByRole('heading', {name:'Mes planètes', exact:true})).toHaveCount(0);
  await mainNavigation.getByRole('link', {name:'Profil', exact:true}).click();
  await expect(page.getByRole('link', {name:'Paramètres', exact:true})).toBeVisible();
  await mainNavigation.getByRole('link', {name:'Technologie', exact:true}).click();
  await expect(page.locator('.technology-tree')).toBeVisible();
  await page.goto('/carte-v2');
  expect(menuLabels.indexOf('Rapports')).toBeLessThan(menuLabels.indexOf('Messages'));
  await page.goto('/carte-v2');
  const map = page.locator('.galaxy-v2');
  await expect(map).toBeVisible();
  await expect(page.locator('canvas.gv2-canvas--galaxy')).toBeVisible();

  await expect(page.getByRole('link', {name:'Carte interactive (V2)', exact:true})).toHaveAttribute('aria-current', 'page');
  await page.getByRole('link', {name:'Carte classique', exact:true}).click();
  await expect(page).toHaveURL(/\/carte(?:\?.*)?$/);
  await page.getByRole('link', {name:'Carte interactive (V2)', exact:true}).click();
  await expect(page).toHaveURL(/\/carte-v2(?:\?.*)?$/);
  await page.waitForLoadState('load');

  const data = JSON.parse(await map.getAttribute('data-galaxy-v2-map-value'));
  const homeSystem = data.systems.find(system => system.known && system.planets.some(planet => planet.ownedByCurrentPlayer));
  expect(homeSystem, 'la carte doit exposer le système persistant de la colonie').toBeTruthy();
  const homePlanet = homeSystem.planets.find(planet => planet.ownedByCurrentPlayer);
  expect(homePlanet.coordinates).toMatch(/^[^:]+:\d+:\d+$/);

  for (const system of data.systems.filter(system => !system.known)) expect(system.planets).toEqual([]);
  expect(homePlanet.relation).toBe('own');
  const emptyJournal = page.locator('.gv2-flight-log.is-empty');
  await expect(emptyJournal).toBeVisible();
  await expect(emptyJournal).not.toHaveAttribute('open', '');
  await emptyJournal.locator('summary').click();
  await expect(emptyJournal.locator('.gv2-empty-log')).toBeVisible();
  await emptyJournal.locator('summary').click();

  await expect(page.locator('[data-galaxy-v2-target="legend"] li')).toHaveCount(6);
  await expect(page.locator('.gv2-map-settings .gv2-legend')).not.toHaveAttribute('open', '');
  await page.locator('.gv2-map-settings .gv2-legend summary').click();
  await expect(page.getByRole('button', { name: 'Exploration', exact: true })).toHaveAttribute('aria-pressed', 'true');
  await expect(page.getByRole('checkbox', { name: 'Vos planètes' })).toBeChecked();
  await expect(page.getByRole('checkbox', { name: 'Inhabitée' })).toHaveCount(0);
  await page.getByRole('checkbox', { name: 'Inexploré' }).uncheck();
  await page.getByRole('button', { name: 'PNJ', exact: true }).click();
  await expect(page.getByRole('button', { name: 'PNJ', exact: true })).toHaveAttribute('aria-pressed', 'true');
  await expect(page.locator('[data-galaxy-v2-target="legend"]')).toContainText('niveaux 1–3');
  await expect(page.getByRole('checkbox', { name: 'Inexploré' })).not.toBeChecked();
  await page.getByRole('checkbox', { name: 'Inexploré' }).check();

  await page.getByRole('button', { name: 'Joueurs', exact: true }).focus();
  await page.keyboard.press('Enter');
  await expect(page.getByRole('button', { name: 'Joueurs', exact: true })).toHaveAttribute('aria-pressed', 'true');
  await expect(page.locator('[data-galaxy-v2-target="legend"]')).toContainText('Joueur ennemi');
  await page.getByRole('checkbox', { name: 'Neutre / vierge' }).focus();
  await page.keyboard.press('Space');
  await expect(page.getByRole('checkbox', { name: 'Neutre / vierge' })).not.toBeChecked();

  await page.screenshot({ path: 'output/playwright/galaxy-modes-desktop.png' });
  await page.getByRole('button', { name: 'Exploration', exact: true }).click();

  await expect(page.getByRole('checkbox', { name: 'Neutre / vierge' })).not.toBeChecked();
  expect(await page.evaluate(() => window.galaxyCoordinateLabels)).toEqual([]);
  const labelSearch = page.getByRole('searchbox', { name: 'Rechercher un système ou une planète connue' });
  await labelSearch.fill(homeSystem.name);
  const labelResult = page.locator('.gv2-search-results button').filter({ hasText: homeSystem.name }).first();
  await labelResult.focus();
  await expect.poll(() => page.evaluate(() => window.galaxyCoordinateLabels)).toContain(homeSystem.galaxy + ':' + homeSystem.number);
  const homePoint = await page.evaluate(key => window.galaxyDrawnLabels[key], homeSystem.galaxy + ':' + homeSystem.number);
  await labelSearch.focus();
  await labelSearch.fill('');
  await expect.poll(() => page.evaluate(() => window.galaxyCoordinateLabels)).toEqual([]);
  expect(homePoint).toBeTruthy();
  const galaxyBounds = await page.locator('.gv2-canvas--galaxy').boundingBox();
  await page.mouse.move(galaxyBounds.x + homePoint.x, galaxyBounds.y + homePoint.y);
  await expect(page.locator('.gv2-tooltip-title')).toHaveText(homeSystem.name);
  await expect.poll(() => page.evaluate(() => window.galaxyCoordinateLabels)).toContain(homeSystem.galaxy + ':' + homeSystem.number);
  await expect(page.locator('.gv2-tooltip-facts')).toContainText('Vos colonies');
  const hoverBounds = await page.locator('.gv2-tooltip').boundingBox();
  const hudBounds = await page.locator('.gv2-hud').boundingBox();
  expect(hoverBounds.y).toBeGreaterThanOrEqual(hudBounds.y + hudBounds.height);
  expect(hoverBounds.y + hoverBounds.height).toBeLessThanOrEqual(galaxyBounds.y + galaxyBounds.height);

  await page.screenshot({ path: 'output/playwright/galaxy-system-hover.png' });
  await page.mouse.move(0, 0);
  await expect.poll(() => page.evaluate(() => window.galaxyCoordinateLabels)).toEqual([]);

  const search = page.getByRole('searchbox', { name: 'Rechercher un système ou une planète connue' });
  await search.fill(homeSystem.name);
  const result = page.locator('.gv2-search-results button').filter({ hasText: homeSystem.name }).first();
  await expect(result).toBeVisible();
  await result.click();
  await expect(page.locator('.gv2-system-panel')).toBeVisible();
  await expect(page.locator('.gv2-system-panel')).toContainText(homeSystem.name);
  const line = page.locator('[data-galaxy-v2-target="systemContent"] .gv2-planet-choice').first();
  const nameCell = line.locator('.gv2-planet-choice-name');
  const originalName = await nameCell.textContent();
  await nameCell.evaluate(element => { element.textContent = 'Système 043 - 1'; });
  const cells = await line.locator('.gv2-planet-number, .gv2-planet-choice-name, .gv2-planet-owner').evaluateAll(nodes => nodes.map(node => { const box = node.getBoundingClientRect();return { x: box.x, y: box.y, right: box.right, height: box.height }; }));
  expect(cells[0].x).toBeLessThan(cells[1].x);
  expect(cells[1].x).toBeLessThan(cells[2].x);
  expect(Math.abs(cells[1].y + cells[1].height / 2 - cells[2].y - cells[2].height / 2)).toBeLessThan(2);
  await nameCell.evaluate(element => { element.textContent = 'Système au nom particulièrement long pour vérifier le retour à la ligne sans aucune perte d’information'; });
  expect(await line.evaluate(element => element.scrollWidth <= element.clientWidth)).toBe(true);
  await nameCell.evaluate((element, name) => { element.textContent = name; }, originalName);

  expect(await page.evaluate(name => window.galaxyDrawnLabels[name]?.canvas.includes('gv2-canvas--system') ?? false, homePlanet.name)).toBe(false);

  await expect(page.locator('.gv2-system-panel .gv2-planet-list .gv2-marker--own')).toBeVisible();
  const solarLegend = page.locator('.gv2-system-panel .gv2-system-legend');
  await solarLegend.locator('summary').click();
  await expect(solarLegend.locator('ul')).toContainText('Votre monde');
  await expect(solarLegend.locator('ul')).toContainText('Faction PNJ');
  await page.screenshot({ path: 'output/playwright/galaxy-solar-legend-desktop.png' });
  await solarLegend.locator('summary').click();
  const movingLabel = String(homePlanet.position).padStart(2, '0');
  const orbitBefore = await page.evaluate(key => window.galaxyDrawnLabels[key], movingLabel);
  await page.emulateMedia({ reducedMotion: 'no-preference' });
  await expect.poll(async () => page.evaluate(({ key, before }) => {
    const point = window.galaxyDrawnLabels[key];
    return Math.hypot(point.x - before.x, point.y - before.y);
  }, { key: movingLabel, before: orbitBefore })).toBeGreaterThan(2);
  await page.emulateMedia({ reducedMotion: 'reduce' });
  const findClickablePlanet = planets => page.evaluate(planets => {
    const canvas = document.querySelector('.gv2-canvas--system');
    const rect = canvas.getBoundingClientRect();
    for (const planet of planets) {
      const point = window.galaxyDrawnLabels[String(planet.position).padStart(2, '0')];
      if (!point?.canvas.includes('gv2-canvas--system')) continue;
      const x = rect.left + point.x, y = rect.top + point.y;
      if (document.elementFromPoint(x, y) === canvas) return { name: planet.name, x, y };
    }
    return null;
  }, planets);
  const clickable = await findClickablePlanet(homeSystem.planets);
  expect(clickable, 'une planète dessinée doit être accessible au clic').toBeTruthy();
  await page.mouse.click(clickable.x, clickable.y);
  await expect(page.locator('.gv2-canvas--planet')).toHaveClass(/is-active/);
  await expect(page.locator('.gv2-planet-panel')).toContainText(clickable.name);
  await page.locator('[data-galaxy-v2-target="planetBack"]').click();
  await expect(page.locator('.gv2-system-panel')).toBeVisible();
  await page.locator('.gv2-planet-choice').filter({ hasText: homePlanet.name }).click();
  await expect(page.locator('.gv2-planet-panel')).toBeVisible();
  await expect(page.locator('.gv2-planet-panel')).toContainText(homePlanet.name);
  await expect(page.locator('.gv2-planet-panel .gv2-facts')).toContainText(homePlanet.coordinates);

  await page.locator('.gv2-primary-action').click();
  await expect(page.locator('canvas.gv2-canvas--planet')).toHaveClass(/is-active/);
  await expect(page.locator('.gv2-planet-panel')).toBeVisible();
  await page.locator('[data-galaxy-v2-target="planetBack"]').click();
  await expect(page.locator('.gv2-system-panel')).toBeVisible();
  await page.locator('[data-galaxy-v2-target="systemBack"]').click();
  await expect(page.locator('.gv2-system-panel')).toBeHidden();

  await page.getByRole('navigation', {name:'Navigation principale'}).getByRole('link', { name: 'Galaxie', exact: true }).click();
  await expect(page).toHaveURL(/\/carte-v2(?:\?.*)?$/);
  await expect(page.locator('.galaxy-v2')).toBeVisible();
  await page.getByRole('link', {name:'Carte classique', exact:true}).click();
  await expect(page).toHaveURL(/\/carte(?:\?.*)?$/);
  await expect(page.locator('.galaxy-v2')).toHaveCount(0);

  await page.setViewportSize({ width: 390, height: 844 });
  await page.goto('/carte-v2');
  await expect(page.locator('.galaxy-v2')).toBeVisible();
  await expect.poll(() => page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(390);
  const filters = page.locator('.gv2-filter-toggle');
  await expect(filters).toHaveAttribute('aria-expanded', 'false');
  await expect(page.locator('#gv2-filter-content')).toBeHidden();
  await expect(emptyJournal).toBeVisible();
  const mobileMarkers = await page.evaluate(() => {
    const canvas = document.querySelector('.gv2-canvas--galaxy');
    const rect = canvas.getBoundingClientRect();
    return (window.galaxyUnknownMarkers || []).map(point => ({
      clickable: document.elementFromPoint(rect.left + point.x, rect.top + point.y) === canvas,
    }));
  });
  expect(mobileMarkers).toHaveLength(data.systems.filter(system => !system.known).length);
  expect(mobileMarkers.every(marker => marker.clickable), 'les commandes ne doivent pas masquer les systèmes inconnus au cadrage initial').toBe(true);
  await page.screenshot({ path: 'output/playwright/galaxy-layout-mobile.png' });
  await filters.focus();
  await page.keyboard.press('Enter');
  await expect(filters).toHaveAttribute('aria-expanded', 'true');
  await expect(page.locator('.gv2-tooltip')).toBeHidden();
  await expect(emptyJournal).toBeHidden();
  await expect(page.locator('[data-galaxy-v2-target="legend"]')).toBeVisible();
  await page.getByRole('button', { name: 'PNJ', exact: true }).click();
  await expect(page.locator('[data-galaxy-v2-target="legend"]')).toContainText('niveaux 1–3');
  await page.screenshot({ path: 'output/playwright/galaxy-modes-mobile.png' });
  await filters.click();
  await expect(filters).toHaveAttribute('aria-expanded', 'false');
  await expect(filters).toContainText('PNJ');
  await expect(page.locator('#gv2-filter-content')).toBeHidden();
  await expect(emptyJournal).toBeVisible();
  const mobileSearch = page.getByRole('searchbox', { name: 'Rechercher un système ou une planète connue' });
  await mobileSearch.fill(homeSystem.name);
  await page.locator('.gv2-search-results button').filter({ hasText: homeSystem.name }).first().click();
  const sheet = page.locator('.gv2-sheet-toggle');
  await expect(sheet).toHaveAttribute('aria-expanded', 'false');
  await expect(page.locator('#gv2-system-content')).toBeHidden();
  await page.screenshot({ path: 'output/playwright/galaxy-solar-mobile.png' });
  await sheet.focus();
  await page.keyboard.press('Enter');
  await expect(sheet).toHaveAttribute('aria-expanded', 'true');
  await expect(page.locator('.gv2-system-panel .gv2-planet-list .gv2-marker--own')).toBeVisible();
  await page.locator('.gv2-system-panel .gv2-system-legend summary').click();
  await expect(page.locator('.gv2-system-panel .gv2-system-legend ul')).toBeVisible();
  await page.screenshot({ path: 'output/playwright/galaxy-planets-mobile.png' });
  await page.locator('.gv2-system-panel .gv2-system-legend summary').click();
  await sheet.click();
  await expect(sheet).toHaveAttribute('aria-expanded', 'false');
  await expect(page.locator('#gv2-system-content')).toBeHidden();
  const mobilePlanet = await findClickablePlanet(homeSystem.planets);
  expect(mobilePlanet, 'une planète doit rester visible au-dessus du panneau mobile').toBeTruthy();
  await page.touchscreen.tap(mobilePlanet.x, mobilePlanet.y);
  await expect(page.locator('.gv2-canvas--planet')).toHaveClass(/is-active/);
  await expect(page.locator('.gv2-planet-panel')).toContainText(mobilePlanet.name);

  expect(pageErrors).toEqual([]);


});