import { test, expect } from './support/isolated-test.mjs';
import { execFileSync } from 'node:child_process';
import { runIsolatedSql, queryIsolatedSql } from './support/game-fixtures.mjs';
import { readFile } from 'node:fs/promises';

async function enter(page, admin = false) {
  const id = crypto.randomUUID().slice(0, 8);
  const email = `accounts-${id}@example.test`;
  const username = `Accounts-${id}`;
  const password = 'Browser-test-2026!';
  await page.goto('/inscription');
  await page.getByLabel('Adresse email').fill(email);
  await page.getByLabel('Nom de commandant').fill(username);
  await page.getByLabel('Mot de passe (12 caractères minimum)', { exact: true }).fill(password);
  await page.getByLabel('Confirmer le mot de passe').fill(password);
  await page.getByRole('button', { name: 'Créer mon compte' }).click();
  await expect(page).toHaveURL(/connexion/);
  if (admin) execFileSync('docker', ['compose', '--project-name', process.env.BROWSER_ROUTER, '--env-file', '.env.local', 'exec', '-T', 'app', 'php', 'bin/console', 'app:admin:grant', email], { stdio: ['ignore', 'pipe', 'pipe'] });
  await page.getByLabel('Adresse email').fill(email);
  await page.getByLabel('Mot de passe', { exact: true }).fill(password);
  await page.getByRole('button', { name: /Connexion/ }).click();
  await expect(page).toHaveURL(/univers$/);
  await page.locator('.universe-card').filter({ has: page.getByRole('button') }).first().getByRole('button').click();
  await expect(page).toHaveURL(/planetes\/\d+$/);
  return { email, username };
}

function prepareFlights(email) {
  const project = process.env.BROWSER_ROUTER;
  const home = Number(queryIsolatedSql(project, `SELECT planet.id FROM planet JOIN player_universe pu ON pu.id=planet.owner_id JOIN player p ON p.id=pu.player_id WHERE p.email='${email}' LIMIT 1`));
  const target = Number(queryIsolatedSql(project, `SELECT other.id FROM planet other JOIN solar_system s ON s.id=other.solar_system_id JOIN galaxy g ON g.id=s.galaxy_id JOIN player_universe pu ON pu.universe_id=g.universe_id JOIN player p ON p.id=pu.player_id WHERE p.email='${email}' AND other.id<>${home} LIMIT 1`));
  runIsolatedSql(project, `UPDATE planet SET name='OrigineInterstellaireSansEspaceAvecUnNomTresLongPourLeTest' WHERE id=${home}`);
  runIsolatedSql(project, `UPDATE planet SET name='DestinationInterstellaireSansEspaceAvecUnNomTresLong' WHERE id=${target}`);
  for (const status of ['traveling', 'returning']) runIsolatedSql(project, `INSERT INTO fleet (player_universe_id,home_planet_id,destination_id,status,mission,speed_ratio,cargo,reserved_fuel,orbit_fuel,orbit_duration_seconds,departure_at,arrival_at,travel_seconds,dispatch_token)
    SELECT pu.id,${home},${target},'${status}','transport',1,'{}',0,0,0,DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 HOUR),DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 HOUR),7200,'${crypto.randomUUID()}' FROM player_universe pu JOIN player p ON p.id=pu.player_id WHERE p.email='${email}'`);
}

test('Captures comptes, sécurité, journal et flottes actives', async ({ page, browser }, testInfo) => {
  await enter(page, true);
  const player = await browser.newPage();
  const target = await enter(player);
  await player.goto('/admin');
  await expect(player).toHaveURL(/acces-refuse/);
  for (const name of ['players', 'security', 'audit']) {
    await page.goto('/admin#admin-' + name);
    await expect(page.locator('#admin-' + name)).toBeVisible();
    for (const width of [1440, 390]) {
      await page.setViewportSize({ width, height: 900 });
      await page.evaluate(() => window.scrollTo({ top: 0, behavior: 'instant' }));
      await page.screenshot({ path: testInfo.outputPath(`admin-${name}-${width}.png`), animations: 'disabled' });
    }
  }
  prepareFlights(target.email);
  await player.goto('/flottes');
  await expect(player.locator('.flight-operation')).toHaveCount(2);
  for (const width of [1440, 390]) {
    await player.setViewportSize({ width, height: 900 });
    await player.locator('.flight-operations').scrollIntoViewIfNeeded();
    await player.screenshot({ path: testInfo.outputPath(`active-flights-${width}.png`), animations: 'disabled' });
  }
  await player.close();
});

async function decide(page, button, accept, name) {
  const awaitingDialog = page.waitForEvent('dialog');
  const clicking = button.click();
  const dialog = await awaitingDialog;
  expect(dialog.type()).toBe('confirm');
  expect(dialog.message()).toContain(name);
  if (accept) await dialog.accept();
  else await dialog.dismiss();
  await clicking;
}

