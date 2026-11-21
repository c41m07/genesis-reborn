import { test, expect } from './support/isolated-test.mjs';
import { execFileSync } from 'node:child_process';
import AxeBuilder from '@axe-core/playwright';
import { runIsolatedSql, queryIsolatedSql } from './support/game-fixtures.mjs';

async function enter(page, admin = false) {
  const id = crypto.randomUUID().slice(0, 8);
  const email = `inventory-${id}@example.test`;
  const password = crypto.randomUUID() + 'aA1!';
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

test('Catalogue admin : familles dédiées, recherche transversale et lecture sans JS', async ({ page, browser }, testInfo) => {
  await enter(page, true);
  const universeId = queryIsolatedSql(process.env.BROWSER_ROUTER, 'SELECT id FROM universe ORDER BY id LIMIT 1');
  const route = `/admin/universes/${Number(universeId)}/catalogue`;
  await page.goto(route);
  await expect(page.getByRole('heading', {name:'Bâtiments',exact:true})).toBeVisible();
  await expect(page.locator('input[name^="values["]')).toHaveCount(0);
  const families = page.getByRole('navigation', {name:'Familles de catalogues'});
  await families.getByRole('link', {name:'Vaisseaux',exact:true}).click();
  await expect(page).toHaveURL(new RegExp(route + '/ships$'));
  await expect(families.getByRole('link', {name:'Vaisseaux',exact:true})).toHaveAttribute('aria-current','page');
  const query=page.getByLabel('Rechercher dans toutes les familles');
  await query.fill('zz-sans-résultat');await page.getByRole('button',{name:'Rechercher',exact:true}).click();
  await expect(page.getByText('Aucune entrée ne correspond à cette recherche.')).toBeVisible();
  await query.fill('fighter');await page.getByRole('button',{name:'Rechercher',exact:true}).click();
  await expect(page.getByRole('link',{name:/^Modifier /})).toHaveCount(3);
  const edit=page.locator(`a[href="${route}/ships/fighter"]`);await expect(edit).toHaveCount(1);
  await edit.focus();await page.keyboard.press('Enter');
  await expect(page).toHaveURL(new RegExp(route + '/ships/fighter$'));
  const input=page.locator('input[name="values[precision]"]');
  await input.fill('83');await page.waitForTimeout(1100);await expect(input).toHaveValue('83');
  for (const width of [1920,1440,1280,768,390]) {
    await page.setViewportSize({width,height:900});
    expect(await page.evaluate(()=>document.documentElement.scrollWidth)).toBeLessThanOrEqual(width);
    if ([1440,390].includes(width)) await page.screenshot({path:testInfo.outputPath(`catalogue-edit-${width}.png`),fullPage:true,animations:'disabled'});
  }
  const context=await browser.newContext({javaScriptEnabled:false,storageState:await page.context().storageState()});
  const fallback=await context.newPage();await fallback.goto(route);
  await fallback.getByLabel('Rechercher dans toutes les familles').fill('fighter');await fallback.getByRole('button',{name:'Rechercher',exact:true}).click();
  await expect(fallback.getByRole('link',{name:/^Modifier /})).toHaveCount(3);
  await fallback.locator(`a[href="${route}/ships/fighter"]`).click();
  await expect(fallback.locator('input[name="values[precision]"]')).toBeVisible();
  await context.close();
});

test('Inventaire : état vide, capacité pleine, attente et effet configuré', async ({ page }, testInfo) => {
  const email = await enter(page);
  const project = process.env.BROWSER_ROUTER;
  await page.goto('/boosts');
  await expect(page.getByRole('heading', { name: 'Aucun bonus disponible' })).toBeVisible();
  const capacity = Number(await page.getByRole('progressbar', { name: 'Emplacements utilisés dans l’inventaire' }).getAttribute('max'));
  const membershipId = Number(queryIsolatedSql(project, `SELECT pu.id FROM player_universe pu JOIN player p ON p.id=pu.player_id WHERE p.email='${email}'`));
  const rows = Array.from({ length: capacity + 3 }, (_, index) => {
    const status = index < capacity ? 'available' : index < capacity + 2 ? 'pending' : 'used';
    return `(${membershipId},'event','ui-${crypto.randomUUID()}','${status}',UTC_TIMESTAMP())`;
  });
  runIsolatedSql(project, `INSERT INTO player_boost (player_universe_id,source_type,source_key,status,earned_at)
    VALUES ${rows.join(',')}`);
  await page.reload();
  await expect(page.locator('.mission-card')).toContainText(`Quantité : ${capacity}`);
  await expect(page.locator('.mission-card')).toContainText('25,00 %');
  await expect(page.getByText('Inventaire plein.', { exact: false })).toBeVisible();
  await page.getByRole('navigation',{name:'Navigation de l’inventaire'}).getByRole('link',{name:/Récompenses en attente/}).click();
  await expect(page.locator('.boost-pending')).toContainText('2 bonus en attente');
  await page.getByRole('navigation',{name:'Navigation de l’inventaire'}).getByRole('link',{name:/^Boosts/}).click();
  await expect(page.getByRole('progressbar')).toHaveAttribute('value', String(capacity));
  for (const width of [1920, 1440, 1280, 768, 390]) {
    await page.setViewportSize({ width, height: 900 });
    expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(width);
  }
  await page.screenshot({ path: testInfo.outputPath('inventory-full-390.png'), fullPage: true, animations: 'disabled' });
});

test('Inventaire : accélérer une file sans proposer la flotte en cours', async ({ page }, testInfo) => {
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
  const fleetState = () => queryIsolatedSql(project, `SELECT arrival_at, travel_seconds, reserved_fuel, orbit_fuel, dispatch_token, navigation FROM fleet WHERE id=${fleetId}`);
  const initialFleet = fleetState();
  await page.goto('/boosts');
  for (const width of [1440, 768, 390]) {
    await page.setViewportSize({ width, height: 900 });
    expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(width);
  }
  await page.screenshot({ path: testInfo.outputPath('boost-use-390.png'), fullPage: true });
  await page.getByLabel('Opération à accélérer').selectOption('queue-' + queueId);
  const queueForm = page.locator(`form[action$="/queue/${queueId}"]`);
  await expect(queueForm.getByRole('button')).toBeVisible();
  await queueForm.locator('input[name="_token"]').evaluate(input => { input.value = 'invalid'; });
  const deniedResponse = page.waitForResponse(response => response.url().endsWith(`/boosts/utiliser/queue/${queueId}`) && response.request().method() === 'POST');
  await queueForm.getByRole('button').click();
  await page.getByRole('dialog').getByRole('button', { name: 'Utiliser un boost', exact: true }).click();
  expect((await deniedResponse).status()).toBe(403);
  await expect(page.getByRole('heading', { name: 'Accès refusé' })).toBeVisible();
  expect(queryIsolatedSql(project, `SELECT status FROM player_boost WHERE source_key='use-queue'`)).toBe('available');
  await page.goto('/boosts');
  await page.getByLabel('Opération à accélérer').selectOption('queue-' + queueId);
  await queueForm.getByRole('button').click();
  const dialog = page.getByRole('dialog');
  await expect(dialog).toContainText('25,00 %');
  await page.screenshot({ path: testInfo.outputPath('boost-review-390.png'), fullPage: false });
  await expect(dialog.getByRole('button', { name: 'Revenir à l’inventaire' })).toBeFocused();
  expect((await new AxeBuilder({ page }).withTags(['wcag2a','wcag2aa','wcag21aa']).analyze()).violations).toEqual([]);
  await page.keyboard.press('Tab');
  await expect(dialog.getByRole('button', { name: 'Utiliser un boost', exact: true })).toBeFocused();
  await page.keyboard.press('Tab');
  await expect(dialog.getByRole('button', { name: 'Revenir à l’inventaire' })).toBeFocused();
  await page.keyboard.press('Shift+Tab');
  await expect(dialog.getByRole('button', { name: 'Utiliser un boost', exact: true })).toBeFocused();
  await page.keyboard.press('Escape');
  await expect(queueForm.getByRole('button')).toBeFocused();
  expect(queryIsolatedSql(project, `SELECT status FROM player_boost WHERE source_key='use-queue'`)).toBe('available');
  await queueForm.getByRole('button').click();
  await dialog.getByRole('button', { name: 'Utiliser un boost', exact: true }).click();
  await expect(page.getByRole('status')).toContainText('Boost appliqué');
  expect(Number(queryIsolatedSql(project, `SELECT duration_seconds FROM queue_item WHERE id=${queueId}`))).toBeLessThan(3000);
  expect(queryIsolatedSql(project, `SELECT status FROM player_boost WHERE source_key='use-fleet'`)).toBe('available');
  await expect(page.getByText(/Les missions de flotte ne sont pas accélérables/)).toBeVisible();
  await expect(page.locator('option[value="fleet-' + fleetId + '"]')).toHaveCount(0);
  await expect(page.locator(`form[action$="/fleet/${fleetId}"]`)).toHaveCount(0);
  expect(fleetState()).toBe(initialFleet);
  expect(queryIsolatedSql(project, `SELECT COUNT(*) FROM player_boost WHERE player_universe_id=${membershipId} AND status='used'`)).toBe('1');
});


test('Inventaire : pages directes, navigation, attente paginée et anciens fragments', async ({ page, browser }, testInfo) => {
  test.setTimeout(180000);
  const email=await enter(page), project=process.env.BROWSER_ROUTER;
  const member=Number(queryIsolatedSql(project,`SELECT current_player_universe_id FROM player WHERE email='${email}'`));
  runIsolatedSql(project, `INSERT INTO player_boost (player_universe_id,source_type,source_key,status,earned_at) VALUES ${Array.from({length:28},(_,i)=>`(${member},'event','pending-${member}-${i}','pending',DATE_ADD(UTC_TIMESTAMP(),INTERVAL ${i} SECOND))`).join(',')}`);
  const routes=[['/boosts','Boosts'],['/inventaire/boites','Boîtes de ressources'],['/inventaire/recompenses','Récompenses en attente']];
  for (const theme of ['dark','light']) {
    runIsolatedSql(project,`UPDATE player SET preferences=JSON_SET(CASE WHEN JSON_TYPE(preferences)='OBJECT' THEN preferences ELSE JSON_OBJECT() END,'$.theme','${theme}') WHERE email='${email}'`);
    for (const width of [1440,390,320]) {
      await page.setViewportSize({width,height:900});
      for (const [url,title] of routes) {
        expect((await page.goto(url)).status()).toBe(200);
        await expect(page.locator('h1')).toHaveText(title);
        await expect(page.locator('body')).toHaveAttribute('data-theme',theme);
        await expect(page.locator(`nav[aria-label="Navigation de l’inventaire"] a[href="${url}"]`)).toHaveAttribute('aria-current','page');
        await expect(page.locator('.sidebar a[href="/boosts"]')).toHaveClass(/active/);
        expect(await page.evaluate(()=>document.documentElement.scrollWidth)).toBeLessThanOrEqual(width);
        expect((await new AxeBuilder({page}).include('main').analyze()).violations).toEqual([]);
      }
      await page.screenshot({path:testInfo.outputPath(`pending-${theme}-${width}.png`),fullPage:true});
    }
  }
  await expect(page.locator('[data-pending-item]')).toHaveCount(25);
  await page.getByRole('link',{name:'Récompenses suivantes',exact:true}).focus();await page.keyboard.press('Enter');
  await expect(page).toHaveURL(/recompenses\?page=2$/);await page.reload();await expect(page.locator('[data-pending-item]')).toHaveCount(3);
  await page.goBack();await expect(page.locator('[data-pending-item]')).toHaveCount(25);
  for (const [fragment,path] of [['inventory-boxes','/inventaire/boites'],['inventory-pending','/inventaire/recompenses'],['inventory-boosts','/boosts']]) {
    await page.goto('/boosts#'+fragment);await expect(page).toHaveURL(new RegExp(path+'#'+fragment+'$'));
  }
  const context=await browser.newContext({baseURL:new URL(page.url()).origin,storageState:await page.context().storageState(),javaScriptEnabled:false,viewport:{width:390,height:900}});
  try {
    const plain=await context.newPage();await plain.goto('/inventaire/recompenses?page=2');
    await expect(plain.locator('[data-pending-item]')).toHaveCount(3);
    const nav=plain.getByRole('navigation',{name:'Navigation de l’inventaire'});
    await nav.getByRole('link',{name:/^Boosts/}).click();await expect(plain.locator('h1')).toHaveText('Boosts');
    await nav.getByRole('link',{name:/Boîtes de ressources/}).click();await expect(plain.locator('h1')).toHaveText('Boîtes de ressources');
    await nav.getByRole('link',{name:/Récompenses en attente/}).click();await expect(plain.locator('[data-pending-item]')).toHaveCount(25);
  } finally {await context.close();}
});
