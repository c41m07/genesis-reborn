import { test, expect } from './support/isolated-test.mjs';
import { runIsolatedSql } from './support/game-fixtures.mjs';

async function enter(page) {
  const id = crypto.randomUUID().slice(0, 8);
  const email = `maps-ui-${id}@example.test`;
  const password = 'Browser-test-2026!';
  await page.goto('/inscription');
  await page.getByLabel('Adresse email').fill(email);
  await page.getByLabel('Nom de commandant').fill(`MapUI-${id}`);
  await page.getByLabel('Mot de passe (12 caractères minimum)', { exact: true }).fill(password);
  await page.getByLabel('Confirmer le mot de passe').fill(password);
  await page.getByRole('button', { name: 'Créer mon compte' }).click();
  await expect(page).toHaveURL(/connexion/);
  await page.getByLabel('Adresse email').fill(email);
  await page.getByLabel('Mot de passe', { exact: true }).fill(password);
  await page.getByRole('button', { name: /Connexion/ }).click();
  await page.locator('.universe-card').filter({ has: page.getByRole('button') }).first().getByRole('button').click();
  await expect(page).toHaveURL(/planetes\/\d+$/);
  return email;
}

test('Technologie et cartes : captures des trois interfaces', async ({ page }, testInfo) => {
  await enter(page);
  await page.emulateMedia({ reducedMotion: 'reduce' });
  for (const [route, name] of [['/technologies', 'technology'], ['/carte', 'map-classic'], ['/carte-v2', 'map-v2']]) {
    await page.goto(route);
    await expect(page.locator(route === '/carte-v2' ? '.galaxy-v2' : 'main h1')).toBeVisible();
    for (const width of [1920, 1440, 1280, 768, 390]) {
      await page.setViewportSize({ width, height: 900 });
      await page.evaluate(() => window.scrollTo({ top: 0, behavior: 'instant' }));
      expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(width);
      if ([1440, 390].includes(width)) await page.screenshot({ path: testInfo.outputPath(`${name}-${width}.png`), animations: 'disabled' });
    }
  }
});

