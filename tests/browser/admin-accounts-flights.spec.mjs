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

test('Administration : onglets clavier, comptes protégés, confirmations et journal', async ({ page, browser }, testInfo) => {
  const admin = await enter(page, true);
  const player = await browser.newPage();
  const target = await enter(player);
  await player.goto('/admin');
  await expect(player).toHaveURL(/acces-refuse/);
  await page.goto('/admin');
  const tabs = page.getByRole('tablist', { name: 'Sections d’administration' });
  await tabs.getByRole('tab', { name: 'Joueurs', exact: true }).focus();
  await page.keyboard.press('ArrowRight');
  await expect(tabs.getByRole('tab', { name: 'Univers', exact: true })).toBeFocused();
  await expect(page.locator('#admin-universes')).toBeVisible();
  await page.keyboard.press('ArrowLeft');
  await expect(tabs.getByRole('tab', { name: 'Joueurs', exact: true })).toBeFocused();
  await page.keyboard.press('End');
  await expect(tabs.getByRole('tab', { name: 'Journal', exact: true })).toBeFocused();
  await page.keyboard.press('ArrowRight');
  await expect(tabs.getByRole('tab', { name: 'Vue d’ensemble', exact: true })).toBeFocused();
  await page.keyboard.press('ArrowLeft');
  await expect(tabs.getByRole('tab', { name: 'Journal', exact: true })).toBeFocused();
  await page.keyboard.press('Home');
  await expect(tabs.getByRole('tab', { name: 'Vue d’ensemble', exact: true })).toBeFocused();
  await tabs.getByRole('tab', { name: 'Joueurs', exact: true }).click();
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
  await page.goto('/admin#admin-players');
  await expect(targetRow).toHaveAttribute('data-filter-role', 'admin');
  await expect(targetRow.getByRole('button', { name: /Suspendre/ })).toHaveCount(0);
  await decide(page, targetRow.getByRole('button', { name: 'Retirer les droits d’administration à ' + target.username }), true, target.username);
  await page.goto('/admin#admin-players');
  await expect(targetRow).toHaveAttribute('data-filter-role', 'player');
  await decide(page, targetRow.getByRole('button', { name: 'Suspendre le compte ' + target.username }), false, target.username);
  await expect(targetRow).toHaveAttribute('data-filter-status', 'active');
  await decide(page, targetRow.getByRole('button', { name: 'Suspendre le compte ' + target.username }), true, target.username);
  await page.goto('/admin#admin-players');
  await expect(targetRow).toHaveAttribute('data-filter-status', 'suspended');
  await panel.getByLabel('État', { exact: true }).selectOption('suspended');
  await expect(panel.locator('tr[data-admin-filter-target="row"]:visible')).toHaveCount(1);
  for (const width of [1920, 1440, 1280, 768, 390]) {
    await page.setViewportSize({ width, height: 900 });
    expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(width);
  }
  await page.screenshot({ path: testInfo.outputPath('admin-suspended-390.png'), fullPage: true, animations: 'disabled' });
  await targetRow.getByRole('button', { name: 'Réactiver le compte ' + target.username }).click();
  await page.goto('/admin#admin-players');
  await expect(targetRow).toHaveAttribute('data-filter-status', 'active');

  await tabs.getByRole('tab', { name: 'Sécurité', exact: true }).click();
  const security = page.locator('#admin-security');
  await security.getByRole('searchbox', { name: 'Rechercher', exact: true }).fill('zz-aucun-compte');
  await expect(security.getByText('Aucun incident ne correspond à cette recherche.')).toBeVisible();
  await security.getByRole('searchbox', { name: 'Rechercher', exact: true }).fill(target.username);
  await security.getByRole('button', { name: 'Marquer examinée' }).click();
  await page.goto('/admin#admin-security');
  await expect(security.getByText('Aucune tentative refusée en attente d’examen.')).toHaveCount(1);
  await expect(security.getByText('Aucun incident ne correspond à cette recherche.')).toHaveCount(0);
  await tabs.getByRole('tab', { name: 'Journal', exact: true }).click();
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
      const returningRoute = await page.locator('.mission-card[data-status="returning"] p:not(.eyebrow)').innerText();
      expect(returningRoute.trim().startsWith('DestinationInterstellaire')).toBe(true);
      expect(returningRoute.trim().endsWith('OrigineInterstellaireSansEspaceAvecUnNomTresLongPourLeTest')).toBe(true);
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