async function visitAdminLink(page, name) {
  const toggle = page.getByRole('button', { name: 'Menu d’administration' });
  const navigation = page.getByRole('navigation', { name: 'Console d’administration' });
  const openMenu = async () => {
    if (page.viewportSize().width <= 760) {
      await expect(toggle).toBeVisible();
      if (await toggle.getAttribute('aria-expanded') === 'false') await toggle.click();
    }
    await expect(navigation).toBeVisible();
  };
  await openMenu();
  if (await navigation.getByRole('link', { name }).count() === 0) {
    await navigation.getByRole('link', { name: /^Exploitation/ }).click();
    await openMenu();
  }
  await navigation.getByRole('link', { name }).click();
}

test('Administration : rubriques clavier, comptes protégés, confirmations et journal', async ({ page, browser }, testInfo) => {
  const admin = await enter(page, true);
  const player = await browser.newPage();
  const target = await enter(player);
  await player.goto('/admin');
  await expect(player).toHaveURL(/acces-refuse/);
  await page.goto('/admin');
  const navigation = page.getByRole('navigation', { name: 'Console d’administration' });
  await navigation.getByRole('link', { name: /^Joueurs et modération/ }).click();
  await expect(page).toHaveURL(/admin\/rubriques\/players$/);
  await expect(page.locator('.admin-module-card')).toHaveCount(4);
  const playersLink = navigation.getByRole('link', { name: 'Joueurs', exact: true });
  await playersLink.focus();
  await page.keyboard.press('Tab');
  await expect(navigation.getByRole('link', { name: 'Signalements', exact: true })).toBeFocused();
  await page.keyboard.press('Shift+Tab');
  await expect(playersLink).toBeFocused();
  await page.keyboard.press('Enter');
  await expect(page).toHaveURL(/admin\/joueurs$/);
  await expect(navigation.getByRole('link', { name: 'Joueurs', exact: true })).toHaveAttribute('aria-current', 'page');
  await expect(page.getByRole('navigation', { name: 'Fil d’Ariane' })).toContainText('Joueurs et modération');
  await expect(page.getByRole('navigation', { name: 'Joueurs et modération' }).getByRole('link', { name: 'Joueurs', exact: true })).toHaveAttribute('aria-current', 'page');
  const panel = page.locator('#admin-players');
  const ownRow = panel.locator('tr').filter({ hasText: admin.email });
  const targetRow = panel.locator('tr').filter({ hasText: target.email });
  await expect(ownRow.locator('form')).toHaveCount(0);
  await expect(targetRow.getByRole('rowheader')).toContainText(target.username);
  const promote = targetRow.getByRole('button', { name: 'Accorder les droits d’administration à ' + target.username });
  await decide(page, promote, false, target.username);
  await expect(targetRow).toHaveAttribute('data-filter-role', 'player');
  expect(queryIsolatedSql(process.env.BROWSER_ROUTER, "SELECT COUNT(*) FROM admin_audit_log WHERE action='player_promote_admin'")).toBe('0');
  await decide(page, promote, true, target.username);
  await page.goto('/admin/joueurs');
  await expect(targetRow).toHaveAttribute('data-filter-role', 'admin');
  await expect(targetRow.getByRole('button', { name: /Suspendre/ })).toHaveCount(0);
  await decide(page, targetRow.getByRole('button', { name: 'Retirer les droits d’administration à ' + target.username }), true, target.username);
  await page.goto('/admin/joueurs');
  await expect(targetRow).toHaveAttribute('data-filter-role', 'player');
  await decide(page, targetRow.getByRole('button', { name: 'Suspendre le compte ' + target.username }), false, target.username);
  await expect(targetRow).toHaveAttribute('data-filter-status', 'active');
  await decide(page, targetRow.getByRole('button', { name: 'Suspendre le compte ' + target.username }), true, target.username);
  await page.goto('/admin/joueurs');
  await expect(targetRow).toHaveAttribute('data-filter-status', 'suspended');
  await panel.getByLabel('État', { exact: true }).selectOption('suspended');
  await expect(panel.locator('tr[data-admin-filter-target="row"]:visible')).toHaveCount(1);
  for (const width of [1920, 1440, 1280, 768, 390]) {
    await page.setViewportSize({ width, height: 900 });
    expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(width);
  }
  await page.screenshot({ path: testInfo.outputPath('admin-suspended-390.png'), fullPage: true, animations: 'disabled' });
  await targetRow.getByRole('button', { name: 'Réactiver le compte ' + target.username }).click();
  await page.goto('/admin/joueurs');
  await expect(targetRow).toHaveAttribute('data-filter-status', 'active');

  await visitAdminLink(page, /^Sécurité/);
  const security = page.locator('#admin-security');
  await security.getByRole('searchbox', { name: 'Rechercher', exact: true }).fill('zz-aucun-compte');
  await expect(security.getByText('Aucun incident ne correspond à cette recherche.')).toBeVisible();
  await security.getByRole('searchbox', { name: 'Rechercher', exact: true }).fill(target.username);
  await security.getByRole('button', { name: 'Marquer examinée' }).click();
  await page.goto('/admin/securite');
  await expect(security.getByText('Aucune tentative refusée en attente d’examen.')).toHaveCount(1);
  await expect(security.getByText('Aucun incident ne correspond à cette recherche.')).toHaveCount(0);
  await visitAdminLink(page, 'Journal des actions');
  const audit = page.locator('#admin-audit');
  await audit.getByLabel('Action', { exact: true }).selectOption('admin_access_reviewed');
  await expect(audit.locator('tr[data-admin-filter-target="row"]:visible')).toHaveCount(1);
  const downloadPromise = page.waitForEvent('download');
  await audit.getByRole('button', { name: 'Exporter les lignes visibles en CSV' }).click();
  const download = await downloadPromise;
  const csv = await readFile(await download.path(), 'utf8');
  expect(csv).toContain('admin_access_reviewed');
  expect(csv).not.toContain('player_promote_admin');
  await audit.getByRole('searchbox', { name: 'Rechercher dans le journal' }).fill('zz-aucun-journal');
  await expect(audit.getByText('Aucune entrée ne correspond à cette recherche.')).toBeVisible();
  await player.close();
});

