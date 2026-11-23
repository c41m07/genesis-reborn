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

test('Le poste de commandement suit deux recherches et une attaque vers une autre colonie', async ({page, browser}) => {
  test.setTimeout(120000);
  const data = await prepareThreat(page, browser);
  const {project, defender, galaxy} = data;
  const outpost = Number(queryIsolatedSql(project, `SELECT p.id FROM planet p JOIN solar_system s ON s.id=p.solar_system_id
    WHERE s.galaxy_id=${galaxy} AND p.owner_id IS NULL AND p.npc_planet=0 ORDER BY p.id LIMIT 1`));
  expect(outpost).toBeGreaterThan(0);
  runIsolatedSql(project, `
    UPDATE planet SET owner_id=${defender.memberId},name='Balcon de Nérée' WHERE id=${outpost};
    UPDATE fleet f JOIN planet p ON p.id=${outpost} SET f.destination_id=p.id,f.destination_system_id=p.solar_system_id WHERE f.id=${data.fleet};
    INSERT INTO queue_item(planet_id,player_universe_id,queue_type,item_slug,tier,item_level,quantity,duration_seconds,paid_costs,queued_at,started_at,dispatch_token) VALUES
      (${outpost},${defender.memberId},'research','engineering_foundations','basic',1,1,900,'{}',UTC_TIMESTAMP(),UTC_TIMESTAMP(),'watch-first-research'),
      (${defender.planetId},${defender.memberId},'research','naval_engineering','basic',1,1,1200,'{}',UTC_TIMESTAMP(),UTC_TIMESTAMP(),'watch-second-research'),
      (${outpost},${defender.memberId},'building','mine_metal','basic',1,1,1500,'{}',UTC_TIMESTAMP(),UTC_TIMESTAMP(),'watch-later-building'),
      (${data.attacker.planetId},${data.attacker.memberId},'research','signature_masking','basic',1,1,300,'{}',UTC_TIMESTAMP(),UTC_TIMESTAMP(),'watch-foreign-research');
  `);
  await page.setViewportSize({width:1920,height:1080});
  await page.goto('/planetes/'+defender.planetId);
  const watch = page.locator('[data-live-region="overview-deadlines"]');
  await expect(watch.locator('li')).toHaveCount(2);
  await expect(watch.locator('li > div > span')).toHaveText(['Fin de recherche','Fin de recherche']);
  await expect(watch.locator('li').first()).toContainText('Balcon de Nérée');
  await expect(watch.locator('li').last()).toContainText(data.target);
  await expect(watch).not.toContainText('Signature');
  await expect(watch).not.toContainText('Attaque entrante');
  await page.getByRole('heading',{name:'Vue générale',exact:true}).focus();
  runIsolatedSql(project,`UPDATE fleet SET arrival_at=DATE_ADD(UTC_TIMESTAMP(),INTERVAL 29 MINUTE) WHERE id=${data.fleet}`);
  await expect(watch.locator('li').first()).toContainText('Attaque entrante',{timeout:15000});
  await expect(watch.locator('li').first()).toContainText('Balcon de Nérée');
  await expect(watch.locator('li')).toHaveCount(3);
  await expect(watch).not.toContainText('Source confidentielle');
  await expect(watch).not.toContainText('vaisseaux');
  const deadlines = await watch.locator('time').evaluateAll(times => times.map(time => time.dateTime));
  await page.goto('/planetes/'+outpost);
  await expect(watch.locator('li').first()).toContainText('Balcon de Nérée');
  expect(await watch.locator('time').evaluateAll(times => times.map(time => time.dateTime))).toEqual(deadlines);
});

