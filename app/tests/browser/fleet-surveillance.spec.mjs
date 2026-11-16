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

async function setTheme(page, data, theme) {
  runIsolatedSql(data.project,`UPDATE player SET preferences=JSON_SET(CASE WHEN JSON_TYPE(preferences)='OBJECT' THEN preferences ELSE JSON_OBJECT() END,'$.theme','${theme}') WHERE id=${data.defender.playerId}`);
  await page.reload();
  await expect(page.locator('body')).toHaveAttribute('data-theme',theme);
  await expect(page.locator('link[href$="/styles/wireframe-theme.css"]')).toHaveCount(theme==='light'?1:0);
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
  await expect(page.getByRole('heading',{name:/^Flottes entrantes\s+0$/})).toBeVisible();
  await expect(page.locator('.incoming-empty-message')).toHaveText('Aucune flotte entrante visible vers vos colonies.');
  const before=await page.evaluate(galaxy=>fetch('/flottes/surveillance?galaxy='+galaxy).then(r=>r.json()),data.galaxy);
  expect(before.flights).toEqual([]);expect(before.html).not.toContain('Source confidentielle');
  const focus=page.getByRole('link',{name:'Accéder au chantier spatial',exact:true});await focus.focus();
  runIsolatedSql(data.project,'UPDATE fleet SET arrival_at=DATE_ADD(UTC_TIMESTAMP(),INTERVAL 4 MINUTE) WHERE id='+data.fleet);
  const warning=page.locator('[data-incoming-attack="'+data.fleet+'"]');
  await expect(warning).toBeVisible({timeout:15000});await expect(focus).toBeFocused();
  await expect(warning.locator('[data-incoming-progress]')).toHaveCount(0);
  await expect(warning).toContainText(data.target);await expect(warning).toContainText(data.attacker.username);
  await expect(warning).toContainText('3 vaisseaux');await expect(warning).toContainText('Origine non découverte');
  expect(await warning.textContent()).not.toContain('fighter');expect(await warning.textContent()).not.toContain('Source confidentielle');
  await expect(page.locator('[data-incoming-attacks-target="status"]')).toContainText('1 attaque');
  const violations=(await new AxeBuilder({page}).include('.incoming-attack-panel').analyze()).violations.filter(v=>['serious','critical'].includes(v.impact));
  expect(violations).toEqual([]);
  await setTheme(page,data,'light');
  const lightViolations=(await new AxeBuilder({page}).include('.incoming-attack-panel').analyze()).violations.filter(v=>['serious','critical'].includes(v.impact));
  expect(lightViolations).toEqual([]);
  await page.screenshot({path:testInfo.outputPath('attaque-detectee-clair.png'),animations:'disabled'});
  await setTheme(page,data,'dark');
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
  await expect(page.locator('.incoming-attack-panel')).toHaveCount(0);
  const entry=page.locator('[data-flight-id="'+data.fleet+'"]');await expect(entry).toBeAttached({timeout:15000});
  await expect(page.locator('.gv2-canvas--planet.is-active')).toBeVisible();
  await expect(entry).toContainText('Origine non découverte');await expect(entry).toContainText('3 vaisseaux');
  expect(await entry.textContent()).not.toContain('fighter');
  await page.screenshot({path:testInfo.outputPath('carte-menace-tactile.png'),animations:'disabled'});
  runIsolatedSql(data.project,"UPDATE fleet SET mission='spy' WHERE id="+data.fleet);
  await expect(entry).toHaveCount(0,{timeout:15000});
  await expect(page.locator('[data-incoming-attacks-target="status"]')).toContainText('Aucune flotte entrante visible');
  await expect(page.locator('.incoming-attack-panel')).toHaveCount(0);
  await expect(page.locator('.gv2-canvas--planet.is-active')).toBeVisible();
  runIsolatedSql(data.project,"UPDATE fleet SET mission='transport' WHERE id="+data.fleet);
  await page.goto('/flottes');
  const warning=page.locator('[data-incoming-attack="'+data.fleet+'"]');
  await expect(warning).toBeVisible();await expect(warning.locator('time')).toContainText('min');
  await expect(warning.locator('time')).not.toContainText('Arrivée en cours');
  const progress=warning.getByRole('progressbar');
  await expect(progress).toBeVisible();
  const percent=Number(await progress.getAttribute('aria-valuenow'));
  expect(percent).toBeGreaterThan(0);expect(percent).toBeLessThan(100);
  expect(errors).toEqual([]);await context.close();
});


