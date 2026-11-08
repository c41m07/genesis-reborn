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

test('Admin : nouveautés personnelles, total, actualisation et archives masquées', async ({ page, browser }, testInfo) => {
  test.setTimeout(120000);
  const second = await browser.newPage(), visitor = await browser.newPage();
  const own = await enter(page, true), other = await enter(second, true), regular = await enter(visitor);
  const project = process.env.BROWSER_ROUTER;
  runIsolatedSql(project, `INSERT INTO social_message (sender_id,recipient_id,content,created_at) VALUES (${own.memberId},${regular.memberId},'Message signalé',UTC_TIMESTAMP())`);
  const messageId = Number(queryIsolatedSql(project, 'SELECT MAX(id) FROM social_message'));
  runIsolatedSql(project, `INSERT INTO message_report (message_id,reporter_id,reason,status,created_at) VALUES (${messageId},${regular.memberId},'À examiner','open',UTC_TIMESTAMP())`);
  runIsolatedSql(project, `INSERT INTO admin_audit_log (action,target_type,target_id,details,created_at) VALUES ('admin_access_denied','admin_console',0,'{}',UTC_TIMESTAMP())`);
  runIsolatedSql(project, `INSERT INTO user_feedback (player_id,type,priority,title,description,status,created_at) VALUES
    (${regular.playerId},'bug','medium','Nouveau feedback','Description du retour','open',UTC_TIMESTAMP()),
    (${regular.playerId},'other','low','Ancien feedback archivé','Historique à conserver','archived',UTC_TIMESTAMP())`);
  const totalBadge = target => target.locator('.admin-header-link [data-category="total"]');
  await page.goto('/');
  await expect(totalBadge(page)).toHaveText('3');
  for (const width of [1440,768,390]) {
    await page.setViewportSize({width, height:900});
    await page.goto('/');
    await expect(totalBadge(page)).toBeVisible();
    expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(width);
    await page.screenshot({path:testInfo.outputPath(`admin-badge-${width}.png`)});
  }
  await second.goto('/');
  await expect(totalBadge(second)).toHaveText('3');
  await page.goto('/admin/flottes');
  for (const category of ['reports', 'security', 'feedbacks']) await expect(page.locator(`.admin-sidebar [data-category="${category}"]`)).toHaveText('1');
  runIsolatedSql(project, `INSERT INTO user_feedback (player_id,type,priority,title,description,status,created_at) VALUES (${regular.playerId},'bug','high','Second nouveau feedback','Autre retour','open',UTC_TIMESTAMP())`);
  await page.evaluate(() => window.dispatchEvent(new CustomEvent('genesis:refresh')));
  await expect(page.locator('.admin-sidebar [data-category="feedbacks"]')).toHaveText('2');
  await page.goto('/admin');
  await page.goto('/');
  await expect(totalBadge(page)).toHaveText('2');
  await second.goto('/');
  await expect(totalBadge(second)).toHaveText('4');
  await page.goto('/admin/feedbacks');
  await expect(page.locator('.feedback-admin-card')).toHaveCount(2);
  await expect(page.getByText('Ancien feedback archivé', { exact: true })).toHaveCount(0);
  await page.getByRole('combobox', { name: 'Statut', exact: true }).selectOption('archived');
  await page.getByRole('button', { name: 'Appliquer les filtres' }).click();
  await expect(page.locator('.feedback-admin-card')).toHaveCount(1);
  await expect(page.getByText('Ancien feedback archivé', { exact: true })).toBeVisible();
  await page.getByRole('combobox', { name: 'Statut', exact: true }).selectOption('all');
  await page.getByRole('button', { name: 'Appliquer les filtres' }).click();
  await expect(page.locator('.feedback-admin-card')).toHaveCount(3);
  await page.goto('/');
  await expect(totalBadge(page)).toBeHidden();
  runIsolatedSql(project, `INSERT INTO user_feedback (player_id,type,priority,title,description,status,created_at) VALUES (${regular.playerId},'other','low','Retour après lecture','Nouveauté réelle','open',UTC_TIMESTAMP())`);
  await page.evaluate(() => window.dispatchEvent(new CustomEvent('genesis:refresh')));
  await expect(totalBadge(page)).toHaveText('1');
  for (const route of ['/admin/flottes', '/admin/notifications']) {
    await visitor.goto(route);
    await expect(visitor.getByRole('heading', { name: 'Accès refusé' })).toBeVisible();
  }
  await second.close();
  await visitor.close();
});

