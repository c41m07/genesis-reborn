import { test, expect } from './support/isolated-test.mjs';

test('GalaxieV2 relie la galaxie, le système et la planète sans casser la carte classique', async ({ page }) => {
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

  const data = JSON.parse(await map.getAttribute('data-galaxy-v2-map-value'));
  const homeSystem = data.systems.find(system => system.known && system.planets.some(planet => planet.ownedByCurrentPlayer));
  expect(homeSystem, 'la carte doit exposer le système persistant de la colonie').toBeTruthy();
  const homePlanet = homeSystem.planets.find(planet => planet.ownedByCurrentPlayer);
  expect(homePlanet.coordinates).toMatch(/^[^:]+:\d+:\d+$/);

  const search = page.getByRole('searchbox', { name: 'Rechercher un système ou une planète connue' });
  await search.fill(homeSystem.name);
  const result = page.locator('.gv2-search-results button').filter({ hasText: homeSystem.name }).first();
  await expect(result).toBeVisible();
  await result.click();
  await expect(page.locator('.gv2-system-panel')).toBeVisible();
  await expect(page.locator('.gv2-system-panel')).toContainText(homeSystem.name);
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

});