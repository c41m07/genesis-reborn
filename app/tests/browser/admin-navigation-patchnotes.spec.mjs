import { test, expect } from './support/isolated-test.mjs';
import { execFileSync } from 'node:child_process';

async function enter(page, admin = false) {
  const id = crypto.randomUUID().slice(0, 8);
  const email = `notes-${id}@example.test`;
  const password = 'Browser-test-2026!';
  await page.goto('/inscription');
  await page.getByLabel('Adresse email').fill(email);
  await page.getByLabel('Nom de commandant').fill(`Notes-${id}`);
  await page.getByLabel('Mot de passe (12 caractères minimum)', { exact: true }).fill(password);
  await page.getByLabel('Confirmer le mot de passe').fill(password);
  await page.getByRole('button', { name: 'Créer mon compte' }).click();
  await expect(page).toHaveURL(/connexion/);
  if (admin) execFileSync('docker', ['compose', '--project-name', process.env.BROWSER_ROUTER,
    '--env-file', '.env.local', 'exec', '-T', 'app', 'php', 'bin/console', 'app:admin:grant', email],
  { stdio: ['ignore', 'pipe', 'pipe'] });
  await page.getByLabel('Adresse email').fill(email);
  await page.getByLabel('Mot de passe', { exact: true }).fill(password);
  await page.getByRole('button', { name: /Connexion/ }).click();
}

