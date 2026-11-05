import { test, expect } from './support/isolated-test.mjs';
import { execFileSync } from 'node:child_process';

test('Les filtres des retours admin restent utilisables sans débordement', async ({ page }, testInfo) => {
  const id = crypto.randomUUID().slice(0, 8);
  const email = `feedback-admin-${id}@example.test`;
  const password = 'Browser-test-2026!';
  await page.goto('/inscription');
  await page.getByLabel('Adresse email').fill(email);
  await page.getByLabel('Nom de commandant').fill(`FeedbackAdmin-${id}`);
  await page.getByLabel('Mot de passe (12 caractères minimum)', { exact: true }).fill(password);
  await page.getByLabel('Confirmer le mot de passe').fill(password);
  await page.getByRole('button', { name: 'Créer mon compte' }).click();
  await expect(page).toHaveURL(/connexion/);
  execFileSync('docker', [
    'compose', '--project-name', process.env.BROWSER_ROUTER, '--env-file', '.env.local',
    'exec', '-T', 'app', 'php', 'bin/console', 'app:admin:grant', email,
  ], { encoding: 'utf8', stdio: ['ignore', 'pipe', 'pipe'] });
  await page.getByLabel('Adresse email').fill(email);
  await page.getByLabel('Mot de passe', { exact: true }).fill(password);
  await page.getByRole('button', { name: /Connexion/ }).click();
  await page.goto('/admin/feedbacks');
  const filters = page.locator('.feedback-admin-filters');

  for (const width of [1920, 1440, 1280, 930, 768, 390]) {
    await page.setViewportSize({ width, height: 900 });
    await expect(filters.getByRole('button', { name: 'Appliquer les filtres' })).toBeVisible();
    expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(width);
    for (const field of await filters.locator('input, select, button').all()) {
      const bounds = await field.boundingBox();
      expect(bounds.x).toBeGreaterThanOrEqual(0);
      expect(bounds.x + bounds.width).toBeLessThanOrEqual(width);
    }
    if ([930, 390].includes(width)) {
      await page.screenshot({ path: testInfo.outputPath(`feedbacks-${width}.png`), fullPage: true });
    }
  }
  await filters.getByLabel('Recherche').fill('sans-résultat');
  await filters.getByRole('button', { name: 'Appliquer les filtres' }).click();
  await expect(filters.getByLabel('Recherche')).toHaveValue('sans-résultat');
  await expect(page.getByRole('heading', { name: 'Aucun feedback trouvé' })).toBeVisible();
});
