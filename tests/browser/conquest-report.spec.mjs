import { test, expect } from './support/isolated-test.mjs';
import { execFileSync } from 'node:child_process';
import { prepareOwnedColony, revealNpcPlanet, grantSecondColony, accelerateTravelingMission } from './support/game-fixtures.mjs';

async function enter(page) {
  const id = crypto.randomUUID().slice(0, 8);
  const email = 'combat-ui-' + id + '@example.test';
  const password = 'Browser-test-2026!';
  await page.goto('/inscription');
  await page.getByLabel('Adresse email').fill(email);
  await page.getByLabel('Nom de commandant').fill('Combat-UI-' + id);
  await page.getByLabel('Mot de passe (12 caractères minimum)', { exact: true }).fill(password);
  await page.getByLabel('Confirmer le mot de passe').fill(password);
  await page.getByRole('button', { name: 'Créer mon compte' }).click();
  await expect(page).toHaveURL(/connexion/);
  await page.getByLabel('Adresse email').fill(email);
  await page.getByLabel('Mot de passe', { exact: true }).fill(password);
  await page.getByRole('button', { name: /Connexion/ }).click();
  await page.locator('article.universe-card').first().getByRole('button').click();
  await expect(page).toHaveURL(/planetes\/\d+$/);
  return email;
}

function sql(project, query) {
  execFileSync('docker', ['compose', '--project-name', project, '--env-file', '.env.local', 'exec', '-T', 'app', 'php', 'bin/console', 'dbal:run-sql', query], { encoding: 'utf8', stdio: ['ignore', 'pipe', 'pipe'] });
}

test('Conquête PNJ : rapport détaillé, résultat, responsive et confidentialité', async ({ page, browser }, testInfo) => {
  test.setTimeout(180000);
  const email = await enter(page);
  const project = process.env.BROWSER_ROUTER;
  expect(project).toMatch(/^genesis-browser-test-[0-9]+-[0-9]+$/);
  prepareOwnedColony(project,email);
  grantSecondColony(project,email);
  const target = revealNpcPlanet(project,email);

  // Une inscription neuve ne crée pas de flotte tant qu'aucune unité n'est construite.
  const createFleet = "INSERT INTO fleet (status, mission, speed_ratio, cargo, reserved_fuel, orbit_fuel, orbit_duration_seconds, travel_seconds, dispatch_token, player_universe_id, home_planet_id) SELECT 'idle', 'idle', 1, '{}', 0, 0, 0, 0, '00000000-0000-4000-8000-000000000001', pu.id, planet.id FROM player_universe pu INNER JOIN player p ON p.id=pu.player_id INNER JOIN planet ON planet.owner_id=pu.id WHERE p.email='" + email + "' LIMIT 1";
  sql(project, createFleet);
  // Le fixture isolé crédite une flotte puissante; les écrans de production sont couverts par les scénarios de chantier.
  for (const [slug, quantity] of [['fighter', 10], ['colony_ship', 1]]) {
    const insert = "INSERT INTO fleet_ship (fleet_id, slug, quantity) SELECT f.id, '" + slug + "', " + quantity + " FROM fleet f INNER JOIN player_universe pu ON pu.id=f.player_universe_id INNER JOIN player p ON p.id=pu.player_id WHERE p.email='" + email + "' AND f.status='idle' LIMIT 1 ON DUPLICATE KEY UPDATE quantity=VALUES(quantity)";
    sql(project, insert);
  }

  await page.goto('/flottes');
  const form = page.locator('.fleet-form form').first();
  await form.locator('[name="ships[fighter]"]').fill('10');
  await form.locator('[name="ships[colony_ship]"]').fill('1');
  await form.getByRole('button', { name: /Choisir la destination/ }).click();
  await form.locator('[name="mission"]').selectOption('conquer');
  const targetId = target.id;
  await form.locator('[name="coordinates"]').fill(target.coordinates);
  await form.locator('[name="ratio"]').selectOption('0.1');
  await form.getByRole('button', { name: /Vérifier la mission/ }).click();
  await form.getByRole('button', { name: 'Estimer le trajet' }).click();
  await expect(form.locator('.flight-estimate')).toContainText('Carburant aller-retour');
  await form.getByRole('button', { name: /Envoyer la flotte/ }).click();

  accelerateTravelingMission(project, email, 'conquer');
  await expect.poll(async () => {
    await page.reload();
    return page.locator('[data-status="attacking"]').count();
  }, { timeout: 45000, intervals: [1000] }).toBe(1);

  const expireCombat = "UPDATE combat_event SET started_at=UTC_TIMESTAMP()-INTERVAL 2 MINUTE, resolve_at=UTC_TIMESTAMP()-INTERVAL 2 SECOND WHERE status='pending' AND planet_id=" + targetId;
  sql(project, expireCombat);
  const dispatchCombatNow = "INSERT INTO outbox_message (kind, target_id, token, due_at) SELECT 'combat', id, dispatch_token, UTC_TIMESTAMP()-INTERVAL 2 SECOND FROM combat_event WHERE status='pending' AND planet_id=" + targetId;
  sql(project, dispatchCombatNow);
  const reportLink = page.locator('a[href*="/rapports/combat/"]').first();
  await expect.poll(async () => {
    await page.goto('/rapports');
    return reportLink.count();
  }, { timeout: 45000, intervals: [1000] }).toBe(1);

  await page.goto('/rapports?type=economy');
  await expect(page.locator('a[href*="/rapports/combat/"]')).toHaveCount(0);
  await expect(page.getByRole('heading', { name: 'Aucun rapport économique' })).toBeVisible();
  await page.goto('/rapports?type=combat');
  await expect(reportLink).toBeVisible();
  await page.screenshot({ path: testInfo.outputPath('reports-combat.png'), fullPage: true, animations: 'disabled' });
  const reportName = (await reportLink.textContent()).trim();
  await reportLink.click();
  const reportUrl = page.url();
  await expect(page.getByRole('heading', { name: /Rapport ·/ })).toBeVisible();
  await expect(page.getByRole('heading', { name: 'Pertes constatées', exact: true })).toBeVisible();
  const losses = page.getByRole('region', { name: 'Tableau des pertes, défilant horizontalement' });
  await expect(losses).toBeVisible();
  expect(await page.locator('main table tbody tr').count()).toBeGreaterThan(0);
  await losses.focus();
  await expect(losses).toBeFocused();
  await expect(page.getByRole('heading', { name: 'Conquête', exact: true })).toBeVisible();
  await losses.evaluate(element => element.blur());

  await expect(page.locator('main')).toContainText(/Vous avez conquis cette planète PNJ|La conquête a échoué|la limite de colonies empêche|déjà été revendiquée|aucun vaisseau colonie n’a survécu/i);
  await expect(page.getByRole('link', { name: 'Tous les rapports' })).toBeVisible();
  for (const width of [1440, 768, 390]) {
    await page.setViewportSize({ width, height: 900 });
    await expect.poll(() => page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(width);
  }
  await page.screenshot({ path: testInfo.outputPath('combat-detail-390.png'), fullPage: true, animations: 'disabled' });
  await page.goto(reportUrl);
  await page.evaluate(() => window.scrollTo(0, 0));

  expect(reportName).toBeTruthy();

  const outsider = await browser.newPage();
  await enter(outsider);
  const deniedReport = await outsider.goto(reportUrl);
  expect(deniedReport?.status()).toBe(404);
  await outsider.close();
});
