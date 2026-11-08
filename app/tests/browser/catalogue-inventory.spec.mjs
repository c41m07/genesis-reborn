import { test, expect } from './support/isolated-test.mjs';
import { execFileSync } from 'node:child_process';
import { runIsolatedSql, queryIsolatedSql } from './support/game-fixtures.mjs';

async function enter(page, admin = false) {
  const id = crypto.randomUUID().slice(0, 8);
  const email = `inventory-${id}@example.test`;
  const password = 'Browser-test-2026!';
  await page.goto('/inscription');
  await page.getByLabel('Adresse email').fill(email);
  await page.getByLabel('Nom de commandant').fill(`Inventory-${id}`);
  await page.getByLabel('Mot de passe (12 caractères minimum)', { exact: true }).fill(password);
  await page.getByLabel('Confirmer le mot de passe').fill(password);
  await page.getByRole('button', { name: 'Créer mon compte' }).click();
  await expect(page).toHaveURL(/connexion/);
  if (admin) execFileSync('docker', ['compose', '--project-name', process.env.BROWSER_ROUTER,
    '--env-file', '.env.local', 'exec', '-T', 'app', 'php', 'bin/console', 'app:admin:grant', email],
  { stdio: ['ignore', 'pipe', 'pipe'] });
  await page.getByLabel('Adresse email').fill(email);
  await page.getByLabel('Mot de passe', { exact: true }).fill(password);
  await page.getByRole('button', { name: /Connexion/ }).click();
  await page.locator('article.universe-card').first().getByRole('button').click();
  await expect(page).toHaveURL(/planetes\/\d+$/);
  return email;
}

test('Catalogue admin : recherche, famille, état vide et saisie non perdue', async ({ page, browser }, testInfo) => {
  await enter(page, true);
  const universeId = queryIsolatedSql(process.env.BROWSER_ROUTER, 'SELECT id FROM universe ORDER BY id LIMIT 1');
  const route = `/admin/universes/${Number(universeId)}/catalogue`;
  await page.goto(route);
  const tools = page.getByRole('search', { name: 'Rechercher dans les catalogues' });
  await expect(tools).toBeVisible();
  const entries = page.locator('details[data-admin-filter-target="row"]');
  const total = await entries.count();
  expect(total).toBeGreaterThan(4);
  const first = entries.first();
  const family = await first.getAttribute('data-filter-group');
  const slug = (await first.getAttribute('id')).slice(family.length + 1);
  await first.locator('summary').click();
  const input = first.locator('input[type="number"]').first();
  const draft = String(Number(await input.inputValue()) + 1);
  await input.fill(draft);
  await tools.getByLabel('Rechercher une entrée').fill('zz-sans-résultat');
  await expect(page.locator('details[data-admin-filter-target="row"]:visible')).toHaveCount(0);
  await expect(page.getByText('Aucune entrée ne correspond à ces filtres.', { exact: false })).toBeVisible();
  await expect(page.locator('.catalogue-family-nav a:visible')).toHaveCount(0);
  await tools.getByRole('button', { name: 'Effacer les filtres' }).click();
  await expect(tools.getByLabel('Rechercher une entrée')).toBeFocused();
  await expect(page.locator('details[data-admin-filter-target="row"]:visible')).toHaveCount(total);
  await expect(input).toHaveValue(draft);
  await tools.getByLabel('Famille', { exact: true }).selectOption('ships');
  const ships = await entries.filter({ has: page.locator('summary') }).evaluateAll(nodes => nodes.filter(node => node.dataset.filterGroup === 'ships').length);
  await expect(page.locator('details[data-admin-filter-target="row"]:visible')).toHaveCount(ships);
  await tools.getByLabel('Famille', { exact: true }).selectOption(family);
  await tools.getByLabel('Rechercher une entrée').fill(slug);
  await expect(page.locator('details[data-admin-filter-target="row"]:visible')).toHaveCount(1);
  for (const width of [1920, 1440, 1280, 768, 390]) {
    await page.setViewportSize({ width, height: 900 });
    expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(width);
    if (width === 1440) await page.screenshot({ path: testInfo.outputPath('catalogue-open-1440.png'), fullPage: true, animations: 'disabled' });
  }
  await first.locator('summary').click();
  await page.screenshot({ path: testInfo.outputPath('catalogue-filter-390.png'), fullPage: true, animations: 'disabled' });
  const context = await browser.newContext({ javaScriptEnabled: false, storageState: await page.context().storageState() });
  const fallback = await context.newPage();
  await fallback.goto(route);
  await expect(fallback.locator('.catalogue-search')).toBeHidden();
  await expect(fallback.locator('details[data-admin-filter-target="row"]:visible')).toHaveCount(total);
  await context.close();
});

