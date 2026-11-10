import { test, expect } from './support/isolated-test.mjs';
import { execFileSync } from 'node:child_process';
import AxeBuilder from '@axe-core/playwright';
import { runIsolatedSql, queryIsolatedSql } from './support/game-fixtures.mjs';

async function enter(page, admin = false, universe = '') {
  const suffix = crypto.randomUUID().slice(0, 8);
  const email = `signals-${suffix}@example.test`, username = `Signals-${suffix}`, password = 'Browser-test-2026!';
  await page.goto('/inscription');
  await page.getByLabel('Adresse email').fill(email);
  await page.getByLabel('Nom de commandant').fill(username);
  await page.getByLabel('Mot de passe (12 caractères minimum)', { exact: true }).fill(password);
  await page.getByLabel('Confirmer le mot de passe').fill(password);
  await page.getByRole('button', { name: 'Créer mon compte' }).click();
  await expect(page).toHaveURL(/connexion/);
  if (admin) execFileSync('docker', ['compose', '-p', process.env.BROWSER_ROUTER, '--env-file', '.env.local', 'exec', '-T', 'app', 'php', 'bin/console', 'app:admin:grant', email], { stdio: 'pipe' });
  await page.getByLabel('Adresse email').fill(email);
  await page.getByLabel('Mot de passe', { exact: true }).fill(password);
  await page.getByRole('button', { name: /Connexion/ }).click();
  const cards = page.locator('article.universe-card');
  await (universe ? cards.filter({ has: page.getByRole('heading', { name: new RegExp(universe) }) }) : cards.first()).getByRole('button').click();
  await expect(page).toHaveURL(/planetes\/\d+$/);
  const project = process.env.BROWSER_ROUTER;
  const playerId = Number(queryIsolatedSql(project, `SELECT id FROM player WHERE email='${email}'`));
  const memberId = Number(queryIsolatedSql(project, `SELECT current_player_universe_id FROM player WHERE id=${playerId}`));
  const planetId = Number(queryIsolatedSql(project, `SELECT id FROM planet WHERE owner_id=${memberId} LIMIT 1`));
  const universeId = Number(queryIsolatedSql(project, `SELECT universe_id FROM player_universe WHERE id=${memberId}`));
  return { playerId, memberId, planetId, universeId };
}

test('Fiche joueur : présence, temps par univers, refus des accès et responsive', async ({page,browser}, testInfo) => {
  test.setTimeout(120000);
  const userPage = await browser.newPage();
  const user = await enter(userPage);
  await enter(page, true);
  const project = process.env.BROWSER_ROUTER;
  expect(Number(queryIsolatedSql(project, `SELECT COUNT(*) FROM player_activity WHERE membership_id=${user.memberId}`))).toBe(1);
  const request = async values => userPage.evaluate(async values => {
    const body = document.body.dataset;
    const response = await fetch(body.playerActivityUrlValue, {method:'POST', headers:{'X-Requested-With':'XMLHttpRequest'}, body:new URLSearchParams({_token:body.playerActivityTokenValue,membership:body.playerActivityMembershipValue,idle_seconds:'0',...values})});
    return response.status;
  }, values);
  expect(await request({_token:'invalid'})).toBe(403);
  expect(await request({membership:String(user.memberId+100000)})).toBe(409);
  expect(await request({idle_seconds:'300'})).toBe(422);
  runIsolatedSql(project, `UPDATE player_activity SET sampled_at=UTC_TIMESTAMP()-INTERVAL 60 SECOND,total_seconds=3600,daily_seconds=JSON_OBJECT(DATE_FORMAT(UTC_TIMESTAMP(),'%Y-%m-%d'),3600) WHERE membership_id=${user.memberId}`);
  expect(await request({})).toBe(204);
  const seconds = Number(queryIsolatedSql(project, `SELECT total_seconds FROM player_activity WHERE membership_id=${user.memberId}`));
  expect(seconds).toBeGreaterThanOrEqual(3660);
  expect(seconds).toBeLessThan(3670);
  const denied = await userPage.goto(`/admin/joueurs/${user.playerId}`);
  expect(denied.status()).toBe(403);
  await userPage.close();
  await page.goto('/admin/joueurs');
  const row = page.locator('tr').filter({has:page.locator(`a[href='/admin/joueurs/${user.playerId}']`)});
  await expect(row).toContainText('En ligne');
  await row.getByRole('link').click();
  await expect(page.locator('.admin-player-membership')).toContainText('En ligne');
  for (const key of ['today','week','total']) await expect(page.locator(`[data-playtime=${key}]`)).toContainText('1 h');
  for (const theme of ['dark','light']) {
    runIsolatedSql(project, `UPDATE player SET preferences=JSON_SET(CASE WHEN JSON_TYPE(preferences)='OBJECT' THEN preferences ELSE JSON_OBJECT() END,'$.theme','${theme}') WHERE id<>${user.playerId}`);
    for (const width of [1440,390]) {
      await page.setViewportSize({width,height:900});await page.reload();await expect(page.locator('body')).toHaveAttribute('data-theme',theme);
      expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(width);
      expect((await new AxeBuilder({page}).withTags(['wcag2a','wcag2aa','wcag21aa']).analyze()).violations).toEqual([]);
      await page.screenshot({path:testInfo.outputPath(`player-${theme}-${width}.png`),fullPage:true});
    }
  }
  runIsolatedSql(project, `UPDATE player_activity SET last_seen_at=UTC_TIMESTAMP()-INTERVAL 6 MINUTE WHERE membership_id=${user.memberId}`);
  await page.reload();
  await expect(page.locator('.admin-player-membership')).toContainText('Hors ligne');
  expect(Number(queryIsolatedSql(project, `SELECT COUNT(*) FROM admin_audit_log WHERE action='admin_player_view' AND target_id=${user.playerId}`))).toBeGreaterThan(0);
});

test('Suivi client : onglet masqué et inactivité interrompent les échantillons', async ({page}) => {
  await page.clock.install();
  await enter(page);
  await page.waitForLoadState('load');
  const statuses = [];
  await page.route('**/activite/presence', async route => { statuses.push(route.request().postData());await route.fulfill({status:204}); });
  await page.clock.fastForward(301000);
  expect(statuses).toHaveLength(0);
  await page.keyboard.press('Shift');
  await page.clock.fastForward(60000);
  await expect.poll(() => statuses.length).toBe(1);
  await page.evaluate(() => Object.defineProperty(document,'hidden',{configurable:true,value:true}));
  await page.clock.fastForward(60000);
  expect(statuses).toHaveLength(1);
});