test('Flottes entrantes : transport neutre, propre retour séparé et demande d’orbite', async ({ page, browser }, testInfo) => {
  test.setTimeout(150000);
  const errors=[];page.on('pageerror',error=>errors.push(error.message));
  const data=await prepareThreat(page,browser);
  await page.goto('/flottes');
  await expect(page.locator('[data-incoming-attack]')).toHaveCount(0);
  await expect(page.getByRole('heading',{name:/^Flottes entrantes\s+0$/})).toBeVisible();
  await expect(page.locator('.incoming-empty-message')).toHaveText('Aucune flotte entrante visible vers vos colonies.');
  const focus=page.getByRole('link',{name:'Accéder au chantier spatial',exact:true});await focus.focus();
  runIsolatedSql(data.project,"UPDATE fleet SET mission='transport',cargo='{\"metal\":987654321}' WHERE id="+data.fleet);
  const incoming=page.locator('[data-incoming-attack="'+data.fleet+'"]');
  await expect(incoming).toBeVisible({timeout:15000});await expect(focus).toBeFocused();
  await expect(page.getByRole('heading',{name:/^Flottes entrantes\s+1$/})).toBeVisible();
  await expect(incoming).toContainText('Transport');await expect(incoming).toContainText('Arrivée dans');
  await expect(incoming).not.toContainText('Impact');await expect(incoming).toContainText('Origine non découverte');
  await expect(page.locator('.incoming-attack-panel')).not.toHaveClass(/has-hostile/);
  await expect(page.locator('[data-incoming-attacks-target="status"]')).toContainText('1 flotte');
  const payload=await page.evaluate(()=>fetch('/flottes/surveillance').then(r=>r.json()));
  expect(JSON.stringify(payload)).not.toContain('987654321');expect(JSON.stringify(payload)).not.toContain('Source confidentielle');
  const token=crypto.randomUUID();
  runIsolatedSql(data.project,"INSERT INTO fleet(player_universe_id,home_planet_id,destination_id,destination_system_id,status,mission,speed_ratio,cargo,reserved_fuel,orbit_fuel,orbit_duration_seconds,travel_seconds,departure_at,arrival_at,dispatch_token) SELECT "+data.defender.memberId+","+data.defender.planetId+",id,solar_system_id,'returning','transport',1,'{}',0,0,0,10800,DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 HOUR),DATE_ADD(UTC_TIMESTAMP(),INTERVAL 2 HOUR),'"+token+"' FROM planet WHERE id="+data.attacker.planetId);
  const own=Number(queryIsolatedSql(data.project,"SELECT id FROM fleet WHERE dispatch_token='"+token+"'"));
  runIsolatedSql(data.project,"INSERT INTO fleet_ship(fleet_id,slug,tier,quantity) VALUES("+own+",'transport','basic',1)");
  await page.setViewportSize({width:1440,height:1000});
  for(const [label,url,container] of [
    ['vue-generale','/planetes/'+data.defender.planetId,'.dashboard-secondary'],
    ['flottes','/flottes','.fleet-operations-rail'],
    ...['buildings','technologies','ships','defenses'].map(section=>[section,'/planetes/'+data.defender.planetId+'?section='+section,'.colony-catalog-aside']),
  ]) {
    await page.goto(url);
    const ownPanel=page.locator(container+' [data-live-region="active-flights"]');
    const incomingPanel=page.locator(container+' .incoming-attack-panel');
    await expect(page.locator('.incoming-attack-panel')).toHaveCount(1);
    await expect(ownPanel).toBeVisible();await expect(incomingPanel).toBeVisible();
    await expect(ownPanel.locator('[data-active-fleet="'+own+'"]')).toHaveCount(1);
    await expect(incomingPanel.locator('[data-incoming-attack="'+data.fleet+'"]')).toHaveCount(1);
    expect(await ownPanel.evaluate(element=>element.nextElementSibling.matches('.incoming-attacks'))).toBeTruthy();
    const ownBox=await ownPanel.boundingBox(),incomingBox=await incomingPanel.boundingBox();
    expect(incomingBox.y).toBeGreaterThanOrEqual(ownBox.y+ownBox.height-1);
    expect(Math.abs(incomingBox.x-ownBox.x)).toBeLessThan(2);
    expect(Math.abs(incomingBox.width-ownBox.width)).toBeLessThan(2);
    for(const property of ['backgroundImage','backgroundColor','borderRadius','padding','borderTopWidth']) {
      expect(await incomingPanel.evaluate((element,key)=>getComputedStyle(element)[key],property))
        .toEqual(await ownPanel.evaluate((element,key)=>getComputedStyle(element)[key],property));
    }
    for(const property of ['padding','borderInlineStartWidth','borderInlineStartStyle']) {
      expect(await incomingPanel.locator('.flight-operation').evaluate((element,key)=>getComputedStyle(element)[key],property))
        .toEqual(await ownPanel.locator('.flight-operation').evaluate((element,key)=>getComputedStyle(element)[key],property));
    }
    await expect(incomingPanel.locator('.flight-operation .operation-heading')).toBeVisible();
    await expect(incomingPanel.locator('.flight-route')).toBeVisible();
    await expect(incomingPanel.getByRole('progressbar')).toBeVisible();
    await page.locator(container).screenshot({path:testInfo.outputPath('cadres-'+label+'.png'),animations:'disabled'});
  }
  await page.goto('/planetes/'+data.defender.planetId+'?section=buildings');
  await expect(page.getByRole('heading',{name:/^Flottes en vol/})).toBeVisible();
  await expect(page.getByRole('heading',{name:/^Flottes entrantes\s+1$/})).toBeVisible();
  await expect(page.locator('[data-incoming-attack="'+own+'"]')).toHaveCount(0);
  for(const theme of ['dark','light']) {
    await setTheme(page,data,theme);
    for(const width of [1440,390]) {
      await page.setViewportSize({width,height:1000});
      expect(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1)).toBeTruthy();
      await expect(page.locator('.colony-catalog-aside [data-live-region="active-flights"]')).toBeVisible();
      await expect(page.locator('.colony-catalog-aside .incoming-attack-panel')).toBeVisible();
      const violations=(await new AxeBuilder({page}).include('.incoming-attack-panel').analyze()).violations.filter(v=>['serious','critical'].includes(v.impact));
      expect(violations).toEqual([]);
      await page.screenshot({path:testInfo.outputPath(`flottes-entrantes-${theme}-${width}.png`),animations:'disabled'});
    }
  }
  runIsolatedSql(data.project,"UPDATE fleet SET mission='orbit',status='orbit_pending',arrival_at=NULL WHERE id="+data.fleet);
  await expect(incoming).toContainText('Demande d’orbite à traiter',{timeout:15000});
  await expect(incoming.locator('time')).toHaveCount(0);
  await incoming.getByRole('link',{name:'Consulter la demande d’orbite',exact:true}).click();
  await expect(page).toHaveURL(new RegExp('/flottes[?]colony='+data.defender.planetId+'$'));
  expect(errors).toEqual([]);
});