test('Arbre : éléments verrouillés, chaîne de prérequis, filtres, clavier et liens réels', async ({ page, browser }, testInfo) => {
  page.on('pageerror', error => console.log('Erreur JavaScript sur l’arbre : ' + error.message));
  page.on('requestfailed', request => { if (request.url().includes('/build/')) console.log('Asset non chargé : ' + request.url()); });
  const email = await enter(page);
  await page.goto('/technologies');
  expect(await page.locator('.tech-node.locked').count()).toBeGreaterThan(0);
  const nodes = page.locator('.tech-node');
  const total = await nodes.count();
  expect(total).toBeGreaterThan(3);
  for (const group of ['buildings', 'technologies', 'ships', 'defenses']) expect(await nodes.locator(`xpath=self::*[@data-filter-kind='${group}']`).count()).toBeGreaterThan(0);
  const lab = page.locator('#tech-buildings-lab-basic');
  const research = page.locator('#tech-vector_propulsion-basic');
  expect((await lab.boundingBox()).x + (await lab.boundingBox()).width).toBeLessThan((await research.boundingBox()).x);
  const basic = await page.locator('#tech-buildings-lab-basic').boundingBox();
  const intermediate = await page.locator('#tech-buildings-lab-intermediate').boundingBox();
  const superior = await page.locator('#tech-buildings-lab-superior').boundingBox();
  expect(basic.y + basic.height).toBeLessThan(intermediate.y);
  expect(intermediate.y + intermediate.height).toBeLessThan(superior.y);
  await expect.poll(() => page.locator('.technology-graph-links path').count()).toBeGreaterThan(0);
  const first = research;
  const firstId = await first.getAttribute('id');
  const label = await first.locator('summary strong').innerText();
  await first.locator('summary').focus();
  await page.keyboard.press('Enter');
  await expect(first).toHaveAttribute('open', '');
  await expect(first.getByRole('heading', { name: 'Prérequis' })).toBeVisible();
  const selectedPath = await nodes.evaluateAll(rows => {
    const target = rows.find(row => row.classList.contains('is-search-match'));
    const byKey = new Map(rows.map(row => [row.dataset.nodeKey, row]));
    const expected = new Set([target.dataset.nodeKey]);
    const pending = [target];
    while (pending.length) for (const key of JSON.parse(pending.pop().dataset.parents)) {
      if (!expected.has(key)) { expected.add(key); pending.push(byKey.get(key)); }
    }
    return { expected: [...expected].sort(), visible: rows.filter(row => !row.hidden).map(row => row.dataset.nodeKey).sort() };
  });
  expect(selectedPath.visible).toEqual(selectedPath.expected);
  expect(selectedPath.visible.length).toBeLessThan(total);
  await page.getByRole('button', { name: 'Afficher tout l’arbre', exact: true }).click();
  await expect(page.locator('.tech-node:visible')).toHaveCount(total);
  await expect(first).toHaveAttribute('open', '');
  const query = page.getByRole('searchbox', { name: 'Rechercher un élément' });
  await query.fill(label);
  expect(await page.locator('.tech-node:visible').count()).toBeGreaterThan(0);
  expect(await page.locator('.tech-node:visible').count()).toBeLessThan(total);
  // La recherche conserve exactement la fermeture transitive des prérequis.
  await page.getByLabel('Catégorie', { exact: true }).selectOption('ships');
  await query.fill('Cuirassé de ligne');
  const branch = await nodes.evaluateAll(rows => {
    const matches = rows.filter(row => row.classList.contains('is-search-match'));
    const expected = new Set(matches.map(row => row.dataset.nodeKey));
    const byKey = new Map(rows.map(row => [row.dataset.nodeKey, row]));
    const pending = [...matches];
    while (pending.length) for (const key of JSON.parse(pending.pop().dataset.parents)) {
      if (!expected.has(key)) { expected.add(key); pending.push(byKey.get(key)); }
    }
    return { matches: matches.length, expected: [...expected].sort(), visible: rows.filter(row => !row.hidden).map(row => row.dataset.nodeKey).sort() };
  });
  expect(branch.matches).toBeGreaterThan(0);
  expect(branch.expected.length).toBeGreaterThan(branch.matches);
  expect(branch.visible).toEqual(branch.expected);
  await expect(page.locator('.tech-node[data-filter-kind="buildings"]:visible').first()).toBeVisible();
  await expect.poll(() => page.locator('.technology-graph-links path').count()).toBeGreaterThan(0);
  await page.getByLabel('Catégorie', { exact: true }).selectOption('');
  await query.fill('zz-technologie-inexistante');
  await expect(page.getByText('Aucun élément ne correspond à ces filtres.')).toBeVisible();
  await expect(page.locator('.technology-branch-column:visible')).toHaveCount(0);
  await expect.poll(() => page.locator('.technology-graph-links path').count()).toBe(0);
  await page.getByRole('button', { name: 'Effacer les filtres' }).click();
  await expect(query).toBeFocused();
  await expect(page.locator('.tech-node:visible')).toHaveCount(total);
  await expect(first).toHaveAttribute('open', '');
  const available = await nodes.filter({ has: page.locator('summary small', { hasText: 'Disponible' }) }).count();
  await page.getByLabel('Disponibilité', { exact: true }).selectOption('available');
  await expect(page.locator('.tech-node:visible')).toHaveCount(available);
  await page.getByRole('button', { name: 'Effacer les filtres' }).click();
  for (const width of [1920, 1440, 1280, 768, 390]) {
    await page.setViewportSize({ width, height: 900 });
    expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(width);
  }
  const graph = page.getByRole('region', { name: 'Arbre technologique de gauche à droite' });
  await graph.focus();
  await page.keyboard.press('ArrowRight');
  await expect.poll(() => graph.evaluate(element => element.scrollLeft)).toBeGreaterThan(0);
  await query.fill(label);
  await expect.poll(() => page.locator('.tech-node:visible').first().evaluate(element => element.getBoundingClientRect().left - element.closest('.technology-graph').getBoundingClientRect().left)).toBeGreaterThanOrEqual(0);
  await page.evaluate(() => window.scrollTo({ top: 0, behavior: 'instant' }));
  await page.screenshot({ path: testInfo.outputPath('technology-filter-390.png'), fullPage: true, animations: 'disabled' });
  const context = await browser.newContext({ javaScriptEnabled: false, storageState: await page.context().storageState() });
  const fallback = await context.newPage();
  await fallback.goto('/technologies');
  await expect(fallback.getByRole('search', { name: 'Rechercher dans l’arbre technologique' })).toBeHidden();
  await expect(fallback.locator('.tech-node:visible')).toHaveCount(total);
  await context.close();
  await page.locator(`[id="${firstId}"]`).getByRole('link', { name: 'Voir la fiche →' }).click();
  const anchor = new URL(page.url()).hash.slice(1);
  expect(anchor).toMatch(/^item-/);
  await expect(page.locator(`[id="${anchor}"]`)).toBeVisible();
  runIsolatedSql(process.env.BROWSER_ROUTER, `UPDATE player SET preferences=JSON_SET(preferences,'$.theme','light') WHERE email='${email}'`);
  await page.goto('/technologies');
  await query.fill(label);
  await page.setViewportSize({ width: 390, height: 900 });
  await page.screenshot({ path: testInfo.outputPath('technology-light-390.png'), fullPage: true, animations: 'disabled' });
});

