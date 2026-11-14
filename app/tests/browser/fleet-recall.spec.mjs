import { test, expect } from './support/isolated-test.mjs';
import { execFileSync } from 'node:child_process';
import AxeBuilder from '@axe-core/playwright';
import { prepareOwnedColony, revealVirginPlanet, runIsolatedSql, queryIsolatedSql } from './support/game-fixtures.mjs';

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
  return { playerId, memberId, planetId, universeId, email };
}

async function prepare(page) {
  const user=await enter(page), project=process.env.BROWSER_ROUTER;
  prepareOwnedColony(project,user.email);
  const target=revealVirginPlanet(project,user.email);
  runIsolatedSql(project,"UPDATE planet SET owner_id="+user.memberId+",name='Colonie de retour',acquired_at=UTC_TIMESTAMP() WHERE id="+target.id);
  runIsolatedSql(project,"INSERT INTO player_technology (player_universe_id,slug,tier,level) VALUES ("+user.memberId+",'colonial_expansion','basic',1) ON DUPLICATE KEY UPDATE level=GREATEST(level,1)");
  runIsolatedSql(project,"INSERT INTO fleet (player_universe_id,home_planet_id,status,mission,speed_ratio,cargo,reserved_fuel,orbit_fuel,orbit_duration_seconds,travel_seconds,dispatch_token) SELECT "+user.memberId+","+user.planetId+",'idle','idle',1,'{}',0,0,0,0,'' WHERE NOT EXISTS (SELECT 1 FROM fleet WHERE home_planet_id="+user.planetId+" AND status='idle')");
  runIsolatedSql(project,"INSERT INTO fleet_ship (fleet_id,slug,tier,quantity) SELECT id,'transport','basic',3 FROM fleet WHERE home_planet_id="+user.planetId+" AND status='idle' ON DUPLICATE KEY UPDATE quantity=3");
  await page.goto('/flottes');
  const form=page.locator('.fleet-form form').first();
  await form.locator('[name="ships[transport]"]').fill('1');
  await form.getByRole('button',{name:/Choisir la destination/}).click();
  await form.locator('[name="mission"]').selectOption('transport');
  await form.locator('[name="ratio"]').selectOption('0.1');
  await form.locator('select[name="owned_destination"]').selectOption(String(target.id));
  await form.getByRole('button',{name:/Vérifier la mission/}).click();
  const submit=form.getByRole('button',{name:/Envoyer la flotte/});
  await expect(submit).toBeEnabled();await submit.click();
  const fleetId=Number(queryIsolatedSql(project,"SELECT id FROM fleet WHERE player_universe_id="+user.memberId+" AND status='traveling' LIMIT 1"));
  expect(fleetId).toBeGreaterThan(0);
  expect(Number(queryIsolatedSql(project,'SELECT navigation IS NOT NULL FROM fleet WHERE id='+fleetId))).toBe(1);
  return {...user,project,target,fleetId};
}

