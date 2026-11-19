import { test, expect } from './support/isolated-test.mjs';
import { prepareOwnedColony, revealVirginPlanet, runIsolatedSql, queryIsolatedSql } from './support/game-fixtures.mjs';
import AxeBuilder from '@axe-core/playwright';

async function enter(page, name) {
  const suffix = crypto.randomUUID().slice(0, 8);
  const email = 'harbour-' + suffix + '@example.test';
  const password = crypto.randomUUID() + 'aA1!';
  await page.goto('/inscription');
  await page.getByLabel('Adresse email').fill(email);
  await page.getByLabel('Nom de commandant').fill(name + '-' + suffix);
  await page.getByLabel('Mot de passe (12 caractères minimum)', { exact: true }).fill(password);
  await page.getByLabel('Confirmer le mot de passe').fill(password);
  await page.getByRole('button', { name: 'Créer mon compte' }).click();
  await page.getByLabel('Adresse email').fill(email);
  await page.getByLabel('Mot de passe', { exact: true }).fill(password);
  await page.getByRole('button', { name: /Connexion/ }).click();
  await page.locator('.universe-card').filter({ has: page.getByRole('heading', { name: /Omega/ }) }).getByRole('button').click();
  await expect(page).toHaveURL(/planetes\/\d+$/);
  const home = Number(new URL(page.url()).pathname.split('/').at(-1));
  const project = process.env.BROWSER_ROUTER;
  const member = Number(queryIsolatedSql(project, `SELECT owner_id FROM planet WHERE id=${home}`));
  return { home, member, email, project };
}

function fixtureFleet(data, home, target, mission, status, navigation, token) {
  const cargo = JSON.stringify(mission === 'orbit' ? { metal: 987654321 } : { hydrogen: 40000, metal: 120 });
  const reserve = mission === 'orbit' ? 800 : 40000;
  const nav = navigation === null ? 'NULL' : "'" + JSON.stringify(navigation) + "'";
  runIsolatedSql(data.project, `INSERT INTO fleet (player_universe_id,home_planet_id,destination_id,destination_system_id,status,mission,speed_ratio,cargo,reserved_fuel,orbit_fuel,orbit_duration_seconds,travel_seconds,departure_at,arrival_at,dispatch_token,navigation) SELECT ${data.member},${home},id,solar_system_id,'${status}','${mission}',0.1,'${cargo}',${reserve},0,86400,86400,UTC_TIMESTAMP(),${status === 'traveling' ? 'DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 DAY)' : 'NULL'},'${token}',${nav} FROM planet WHERE id=${target}`);
  const id = Number(queryIsolatedSql(data.project, `SELECT MAX(id) FROM fleet WHERE player_universe_id=${data.member}`));
  runIsolatedSql(data.project, `INSERT INTO fleet_ship (fleet_id,slug,tier,quantity) VALUES (${id},'transport','basic',3)`);
  return id;
}

async function verifyView(page, width, info, key, theme) {
  expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(width);
  for (const panel of await page.locator('.fleet-harbour .panel, [data-orbit-request]').all()) {
    expect(await panel.evaluate(element => element.scrollWidth <= element.clientWidth + 1)).toBe(true);
  }
  if (width === 390) {
    expect((await new AxeBuilder({ page }).include('.fleet-harbour').analyze()).violations).toEqual([]);
  }
  if (width !== 320) {
    await page.screenshot({ path: info.outputPath(`jeu-L2B-A-${key}-${theme}-${width}.png`), fullPage: true, animations: 'disabled' });
    if (width === 390) await page.screenshot({ path: info.outputPath(`jeu-L2B-A-${key}-${theme}-390-ecran.png`), animations: 'disabled' });
  }
}

