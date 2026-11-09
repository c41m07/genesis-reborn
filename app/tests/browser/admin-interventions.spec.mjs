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

async function post(page, url, form) {
  return page.evaluate(async ({url,form}) => (await fetch(url,{method:'POST',body:new URLSearchParams(form)})).status,{url,form});
}

test('Ressources admin : confirmation, ajout, répétition et refus sans perte', async ({page,browser}, testInfo) => {
  test.setTimeout(120000);
  const userPage = await browser.newPage();const user = await enter(userPage);await enter(page,true);
  const project = process.env.BROWSER_ROUTER;
  const stock = () => Number(queryIsolatedSql(project,`SELECT current_stock FROM planet_resource WHERE planet_id=${user.planetId} AND resource_type='metal'`));
  await page.goto(`/admin/joueurs/${user.playerId}`);
  const details = page.locator(`#colony-${user.planetId}`);await details.locator('summary').click();
  const form = details.locator('form');
  await form.locator('[name="amounts[metal]"]').fill('100');await form.getByLabel('Motif de la correction (obligatoire)').fill('Réparation du ticket 123');
  const before = stock();await form.getByRole('button').click();
  expect(stock()).toBe(before);
  await form.getByRole('checkbox').check();
  const payload = await form.evaluate(f => Object.fromEntries(new FormData(f)));
  const action = await form.getAttribute('action');await form.getByRole('button').click();
  await expect(page.locator('.flash.success')).toContainText('Correction des ressources');
  const after = stock();expect(after).toBeGreaterThanOrEqual(before+100);expect(after).toBeLessThan(before+110);
  await post(page,action,payload);expect(stock()).toBe(after);
  expect(await post(page,action,{...payload,_token:'invalid'})).toBe(403);
  expect(await post(userPage,action,payload)).toBe(403);
  expect(stock()).toBe(after);
  await page.reload();await details.locator('summary').click();
  await form.locator('[name="operation"]').selectOption('remove');await form.locator('[name="amounts[metal]"]').fill('99999999');
  await form.getByLabel('Motif de la correction (obligatoire)').fill('Vérification du refus');await form.getByRole('checkbox').check();await form.getByRole('button').click();
  await expect(page.locator('.flash.error')).toContainText('Correction refusée');expect(stock()).toBe(after);
  await details.locator('summary').click();await page.setViewportSize({width:390,height:900});
  expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(390);
  expect((await new AxeBuilder({page}).withTags(['wcag2a','wcag2aa','wcag21aa']).analyze()).violations).toEqual([]);
  await page.screenshot({path:testInfo.outputPath('resources-390.png'),fullPage:true});
  const log = JSON.parse(queryIsolatedSql(project,`SELECT details FROM admin_audit_log WHERE action='admin_resources_adjust' AND target_id=${user.planetId}`));
  expect(queryIsolatedSql(project,`SELECT JSON_UNQUOTE(JSON_EXTRACT(details,'$.reason')) FROM admin_audit_log WHERE action='admin_resources_adjust' AND target_id=${user.planetId}`)).toBe('Réparation du ticket 123');
  expect(log.after.metal-log.before.metal).toBeCloseTo(100,4);
  runIsolatedSql(project,`UPDATE player SET preferences=JSON_SET(CASE WHEN JSON_TYPE(preferences)='OBJECT' THEN preferences ELSE JSON_OBJECT() END,'$.theme','light') WHERE id<>${user.playerId}`);
  await page.reload();await details.locator('summary').click();
  expect((await new AxeBuilder({page}).withTags(['wcag2a','wcag2aa','wcag21aa']).analyze()).violations).toEqual([]);
  await userPage.close();
});

test('Flotte admin : accélération, formulaire périmé, rappel et journal', async ({page}, testInfo) => {
  test.setTimeout(120000);const user = await enter(page,true);const project=process.env.BROWSER_ROUTER;
  runIsolatedSql(project,`INSERT INTO fleet (player_universe_id,home_planet_id,destination_id,mission,status,speed_ratio,cargo,reserved_fuel,orbit_fuel,orbit_duration_seconds,departure_at,arrival_at,travel_seconds,dispatch_token) VALUES (${user.memberId},${user.planetId},${user.planetId},'transport','traveling',1,'{"metal":100,"hydrogen":200}',100,0,0,UTC_TIMESTAMP(),UTC_TIMESTAMP()+INTERVAL 1 HOUR,3600,UUID())`);
  const id=Number(queryIsolatedSql(project,`SELECT MAX(id) FROM fleet WHERE player_universe_id=${user.memberId}`));
  runIsolatedSql(project,`INSERT INTO fleet_ship (fleet_id,slug,tier,quantity) VALUES (${id},'transport','basic',1)`);
  const token=queryIsolatedSql(project,`SELECT dispatch_token FROM fleet WHERE id=${id}`);
  await page.goto(`/admin/flottes/${id}`);
  const form=page.locator('.admin-intervention-form');
  await expect(page.locator('body')).not.toContainText(token);
  const version=await form.locator('[name=expected_version]').inputValue();expect(version).not.toBe(token);
  await form.getByLabel('Nouveau délai restant en secondes (pour un changement d’échéance)').fill('120');await form.getByLabel('Motif de l’intervention (obligatoire)').fill('Rétablir le trajet');await form.getByRole('checkbox').check();await form.getByRole('button').click();
  await expect(page.locator('.flash.success')).toContainText('Intervention sur la flotte');
  expect(queryIsolatedSql(project,`SELECT dispatch_token FROM fleet WHERE id=${id}`)).not.toBe(token);
  const fresh=await form.evaluate(f=>Object.fromEntries(new FormData(f)));
  await post(page,`/admin/flottes/${id}/intervention`,{...fresh,expected_version:version,confirmed:'1',reason:'Ancienne fiche'});
  expect(Number(queryIsolatedSql(project,`SELECT COUNT(*) FROM admin_audit_log WHERE action='admin_fleet_reschedule' AND target_id=${id}`))).toBe(1);
  await page.reload();await form.locator('[name=operation]').selectOption('recall');await form.getByLabel('Motif de l’intervention (obligatoire)').fill('Rappel de contrôle');await form.getByRole('checkbox').check();await form.getByRole('button').click();
  await expect(page.locator('.flash.success')).toContainText('Intervention sur la flotte');
  expect(queryIsolatedSql(project,`SELECT status FROM fleet WHERE id=${id}`)).toBe('returning');
  expect(Number(queryIsolatedSql(project,`SELECT COUNT(*) FROM admin_audit_log WHERE action='admin_fleet_recall' AND target_id=${id}`))).toBe(1);
  await page.setViewportSize({width:390,height:900});
  expect((await new AxeBuilder({page}).withTags(['wcag2a','wcag2aa','wcag21aa']).analyze()).violations).toEqual([]);
  await page.screenshot({path:testInfo.outputPath('fleet-intervention-390.png'),fullPage:true});
});