test('Navigation admin mobile : contenu immédiat, clavier, ancres et repli progressif', async ({ page, browser }, testInfo) => {
  await enter(page, true);
  await page.setViewportSize({ width: 390, height: 900 });
  await page.goto('/admin');
  const toggle = page.getByRole('button', { name: 'Menu d’administration' });
  const navigation = page.getByRole('navigation', { name: 'Console d’administration' });
  await expect(toggle).toHaveAttribute('aria-expanded', 'false');
  await expect(navigation).not.toBeVisible();
  expect((await page.getByRole('heading', { name: 'Vue d’ensemble', exact: true }).boundingBox()).y).toBeLessThan(250);
  await page.screenshot({ path: testInfo.outputPath('admin-nav-closed-390.png'), fullPage: true, animations: 'disabled' });
  await toggle.focus();
  await page.keyboard.press('Enter');
  await expect(toggle).toHaveAttribute('aria-expanded', 'true');
  await expect(navigation).toBeVisible();
  await navigation.getByRole('link', { name: 'Animation', exact: true }).click();
  await expect(page).toHaveURL(/admin\/rubriques\/events$/);
  await toggle.click();
  await navigation.getByRole('link', { name: 'Événements', exact: true }).focus();
  await page.keyboard.press('Escape');
  await expect(toggle).toBeFocused();
  await expect(toggle).toHaveAttribute('aria-expanded', 'false');
  await toggle.click();
  await navigation.getByRole('link', { name: /^Joueurs et modération/ }).click();
  await toggle.click();
  await navigation.getByRole('link', { name: 'Signalements', exact: true }).click();
  await expect(page).toHaveURL(/admin\/moderation$/);
  await expect(page.getByRole('heading', { name: 'Signalements de messages', exact: true })).toBeVisible();
  await expect(toggle).toHaveAttribute('aria-expanded', 'false');
  expect(await page.evaluate(() => Boolean(document.activeElement.closest('[hidden]')))).toBe(false);
  for (const width of [1920, 1440, 1280, 768]) {
    await page.setViewportSize({ width, height: 900 });
    await expect(navigation).toBeVisible();
    await expect(toggle).toBeHidden();
    await expect.poll(() => page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(width);
  }
  await page.setViewportSize({ width: 390, height: 900 });
  await expect(toggle).toHaveAttribute('aria-expanded', 'false');
  await toggle.click();
  await navigation.getByRole('link', { name: 'Animation', exact: true }).click();
  await toggle.click();
  await navigation.getByRole('link', { name: 'Patchnotes', exact: true }).click();
  await expect(page).toHaveURL(/admin\/patchnotes$/);
  await expect(toggle).toHaveAttribute('aria-expanded', 'false');
  const fallback = await browser.newContext({ javaScriptEnabled: false, viewport: { width: 390, height: 900 }, storageState: await page.context().storageState() });
  const fallbackPage = await fallback.newPage();
  await fallbackPage.goto('/admin/patchnotes');
  await expect(fallbackPage.getByRole('navigation', { name: 'Console d’administration' })).toBeVisible();
  await expect(fallbackPage.getByRole('button', { name: 'Menu d’administration' })).toBeHidden();
  await fallback.close();
});

test('Notes de version : brouillon privé, erreur conservée, publication et lecture', async ({ page, browser }, testInfo) => {
  await enter(page, true);
  const reader = await browser.newPage();
  await enter(reader);
  await reader.goto('/admin/patchnotes');
  await expect(reader).toHaveURL(/acces-refuse/);
  await reader.goto('/patchnotes');
  await expect(reader.getByRole('heading', { name: 'Aucune patchnote publiée' })).toBeVisible();
  await page.goto('/admin/patchnotes');
  await page.getByRole('link', { name: 'Nouvelle patchnote', exact: true }).focus();
  await page.keyboard.press('Enter');
  const create = page.locator('.admin-patchnote-form');
  await create.getByLabel('Version / tag').fill('v-test');
  await create.getByLabel('Résumé', { exact: true }).fill('Résumé public de validation.');
  await create.getByLabel('Contenu détaillé').fill('Détail public de la mise à jour.');
  await create.getByLabel('Statut').selectOption('published');
  await create.getByLabel('Date de publication (UTC)').fill('2026-11-01T12:30');
  await create.evaluate(form => { form.noValidate = true; });
  await create.getByRole('button', { name: 'Créer la patchnote', exact: true }).click();
  await expect(page.getByRole('alert')).toContainText('Tous les champs sont obligatoires');
  await expect(page.locator('h1')).toHaveText('Nouvelle patchnote');
  await expect(create.getByLabel('Statut')).toHaveValue('published');
  await expect(create.getByLabel('Date de publication (UTC)')).toHaveValue('2026-11-01T12:30');
  const title = 'Évolution' + 'Galactique'.repeat(10);
  await create.getByLabel('Titre', { exact: true }).fill(title);
  await create.getByLabel('Statut').selectOption('draft');
  await create.getByRole('button', { name: 'Créer la patchnote', exact: true }).click();
  await expect(page.getByRole('status')).toContainText('Patchnote créée');
  await reader.reload();
  await expect(reader.locator('.patchnote-card')).toHaveCount(0);
  const editor = page.locator('.patchnote-editor').filter({ hasText: title });
  await editor.getByRole('link', { name: /^Modifier la patchnote/ }).click();
  await page.getByLabel('Statut', { exact: true }).selectOption('published');
  await page.getByRole('button', { name: 'Enregistrer la patchnote', exact: true }).click();
  await expect(page.getByRole('status')).toContainText('Patchnote mise à jour');
  await reader.reload();
  await expect(reader.getByRole('heading', { name: title, exact: true })).toBeVisible();
  await reader.getByText('Lire le détail', { exact: false }).focus();
  await reader.keyboard.press('Enter');
  await expect(reader.locator('.patchnote-content')).toContainText('Détail public');
  for (const width of [1920, 1440, 1280, 768, 390]) {
    await reader.setViewportSize({ width, height: 900 });
    await page.setViewportSize({ width, height: 900 });
    await expect.poll(() => reader.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(width);
    await expect.poll(() => page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(width);
  }
  await reader.screenshot({ path: testInfo.outputPath('patchnotes-player-390.png'), fullPage: true, animations: 'disabled' });
  await editor.getByRole('link', { name: /^Modifier la patchnote/ }).click();
  await expect(page.getByLabel('Date de publication (UTC)', { exact: true })).toHaveValue(/\d{4}-\d{2}-\d{2}T\d{2}:\d{2}/);
  await page.screenshot({ path: testInfo.outputPath('patchnotes-admin-390.png'), fullPage: true, animations: 'disabled' });
  await reader.close();
});

test('Maquette admin : cinq entrées, cartes de rubrique, fil d’Ariane et accès protégés', async ({ page, browser }) => {
  test.setTimeout(120000);
  const guest = await browser.newContext();
  const guestPage = await guest.newPage();
  await guestPage.goto('/admin/rubriques/players');
  await expect(guestPage).toHaveURL(/connexion/);
  await guest.close();
  await enter(page, true);
  await page.goto('/admin');
  const navigation = page.getByRole('navigation', { name: 'Console d’administration' });
  await expect(navigation.locator('.admin-primary-link')).toHaveCount(5);
  await expect(navigation.locator('.admin-navigation-links')).toHaveCount(0);
  await expect(page.locator('.admin-home-actions a')).toHaveCount(4);
  await expect(page.locator('.admin-home-actions').getByRole('link', { name: /^Préparer un événement/ })).toHaveAttribute('href', '/admin/events/assistant');
  const sections = [
    ['players', 'Joueurs et modération', ['Guildes actives', 'Joueurs', 'Signalements', 'Feedbacks', 'Dossiers']],
    ['universe', 'Univers et activité', ['Univers', 'Alertes', 'Flottes et transports']],
    ['events', 'Animation', ['Contenus du jeu', 'Événements', 'Projets de guilde', 'Défis collectifs', 'Rotations hebdomadaires', 'Patchnotes']],
    ['ops', 'Exploitation', ['Sécurité', 'Permissions', 'État des traitements', 'Journal des actions', 'Brouillons juridiques']],
  ];
  for (const [group, label, modules] of sections) {
    await navigation.getByRole('link', { name: new RegExp('^' + label) }).click();
    await expect(page).toHaveURL(new RegExp('/admin/rubriques/' + group + '$'));
    await expect(page.getByRole('heading', { name: label, exact: true })).toBeVisible();
    const cards = page.locator('.admin-module-card');
    const card = name => cards.filter({ has: page.getByRole('heading', { name, exact: true }) });
    await expect(cards.getByRole('heading', { level: 2 })).toHaveText(modules);
    if (group === 'players') await expect(card('Guildes actives')).toHaveAttribute('href', '/admin/guildes');
    if (group === 'events') await expect(card('Projets de guilde')).toHaveAttribute('href', '/admin/projets-guilde');
    if (group === 'ops') await expect(card('Permissions')).toHaveAttribute('href', '/admin/permissions');
    await expect(navigation.locator('.admin-navigation-links')).toHaveCount(1);
    await expect(navigation.locator('.admin-primary-link[aria-current="page"]')).toHaveCount(1);
    await card(modules[0]).click();
    const breadcrumbs = page.getByRole('navigation', { name: 'Fil d’Ariane' });
    await breadcrumbs.getByRole('link', { name: label, exact: true }).click();
    await expect(page).toHaveURL(new RegExp('/admin/rubriques/' + group + '$'));
  }
  const reader = await browser.newPage();
  await enter(reader);
  const denied = await reader.goto('/admin/rubriques/players');
  await expect(reader).toHaveURL(/acces-refuse/);
  expect(denied.status()).toBe(403);
  await reader.close();
});