test('Rappel confirmé : clavier, fret, retour programmé et rejeu sans duplication',async({page},testInfo)=>{
  test.setTimeout(120000);
  const data=await prepare(page);
  await expect.poll(()=>Number(queryIsolatedSql(data.project,'SELECT TIMESTAMPDIFF(SECOND,departure_at,UTC_TIMESTAMP()) FROM fleet WHERE id='+data.fleetId)),{timeout:20000,intervals:[1000]}).toBeGreaterThanOrEqual(8);
  const total=Number(queryIsolatedSql(data.project,'SELECT SUM(quantity) FROM fleet_ship'));
  await page.getByRole('link',{name:'Préparer le rappel de la flotte '+data.fleetId,exact:true}).click();
  await expect(page.getByRole('heading',{name:'Rappeler la flotte #'+data.fleetId,exact:true})).toBeVisible();
  const violations=(await new AxeBuilder({page}).include('.fleet-recall-panel').analyze()).violations.filter(v=>['serious','critical'].includes(v.impact));
  expect(violations).toEqual([]);
  await page.setViewportSize({width:390,height:844});
  expect(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1)).toBeTruthy();
  await page.screenshot({path:testInfo.outputPath('rappel-mobile.png'),fullPage:true,animations:'disabled'});
  const post=page.locator('.fleet-recall-panel form[method="post"]');
  const body=await post.evaluate(form=>Object.fromEntries(new FormData(form)));
  const confirm=page.getByRole('button',{name:'Confirmer le rappel',exact:true});
  await confirm.focus();await page.keyboard.press('Tab');await page.keyboard.press('Shift+Tab');
  await expect(confirm).toBeFocused();
  expect(await confirm.evaluate(element=>element.matches(':focus-visible') && getComputedStyle(element).outlineStyle==='solid' && parseFloat(getComputedStyle(element).outlineWidth)>0)).toBeTruthy();
  await page.keyboard.press('Enter');
  await expect(page.getByText('Flotte rappelée. Le retour est programmé.',{exact:true})).toBeVisible();
  expect(queryIsolatedSql(data.project,'SELECT status FROM fleet WHERE id='+data.fleetId).trim()).toBe('returning');
  expect(Number(queryIsolatedSql(data.project,'SELECT SUM(quantity) FROM fleet_ship'))).toBe(total);
  const status=await page.evaluate(async({url,body})=>(await fetch(url,{method:'POST',body:new URLSearchParams(body)})).status,{url:'/flottes/'+data.fleetId+'/rappel',body});
  expect(status).toBe(200);expect(Number(queryIsolatedSql(data.project,'SELECT SUM(quantity) FROM fleet_ship'))).toBe(total);
});

test('Origine perdue : choix de repli au toucher, origine historique conservée',async({browser},testInfo)=>{
  test.setTimeout(120000);
  const context=await browser.newContext({viewport:{width:390,height:844},hasTouch:true,isMobile:true});const page=await context.newPage();
  const data=await prepare(page);
  runIsolatedSql(data.project,'UPDATE planet SET owner_id=NULL,acquired_at=NULL WHERE id='+data.planetId);
  await page.goto('/flottes/'+data.fleetId+'/rappel');
  await expect(page.getByText('L’origine ne vous appartient plus.',{exact:false})).toBeVisible();
  await page.getByLabel('Colonie de repli',{exact:true}).selectOption(String(data.target.id));
  await page.getByRole('button',{name:'Calculer le retour',exact:true}).tap();
  await expect(page.getByRole('button',{name:'Confirmer le rappel',exact:true})).toBeVisible();
  await page.screenshot({path:testInfo.outputPath('repli-mobile.png'),fullPage:true,animations:'disabled'});
  await page.getByRole('button',{name:'Confirmer le rappel',exact:true}).tap();
  await expect(page.getByText('Flotte rappelée. Le retour est programmé.',{exact:true})).toBeVisible();
  const route=queryIsolatedSql(data.project,"SELECT CONCAT(home_planet_id,':',return_planet_id) FROM fleet WHERE id="+data.fleetId).trim();
  expect(route).toBe(data.planetId+':'+data.target.id);
  await page.goto('/carte-v2');
  const map=JSON.parse(await page.locator('.galaxy-v2').getAttribute('data-galaxy-v2-map-value'));
  const flight=map.flights.find(f=>f.id===data.fleetId);
  expect(flight.recallRoute.returnName).toBe('Colonie de retour');
  await context.close();
});

test('Ancienne flotte sans données fiables : explication et mission inchangée',async({page})=>{
  const data=await prepare(page);
  runIsolatedSql(data.project,'UPDATE fleet SET navigation=NULL,arrival_at=DATE_SUB(arrival_at,INTERVAL 1 SECOND) WHERE id='+data.fleetId);
  const before=queryIsolatedSql(data.project,"SELECT CONCAT(status,':',cargo,':',reserved_fuel) FROM fleet WHERE id="+data.fleetId);
  const response=await page.goto('/flottes/'+data.fleetId+'/rappel');expect(response.status()).toBe(422);
  await expect(page.getByRole('alert')).toContainText('continue normalement');
  await expect(page.getByRole('button',{name:'Confirmer le rappel',exact:true})).toHaveCount(0);
  expect(queryIsolatedSql(data.project,"SELECT CONCAT(status,':',cargo,':',reserved_fuel) FROM fleet WHERE id="+data.fleetId)).toBe(before);
});