test('Admin : huit missions, univers, statuts, pagination, clavier et responsive', async ({ page, browser }, testInfo) => {
  test.setTimeout(120000);
  const peer = await browser.newPage();
  const own = await enter(page, true), other = await enter(peer, false, 'Turbo');
  expect(own.universeId).not.toBe(other.universeId);
  const project = process.env.BROWSER_ROUTER;
  const missions = [['explore','completed'],['transport','returning'],['colonize','traveling'],['attack','attacking'],['spy','completed'],['defend','defending'],['orbit','orbit_pending'],['conquer','occupying'],['orbit','orbiting'],['attack','completed']];
  const row = (member, mission, status) => `(${member.memberId},${member.planetId},${member.planetId},'${mission}','${status}',1,'{}',0,0,0,UTC_TIMESTAMP(),${status === 'completed' ? 'NULL' : 'DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 HOUR)'},3600,UUID())`;
  const columns = '(player_universe_id,home_planet_id,destination_id,mission,status,speed_ratio,cargo,reserved_fuel,orbit_fuel,orbit_duration_seconds,departure_at,arrival_at,travel_seconds,dispatch_token)';
  runIsolatedSql(project, `INSERT INTO fleet ${columns} VALUES ${missions.map(([mission,status], index) => row(index % 2 === 0 ? own : other, mission, status)).join(',')}`);
  await page.goto('/admin/flottes');
  await expect(page.locator('tbody tr')).toHaveCount(10);
  await page.getByRole('combobox', { name: 'Mission', exact: true }).selectOption('transport');
  await page.getByRole('combobox', { name: 'Statut', exact: true }).selectOption('returning');
  await page.getByRole('button', { name: 'Filtrer les missions' }).click();
  await expect(page.locator('tbody tr')).toHaveCount(1);
  await page.getByRole('combobox', { name: 'Univers', exact: true }).selectOption(String(own.universeId));
  await page.getByRole('button', { name: 'Filtrer les missions' }).click();
  await expect(page.getByText('Aucune mission ne correspond aux filtres.', { exact: true })).toBeVisible();
  runIsolatedSql(project, `INSERT INTO fleet ${columns} VALUES ${Array.from({length:55}, () => row(own,'explore','completed')).join(',')}`);
  await page.getByRole('combobox', { name: 'Mission', exact: true }).selectOption('explore');
  await page.getByRole('combobox', { name: 'Statut', exact: true }).selectOption('completed');
  await page.getByRole('button', { name: 'Filtrer les missions' }).click();
  await expect(page.locator('tbody tr')).toHaveCount(50);
  await page.getByRole('link', { name: 'Page suivante' }).click();
  await expect(page.locator('tbody tr')).toHaveCount(6);
  await expect(page.getByRole('combobox', { name: 'Univers', exact: true })).toHaveValue(String(own.universeId));
  await expect(page.getByRole('combobox', { name: 'Mission', exact: true })).toHaveValue('explore');
  for (const theme of ['dark','light']) {
    runIsolatedSql(project, `UPDATE player SET preferences=JSON_SET(CASE WHEN JSON_TYPE(preferences)='OBJECT' THEN preferences ELSE JSON_OBJECT() END,'$.theme','${theme}') WHERE id=${own.playerId}`);
    for (const width of [1440,768,390]) {
      await page.setViewportSize({ width, height:900 });
      await page.reload();
      expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(width);
      await page.screenshot({ path:testInfo.outputPath(`missions-${theme}-${width}.png`), fullPage:true });
    }
    const axe = await new AxeBuilder({page}).withTags(['wcag2a','wcag2aa','wcag21aa']).analyze();
    expect(axe.violations).toEqual([]);
  }
  const region = page.getByRole('region', {name:'Historique des missions, tableau défilant'});
  await region.focus();
  await page.keyboard.press('ArrowRight');
  await expect.poll(() => region.evaluate(node => node.scrollLeft)).toBeGreaterThan(0);
  await peer.close();
});
