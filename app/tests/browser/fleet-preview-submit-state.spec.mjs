import { test, expect } from './support/isolated-test.mjs';
import { execFileSync } from 'node:child_process';

async function enter(page) {
  const id = crypto.randomUUID().slice(0, 8);
  const email = 'fleet-preview-' + id + '@example.test';
  const password = 'Browser-test-2026!';

  await page.goto('/inscription');
  await page.getByLabel('Adresse email').fill(email);
  await page.getByLabel('Nom de commandant').fill('Preview-' + id);
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

test('La prévisualisation valide autorise l’envoi, les erreurs backend et réseau le bloquent', async ({ page }) => {
  test.setTimeout(90000);
  const email = await enter(page);
  const project = process.env.BROWSER_ROUTER;
  expect(project).toMatch(/^genesis-browser-test-[0-9]+-[0-9]+$/);

  const createFleet = "INSERT INTO fleet (status, mission, speed_ratio, cargo, reserved_fuel, orbit_fuel, orbit_duration_seconds, travel_seconds, dispatch_token, player_universe_id, home_planet_id) SELECT 'idle', 'idle', 1, '{}', 0, 0, 0, 0, '00000000-0000-4000-8000-000000000001', pu.id, planet.id FROM player_universe pu INNER JOIN player p ON p.id=pu.player_id INNER JOIN planet ON planet.owner_id=pu.id WHERE p.email='" + email + "' LIMIT 1";
  sql(project, createFleet);
  const addFighter = "INSERT INTO fleet_ship (fleet_id, slug, quantity) SELECT f.id, 'fighter', 1 FROM fleet f INNER JOIN player_universe pu ON pu.id=f.player_universe_id INNER JOIN player p ON p.id=pu.player_id WHERE p.email='" + email + "' AND f.status='idle' LIMIT 1 ON DUPLICATE KEY UPDATE quantity=VALUES(quantity)";
  sql(project, addFighter);
  const addProbe = "INSERT INTO fleet_ship (fleet_id, slug, quantity) SELECT f.id, 'probe', 1 FROM fleet f INNER JOIN player_universe pu ON pu.id=f.player_universe_id INNER JOIN player p ON p.id=pu.player_id WHERE p.email='" + email + "' AND f.status='idle' LIMIT 1 ON DUPLICATE KEY UPDATE quantity=VALUES(quantity)";
  sql(project, addProbe);

  await page.goto('/flottes');
  const form = page.locator('.fleet-form form').first();
  const estimate = form.locator('[data-fleet-preview-target="result"]');
  await expect(estimate).toHaveAttribute('aria-live', 'polite');
  await form.locator('[name="ships[fighter]"]').fill('1');
  await form.locator('[name="ships[probe]"]').fill('1');
  await form.getByRole('button', { name: /Choisir la destination/ }).click();
  await form.locator('[name="coordinates"]').fill('Alpha:2');
  await form.locator('[name="ratio"]').selectOption('0.1');
  await form.getByRole('button', { name: /Vérifier la mission/ }).click();
  const send = form.getByRole('button', { name: /Envoyer la flotte/ });
  await expect(estimate).toContainText('Carburant aller-retour', { timeout: 10000 });
  let releaseEstimate;
  const estimateGate = new Promise(resolve => { releaseEstimate = resolve; });
  await page.route('**/flottes/estimer', async route => { await estimateGate; await route.continue(); }, { times: 1 });
  await form.getByRole('button', { name: 'Estimer le trajet' }).click();
  await expect(estimate).toContainText('Estimation en cours');
  await expect(send).toBeDisabled();
  await page.setViewportSize({ width: 390, height: 844 });
  await expect(page.locator('.sidebar')).toHaveAttribute('aria-hidden', 'true');
  await expect.poll(() => page.locator('.sidebar').evaluate(element => element.getBoundingClientRect().right)).toBeLessThanOrEqual(0);
  await expect.poll(() => page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(390);

  releaseEstimate();
  await expect.poll(() => send.isDisabled(), { timeout: 10000 }).toBe(false);
  await expect(estimate).not.toHaveClass(/error/);

  await form.getByRole('button', { name: /Modifier la destination/ }).click();
  await form.getByRole('button', { name: /Retour à la composition/ }).click();
  await form.locator('[name="ships[probe]"]').fill('0');
  await form.getByRole('button', { name: /Choisir la destination/ }).click();
  await form.getByRole('button', { name: /Vérifier la mission/ }).click();
  await expect(estimate).toContainText('Une sonde exploratrice est nécessaire', { timeout: 10000 });
  await expect(send).toBeDisabled();

  await form.getByRole('button', { name: /Modifier la destination/ }).click();
  await form.getByRole('button', { name: /Retour à la composition/ }).click();
  await form.locator('[name="ships[probe]"]').fill('1');
  await form.getByRole('button', { name: /Choisir la destination/ }).click();
  await form.getByRole('button', { name: /Vérifier la mission/ }).click();
  await page.route('**/flottes/estimer', route => route.abort(), { times: 1 });
  await form.getByRole('button', { name: 'Estimer le trajet' }).click();
  await expect(estimate).toHaveClass(/error/, { timeout: 10000 });
  await expect(send).toBeDisabled();
});