test('Carte classique : systèmes connu/inconnu, tableau défilant et sans JS', async ({ page, browser }, testInfo) => {
  const email = await enter(page);
  await page.goto('/carte-v2');
  const data = JSON.parse(await page.locator('.galaxy-v2').getAttribute('data-galaxy-v2-map-value'));
  const known = data.systems.find(system => system.planets.some(planet => planet.ownedByCurrentPlayer));
  const unknown = data.systems.find(system => !system.known);
  expect(known).toBeTruthy();
  expect(unknown).toBeTruthy();
  await page.goto('/carte');
  await page.getByLabel('Système', { exact: true }).selectOption(String(known.id));
  await page.getByRole('button', { name: 'Afficher le système' }).click();
  await expect(page.locator('.galaxy-table')).toBeVisible();
  await expect(page.locator('.galaxy-table').getByRole('link', { name: /Gérer/ })).toBeVisible();
  const knownUrl = page.url();
  for (const width of [1920, 1440, 1280, 768, 390]) {
    await page.setViewportSize({ width, height: 900 });
    expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(width);
  }
  const scroll = page.getByRole('region', { name: 'Positions du système, défilement horizontal' });
  await scroll.focus();
  await page.keyboard.press('ArrowRight');
  await expect.poll(() => scroll.evaluate(element => element.scrollLeft)).toBeGreaterThan(0);
  await page.evaluate(() => window.scrollTo({ top: 0, behavior: 'instant' }));
  await page.screenshot({ path: testInfo.outputPath('map-classic-table-390.png'), fullPage: true, animations: 'disabled' });
  await scroll.evaluate(element => { element.scrollLeft = element.scrollWidth; });
  await page.evaluate(() => window.scrollTo({ top: 0, behavior: 'instant' }));
  await page.screenshot({ path: testInfo.outputPath('map-classic-actions-390.png'), fullPage: true, animations: 'disabled' });
  await page.locator('.galaxy-table').getByRole('link', { name: /Gérer/ }).click();
  await expect(page).toHaveURL(/planetes\/\d+/);
  await page.goto(knownUrl);
  await page.getByLabel('Système', { exact: true }).selectOption(String(unknown.id));
  await page.getByRole('button', { name: 'Afficher le système' }).click();
  await expect(page.locator('.galaxy-unknown-panel')).toBeVisible();
  await expect(page.locator('.galaxy-table')).toHaveCount(0);
  const context = await browser.newContext({ javaScriptEnabled: false, storageState: await page.context().storageState() });
  const fallback = await context.newPage();
  await fallback.goto(knownUrl);
  await expect(fallback.locator('.galaxy-table')).toBeVisible();
  await context.close();
  runIsolatedSql(process.env.BROWSER_ROUTER, `UPDATE player SET preferences=JSON_SET(CASE WHEN JSON_TYPE(preferences)='OBJECT' THEN preferences ELSE JSON_OBJECT() END,'$.theme','light') WHERE email='${email}'`);
  await page.goto(knownUrl);
  await page.evaluate(() => window.scrollTo({ top: 0, behavior: 'instant' }));
  await page.screenshot({ path: testInfo.outputPath('map-classic-light-390.png'), fullPage: true, animations: 'disabled' });
});