test('Une attaque apparaît au seuil sans recharger, avec focus préservé et données privées masquées',async({page,browser},testInfo)=>{
  test.setTimeout(120000);
  const data=await prepareThreat(page,browser);
  await page.goto('/flottes');
  await expect(page.locator('[data-incoming-attack]')).toHaveCount(0);
  await expect(page.getByRole('heading',{name:/^Flottes entrantes\s+0$/})).toBeVisible();
  await expect(page.locator('.incoming-empty-message')).toHaveText('Aucune flotte entrante visible vers vos colonies.');
  const before=await page.evaluate(galaxy=>fetch('/flottes/surveillance?galaxy='+galaxy).then(r=>r.json()),data.galaxy);
  expect(before.flights).toEqual([]);expect(before.html).not.toContain('Source confidentielle');
  const focus=page.getByRole('navigation',{name:'Navigation des flottes'}).getByRole('link',{name:'Préparer une mission',exact:true});await focus.focus();
  runIsolatedSql(data.project,'UPDATE fleet SET arrival_at=DATE_ADD(UTC_TIMESTAMP(),INTERVAL 29 MINUTE) WHERE id='+data.fleet);
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
  runIsolatedSql(data.project,'UPDATE fleet SET arrival_at=DATE_ADD(UTC_TIMESTAMP(),INTERVAL 29 MINUTE) WHERE id='+data.fleet);
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
  const focus=page.getByRole('navigation',{name:'Navigation des flottes'}).getByRole('link',{name:'Préparer une mission',exact:true});await focus.focus();
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
  const reportLink = incoming.getByRole('link', {name: 'Consulter le récapitulatif du transport', exact: true});
  await expect(reportLink).toHaveCount(0);
  // Le rapport est une fixture indépendante de la cargaison privée de la flotte.
  runIsolatedSql(data.project,
    'INSERT INTO transport_report (fleet_id,sender_id,recipient_id,status,snapshot,created_at) VALUES ('+
    data.fleet+','+data.attacker.memberId+','+data.defender.memberId+",'traveling',"+
    "JSON_OBJECT('sender','Expéditeur de test','recipient','Destinataire de test','origin','Colonie de test','destination','Cible de test',"+
    "'sent',JSON_OBJECT('metal',123),'delivered',JSON_OBJECT(),'returned',JSON_OBJECT()),UTC_TIMESTAMP())",
  );
  const reportId = Number(queryIsolatedSql(data.project, 'SELECT id FROM transport_report WHERE fleet_id='+data.fleet));
  expect(reportId).toBeGreaterThan(0);
  await expect(reportLink).toHaveAttribute('href', '/rapports/transport/'+reportId, {timeout: 15000});
  await expect(focus).toBeFocused();
  const token=crypto.randomUUID();
  runIsolatedSql(data.project,"INSERT INTO fleet(player_universe_id,home_planet_id,destination_id,destination_system_id,status,mission,speed_ratio,cargo,reserved_fuel,orbit_fuel,orbit_duration_seconds,travel_seconds,departure_at,arrival_at,dispatch_token) SELECT "+data.defender.memberId+","+data.defender.planetId+",id,solar_system_id,'returning','transport',1,'{}',0,0,0,10800,DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 HOUR),DATE_ADD(UTC_TIMESTAMP(),INTERVAL 2 HOUR),'"+token+"' FROM planet WHERE id="+data.attacker.planetId);
  const own=Number(queryIsolatedSql(data.project,"SELECT id FROM fleet WHERE dispatch_token='"+token+"'"));
  runIsolatedSql(data.project,"INSERT INTO fleet_ship(fleet_id,slug,tier,quantity) VALUES("+own+",'transport','basic',1)");
  await page.setViewportSize({width:1440,height:1000});
  for(const [label,url,container] of [
    ['vue-generale','/planetes/'+data.defender.planetId,'.dashboard-secondary'],
    ['flottes','/flottes','#active-missions'],
  ]) {
    await page.goto(url);
    const ownPanel=page.locator(container+' [data-live-region="active-flights"]');
    const incomingPanel=page.locator(container+' .incoming-attack-panel');
    await expect(page.locator('.incoming-attack-panel')).toHaveCount(1);
    await expect(ownPanel).toBeVisible();await expect(incomingPanel).toBeVisible();
    await expect(ownPanel.locator('[data-active-fleet="'+own+'"]')).toHaveCount(1);
    await expect(incomingPanel.locator('[data-incoming-attack="'+data.fleet+'"]')).toHaveCount(1);
    const ownBox=await ownPanel.boundingBox(),incomingBox=await incomingPanel.boundingBox();
    if (label === 'vue-generale') {
      expect(await ownPanel.evaluate(element=>element.previousElementSibling.matches('.incoming-attacks'))).toBeTruthy();
      expect(ownBox.y).toBeGreaterThanOrEqual(incomingBox.y+incomingBox.height-1);
    } else {
      expect(await ownPanel.evaluate(element=>element.nextElementSibling.matches('.incoming-attacks'))).toBeTruthy();
      expect(incomingBox.y).toBeGreaterThanOrEqual(ownBox.y+ownBox.height-1);
    }
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
  // Dans les catalogues validés, vols et arrivées occupent deux suivis dépliables.
  for (const section of ['buildings','technologies','ships','defenses']) {
    await page.goto('/planetes/'+data.defender.planetId+'?section='+section);
    const rail = page.locator('.catalog-operations-rail');
    const flights = rail.locator('[data-catalog-disclosure="flights"]');
    const arrivals = rail.locator('[data-catalog-disclosure="incoming"]');
    await expect(rail).toHaveCSS('position', 'sticky');
    await expect(flights.locator(':scope > summary')).toContainText('Vols · 1');
    await expect(arrivals.locator(':scope > summary')).toContainText('Entrants · 1');
    await flights.locator(':scope > summary').click();
    await expect(flights.locator('[data-active-fleet="'+own+'"]')).toBeVisible();
    await arrivals.locator(':scope > summary').click();
    await expect(flights).not.toHaveAttribute('open');
    await expect(arrivals.locator('[data-incoming-attack="'+data.fleet+'"]')).toBeVisible();
    await expect(page.locator('.incoming-attack-panel')).toHaveCount(1);
    await expect(arrivals.locator('.flight-route')).toBeVisible();
    await expect(arrivals.getByRole('progressbar')).toBeVisible();
    const ownBox = await flights.locator(':scope > summary').boundingBox();
    const incomingBox = await arrivals.locator(':scope > summary').boundingBox();
    expect(incomingBox.x).toBeGreaterThanOrEqual(ownBox.x+ownBox.width-1);
    expect(Math.abs(incomingBox.y-ownBox.y)).toBeLessThan(2);
    await page.screenshot({path:testInfo.outputPath('cadres-'+section+'.png'),animations:'disabled'});
  }
  await page.goto('/planetes/'+data.defender.planetId+'?section=buildings');
  await expect(page.locator('[data-catalog-disclosure="flights"] > summary')).toContainText('Vols · 1');
  await expect(page.locator('[data-catalog-disclosure="incoming"] > summary')).toContainText('Entrants · 1');
  await expect(page.locator('[data-incoming-attack="'+own+'"]')).toHaveCount(0);
  for(const theme of ['dark','light']) {
    await setTheme(page,data,theme);
    for(const width of [1440,390]) {
      await page.setViewportSize({width,height:1000});
      expect(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1)).toBeTruthy();
      const rail = page.locator('.catalog-operations-rail');
      const arrivals = rail.locator('[data-catalog-disclosure="incoming"]');
      await expect(rail.locator('[data-live-region="active-flights"]')).toBeVisible();
      if (await arrivals.getAttribute('open') === null) await arrivals.locator(':scope > summary').click();
      await expect(arrivals.locator('.incoming-attack-panel')).toBeVisible();
      const violations=(await new AxeBuilder({page}).include('.incoming-attack-panel').analyze()).violations.filter(v=>['serious','critical'].includes(v.impact));
      expect(violations).toEqual([]);
      await page.screenshot({path:testInfo.outputPath(`flottes-entrantes-${theme}-${width}.png`),animations:'disabled'});
    }
  }
  await page.goto('/flottes');
  for (const width of [1440, 390]) {
    await page.setViewportSize({width, height: 844});
    await expect(reportLink).toBeVisible();
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth+1)).toBeTruthy();
  }
  const colonyLink = incoming.getByRole('link', {name: 'Ouvrir la colonie '+data.target, exact: true});
  await colonyLink.focus();
  await page.keyboard.press('Tab');
  await expect(reportLink).toBeFocused();
  expect(await reportLink.evaluate(element => element.matches(':focus-visible')
    && getComputedStyle(element).outlineStyle === 'solid')).toBeTruthy();
  await page.keyboard.press('Enter');
  await expect(page).toHaveURL(new RegExp('/rapports/transport/'+reportId+'$'));
  await expect(page.getByRole('heading', {name: 'Rapport de transport', exact: true})).toBeVisible();
  await expect(page.getByRole('table', {name: 'Quantités envoyées, livrées et retournées'})).toContainText('123,00');
  await page.goBack();
  await expect(reportLink).toBeVisible();
  runIsolatedSql(data.project,"UPDATE fleet SET mission='orbit',status='orbit_pending',arrival_at=NULL WHERE id="+data.fleet);
  await expect(incoming).toContainText('Demande d’orbite à traiter',{timeout:15000});
  await expect(reportLink).toHaveCount(0);
  await expect(incoming.locator('time')).toHaveCount(0);
  await incoming.getByRole('link',{name:'Consulter la demande d’orbite',exact:true}).click();
  await expect(page).toHaveURL(new RegExp('/flottes/orbites[?]colony='+data.defender.planetId+'$'));
  expect(errors).toEqual([]);
});


