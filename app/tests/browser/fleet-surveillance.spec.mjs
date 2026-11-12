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
  return { playerId, memberId, planetId, universeId, email, username };
}

async function prepareThreat(page,browser) {
  const defender=await enter(page),project=process.env.BROWSER_ROUTER;
  const context=await browser.newContext();const second=await context.newPage();
  const attacker=await enter(second);
  const galaxy=Number(queryIsolatedSql(project,'SELECT s.galaxy_id FROM planet p JOIN solar_system s ON s.id=p.solar_system_id WHERE p.id='+defender.planetId));
  const source=Number(queryIsolatedSql(project,'SELECT p.id FROM planet p JOIN solar_system s ON s.id=p.solar_system_id WHERE s.galaxy_id='+galaxy+' AND NOT EXISTS (SELECT 1 FROM player_discovery d WHERE d.player_universe_id='+defender.memberId+' AND d.solar_system_id=s.id) ORDER BY p.id LIMIT 1'));
  expect(source).toBeGreaterThan(0);
  runIsolatedSql(project,"UPDATE planet SET owner_id="+attacker.memberId+",npc_planet=0,name='Source confidentielle' WHERE id="+source);
  runIsolatedSql(project,"INSERT INTO fleet (player_universe_id,home_planet_id,destination_id,destination_system_id,status,mission,speed_ratio,cargo,reserved_fuel,orbit_fuel,orbit_duration_seconds,travel_seconds,departure_at,arrival_at,dispatch_token) SELECT "+attacker.memberId+","+source+",id,solar_system_id,'traveling','attack',1,'{}',0,0,0,10800,DATE_SUB(UTC_TIMESTAMP(),INTERVAL 2 HOUR),DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 HOUR),'test-attack-surveillance' FROM planet WHERE id="+defender.planetId);
  const fleet=Number(queryIsolatedSql(project,"SELECT id FROM fleet WHERE player_universe_id="+attacker.memberId+" AND status='traveling' ORDER BY id DESC LIMIT 1"));
  runIsolatedSql(project,"INSERT INTO fleet_ship (fleet_id,slug,tier,quantity) VALUES ("+fleet+",'fighter','basic',3)");
  const target=queryIsolatedSql(project,'SELECT name FROM planet WHERE id='+defender.planetId);
  await context.close();
  return {project,defender,attacker,galaxy,fleet,target};
}

test('Une attaque apparaît au seuil sans recharger, avec focus préservé et données privées masquées',async({page,browser},testInfo)=>{
  test.setTimeout(120000);
  const data=await prepareThreat(page,browser);
  await page.goto('/flottes');
  await expect(page.locator('[data-incoming-attack]')).toHaveCount(0);
  const before=await page.evaluate(galaxy=>fetch('/flottes/surveillance?galaxy='+galaxy).then(r=>r.json()),data.galaxy);
  expect(before.flights).toEqual([]);expect(before.html).not.toContain('Source confidentielle');
  const focus=page.getByRole('link',{name:'Accéder au chantier spatial',exact:true});await focus.focus();
  runIsolatedSql(data.project,'UPDATE fleet SET arrival_at=DATE_ADD(UTC_TIMESTAMP(),INTERVAL 4 MINUTE) WHERE id='+data.fleet);
  const warning=page.locator('[data-incoming-attack="'+data.fleet+'"]');
  await expect(warning).toBeVisible({timeout:15000});await expect(focus).toBeFocused();
  await expect(warning).toContainText(data.target);await expect(warning).toContainText(data.attacker.username);
  await expect(warning).toContainText('3 vaisseaux');await expect(warning).toContainText('Origine non découverte');
  expect(await warning.textContent()).not.toContain('fighter');expect(await warning.textContent()).not.toContain('Source confidentielle');
  await expect(page.locator('[data-incoming-attacks-target="status"]')).toContainText('1 attaque');
  const violations=(await new AxeBuilder({page}).include('.incoming-attack-panel').analyze()).violations.filter(v=>['serious','critical'].includes(v.impact));
  expect(violations).toEqual([]);
  await page.evaluate(()=>document.body.dataset.theme='light');
  const lightViolations=(await new AxeBuilder({page}).include('.incoming-attack-panel').analyze()).violations.filter(v=>['serious','critical'].includes(v.impact));
  expect(lightViolations).toEqual([]);
  await page.screenshot({path:testInfo.outputPath('attaque-detectee-clair.png'),animations:'disabled'});
  await page.evaluate(()=>document.body.dataset.theme='dark');
  await page.setViewportSize({width:390,height:844});
  expect(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1)).toBeTruthy();
  await page.screenshot({path:testInfo.outputPath('attaque-detectee-mobile.png'),animations:'disabled'});
  const colony=warning.getByRole('link',{name:'Ouvrir la colonie '+data.target,exact:true});
  await colony.focus();await page.keyboard.press('Tab');await page.keyboard.press('Shift+Tab');await expect(colony).toBeFocused();
  expect(await colony.evaluate(element=>element.matches(':focus-visible')&&getComputedStyle(element).outlineStyle==='solid')).toBeTruthy();
  await page.keyboard.press('Enter');await expect(page).toHaveURL(new RegExp('/planetes/'+data.defender.planetId+'$'));
});

test('Carte tactile : détection synchronisée, vue conservée, horloge serveur et sondes masquées',async({browser},testInfo)=>{
  test.setTimeout(120000);
  const context=await browser.newContext({viewport:{width:390,height:844},hasTouch:true,isMobile:true});
  const page=await context.newPage();const errors=[];page.on('pageerror',error=>errors.push(error.message));
  const data=await prepareThreat(page,browser);
  await page.addInitScript(()=>{const now=Date.now.bind(Date);Date.now=()=>now()+7200000;});
  await page.emulateMedia({reducedMotion:'reduce'});await page.goto('/carte-v2');
  await page.getByLabel('Rechercher un système ou une planète connue',{exact:true}).fill(data.target);
  await page.locator('#gv2-search-results button').first().tap();
  await page.getByRole('button',{name:/Vue rapprochée/}).tap();
  await expect(page.locator('.gv2-canvas--planet.is-active')).toBeVisible();
  runIsolatedSql(data.project,'UPDATE fleet SET arrival_at=DATE_ADD(UTC_TIMESTAMP(),INTERVAL 4 MINUTE) WHERE id='+data.fleet);
  const warning=page.locator('[data-incoming-attack="'+data.fleet+'"]');
  await expect(warning).toBeVisible({timeout:15000});await expect(warning.locator('time')).toContainText('min');
  await expect(warning.locator('time')).not.toContainText('Impact imminent');
  await expect(page.locator('.gv2-canvas--planet.is-active')).toBeVisible();
  const entry=page.locator('[data-flight-id="'+data.fleet+'"]');await expect(entry).toBeAttached();
  await expect(entry).toContainText('Origine non découverte');await expect(entry).toContainText('3 vaisseaux');
  expect(await entry.textContent()).not.toContain('fighter');
  await page.screenshot({path:testInfo.outputPath('carte-menace-tactile.png'),animations:'disabled'});
  runIsolatedSql(data.project,"UPDATE fleet SET mission='spy' WHERE id="+data.fleet);
  await expect(warning).toHaveCount(0,{timeout:15000});await expect(entry).toHaveCount(0,{timeout:15000});
  await expect(page.locator('.gv2-canvas--planet.is-active')).toBeVisible();
  expect(errors).toEqual([]);await context.close();
});
