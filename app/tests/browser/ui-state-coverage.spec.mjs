import { test, expect } from './support/isolated-test.mjs';

async function signIn(page) {
  const id = crypto.randomUUID().slice(0, 8);
  const email = 'ui-state-' + id + '@example.test';
  const password = 'Browser-test-2026!';
  await page.goto('/inscription');
  await page.getByLabel('Adresse email').fill(email);
  await page.getByLabel('Nom de commandant').fill('UI-State-' + id);
  await page.getByLabel('Mot de passe (12 caractères minimum)', { exact: true }).fill(password);
  await page.getByLabel('Confirmer le mot de passe').fill(password);
  await page.getByRole('button', { name: 'Créer mon compte' }).click();
  await expect(page).toHaveURL(/connexion/);
  await page.getByLabel('Adresse email').fill(email);
  await page.getByLabel('Mot de passe', { exact: true }).fill(password);
  await page.getByRole('button', { name: /Connexion/ }).click();
  await page.locator('article.universe-card').filter({ has: page.getByRole('heading', { name: /Turbo/ }) }).getByRole('button').click();
  await expect(page).toHaveURL(/planetes\/\d+$/);
  return Number(new URL(page.url()).pathname.split('/').at(-1));
}

test('Les écrans métier se chargent, restent stables et exposent un arbre accessible', async ({ page }) => {
  const pageErrors = [];
  page.on('pageerror', error => pageErrors.push(error.message));
  const planetId = await signIn(page);
  const routes = [
    ['Accueil', '/'],
    ['Planètes', '/planetes'],
    ['Vue générale', '/planetes/' + planetId],
    ['Bâtiments', '/planetes/' + planetId + '?section=buildings'],
    ['Recherche', '/planetes/' + planetId + '?section=technologies'],
    ['Chantier', '/planetes/' + planetId + '?section=ships'],
    ['Défenses', '/planetes/' + planetId + '?section=defenses'],
    ['Galaxie', '/carte'],
    ['Flottes', '/flottes'],
    ['Rapports', '/rapports'],
    ['Diplomatie', '/diplomatie'],
    ['Messages', '/messages'],
    ['Classement', '/classement'],
    ['Univers', '/univers'],
    ['Profil', '/profil'],
    ['Préférences', '/profil/preferences'],
    ['Boosts', '/boosts']
  ];
  const session = await page.context().newCDPSession(page);
  await session.send('Accessibility.enable');
  const interactiveRoles = new Set(['button', 'link', 'textbox', 'checkbox', 'combobox', 'radio', 'switch']);
  const findings = [];

  for (const [label, route] of routes) {
    const response = await page.goto(route, { waitUntil: 'domcontentloaded' });
    const status = response?.status() ?? 0;
    await expect(page.locator('main#main'), label + ': la zone principale est visible').toBeVisible();
    await expect(page.locator('main#main').getByRole('heading', { level: 1 }).first(), label + ': titre principal présent').toBeVisible();
    const stuckBusy = await page.locator('[aria-busy="true"], [data-loading="true"]').evaluateAll(nodes => nodes.filter(node => {
      const style = getComputedStyle(node);
      return style.display !== 'none' && style.visibility !== 'hidden';
    }).length);
    const brokenImages = await page.locator('img').evaluateAll(nodes => nodes.filter(node => node.complete && node.currentSrc && node.naturalWidth === 0).map(node => node.currentSrc));
    const tree = await session.send('Accessibility.getFullAXTree');
    const unnamed = tree.nodes.filter(node => !node.ignored && interactiveRoles.has(node.role?.value) && !(node.name?.value ?? '').trim()).map(node => node.role.value);
    findings.push({ label, route, status, stuckBusy, brokenImages, unnamed });
    expect(status, label + ': réponse HTTP').toBe(200);
    if (route === '/') await expect(page.locator('.dashboard-journal .dashboard-empty')).toContainText('Aucun événement d’absence à consulter');
    if (route === '/flottes') {
      await expect(page.getByRole('heading', { name: 'Aucun vaisseau disponible' })).toBeVisible();
      const orbit = page.locator('[data-live-region="orbit-requests"]');
      await expect(orbit.locator('summary')).toContainText('Demandes en cours : 0');
      await expect(orbit.getByText('Aucune demande d’orbite envoyée ou reçue en cours.', { exact: true })).toBeHidden();
      await orbit.locator('summary').focus();
      await page.keyboard.press('Enter');
      await expect(page.getByText('Aucune demande d’orbite envoyée ou reçue en cours.', { exact: true })).toBeVisible();
      await expect(page.getByText('Aucune flotte en vol. Les départs et les retours de toutes vos colonies apparaissent ici.')).toBeVisible();
      await expect(page.getByRole('heading', { name: 'Journal des missions' })).toHaveCount(0);
    }
    if (route === '/rapports') await expect(page.getByRole('heading', { name: 'Aucun rapport dans cette catégorie' })).toBeVisible();
    if (route === '/messages') await expect(page.getByText('Aucune conversation privée pour le moment.')).toBeVisible();
    if (route === '/boosts') {
      await expect(page.getByRole('heading', { name: 'Aucun bonus disponible', exact: true })).toBeVisible();
      await expect(page.getByText('Les récompenses de vos missions, événements et projets de guilde apparaîtront ici.')).toBeVisible();
    }
    expect(stuckBusy, label + ': aucun état de chargement bloqué').toBe(0);
    expect(brokenImages, label + ': aucune image cassée').toEqual([]);
    expect(unnamed, label + ': commandes nommées dans l’arbre d’accessibilité').toEqual([]);
  }

  expect(pageErrors, JSON.stringify({ findings, pageErrors }, null, 2)).toEqual([]);
  await session.detach();
});