test('Le destinataire consulte le fret au clavier et au toucher sans perdre le lien du rapport', async ({ browser }) => {
  test.setTimeout(90000);
  const context = await browser.newContext({ hasTouch: true, viewport: { width: 1440, height: 900 } });
  const page = await context.newPage();
  const errors = [];
  page.on('pageerror', error => errors.push(error.message));
  try {
    const data = await prepareThreat(page, browser);
    await page.goto('/flottes');
    const row = page.locator('[data-incoming-attack="' + data.fleet + '"]');
    const detail = row.getByRole('region', { name: 'Composition et fret' });
    const trigger = row.getByRole('button', { name: /^Composition et fret/ });
    const report = row.getByRole('link', { name: 'Consulter le récapitulatif du transport', exact: true });
    const colonyLink = row.getByRole('link', { name: 'Ouvrir la colonie ' + data.target, exact: true });

    const statements = [
      "UPDATE fleet SET mission='transport',cargo=JSON_OBJECT('hydrogen',987654366),reserved_fuel=987654321 WHERE id="+data.fleet,
      "UPDATE player SET preferences=JSON_SET(CASE WHEN JSON_TYPE(preferences)='OBJECT' THEN preferences ELSE JSON_OBJECT() END,'$.transportReception','all') WHERE id="+data.defender.playerId,
      "INSERT INTO transport_report (fleet_id,sender_id,recipient_id,status,snapshot,created_at) VALUES ("+
        data.fleet+","+data.attacker.memberId+","+data.defender.memberId+",'traveling',JSON_OBJECT('sent',JSON_OBJECT('metal',123,'hydrogen',45)),UTC_TIMESTAMP())",
      "INSERT INTO fleet_mission_trace (fleet_id,universe_id,sender_id,recipient_id,mission,target_was_player,departed_at,snapshot) VALUES ("+
        data.fleet+","+data.defender.universeId+","+data.attacker.memberId+","+data.defender.memberId+",'transport',1,UTC_TIMESTAMP(),"+
        "JSON_OBJECT('ships',JSON_ARRAY(JSON_OBJECT('slug','fighter','tier','basic','quantity',3)), 'freight',JSON_OBJECT('metal',123,'hydrogen',45),'fuel',987654321))",
    ];
    for (const statement of statements) runIsolatedSql(data.project, statement);
    await expect(report).toBeVisible({ timeout: 15000 });
    await expect(trigger).toHaveCount(0);
    runIsolatedSql(data.project,
      'INSERT IGNORE INTO player_discovery (player_universe_id,solar_system_id) SELECT '+
      data.defender.memberId+',p.solar_system_id FROM fleet f JOIN planet p ON p.id=f.home_planet_id WHERE f.id='+data.fleet,
    );
    await expect(trigger).toBeVisible({ timeout: 15000 });
    await row.hover();
    await expect(detail).toBeVisible();
    await expect(detail).toContainText('Basique');
    await expect(detail).toContainText('Métal');
    await expect(detail).toContainText('123');
    await expect(detail).toContainText('Hydrogène');
    await expect(detail).toContainText('45');
    await expect(detail).not.toContainText('987654');
    await page.mouse.move(3, 3);
    await expect(detail).toBeHidden();

    await report.focus();
    await page.keyboard.press('Tab');
    await expect(trigger).toBeFocused();
    await expect(detail).toBeVisible();
    await page.keyboard.press('Escape');
    await expect(detail).toBeHidden();
    await page.setViewportSize({ width: 390, height: 844 });
    await trigger.tap();
    await expect(detail).toBeVisible();
    const rect = await detail.boundingBox();
    expect(rect.x).toBeGreaterThanOrEqual(0);
    expect(rect.x + rect.width).toBeLessThanOrEqual(390);
    expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(390);

    // Une arrivée reste connue après la suppression de la preuve synthétique,
    // mais le focus ne doit pas prolonger la présence du popover.
    await trigger.focus();
    runIsolatedSql(data.project,'DELETE FROM fleet_mission_trace WHERE fleet_id='+data.fleet);
    await expect(trigger).toHaveCount(0, { timeout: 15000 });
    await expect(detail).toHaveCount(0);
    await expect(colonyLink).toBeFocused();
    await expect(report).toBeVisible();
    expect(errors).toEqual([]);
  } finally {
    await context.close();
  }
});