test('Flottes actives : aller, retour, noms longs et thème clair', async ({ page }, testInfo) => {
  const { email } = await enter(page);
  prepareFlights(email);
  for (const route of ['/flottes', '/']) {
    await page.goto(route);
    const operations = page.locator('.flight-operations');
    await expect(operations.locator('.flight-operation')).toHaveCount(2);
    await expect(operations).toContainText('ALLER');
    await expect(operations).toContainText('RETOUR');
    if (route === '/flottes') {
      const returning = operations.locator('.flight-operation').filter({ has: page.getByText('RETOUR', { exact: true }) });
      await expect(returning.locator('.flight-route>span').first()).toHaveText(/^DestinationInterstellaire/);
      await expect(returning.locator('.flight-route>span').last()).toHaveText('OrigineInterstellaireSansEspaceAvecUnNomTresLongPourLeTest');
      await expect(page.getByRole('heading', { name: 'Journal des missions' })).toHaveCount(0);
    }
    for (const width of [1920, 1440, 1280, 768, 390]) {
      await page.setViewportSize({ width, height: 900 });
      expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(width);
      for (const label of await operations.locator('.flight-route>span').all()) {
        expect(await label.evaluate(element => element.scrollWidth - element.clientWidth)).toBeLessThanOrEqual(1);
      }
    }
    for (const bar of await operations.getByRole('progressbar').all()) {
      const value = Number(await bar.getAttribute('aria-valuenow'));
      expect(value).toBeGreaterThan(0);
      expect(value).toBeLessThan(100);
      await expect(bar).toHaveAccessibleName(/flotte/);
    }
    await operations.scrollIntoViewIfNeeded();
    await page.screenshot({ path: testInfo.outputPath(route === '/' ? 'overview-active-390.png' : 'fleets-active-390.png'), animations: 'disabled' });
  }
  runIsolatedSql(process.env.BROWSER_ROUTER, `UPDATE player SET preferences=JSON_SET(CASE WHEN JSON_TYPE(preferences)='OBJECT' THEN preferences ELSE JSON_OBJECT() END,'$.theme','light') WHERE email='${email}'`);
  await page.goto('/flottes');
  await page.locator('.flight-operations').scrollIntoViewIfNeeded();
  await page.screenshot({ path: testInfo.outputPath('fleets-active-light-390.png'), animations: 'disabled' });
});


test('Relais admin : échéance, retard, nature inconnue et lecture mobile sans jeton', async ({page}, testInfo) => {
  await enter(page, true);
  const token=crypto.randomUUID();
  runIsolatedSql(process.env.BROWSER_ROUTER, `INSERT INTO outbox_message (kind,target_id,token,due_at) VALUES ('diagnostic_test',987654,'${token}',UTC_TIMESTAMP()-INTERVAL 5 MINUTE)`);
  await page.goto('/admin/exploitation');
  const details=page.locator('.admin-operations details');
  await details.locator('summary').focus();await page.keyboard.press('Enter');
  const row=details.getByRole('row').filter({hasText:'diagnostic_test'});
  await expect(row).toContainText('#987654');
  await expect(row).toContainText(/3\d{2} s/);
  await expect(details).toContainText('ne sont pas enregistrées');
  await expect(details).not.toContainText(token);
  for(const width of [1440,768,390]) {
    await page.setViewportSize({width,height:900});
    await expect.poll(()=>page.evaluate(()=>document.documentElement.scrollWidth)).toBeLessThanOrEqual(width);
    await expect(row).toBeVisible();
    await row.scrollIntoViewIfNeeded();
    if(width===390) {
      const region=details.getByRole('region',{name:'Relais en attente, tableau défilant'});
      await region.focus();await page.keyboard.press('ArrowRight');
      await expect.poll(()=>region.evaluate(el=>el.scrollLeft)).toBeGreaterThan(0);
    }
    await page.screenshot({path:testInfo.outputPath(`pending-relays-${width}.png`),animations:'disabled'});
  }
});
