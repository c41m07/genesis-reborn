import { test, expect } from './support/isolated-test.mjs';
import { execFileSync } from 'node:child_process';

async function registerAndLogin(page) {
  const id = crypto.randomUUID().slice(0, 8);
  const email = `admin-events-${id}@example.test`;
  const password = 'Browser-test-2026!';

  await page.goto('/inscription');
  await page.getByLabel('Adresse email').fill(email);
  await page.getByLabel('Nom de commandant').fill(`AdminEvents-${id}`);
  await page.getByLabel('Mot de passe (12 caractères minimum)', { exact: true }).fill(password);
  await page.getByLabel('Confirmer le mot de passe').fill(password);
  await page.getByRole('button', { name: 'Créer mon compte' }).click();
  await expect(page).toHaveURL(/connexion/);
  await page.getByLabel('Adresse email').fill(email);
  await page.getByLabel('Mot de passe', { exact: true }).fill(password);
  await page.getByRole('button', { name: /Connexion/ }).click();

  await page.goto('/admin/events');
  await expect(page).toHaveURL(/acces-refuse/);
  const project = process.env.BROWSER_ROUTER;
  expect(project).toMatch(/^genesis-browser-test-[0-9]+-[0-9]+$/);
  execFileSync('docker', [
    'compose', '--project-name', project, '--env-file', '.env.local', 'exec', '-T',
    'app', 'php', 'bin/console', 'app:admin:grant', email,
  ], { encoding: 'utf8', stdio: ['ignore', 'pipe', 'pipe'] });

  await page.context().clearCookies();
  await page.goto('/connexion');
  await page.getByLabel('Adresse email').fill(email);
  await page.getByLabel('Mot de passe', { exact: true }).fill(password);
  await page.getByRole('button', { name: /Connexion/ }).click();
  await page.goto('/admin/events');
}

test('Formulaire d’événements admin lisible aux tailles desktop, tablette et mobile', async ({ page }) => {
  await registerAndLogin(page);
  const form = page.locator('form.admin-event-form').first();
  const description = form.locator('textarea[name="description"]');
  const visibility = form.getByLabel('Visible aux joueurs');

  for (const width of [1440, 768, 390]) {
    await page.setViewportSize({ width, height: 900 });
    await expect.poll(() => page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(width);
    await expect(description).toBeVisible();
    await expect.poll(() => description.evaluate(element => element.getBoundingClientRect().width)).toBeGreaterThan(240);
    await expect(visibility).toBeVisible();
    const checkboxSize = await visibility.evaluate(element => {
      const rect = element.getBoundingClientRect();
      return { width: rect.width, height: rect.height };
    });
    expect(checkboxSize.width).toBe(18);
    expect(checkboxSize.height).toBe(18);
    await expect(form.getByRole('button', { name: 'Créer' })).toBeEnabled();
  }
});