test('Inventaire : état vide, capacité pleine, attente et effet configuré', async ({ page }, testInfo) => {
  const email = await enter(page);
  const project = process.env.BROWSER_ROUTER;
  await page.goto('/boosts');
  await expect(page.getByRole('heading', { name: 'Aucun bonus disponible' })).toBeVisible();
  const capacity = Number(await page.getByRole('progressbar', { name: 'Emplacements utilisés dans l’inventaire' }).getAttribute('max'));
  const membershipId = Number(queryIsolatedSql(project, `SELECT pu.id FROM player_universe pu JOIN player p ON p.id=pu.player_id WHERE p.email='${email}'`));
  for (let index = 0; index < capacity + 3; index++) {
    const status = index < capacity ? 'available' : index < capacity + 2 ? 'pending' : 'used';
    runIsolatedSql(project, `INSERT INTO player_boost (player_universe_id,source_type,source_key,status,earned_at)
      VALUES (${membershipId},'event','ui-${crypto.randomUUID()}','${status}',UTC_TIMESTAMP())`);
  }
  await page.reload();
  await expect(page.locator('.mission-card')).toContainText(`Quantité : ${capacity}`);
  await expect(page.locator('.mission-card')).toContainText('25,00 %');
  await expect(page.getByText('Inventaire plein.', { exact: false })).toBeVisible();
  await expect(page.locator('.boost-pending')).toContainText('2 bonus en attente');
  await expect(page.getByRole('progressbar')).toHaveAttribute('value', String(capacity));
  for (const width of [1920, 1440, 1280, 768, 390]) {
    await page.setViewportSize({ width, height: 900 });
    expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(width);
  }
  await page.screenshot({ path: testInfo.outputPath('inventory-full-390.png'), fullPage: true, animations: 'disabled' });
});

test('Inventaire : appliquer un boost à une file puis à une flotte', async ({ page }, testInfo) => {
  const email = await enter(page);
  const project = process.env.BROWSER_ROUTER;
  const membershipId = Number(queryIsolatedSql(project, `SELECT pu.id FROM player_universe pu JOIN player p ON p.id=pu.player_id WHERE p.email='${email}'`));
  const planetId = Number(queryIsolatedSql(project, `SELECT id FROM planet WHERE owner_id=${membershipId} LIMIT 1`));
  runIsolatedSql(project, `INSERT INTO player_boost (player_universe_id,source_type,source_key,status,earned_at) VALUES
    (${membershipId},'event','use-queue','available',UTC_TIMESTAMP()),
    (${membershipId},'event','use-fleet','pending',UTC_TIMESTAMP())`);
  runIsolatedSql(project, `INSERT INTO queue_item (planet_id,player_universe_id,queue_type,item_slug,tier,item_level,quantity,duration_seconds,started_at,queued_at,dispatch_token,paid_costs)
    VALUES (${planetId},${membershipId},'building','mine_metal','basic',1,1,3600,UTC_TIMESTAMP(),UTC_TIMESTAMP(),UUID(),'{}')`);
  const queueId = Number(queryIsolatedSql(project, `SELECT id FROM queue_item WHERE player_universe_id=${membershipId}`));
  runIsolatedSql(project, `INSERT INTO fleet (player_universe_id,home_planet_id,destination_system_id,status,mission,speed_ratio,cargo,reserved_fuel,orbit_fuel,orbit_duration_seconds,departure_at,arrival_at,travel_seconds,dispatch_token)
    SELECT ${membershipId},${planetId},solar_system_id,'traveling','explore',1,'{}',0,0,0,UTC_TIMESTAMP(),DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 HOUR),3600,UUID() FROM planet WHERE id=${planetId}`);
  const fleetId = Number(queryIsolatedSql(project, `SELECT id FROM fleet WHERE player_universe_id=${membershipId} AND status='traveling'`));
  await page.goto('/boosts');
  for (const width of [1440, 768, 390]) {
    await page.setViewportSize({ width, height: 900 });
    expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(width);
  }
  await page.screenshot({ path: testInfo.outputPath('boost-use-390.png'), fullPage: true });
  const queueForm = page.locator(`form[action$="/queue/${queueId}"]`);
  await expect(queueForm.getByRole('button')).toBeVisible();
  await queueForm.locator('input[name="_token"]').evaluate(input => { input.value = 'invalid'; });
  const deniedResponse = page.waitForResponse(response => response.url().endsWith(`/boosts/utiliser/queue/${queueId}`) && response.request().method() === 'POST');
  await queueForm.getByRole('button').click();
  expect((await deniedResponse).status()).toBe(403);
  await expect(page.getByRole('heading', { name: 'Accès refusé' })).toBeVisible();
  expect(queryIsolatedSql(project, `SELECT status FROM player_boost WHERE source_key='use-queue'`)).toBe('available');
  await page.goto('/boosts');
  await queueForm.getByRole('button').click();
  await expect(page.getByRole('status')).toContainText('Boost appliqué');
  expect(Number(queryIsolatedSql(project, `SELECT duration_seconds FROM queue_item WHERE id=${queueId}`))).toBeLessThan(3000);
  expect(queryIsolatedSql(project, `SELECT status FROM player_boost WHERE source_key='use-fleet'`)).toBe('available');
  await page.locator(`form[action$="/fleet/${fleetId}"]`).getByRole('button').click();
  await expect(page.getByRole('status')).toContainText('Boost appliqué');
  expect(Number(queryIsolatedSql(project, `SELECT TIMESTAMPDIFF(SECOND,UTC_TIMESTAMP(),arrival_at) FROM fleet WHERE id=${fleetId}`))).toBeLessThan(2800);
  expect(queryIsolatedSql(project, `SELECT COUNT(*) FROM player_boost WHERE player_universe_id=${membershipId} AND status='used'`)).toBe('2');
  await expect(page.getByRole('heading', { name: 'Aucun bonus disponible' })).toBeVisible();
});