test('L2B A : contextes, confidentialité, actualisation des demandes et stocks sans JavaScript', async ({ page, browser, baseURL }, info) => {
  test.setTimeout(300000);
  const owner = await enter(page, 'Orion');
  const { project } = owner;
  const peerContext = await browser.newContext({ baseURL });
  try {
    const peerPage = await peerContext.newPage();
    const peer = await enter(peerPage, 'Antarès');
    prepareOwnedColony(project, owner.email);
    const target = revealVirginPlanet(project, owner.email);
    runIsolatedSql(project, `UPDATE planet SET owner_id=${owner.member},name='Avant-poste des Constellations Oubliées',acquired_at=UTC_TIMESTAMP() WHERE id=${target.id}`);
    runIsolatedSql(project, `INSERT IGNORE INTO planet_resource (planet_id,resource_type,current_stock,production_rate,last_updated_at) SELECT ${target.id},resource_type,0,0,UTC_TIMESTAMP() FROM planet_resource WHERE planet_id=${owner.home}`);
    const lost = revealVirginPlanet(project, owner.email);
    const peerSource = Number(queryIsolatedSql(project, `SELECT p.id FROM planet p JOIN solar_system s ON s.id=p.solar_system_id JOIN galaxy g ON g.id=s.galaxy_id WHERE g.universe_id=(SELECT universe_id FROM player_universe WHERE id=${owner.member}) AND p.owner_id IS NULL AND p.npc_planet=0 AND p.id<>${lost.id} AND p.solar_system_id NOT IN (SELECT solar_system_id FROM planet WHERE id IN (${owner.home},${target.id})) LIMIT 1`));
    runIsolatedSql(project, `UPDATE planet SET owner_id=${peer.member},name='Origine confidentielle',acquired_at=UTC_TIMESTAMP() WHERE id=${peerSource}`);
    runIsolatedSql(project, `DELETE FROM player_discovery WHERE player_universe_id=${owner.member} AND solar_system_id=(SELECT solar_system_id FROM planet WHERE id=${peerSource})`);

    runIsolatedSql(project, `INSERT INTO fleet (player_universe_id,home_planet_id,status,mission,speed_ratio,cargo,stored_fuel,reserved_fuel,orbit_fuel,orbit_duration_seconds,travel_seconds,dispatch_token) VALUES (${owner.member},${owner.home},'idle','idle',1,'{"metal":2000,"nano":1000}',1400,0,0,0,0,'harbour-idle')`);
    const idle = Number(queryIsolatedSql(project, `SELECT MAX(id) FROM fleet WHERE player_universe_id=${owner.member}`));
    for (const [slug, quantity] of [['transport', 24], ['fighter', 1250], ['probe', 15]]) {
      runIsolatedSql(project, `INSERT INTO fleet_ship (fleet_id,slug,tier,quantity) VALUES (${idle},'${slug}','basic',${quantity})`);
    }
    const held = [owner.home, lost.id].map((home, index) => fixtureFleet(owner, home, target.id, 'station', 'station_waiting', { station: { position_planet_id: target.id } }, 'harbour-wait-' + index));
    const requestState = (recipient, planet, status) => ({ orbit_request: { version: 1, key: crypto.randomUUID().replaceAll('-', ''), recipient_id: recipient, planet_id: planet, status } });
    // Les états d'affichage sont synthétiques ; les parcours existants vérifient les départs réels.
    const incoming = [0, 1].map(index => fixtureFleet(peer, peerSource, owner.home, 'orbit', 'traveling', requestState(owner.member, owner.home, 'pending'), 'harbour-orbit-' + index));
    const legacy = fixtureFleet(peer, peerSource, owner.home, 'orbit', 'orbit_pending', null, 'harbour-legacy');
    fixtureFleet(owner, owner.home, target.id, 'orbit', 'traveling', requestState(owner.member, target.id, 'accepted_auto'), 'harbour-outgoing');

    for (const theme of ['light', 'dark']) {
      runIsolatedSql(project, `UPDATE player SET preferences=JSON_SET(CASE WHEN JSON_TYPE(preferences)='OBJECT' THEN preferences ELSE JSON_OBJECT() END,'$.theme','${theme}') WHERE email='${owner.email}'`);
      for (const width of [390, 1440, 768, 320]) {
        await page.setViewportSize({ width, height: 900 });
        await page.goto('/flottes/orbites');
        const requests = page.locator('[data-live-region="orbit-requests"]');
        await expect(requests.locator('[data-orbit-requests-target="count"]')).toHaveText('4');
        await expect(requests.locator(`[data-orbit-request="${incoming[0]}"]`)).toContainText('système non découvert');
        await expect(requests.locator(`[data-orbit-request="${incoming[0]}"]`)).not.toContainText('Origine confidentielle');
        await expect(requests).not.toContainText('987654321');
        await expect(requests.locator(`[data-orbit-request="${legacy}"] [data-orbit-deadline]`)).toHaveCount(0);
        await verifyView(page, width, info, 'orbites', theme);

        await page.goto('/flottes/quai');
        await expect(page.locator('[data-held-fleet]')).toHaveCount(2);
        await expect(page.getByRole('heading', { name: 'Stationnements en attente · tout l’empire', exact: true })).toBeVisible();
        await verifyView(page, width, info, 'quai', theme);

        expect((await page.goto(`/flottes/${held[1]}/reprendre?planet=${owner.home}`)).status()).toBe(200);
        await expect(page.getByRole('button', { name: 'Confirmer la reprise', exact: true })).toBeVisible();
        await verifyView(page, width, info, 'reprise', theme);
      }
    }
    expect((await peerPage.goto(`/flottes/${held[1]}/reprendre`)).status()).toBe(404);

    runIsolatedSql(project, `UPDATE fleet SET reserved_fuel=0,cargo=JSON_SET(cargo,'$.hydrogen',0) WHERE id=${held[1]}`);
    expect((await page.goto(`/flottes/${held[1]}/reprendre?planet=${owner.home}`)).status()).toBe(422);
    await expect(page.getByRole('alert')).toContainText('Réserve insuffisante pour ce repli');
    await expect(page.getByRole('button', { name: 'Confirmer la reprise', exact: true })).toHaveCount(0);
    runIsolatedSql(project, `UPDATE fleet SET reserved_fuel=40000,cargo=JSON_SET(cargo,'$.hydrogen',40000) WHERE id=${held[1]}`);

    await page.goto('/flottes/orbites');
    const first = page.locator(`[data-orbit-request="${incoming[0]}"]`);
    const accept = first.getByRole('button', { name: /Accepter l’orbite/ });
    await accept.focus();
    runIsolatedSql(project, `UPDATE fleet SET navigation=JSON_SET(navigation,'$.orbit_request.status','refused') WHERE id=${incoming[0]}`);
    const updated = page.waitForResponse(response => response.url().includes('/flottes/surveillance'));
    await updated;
    await expect(accept).toBeFocused();
    await expect(page.locator('[data-orbit-requests-target="count"]')).toHaveText('4');
    await page.locator('.space-navigation-menu > summary').focus();
    await expect(page.locator('[data-orbit-requests-target="count"]')).toHaveText('3');
    await expect(first).toHaveCount(0);

    // Un changement de colonie ne masque pas les stationnements d'une autre origine de l'empire.
    await page.goto('/planetes/' + target.id);
    await page.goto('/flottes/quai');
    await expect(page.locator('.fleet-hold-reserve')).toHaveCount(0);
    await expect(page.locator('[data-held-fleet]')).toHaveCount(2);
    await page.goto('/planetes/' + owner.home);
    runIsolatedSql(project, `UPDATE planet_resource SET current_stock=9950,last_updated_at=UTC_TIMESTAMP() WHERE planet_id=${owner.home} AND resource_type IN ('hydrogen','metal','nano')`);
    const plainContext = await browser.newContext({ baseURL, storageState: await page.context().storageState(), javaScriptEnabled: false, viewport: { width: 390, height: 900 } });
    try {
      const plain = await plainContext.newPage();
      await plain.goto('/flottes/quai');
      await expect(plain.getByRole('navigation', { name: 'Navigation des flottes' })).toBeVisible();
      await plain.getByRole('button', { name: 'Décharger le carburant', exact: true }).click();
      await expect(plain.getByText(/Déchargement effectué/)).toBeVisible();
      const fuel = Number(queryIsolatedSql(project, `SELECT stored_fuel FROM fleet WHERE id=${idle}`));
      expect(fuel).toBeGreaterThan(0);
      expect(fuel).toBeLessThan(1400);
      await plain.getByRole('button', { name: 'Décharger le fret', exact: true }).click();
      const metal = Number(queryIsolatedSql(project, `SELECT JSON_EXTRACT(cargo,'$.metal') FROM fleet WHERE id=${idle}`));
      expect(metal).toBeGreaterThan(0);
      expect(metal).toBeLessThan(2000);
      await plain.goto('/flottes/orbites');
      await plain.locator(`[data-orbit-request="${incoming[1]}"]`).getByRole('button', { name: /Accepter l’orbite/ }).click();
      await expect(plain.getByText('Demande d’orbite acceptée.', { exact: true })).toBeVisible();
      await plain.goto(`/flottes/${held[1]}/reprendre`);
      await plain.getByLabel('Colonie de destination', { exact: true }).selectOption(String(owner.home));
      await plain.getByRole('button', { name: 'Calculer le trajet', exact: true }).click();
      await plain.getByRole('button', { name: 'Confirmer la reprise', exact: true }).click();
      await expect(plain.getByText('Reprise du stationnement enregistrée.', { exact: true })).toBeVisible();
      expect(queryIsolatedSql(project, `SELECT status FROM fleet WHERE id=${held[1]}`)).toBe('returning');
    } finally { await plainContext.close(); }
  } finally { await peerContext.close(); }
});
