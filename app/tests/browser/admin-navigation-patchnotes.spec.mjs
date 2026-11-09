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
  expect((await page.getByRole('heading', { name: 'Administration', exact: true }).boundingBox()).y).toBeLessThan(250);
  await page.screenshot({ path: testInfo.outputPath('admin-nav-closed-390.png'), fullPage: true, animations: 'disabled' });
  await toggle.focus();
  await page.keyboard.press('Enter');
  await expect(toggle).toHaveAttribute('aria-expanded', 'true');
  await expect(navigation).toBeVisible();
  await navigation.getByRole('link', { name: 'Événements', exact: true }).focus();
  await page.keyboard.press('Escape');
  await expect(toggle).toBeFocused();
  await expect(toggle).toHaveAttribute('aria-expanded', 'false');
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
    expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(width);
  }
  await page.setViewportSize({ width: 390, height: 900 });
  await expect(toggle).toHaveAttribute('aria-expanded', 'false');
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
  const create = page.locator('.patchnote-create');
  await create.locator('summary').focus();
  await page.keyboard.press('Enter');
  await create.getByLabel('Version / tag').fill('v-test');
  await create.getByLabel('Résumé', { exact: true }).fill('Résumé public de validation.');
  await create.getByLabel('Contenu détaillé').fill('Détail public de la mise à jour.');
  await create.getByLabel('Statut').selectOption('published');
  await create.getByLabel('Date de publication').fill('2026-11-01T12:30');
  await create.locator('form').evaluate(form => { form.noValidate = true; });
  await create.getByRole('button', { name: 'Créer', exact: true }).click();
  await expect(page.getByRole('alert')).toContainText('Tous les champs sont obligatoires');
  await expect(create).toHaveAttribute('open', '');
  await expect(create.getByLabel('Statut')).toHaveValue('published');
  await expect(create.getByLabel('Date de publication')).toHaveValue('2026-11-01T12:30');
  const title = 'Évolution' + 'Galactique'.repeat(10);
  await create.getByLabel('Titre', { exact: true }).fill(title);
  await create.getByLabel('Statut').selectOption('draft');
  await create.getByRole('button', { name: 'Créer', exact: true }).click();
  await expect(page.getByRole('status')).toContainText('Patchnote créée');
  await reader.reload();
  await expect(reader.locator('.patchnote-card')).toHaveCount(0);
  const editor = page.locator('.patchnote-editor').filter({ hasText: title });
  await editor.locator('summary').click();
  await editor.getByLabel('Statut').selectOption('published');
  await editor.getByRole('button', { name: 'Enregistrer', exact: true }).click();
  await expect(page.getByRole('status')).toContainText('Patchnote mise à jour');
  await reader.reload();
  await expect(reader.getByRole('heading', { name: title, exact: true })).toBeVisible();
  await reader.getByText('Lire le détail', { exact: false }).focus();
  await reader.keyboard.press('Enter');
  await expect(reader.locator('.patchnote-content')).toContainText('Détail public');
  for (const width of [1920, 1440, 1280, 768, 390]) {
    await reader.setViewportSize({ width, height: 900 });
    await page.setViewportSize({ width, height: 900 });
    expect(await reader.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(width);
    expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(width);
  }
  await reader.screenshot({ path: testInfo.outputPath('patchnotes-player-390.png'), fullPage: true, animations: 'disabled' });
  await editor.locator('summary').click();
  await expect(editor.getByLabel('Publication', { exact: true })).toHaveValue(/\d{4}-\d{2}-\d{2}T\d{2}:\d{2}/);
  await page.screenshot({ path: testInfo.outputPath('patchnotes-admin-390.png'), fullPage: true, animations: 'disabled' });
  await reader.close();
});
