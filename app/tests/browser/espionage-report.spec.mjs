import { test, expect } from './support/isolated-test.mjs';
import { prepareOwnedColony, completedFleetCount, revealNpcPlanet, accelerateQueuedItem, accelerateTravelingMission, runIsolatedSql, queryIsolatedSql, planetCoordinates } from './support/game-fixtures.mjs';

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
  runIsolatedSql(process.env.BROWSER_ROUTER, `INSERT INTO planet_building (planet_id,slug,tier,level) VALUES (${planet},'mine_metal','basic',2),(${planet},'mine_nano','basic',2)`);
  runIsolatedSql(process.env.BROWSER_ROUTER, `INSERT INTO player_technology (player_universe_id,slug,tier,level) SELECT owner_id,'naval_engineering','basic',1 FROM planet WHERE id=${planet}`);
  const target = revealNpcPlanet(process.env.BROWSER_ROUTER,email);
  const card = slug => page.locator('#item-'+slug+'-basic');
  await page.getByRole('navigation', { name: 'Navigation principale', exact: true }).getByRole('link', { name: 'Bâtiments', exact: true }).click();
  await card('shipyard').getByRole('button', { name: /Améliorer .* niveau 1/ }).click();
  accelerateQueuedItem(process.env.BROWSER_ROUTER, email, 'building', 'shipyard');
  await page.reload();
  await expect(page.locator('#item-shipyard-basic .catalogue-level-badge')).toHaveText('Niv. 1', { timeout: 30000 });
  await page.getByRole('navigation', { name: 'Navigation principale', exact: true }).getByRole('link', { name: 'Chantier spatial', exact: true }).click();
  await card('probe').getByRole('button', { name: /Construire/ }).click();
  accelerateQueuedItem(process.env.BROWSER_ROUTER, email, 'shipyard', 'probe');
  await page.reload();
  await expect(page.locator('#item-probe-basic .catalogue-level-badge')).toHaveText('1 en réserve', { timeout: 60000 });

  await page.getByRole('navigation',{name:page.viewportSize().width<=740?'Navigation mobile':'Navigation principale'}).getByRole('link', { name: 'Flottes', exact: true }).click();
   await page.getByRole('navigation',{name:'Navigation des flottes'}).getByRole('link',{name:'Préparer une mission',exact:true}).click();
  const form = page.locator('.fleet-form form').first();
  await form.locator('[name="ships[probe]"]').fill('1');
  await form.getByRole('button', { name: /Choisir la destination/ }).click();
  await form.locator('[name="mission"]').selectOption('spy');
  await form.getByLabel('Destination personnalisée', { exact: true }).check();await form.locator('[name="coordinates"]').fill(target.coordinates);
  await form.locator('[name="ratio"]').selectOption('0.1');
  await form.getByRole('button', { name: /Vérifier la mission/ }).click();
  await form.getByRole('button', { name: 'Estimer le trajet' }).click();
  await expect(form.locator('.flight-estimate')).toContainText('Carburant aller-retour');
  await form.getByRole('button', { name: /Envoyer la flotte/ }).click();
  await expect(page.locator('[data-status="traveling"]')).toHaveCount(1);
  accelerateTravelingMission(process.env.BROWSER_ROUTER, email, 'spy');
  await expect.poll(() => completedFleetCount(process.env.BROWSER_ROUTER, email), { timeout: 90000, intervals: [1000] }).toBe(1);
  await expect(page.locator('[data-active-fleet]')).toHaveCount(0, { timeout: 15000 });

  await page.getByRole('link', { name: 'Rapports', exact: true }).click();
  const reportLink = page.locator('a[href*="/rapports/espionnage/"]').first();
  await expect(reportLink).toBeVisible();

  const reportTitle = (await reportLink.locator('.report-copy strong').textContent()).trim();
  const reportName = reportTitle.replace('Renseignements · ', '');
  await reportLink.click();
  await expect(page.getByRole('heading', { name: 'Rapport d’espionnage', exact: true })).toBeVisible();
  await expect(page.locator('.report-heading')).toContainText(reportName);
  const risk=Number(queryIsolatedSql(process.env.BROWSER_ROUTER,`SELECT detection_chance FROM espionage_report WHERE id=${Number(new URL(page.url()).pathname.split('/').pop())}`));
  expect(risk).toBeGreaterThanOrEqual(5);expect(risk).toBeLessThanOrEqual(95);
  await expect(page.locator('.report-summary')).toContainText(`Risque de détection estimé : ${risk} %`);
  await expect(page.getByRole('status')).toContainText(/Sondes détectées|catégorie.*renseignements obtenue/);
  await expect(page.getByRole('link', { name: 'Retour aux rapports' })).toBeVisible();

  const reportUrl = page.url();
  await page.getByRole('link', { name: 'Reprendre ces renseignements dans le simulateur' }).click();
  const source = page.locator('.simulation-wizard-sources summary');
  await expect(source).toContainText(reportTitle);
  if (await source.locator('..').getAttribute('open') === null) {
    await source.click();
  }
  await expect(page.locator('#simulation-intel option:checked')).toContainText(reportTitle);
  await expect(page.locator('#simulation-intel option:checked')).not.toContainText('Rapport n°');

  await page.setViewportSize({ width: 390, height: 844 });
  await page.goto(reportUrl);
  await expect.poll(() => page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(390);
  await page.evaluate(() => window.scrollTo(0, 0));

});

 test('Exploration : le départ programme une arrivée réelle sans accélération', async ({ page }) => {
   test.setTimeout(180000);
   const email = await enter(page), project = process.env.BROWSER_ROUTER;
   const planet = Number(new URL(page.url()).pathname.split('/').pop());
   prepareOwnedColony(project, email);
   runIsolatedSql(project, `INSERT INTO fleet (player_universe_id,home_planet_id,status,mission,speed_ratio,cargo,reserved_fuel,orbit_fuel,orbit_duration_seconds,travel_seconds,dispatch_token)
     SELECT p.owner_id,p.id,'idle','idle',1,'{}',0,0,0,0,UUID() FROM planet p WHERE p.id=${planet}
     AND NOT EXISTS (SELECT 1 FROM fleet WHERE home_planet_id=p.id AND status='idle')`);
   runIsolatedSql(project, `INSERT INTO fleet_ship (fleet_id,slug,tier,quantity)
     SELECT id,'probe','basic',1 FROM fleet WHERE home_planet_id=${planet} AND status='idle'
     ON DUPLICATE KEY UPDATE quantity=1`);
   // Le système de la colonie est déjà connu. Choisir une destination inconnue proche
   // à partir de la carte et conserver le trajet réellement exécuté par les workers.
   await page.goto('/carte-v2');
   const map = JSON.parse(await page.locator('[data-galaxy-v2-map-value]').getAttribute('data-galaxy-v2-map-value'));
   const system = map.systems.filter(candidate => !candidate.known && Number.isFinite(candidate.distanceFromOrigin))
     .sort((a, b) => a.distanceFromOrigin - b.distanceFromOrigin)[0];
   expect(system).toBeDefined();
   const coordinates = system.galaxy + ':' + system.number;
   await page.getByRole('navigation',{name:page.viewportSize().width<=740?'Navigation mobile':'Navigation principale'}).getByRole('link', { name: 'Flottes', exact: true }).click();
   await page.getByRole('navigation',{name:'Navigation des flottes'}).getByRole('link',{name:'Préparer une mission',exact:true}).click();
   const form = page.locator('.fleet-form form').first();
   await form.locator('[name="ships[probe]"]').fill('1');
   await form.getByRole('button', { name: /Choisir la destination/ }).click();
   await form.locator('[name="mission"]').selectOption('explore');
   await form.getByLabel('Destination personnalisée', { exact: true }).check();await form.locator('[name="coordinates"]').fill(coordinates);
   await form.locator('[name="ratio"]').selectOption('1');
   await form.getByRole('button', { name: /Vérifier la mission/ }).click();
   await form.getByRole('button', { name: 'Estimer le trajet' }).click();
   await expect(form.locator('.flight-estimate')).toContainText('Carburant aller-retour');
   await form.getByRole('button', { name: /Envoyer la flotte/ }).click();
   await expect(page.locator('[data-status="traveling"]')).toHaveCount(1);
   const fleet = Number(queryIsolatedSql(project, `SELECT id FROM fleet WHERE home_planet_id=${planet} AND mission='explore' ORDER BY id DESC LIMIT 1`));
   expect(queryIsolatedSql(project, `SELECT LENGTH(dispatch_token)>0 FROM fleet WHERE id=${fleet}`)).toBe('1');
   // Aucun événement n'est ajouté par le test : le relais et le worker traitent le départ réel.
   await expect.poll(() => queryIsolatedSql(project, `SELECT status FROM fleet WHERE id=${fleet}`), { timeout: 90000, intervals: [1000] }).toBe('completed');
   await expect(page.locator('[data-active-fleet]')).toHaveCount(0, { timeout: 15000 });
   expect(queryIsolatedSql(project, `SELECT quantity FROM fleet_ship WHERE fleet_id=${fleet} AND slug='probe'`)).toBe('0');
 });
