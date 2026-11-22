import { test, expect } from './support/isolated-test.mjs';
import { queryIsolatedSql, runIsolatedSql } from './support/game-fixtures.mjs';

test('Trafic de campagne : sonde privée dans Flottes, Vue générale et Galaxie', async ({ page }) => {
  const stamp = crypto.randomUUID().slice(0, 8);
  const email = 'traffic-' + stamp + '@example.test';
  const password = crypto.randomUUID() + '!Aa1';
  const project = process.env.BROWSER_ROUTER;
  await page.goto('/inscription');
  await page.getByLabel('Adresse email').fill(email);
  await page.getByLabel('Nom de commandant').fill('Trafic-' + stamp);
  await page.getByLabel('Mot de passe (12 caractères minimum)', { exact: true }).fill(password);
  await page.getByLabel('Confirmer le mot de passe').fill(password);
  await page.getByRole('button', { name: 'Créer mon compte' }).click();
  await expect(page).toHaveURL(/connexion/);
  await page.getByLabel('Adresse email').fill(email);
  await page.getByLabel('Mot de passe', { exact: true }).fill(password);
  await page.getByRole('button', { name: /Connexion/ }).click();
  await page.locator('article.universe-card').filter({ has: page.getByRole('heading', { name: /Omega/ }) }).getByRole('button').click();
  await expect(page).toHaveURL(/planetes\/\d+$/);
  const origin = Number(new URL(page.url()).pathname.split('/').at(-1));
  const member = Number(queryIsolatedSql(project, 'SELECT owner_id FROM planet WHERE id=' + origin));
  const system = Number(queryIsolatedSql(project, 'SELECT solar_system_id FROM planet WHERE id=' + origin));
  const galaxy = Number(queryIsolatedSql(project, 'SELECT galaxy_id FROM solar_system WHERE id=' + system));
  expect(Math.min(origin, member, system, galaxy)).toBeGreaterThan(0);
  runIsolatedSql(project, "INSERT INTO fleet (player_universe_id,home_planet_id,status,mission,speed_ratio,cargo,reserved_fuel,orbit_fuel,orbit_duration_seconds,travel_seconds,dispatch_token) SELECT owner_id,id,'idle','idle',1,'{}',0,0,0,0,'' FROM planet WHERE id=" + origin + " AND NOT EXISTS (SELECT 1 FROM fleet WHERE home_planet_id=" + origin + " AND status='idle')");
  runIsolatedSql(project, "INSERT INTO fleet_ship (fleet_id,slug,tier,quantity) SELECT id,'probe','basic',1 FROM fleet WHERE home_planet_id=" + origin + " AND status='idle' ON DUPLICATE KEY UPDATE quantity=1");
  runIsolatedSql(project, "UPDATE planet_resource SET current_stock=5000 WHERE planet_id=" + origin + " AND resource_type='hydrogen'");

  await page.goto('/evenements/campagne-pirate');
  await page.getByRole('button', { name: 'Envoyer la sonde', exact: true }).click();
  await expect(page).toHaveURL(/campagne-pirate\/1$/);
  // Une suite plus chargée peut laisser tourner le vrai relay ; garder la sonde du fixture en trajet.
  runIsolatedSql(project, "UPDATE pirate_reconnaissance SET due_at=DATE_ADD(UTC_TIMESTAMP(), INTERVAL 1 HOUR) WHERE membership_id=" + member + " AND reported_at IS NULL");

  for (const url of ['/flottes', '/planetes/' + origin]) {
    await page.goto(url);
    const flights = page.getByRole('region', { name: 'Flottes en vol dans votre empire' });
    const scout = flights.locator('[data-campaign-kind="scout"]');
    await expect(scout).toHaveCount(1);
    await expect(scout).toContainText('Sonde de reconnaissance');
    await expect(scout.locator('time')).toHaveAttribute('datetime', /T/);
    await expect(scout.getByRole('button')).toHaveCount(0);
    await expect(scout.getByRole('link', { name: 'Consulter le dossier de campagne' })).toHaveAttribute('href', '/evenements/campagne-pirate/1');
    await expect(flights.locator('[data-active-fleet]')).toHaveCount(0);
    await expect(page.locator('[data-campaign-kind="pirates"],[data-campaign-kind="convoy"]')).toHaveCount(0);
  }

  await page.goto('/carte-v2?galaxy=' + galaxy + '&system=' + system);
  const shell = page.locator('.galaxy-v2');
  const read = JSON.parse(await shell.getAttribute('data-galaxy-v2-map-value'));
  expect(read.campaignTraffic).toHaveLength(1);
  expect(read.campaignTraffic[0]).toMatchObject({ kind: 'scout', mission: 1, systems: [system] });
  expect(Object.keys(read.campaignTraffic[0]).sort()).toEqual(['eta', 'kind', 'label', 'mission', 'overdue', 'systems']);
  const payload = await page.evaluate(async id => {
    const response = await fetch('/flottes/surveillance?campaign=1&galaxy=' + id);
    return { data: await response.json(), cache: response.headers.get('cache-control') };
  }, galaxy);
  expect(payload.cache).toMatch(/no-store/);
  expect(payload.data.campaignTraffic).toEqual(read.campaignTraffic);
  await expect(page.locator('.gv2-flight-log [data-campaign-kind="scout"]')).toBeVisible();
  await expect(page.locator('.gv2-flight-log [data-campaign-kind="pirates"]')).toHaveCount(0);
});
