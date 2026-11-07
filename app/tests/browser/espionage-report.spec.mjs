import { test, expect } from './support/isolated-test.mjs';
import { prepareOwnedColony, revealNpcPlanet, accelerateQueuedItem, accelerateTravelingMission, runIsolatedSql } from './support/game-fixtures.mjs';

async function enter(page) {
  const id = crypto.randomUUID().slice(0, 8);
  const email = 'spy-ui-' + id + '@example.test';
  const password = 'Browser-test-2026!';
  await page.goto('/inscription');
  await page.getByLabel('Adresse email').fill(email);
  await page.getByLabel('Nom de commandant').fill('Spy-UI-' + id);
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

test('Espionnage PNJ : envoyer une sonde et consulter le rapport privé', async ({ page }) => {
  test.setTimeout(180000);
  const email=await enter(page);
  prepareOwnedColony(process.env.BROWSER_ROUTER,email);
  // Conditions initiales du chantier ; la construction et la mission restent jouées dans l’interface.
  const planet = Number(new URL(page.url()).pathname.split('/').pop());
  runIsolatedSql(process.env.BROWSER_ROUTER, `INSERT INTO planet_building (planet_id,slug,tier,level) VALUES (${planet},'mine_metal','basic',2)`);
  runIsolatedSql(process.env.BROWSER_ROUTER, `INSERT INTO player_technology (player_universe_id,slug,tier,level) SELECT owner_id,'naval_engineering','basic',1 FROM planet WHERE id=${planet}`);
  const target = revealNpcPlanet(process.env.BROWSER_ROUTER,email);
  const card = slug => page.locator('#item-'+slug+'-basic');
  await page.getByRole('link', { name: 'Bâtiments', exact: true }).click();
  await card('shipyard').getByRole('button', { name: /Améliorer .* niveau 1/ }).click();
  accelerateQueuedItem(process.env.BROWSER_ROUTER, email, 'building', 'shipyard');
  await page.reload();
  await expect(page.locator('#building-tab-shipyard-basic .building-rank-level')).toHaveText('1', { timeout: 30000 });
  await page.getByRole('link', { name: 'Chantier spatial', exact: true }).click();
  await card('probe').getByRole('button', { name: /Construire/ }).click();
  accelerateQueuedItem(process.env.BROWSER_ROUTER, email, 'shipyard', 'probe');
  await page.reload();
  await expect(page.locator('#building-tab-probe-basic .building-rank-level')).toHaveText('1', { timeout: 60000 });

  await page.getByRole('link', { name: 'Flottes et missions', exact: true }).click();
  const form = page.locator('.fleet-form form').first();
  await form.locator('[name="ships[probe]"]').fill('1');
  await form.getByRole('button', { name: /Choisir la destination/ }).click();
  await form.locator('[name="mission"]').selectOption('spy');
  await form.locator('[name="coordinates"]').fill(target.coordinates);
  await form.locator('[name="ratio"]').selectOption('0.1');
  await form.getByRole('button', { name: /Vérifier la mission/ }).click();
  await form.getByRole('button', { name: 'Estimer le trajet' }).click();
  await expect(form.locator('.flight-estimate')).toContainText('Carburant aller-retour');
  await form.getByRole('button', { name: /Envoyer la flotte/ }).click();
  await expect(page.locator('[data-status="traveling"]')).toHaveCount(1);
  accelerateTravelingMission(process.env.BROWSER_ROUTER, email, 'spy');
  await expect.poll(() => page.locator('[data-status="completed"]').count(), { timeout: 90000, intervals: [1000] }).toBe(1);

  await page.getByRole('link', { name: 'Rapports', exact: true }).click();
  const reportLink = page.locator('a[href*="/rapports/espionnage/"]').first();
  await expect(reportLink).toBeVisible();

  const reportName = (await reportLink.locator('.report-copy strong').textContent()).replace('Renseignements · ', '').trim();
  await reportLink.click();
  await expect(page.getByRole('heading', { name: 'Rapport d’espionnage', exact: true })).toBeVisible();
  await expect(page.locator('.report-heading')).toContainText(reportName);
  await expect(page.getByRole('status')).toContainText(/Sondes détectées|catégorie.*renseignements obtenue/);
  await expect(page.getByRole('link', { name: 'Retour aux rapports' })).toBeVisible();

  const reportUrl = page.url();
  await page.setViewportSize({ width: 390, height: 844 });
  await page.goto(reportUrl);
  await expect.poll(() => page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(390);
  await page.evaluate(() => window.scrollTo(0, 0));

});
