import { test, expect } from './support/isolated-test.mjs';
import { queryIsolatedSql, runIsolatedSql } from './support/game-fixtures.mjs';

test('Trafic de campagne : sonde privée dans Flottes, Vue générale et Galaxie', async ({ page, browser }, info) => {
  test.setTimeout(90000);
  await page.emulateMedia({ reducedMotion: 'reduce' });
  await page.addInitScript(() => {
    const fill = CanvasRenderingContext2D.prototype.fillText;
    const stroke = CanvasRenderingContext2D.prototype.stroke;
    window.testGalaxyGlyphs = new Set();
    window.testOwnedRing = false;
    window.testCampaignAnchor = false;
    window.testFirstPlanet = null;
    CanvasRenderingContext2D.prototype.fillText = function (text, x, y, ...rest) {
      if (this.canvas.classList.contains('gv2-canvas--galaxy')) window.testGalaxyGlyphs.add(text);
      if (this.canvas.classList.contains('gv2-canvas--system') && /^\d{2}$/.test(text) && text === window.testPlanetPosition) {
        window.testFirstPlanet = [x, y];
      }
      return fill.call(this, text, x, y, ...rest);
    };
    CanvasRenderingContext2D.prototype.stroke = function (...args) {
      if (this.canvas.classList.contains('gv2-canvas--galaxy') && this.strokeStyle === '#00e676' && this.lineWidth >= 3) window.testOwnedRing = true;
      if (this.canvas.classList.contains('gv2-canvas--galaxy') && this.strokeStyle === '#e6bbff' && this.getLineDash().length) window.testCampaignAnchor = true;
      return stroke.apply(this, args);
    };
  });
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
  // Le relay du lot CI ne doit pas terminer le scénario ; le reçu et son ETA restent intacts.
  runIsolatedSql(project, "UPDATE outbox_message o JOIN pirate_reconnaissance r ON r.id=o.target_id SET o.due_at=DATE_ADD(UTC_TIMESTAMP(), INTERVAL 1 HOUR) WHERE o.kind='pirate_reconnaissance' AND r.membership_id=" + member);

  for (const url of ['/flottes', '/planetes/' + origin]) {
    await page.goto(url);
    const flights = page.getByRole('region', { name: 'Flottes en vol dans votre empire' });
    const scout = flights.locator('[data-campaign-kind="scout"]');
    await expect(scout).toHaveCount(1);
    await expect(scout).toContainText('Sonde de reconnaissance');
    await expect(scout.locator('time')).toHaveAttribute('datetime', /T/);
    await expect(scout.getByRole('progressbar')).toBeVisible();
    await scout.getByText('Composition', { exact: true }).click();
    await expect(scout.getByText(/1 × .*sonde/i)).toBeVisible();
    const countdown = scout.locator('[data-progress-target="remaining"]');
    const previous = await countdown.textContent();
    await expect(countdown).not.toHaveText(previous);
    await expect(scout.getByRole('button')).toHaveCount(0);
    await expect(scout.getByRole('link', { name: 'Consulter le dossier de campagne' })).toHaveAttribute('href', '/evenements/campagne-pirate/1');
    await expect(flights.locator('[data-active-fleet]')).toHaveCount(0);
    await expect(page.locator('[data-campaign-kind="pirates"],[data-campaign-kind="convoy"]')).toHaveCount(0);
  }

  const metric = page.locator('[data-live-region="overview-flight-metric"]');
  await expect(metric.locator('strong')).toHaveText('1');
  const refreshedMetric = await page.evaluate(async () => {
    const response = await fetch(location.href, { headers: { 'X-Genesis-Live': '1', Accept: 'application/json' } });
    return (await response.json()).regions['overview-flight-metric'];
  });
  expect(refreshedMetric).toContain('<strong>1</strong>');

  await page.goto('/carte-v2?galaxy=' + galaxy + '&system=' + system);
  const shell = page.locator('.galaxy-v2');
  const read = JSON.parse(await shell.getAttribute('data-galaxy-v2-map-value'));
  expect(read.campaignTraffic).toHaveLength(1);
  expect(read.campaignTraffic[0]).toMatchObject({ kind: 'scout', mission: 1, systems: [system] });
  expect(Object.keys(read.campaignTraffic[0]).sort()).toEqual(['departure', 'eta', 'kind', 'label', 'mission', 'overdue', 'systems']);
  const payload = await page.evaluate(async id => {
    const response = await fetch('/flottes/surveillance?campaign=1&galaxy=' + id);
    return { data: await response.json(), cache: response.headers.get('cache-control') };
  }, galaxy);
  expect(payload.cache).toMatch(/no-store/);
  expect(payload.data.campaignTraffic).toEqual(read.campaignTraffic);
  await expect(page.locator('.gv2-flight-log [data-campaign-kind="scout"]')).toBeVisible();
  await expect(page.locator('.gv2-flight-log [data-campaign-kind="scout"]').getByRole('progressbar')).toBeVisible();
  await expect.poll(() => page.evaluate(() => window.testCampaignAnchor)).toBe(true);
  await expect(page.locator('.gv2-flight-log [data-campaign-kind="pirates"]')).toHaveCount(0);

  const station = page.getByRole('link', { name: 'Station pirate repérée, aucune case planétaire', exact: true });
  const enter = page.getByRole('button', { name: 'Entrer dans le système', exact: true });
  await expect(shell).toHaveAttribute('data-view', 'galaxy');
  await expect(page.getByRole("button", { name: /Votre monde/ })).toBeVisible();
  expect(await page.evaluate(() => ({ ring: window.testOwnedRing, house: window.testGalaxyGlyphs.has('⌂') }))).toEqual({ ring: true, house: false });
  await enter.click();
  await expect(shell).toHaveAttribute('data-view', 'system');
  await expect(station).toHaveCount(0);

  // Rapport de test sur le reçu créé par le vrai départ. La durée native reste inchangée.
  const shift = Number(queryIsolatedSql(project, 'SELECT GREATEST(TIMESTAMPDIFF(SECOND, UTC_TIMESTAMP(), due_at), 0) + 2 FROM pirate_reconnaissance WHERE membership_id=' + member + ' AND reported_at IS NULL'));
  expect(shift).toBeGreaterThanOrEqual(2);
  runIsolatedSql(project, 'UPDATE pirate_reconnaissance SET departed_at=DATE_SUB(departed_at,INTERVAL ' + shift + ' SECOND), due_at=DATE_SUB(due_at,INTERVAL ' + shift + ' SECOND), reported_at=UTC_TIMESTAMP(), detected=0 WHERE membership_id=' + member + ' AND reported_at IS NULL');
  const mapUrl = '/carte-v2?galaxy=' + galaxy + '&system=' + system;
  await page.setViewportSize({ width: 1920, height: 1080 });
  await page.goto(mapUrl);
  const reported = JSON.parse(await shell.getAttribute('data-galaxy-v2-map-value')).campaignTraffic;
  expect(reported).toHaveLength(1);
  expect(reported[0]).toMatchObject({ kind: 'pirates', mission: 1, systems: [system], eta: null });
  expect(Object.keys(reported[0]).sort()).toEqual(['departure', 'eta', 'kind', 'label', 'mission', 'overdue', 'systems']);
  await expect.poll(() => page.evaluate(() => window.testOwnedRing)).toBe(true);
  expect(await page.evaluate(() => ['P', '◆', '◇'].some(symbol => window.testGalaxyGlyphs.has(symbol)))).toBe(false);
  await expect(station).toHaveCount(0);
  const viewData = JSON.parse(await shell.getAttribute('data-galaxy-v2-map-value'));
  const own = viewData.systems.find(row => row.id === system);
  const unknown = viewData.systems.find(row => !row.known);
  expect(unknown).toBeTruthy();
  expect(unknown.hall).toBeUndefined();
  await page.goto('/carte-v2?galaxy=' + galaxy + '&system=' + unknown.id);
  await expect(enter).toHaveCount(0);
  await expect(station).toHaveCount(0);
  await page.goto(mapUrl);
  await enter.click();
  await expect(station).toBeVisible();
  const sprite = station.locator('img');
  await expect.poll(() => sprite.evaluate(image => image.naturalWidth)).toBe(192);
  const detail = page.locator('#gv2-station-detail');
  const rest = await sprite.boundingBox();
  expect(rest.width).toBeLessThanOrEqual(19);
  expect((await station.boundingBox()).width).toBeGreaterThanOrEqual(44);
  await sprite.hover();
  await expect(detail).toContainText('aucune case planétaire');
  const enlarged = await sprite.boundingBox();
  expect(enlarged.width).toBeGreaterThan(rest.width);
  expect(enlarged.width).toBeLessThanOrEqual(23);
  expect(enlarged.x + enlarged.width / 2).toBeCloseTo(rest.x + rest.width / 2, 1);
  await page.mouse.move(4, 4);
  const animatedPosition = String(own.planets[0].position).padStart(2, '0');
  await page.evaluate(position => { window.testPlanetPosition = position; }, animatedPosition);
  await page.getByRole('button', { name: "Animer la galaxie et les planètes", exact: true }).click();
  await expect.poll(() => page.evaluate(() => window.testFirstPlanet)).not.toBeNull();
  const planetBefore = await page.evaluate(() => window.testFirstPlanet);
  const fixedBefore = await station.getAttribute('style');
  await expect.poll(() => page.evaluate(() => window.testFirstPlanet)).not.toEqual(planetBefore);
  expect(await station.getAttribute('style')).toBe(fixedBefore);
  await page.getByRole('button', { name: "Pause des animations", exact: true }).click();
  await station.focus();
  await page.keyboard.press('Tab');
  await page.keyboard.press('Shift+Tab');
  await expect(station).toBeFocused();
  await expect(detail).toBeVisible();
  await page.keyboard.press('Escape');
  await expect(detail).toBeHidden();
  await expect(station).toBeFocused();
  await expect(shell).toHaveAttribute('data-view', 'system');
  await page.screenshot({ path: info.outputPath('stations-1920x1080.png'), animations: 'disabled' });

  const mobile = await browser.newContext({ hasTouch: true, reducedMotion: 'reduce', viewport: { width: 390, height: 844 }, storageState: await page.context().storageState() });
  try {
    const screen = await mobile.newPage();
    await screen.goto(new URL(mapUrl, page.url()).href);
    await screen.getByRole('button', { name: 'Entrer dans le système', exact: true }).tap();
    const mobileStation = screen.getByRole('link', { name: 'Station pirate repérée, aucune case planétaire', exact: true });
    await expect(mobileStation).toBeVisible();
    expect(await screen.evaluate(() => document.documentElement.scrollWidth)).toBe(390);
    await mobileStation.scrollIntoViewIfNeeded();
    await screen.screenshot({ path: info.outputPath('stations-390x844.png'), animations: 'disabled' });
    await mobileStation.tap();
    await expect(screen).toHaveURL(/\/evenements\/campagne-pirate$/);
  } finally {
    await mobile.close();
  }

  // Entre deux réponses identiques, un détail natif garde le focus.
  await page.goto('/planetes/' + origin);
  const panel = page.getByRole('region', { name: /Flottes entrantes/ });
  const first = await panel.evaluate(element => {
    const fixture = element.cloneNode(true);
    fixture.querySelector('[data-incoming-panel-count]').textContent = '1';
    fixture.querySelector('.incoming-empty-message')?.remove();
    fixture.insertAdjacentHTML('beforeend', '<button type="button" data-incoming-attack="fixture">Détail ordinaire</button>');
    return fixture.outerHTML;
  });
  let surveillanceHtml = first;
  await page.route('**/flottes/surveillance?**', route => route.fulfill({
    json: { html: surveillanceHtml, membership: member, serverNow: new Date().toISOString() },
    headers: { 'Cache-Control': 'private, no-store' },
  }));
  const refresh = () => page.evaluate(() => window.dispatchEvent(new Event('genesis:refresh')));
  const native = panel.getByRole('button', { name: 'Détail ordinaire' });
  await refresh();
  await expect(native).toBeVisible();
  await native.focus();
  surveillanceHtml = first.replace('</section>', '<article data-campaign-incoming="1" data-campaign-kind="convoy">Convoi protégé</article></section>');
  await refresh();
  await expect(panel.getByText('Convoi protégé')).toBeVisible();
  await expect(panel.locator('[data-incoming-panel-count]')).toHaveText('2');
  await expect(native).toBeFocused();
  surveillanceHtml = first;
  await refresh();
  await expect(panel.getByText('Convoi protégé')).toHaveCount(0);
  await expect(panel.locator('[data-incoming-panel-count]')).toHaveText('1');
  await expect(native).toBeFocused();
});