test('V2 : parcours complet au clavier, focus et retour sans canvas', async ({ page, browser }, testInfo) => {
  await enter(page);
  await page.emulateMedia({ reducedMotion: 'reduce' });
  await page.goto('/carte-v2');
  const data = JSON.parse(await page.locator('.galaxy-v2').getAttribute('data-galaxy-v2-map-value'));
  const known = data.systems.find(system => system.planets.some(planet => planet.ownedByCurrentPlayer));
  const planet = known.planets.find(item => item.ownedByCurrentPlayer);
  const search = page.getByRole('searchbox', { name: 'Rechercher un système ou une planète connue' });
  await expect(page.getByRole('button', { name: 'Effacer la recherche' })).toBeHidden();
  await search.fill('Système');
  const results = page.locator('.gv2-search-results button');
  await search.press('ArrowDown');
  await expect(results.first()).toBeFocused();
  await page.keyboard.press('ArrowDown');
  await expect(results.nth(1)).toBeFocused();
  await page.keyboard.press('ArrowUp');
  await expect(results.first()).toBeFocused();
  await page.keyboard.press('Escape');
  await expect(search).toBeFocused();
  await expect(page.locator('.gv2-search-results')).toBeHidden();
  await search.fill('zz-introuvable');
  await expect(page.locator('.gv2-search-empty')).toContainText('Aucun résultat connu');
  await expect(page.locator('[data-galaxy-v2-target="searchStatus"]')).toContainText('0 résultat');
  await page.getByRole('button', { name: 'Effacer la recherche' }).click();
  await expect(search).toBeFocused();
  await expect(search).toHaveValue('');
  await search.fill(known.name);
  await search.press('ArrowDown');
  await page.keyboard.press('Enter');
  const systemPanel = page.getByRole('complementary', { name: 'Informations sur le système sélectionné' });
  await expect(systemPanel.locator('h2')).toBeFocused();
  const choice = systemPanel.getByRole('button').filter({ hasText: planet.name });
  await choice.focus();
  await page.keyboard.press('Enter');
  const planetPanel = page.getByRole('complementary', { name: 'Informations sur la planète sélectionnée' });
  await expect(planetPanel.locator('h2')).toBeFocused();
  await expect(systemPanel).toBeHidden();
  await expect(planetPanel).toContainText(planet.coordinates);
  for (const width of [1920, 1440, 1280, 768, 390]) {
    await page.setViewportSize({ width, height: 900 });
    expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(width);
    const panel = await planetPanel.boundingBox();
    expect(panel.x).toBeGreaterThanOrEqual(0);
    expect(panel.x + panel.width).toBeLessThanOrEqual(width);
    const controls = await page.locator('.gv2-map-controls').boundingBox();
    const overlaps = controls.x < panel.x + panel.width && controls.x + controls.width > panel.x && controls.y < panel.y + panel.height && controls.y + controls.height > panel.y;
    expect(overlaps, 'les commandes de zoom doivent laisser les informations et leur fermeture accessibles').toBe(false);
  }
  await page.screenshot({ path: testInfo.outputPath('map-v2-planet-390.png'), animations: 'disabled' });
  await page.keyboard.press('Escape');
  await expect(choice).toBeFocused();
  await expect(systemPanel).toBeVisible();
  await page.keyboard.press('Enter');
  await planetPanel.getByRole('button', { name: /Vue rapprochée/ }).focus();
  await page.keyboard.press('Enter');
  await expect(page.locator('canvas.gv2-canvas--planet')).toHaveClass(/is-active/);
  await page.keyboard.press('Escape');
  await expect(choice).toBeFocused();
  await page.keyboard.press('Escape');
  await expect(search).toBeFocused();
  await expect(systemPanel).toBeHidden();
  const context = await browser.newContext({ javaScriptEnabled: false, storageState: await page.context().storageState() });
  const fallback = await context.newPage();
  await fallback.goto('/carte-v2');
  await fallback.getByRole('link', { name: 'Carte classique', exact: true }).click();
  await expect(fallback).toHaveURL(/carte(?:\?.*)?$/);
  await expect(fallback.getByRole('button', { name: 'Afficher le système' })).toBeVisible();
  await context.close();
});
