import { test, expect } from './support/isolated-test.mjs';
import { execFileSync } from 'node:child_process';

async function enter(page) {
  const id = crypto.randomUUID().slice(0, 8), email = 'fleet-wizard-' + id + '@example.test', password = 'Browser-test-2026!';
  await page.goto('/inscription');
  await page.getByLabel('Adresse email').fill(email);
  await page.getByLabel('Nom de commandant').fill('Wizard-' + id);
  await page.getByLabel('Mot de passe (12 caractères minimum)', { exact: true }).fill(password);
  await page.getByLabel('Confirmer le mot de passe').fill(password);
  await page.getByRole('button', { name: 'Créer mon compte' }).click();
  await expect(page).toHaveURL(/connexion/);
  await page.getByLabel('Adresse email').fill(email);
  await page.getByLabel('Mot de passe', { exact: true }).fill(password);
  await page.getByRole('button', { name: /Connexion/ }).click();
  await page.locator('article.universe-card').first().getByRole('button').click();
  await expect(page).toHaveURL(new RegExp('planetes/[0-9]+$'));
  return email;
}

function sql(project, query) {
  execFileSync('docker', ['compose', '--project-name', project, '--env-file', '.env.local', 'exec', '-T', 'app', 'php', 'bin/console', 'dbal:run-sql', query], { encoding: 'utf8', stdio: ['ignore', 'pipe', 'pipe'] });
}

test('Le parcours de flotte guide de la composition à la confirmation', async ({ page }) => {
  test.setTimeout(90000);
  const email = await enter(page);
  const project = process.env.BROWSER_ROUTER;
  expect(project).toMatch(/^genesis-browser-test-[0-9]+-[0-9]+$/);
  const createFleet = "INSERT INTO fleet (status, mission, speed_ratio, cargo, reserved_fuel, orbit_fuel, orbit_duration_seconds, travel_seconds, dispatch_token, player_universe_id, home_planet_id) SELECT 'idle', 'idle', 1, '{}', 0, 0, 0, 0, '00000000-0000-4000-8000-000000000001', pu.id, planet.id FROM player_universe pu INNER JOIN player p ON p.id=pu.player_id INNER JOIN planet ON planet.owner_id=pu.id WHERE p.email='" + email + "' LIMIT 1";
  sql(project, createFleet);
  const addShip = "INSERT INTO fleet_ship (fleet_id, slug, quantity) SELECT f.id, 'fighter', 2 FROM fleet f INNER JOIN player_universe pu ON pu.id=f.player_universe_id INNER JOIN player p ON p.id=pu.player_id WHERE p.email='" + email + "' AND f.status='idle' LIMIT 1 ON DUPLICATE KEY UPDATE quantity=VALUES(quantity)";
  sql(project, addShip);
  await page.goto('/flottes');
  const form = page.locator('.fleet-form form').first();
  const steps = form.locator('[data-fleet-wizard-target="step"]');
  await expect(steps.nth(0)).toBeVisible();
  await expect(form.getByText('Étape 1 / 3')).toBeVisible();
  await form.getByRole('button', { name: /Choisir la destination/ }).click();
  await expect(form.locator('[data-fleet-wizard-target="error"]')).toContainText('Choisissez au moins un vaisseau');
  await form.locator('[name="ships[fighter]"]').fill('1');
  await form.getByRole('button', { name: /Choisir la destination/ }).click();
  await expect(steps.nth(1)).toBeVisible();
  await expect(form.getByRole('heading', { name: 'Choisissez la destination' })).toBeVisible();
  await form.locator('[name="coordinates"]').fill('Alpha:2');
  await form.getByRole('button', { name: /Vérifier la mission/ }).click();
  await expect(steps.nth(2)).toBeVisible();
  await expect(form.locator('[data-fleet-wizard-target="summary"]')).toContainText('Chasseur');
  await expect(form.locator('[data-fleet-wizard-target="summary"]')).toContainText('Exploration');
  await page.evaluate(() => window.scrollTo({ top: 0, behavior: 'instant' }));

  await page.setViewportSize({ width: 390, height: 844 });
  await expect.poll(() => page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(390);
  await page.keyboard.press('Escape');
  await page.evaluate(() => { document.activeElement?.blur(); window.scrollTo({ top: 0, behavior: 'instant' }); });
  await expect.poll(() => page.locator('.sidebar').evaluate(el => el.getBoundingClientRect().right)).toBeLessThanOrEqual(0);

  await form.getByRole('button', { name: /Modifier la destination/ }).click();
  await expect(steps.nth(1)).toBeVisible();
  await expect(form.locator('[name="ships[fighter]"]')).toHaveValue('1');
});
