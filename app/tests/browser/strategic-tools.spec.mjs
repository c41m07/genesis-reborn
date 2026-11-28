import { test, expect } from './support/isolated-test.mjs';
import { runIsolatedSql, queryIsolatedSql } from './support/game-fixtures.mjs';
import AxeBuilder from '@axe-core/playwright';

async function register(page) {
  const id=crypto.randomUUID().slice(0,8),email='strategy-'+id+'@example.test',name='Strategie-'+id,password='Strategic-browser-2026!';
  await page.goto('/inscription');await page.getByLabel('Adresse email').fill(email);await page.getByLabel('Nom de commandant').fill(name);await page.getByLabel('Mot de passe (12 caractères minimum)',{exact:true}).fill(password);await page.getByLabel('Confirmer le mot de passe').fill(password);await page.getByRole('button',{name:'Créer mon compte'}).click();
  await page.getByLabel('Adresse email').fill(email);await page.getByLabel('Mot de passe',{exact:true}).fill(password);await page.getByRole('button',{name:/Connexion/}).click();await page.locator('.universe-card').filter({has:page.getByRole('heading',{name:/Omega/})}).getByRole('button').click();await expect(page).toHaveURL(/planetes\/\d+$/);
  const planet=Number(page.url().split('/').pop()),member=Number(queryIsolatedSql(process.env.BROWSER_ROUTER,`SELECT owner_id FROM planet WHERE id=${planet}`));
  return {email,name,password,planet,member};
}

async function setTheme(page,theme) {
 await page.goto('/profil/preferences');await page.getByLabel('Thème de l’interface').selectOption(theme);await page.getByRole('button',{name:'Enregistrer les paramètres'}).click();await expect(page.locator('body')).toHaveAttribute('data-theme',theme);
}

function ships(owner) {
 const p=process.env.BROWSER_ROUTER;
 runIsolatedSql(p,`INSERT INTO fleet(player_universe_id,home_planet_id,status,mission,speed_ratio,cargo,reserved_fuel,orbit_fuel,orbit_duration_seconds,travel_seconds,dispatch_token) VALUES(${owner.member},${owner.planet},'idle','idle',1,'{}',0,0,0,0,'')`);
 runIsolatedSql(p,`INSERT INTO fleet_ship(fleet_id,slug,tier,quantity) SELECT MAX(id),'fighter','basic',2 FROM fleet WHERE player_universe_id=${owner.member}`);
}

test('Compositions : refus, manques, chargement explicite et absence d’envoi automatique',async({page,browser,baseURL},testInfo)=>{
 test.setTimeout(180000);const owner=await register(page);ships(owner);
 await page.goto('/flottes/compositions/creer');await page.getByLabel('Nom de la composition',{exact:true}).fill('Escorte personnelle');await page.locator('#composition-fighter-basic').fill('5');await page.getByRole('button',{name:'Enregistrer la composition'}).click();await expect(page.locator('.flash.success')).toBeVisible();
 await page.getByRole('link',{name:'Vérifier et charger',exact:true}).click();await expect(page.locator('main')).toContainText('Des vaisseaux manquent');await expect(page.locator('.strategic-page tbody tr').first()).toContainText('3');
 const url=page.url();const noJsContext=await browser.newContext({baseURL,storageState:await page.context().storageState(),javaScriptEnabled:false,viewport:{width:390,height:900}});
 try {const noJs=await noJsContext.newPage();await noJs.goto(url);await noJs.getByRole('link',{name:'Accepter les quantités disponibles et préparer'}).click();await expect(noJs.locator('[name="ships[fighter]"]')).toHaveValue('2');await expect(noJs.locator('[name="require_exact_composition"]')).toHaveValue('1');}finally{await noJsContext.close();}
 await page.getByRole('link',{name:'Accepter les quantités disponibles et préparer'}).click();await expect(page.locator('[name="ships[fighter]"]')).toHaveValue('2');
 expect(Number(queryIsolatedSql(process.env.BROWSER_ROUTER,"SELECT COUNT(*) FROM fleet WHERE status='traveling'"))).toBe(0);
 await page.goto('/flottes/compositions');await page.getByRole('link',{name:'Modifier Escorte personnelle'}).click();await page.getByLabel('Nom de la composition',{exact:true}).fill('X'.repeat(80));await page.getByRole('button',{name:'Enregistrer la composition'}).click();await expect(page.getByRole('heading',{name:'X'.repeat(80)})).toBeVisible();
 for(const theme of ['dark','light']){await setTheme(page,theme);await page.goto('/flottes/compositions');await expect(page.locator('body')).toHaveAttribute('data-theme',theme);for(const width of [1440,390,320]){await page.setViewportSize({width,height:900});await expect.poll(()=>page.evaluate(()=>document.documentElement.scrollWidth)).toBeLessThanOrEqual(width);}expect((await new AxeBuilder({page}).include('main').analyze()).violations).toEqual([]);await page.screenshot({path:testInfo.outputPath(`compositions-${theme}.png`),fullPage:true});}
});

test('P13 : assistant face-à-face et contrôles natifs du simulateur', async ({page,browser,baseURL},testInfo)=>{
 test.setTimeout(120000);
 const owner=await register(page);
 ships(owner);
 for (const theme of ['dark','light']) {
  await setTheme(page,theme);
  for (const viewport of [{width:1920,height:1080},{width:390,height:844}]) {
   await page.setViewportSize(viewport);
   await page.goto('/simulateur');
   await expect(page.locator('body')).toHaveAttribute('data-theme',theme);
   const nav=page.getByRole('navigation',{name:'Étapes du simulateur'});
   await expect(nav.getByRole('link')).toHaveText(['01Vaisseaux','02Défenses','03Recherches','04Simulation']);
   await expect(nav.getByRole('link',{name:'Vaisseaux',exact:true})).toHaveAttribute('aria-current','step');
   const symbolFor = input => page.locator(`tr:has(#${input}) th[scope="row"] svg[aria-hidden="true"] use`);
   for (const side of ['attacker','defender']) {
    await expect(symbolFor(`sim-${side}-ships-fighter-basic`)).toHaveAttribute('href', /#fleet$/);
    await expect(symbolFor(`sim-${side}-ships-probe-basic`)).toHaveAttribute('href', /#probe$/);
   }
   const attacker=page.locator('#sim-attacker-ships-fighter-basic');
   const defender=page.locator('#sim-defender-ships-fighter-basic');
   await attacker.fill('24');
   await defender.fill('18');
   const shipsStep=page.getByRole('region',{name:'Vaisseaux',exact:true});
   const cards=shipsStep.locator('.simulation-wizard-camp');
   await expect(cards).toHaveCount(2);
   const classes = ['Légers', 'Bombardiers', 'Escorteurs', 'Lourds', 'Capitaux', 'Transport', 'Colonisation', 'Espionnage'];
   await expect(cards.first().locator('th[scope="rowgroup"]')).toHaveText(classes);
   await expect(cards.last().locator('th[scope="rowgroup"]')).toHaveText([...classes, 'Utilitaires']);
   for (const card of await cards.all()) {
    const light = card.locator('tbody').filter({ has: page.getByRole('rowheader', { name: 'Chasseur de patrouille', exact: true }) });
    await expect(light.locator('th[scope="row"]')).toHaveText(['Chasseur de patrouille', 'Intercepteur atmosphérique', 'Chasseur furtif']);
   }
   // Lire les deux rectangles au même instant même si le navigateur vient de défiler.
   const [left,right]=await cards.evaluateAll(elements=>elements.map(el=>el.getBoundingClientRect().toJSON()));
   const swap=page.getByRole('button',{name:'Inverser l’attaquant et le défenseur',exact:true});
   await expect(swap).toBeVisible();
   await expect(swap.locator('use')).toHaveAttribute('href', /#swap-camps$/);
   const button=await swap.boundingBox();
   const header=await shipsStep.locator('header').boundingBox();
   expect(button.width).toBeGreaterThanOrEqual(44);
   expect(button.height).toBeGreaterThanOrEqual(44);
   expect(Math.abs(button.x+button.width/2-header.x-header.width/2)).toBeLessThan(2);
   expect(button.y+button.height).toBeLessThanOrEqual(left.top);
   const arrowOffset = () => swap.evaluate(element => {
    const button = element.getBoundingClientRect();
    const arrows = element.querySelector('use').getBoundingClientRect();
    return Math.max(
     Math.abs(arrows.x + arrows.width / 2 - button.x - button.width / 2),
     Math.abs(arrows.y + arrows.height / 2 - button.y - button.height / 2),
    );
   });
   expect(await arrowOffset()).toBeLessThan(0.6);
   await swap.hover();
   await swap.locator('svg').evaluate(async icon => {
    await Promise.all(icon.getAnimations().map(animation => animation.finished));
   });
   expect(await arrowOffset()).toBeLessThan(0.6);
   if (viewport.width===1920) {
    expect(right.left).toBeGreaterThanOrEqual(left.right);
    expect(Math.abs(left.top-right.top)).toBeLessThanOrEqual(2);
   } else {
    expect(right.top).toBeGreaterThanOrEqual(left.bottom);
   }
   const next=page.getByRole('link',{name:'Continuer : défenses'});
   const tables=shipsStep.getByRole('table');
   for (const table of await tables.all()) {
    expect(await table.evaluate(el=>el.scrollWidth<=el.clientWidth+1), 'les trois rangs tiennent dans la carte').toBe(true);
   }
   await page.evaluate(()=>window.scrollTo({top:0,behavior:'instant'}));
   for (const card of await cards.all()) {
    expect(await card.evaluate(el => el.querySelector('tbody:last-of-type tr:last-child').getBoundingClientRect().bottom <= el.getBoundingClientRect().bottom)).toBe(true);
   }
   await page.screenshot({path:testInfo.outputPath('P13-wizard-ships-'+theme+'-'+viewport.width+'.png'),scale:'css',animations:'disabled'});
   await next.focus();
   await page.keyboard.press('Enter');
   await expect(page.getByRole('heading',{name:'Défenses',exact:true})).toBeFocused();
   await expect(page.locator('#simulation-defenses header')).toContainText('Défenses');
   await expect(page.locator('#simulation-defenses header #simulation-swap')).toBeVisible();
   await expect(page.getByText('Non applicable',{exact:true})).toBeVisible();
   await expect(page.locator('[name^="forces[attacker][defenses]"]')).toHaveCount(0);
   await expect(page.locator('[name^="forces[defender][defenses]"]').first()).toBeVisible();
   await expect(page.getByRole('region', { name: 'Défenses', exact: true }).locator('th[scope="rowgroup"]')).toHaveText(['Anti-légers', 'Anti-escorteurs', 'Anti-lourds', 'Anti-capitaux']);
   await expect(symbolFor('sim-defender-defenses-ground_turret_anti_light-basic')).toHaveAttribute('href', /#diplomacy$/);
   await expect(symbolFor('sim-defender-fortress-fortress-basic')).toHaveAttribute('href', /#fortification$/);
   await page.getByRole('link',{name:'Continuer : recherches'}).click();
   await expect(page.getByRole('heading',{name:'Recherches',exact:true})).toBeFocused();
   for (const side of ['attacker','defender']) {
    await expect(symbolFor(`sim-${side}-research-vector_propulsion-basic`)).toHaveAttribute('href', /#propulsion$/);
    await expect(symbolFor(`sim-${side}-research-weapon_calibration-basic`)).toHaveAttribute('href', /#target$/);
    await expect.poll(() => symbolFor(`sim-${side}-research-vector_propulsion-basic`).evaluate(use => use.getBBox().width)).toBeGreaterThan(0);
   }
   for (const table of await page.getByRole('region', { name: 'Recherches', exact: true }).getByRole('table').all()) {
    await expect(table.locator('th[scope="rowgroup"]')).toHaveText(['Logistique', 'Propulsion', 'Combat']);
   }
   await expect(page.locator('[name^="forces[attacker][research]"]').first()).toBeVisible();
   await expect(page.locator('[name^="forces[defender][research]"]').first()).toBeVisible();
   await page.getByRole('link',{name:'Continuer : simulation'}).click();
   const guide=page.locator('.first-steps-popup-enhanced');
   const toggle=guide.locator(':scope > summary');
   if (await guide.getAttribute('open')===null) await toggle.press('Enter');
   await expect(guide).toHaveAttribute('open','');
   const checkbox=page.getByRole('checkbox',{name:/Je confirme les hypothèses/});
   const submit=page.getByRole('button',{name:'Simuler 10 combats'});
   for (const control of [checkbox,submit]) {
    await control.evaluate(element=>element.scrollIntoView({block:'center',behavior:'instant'}));
    await expect.poll(()=>control.evaluate(element=>{
     const r=element.getBoundingClientRect();
     const hit=document.elementFromPoint(r.x+r.width/2,r.y+r.height/2);
     return element===hit || element.contains(hit);
    })).toBe(true);
   }
   await page.screenshot({path:testInfo.outputPath('P13-guide-ouvert-'+theme+'-'+viewport.width+'.png'),scale:'css',animations:'disabled'});
   await expect(page.locator('#simulation-result')).toHaveCount(0);
   expect((await new AxeBuilder({page}).include('main').analyze()).violations).toEqual([]);
   await nav.getByRole('link',{name:'Vaisseaux',exact:true}).click();
   await expect(attacker).toHaveValue('24');
   await expect(defender).toHaveValue('18');
   await swap.focus();
   await page.keyboard.press('Enter');
   await expect(attacker).toHaveValue('18');
   await expect(defender).toHaveValue('24');
   await page.waitForLoadState('load');
   const sources = page.locator('.simulation-wizard-sources');
   if (!await sources.evaluate(element => element.open)) await sources.locator('summary').click();
   const panels = sources.locator('.simulation-wizard-source');
   await expect(panels.locator('h2 span')).toHaveText(['Attaquant', 'Défenseur']);
   await expect(panels.first().locator('#simulation-source-intel-title')).toBeVisible();
   await expect(panels.last().getByLabel('Colonie à défendre')).toBeVisible();
   const [attackBox, defenseBox] = await panels.evaluateAll(nodes => nodes.map(node => node.getBoundingClientRect().toJSON()));
   if (viewport.width === 1920) expect(defenseBox.left).toBeGreaterThanOrEqual(attackBox.right);
   else expect(defenseBox.top).toBeGreaterThanOrEqual(attackBox.bottom);
   await nav.getByRole('link',{name:'Simulation',exact:true}).click();
   const recap = page.locator('.simulation-wizard-recap .simulation-wizard-camps > div');
   await expect(recap.locator('strong')).toHaveText(['Attaquant','Défenseur']);
   await expect(recap.last()).toContainText('Les défenses de votre colonie participent au combat.');
   await expect.poll(()=>page.evaluate(()=>document.documentElement.scrollWidth)).toBeLessThanOrEqual(viewport.width);
  }
 }
 const context=await browser.newContext({baseURL,storageState:await page.context().storageState(),javaScriptEnabled:false});
 try {
  const tab=await context.newPage();
  await tab.goto('/simulateur');
  const draft=tab.locator('#sim-defender-ships-fighter-basic');
  await draft.fill('18');
  const posts=[];
  tab.on('request', request=>{if(request.method()==='POST') posts.push(request);});
  await draft.press('Enter');
  await expect(draft).toHaveValue('18');
  expect(posts).toHaveLength(0);
  const nativeImport=tab.getByRole('button',{name:'Charger mes forces'});
  const request=tab.waitForRequest(request=>request.method()==='POST' && new URL(request.url()).pathname==='/simulateur');
  await nativeImport.click();
  expect(new URLSearchParams((await request).postData()).get('wizard_action')).toBe('load_own');
  await expect(tab.locator('#sim-attacker-ships-fighter-basic')).toHaveValue('2');
  await expect(tab.locator('#sim-defender-ships-fighter-basic')).toHaveValue('18');
  await tab.getByRole('button', {name:'Inverser l’attaquant et le défenseur'}).click();
  await expect(tab.locator('#sim-attacker-ships-fighter-basic')).toHaveValue('18');
  await expect(tab.locator('#sim-defender-ships-fighter-basic')).toHaveValue('2');
  const sourceTitles = tab.locator('.simulation-wizard-source h2');
  await expect(sourceTitles.nth(0)).toHaveAttribute('id', 'simulation-source-intel-title');
  await expect(sourceTitles.nth(1)).toContainText('Défenseur');
  await tab.getByRole('button', {name:'Inverser l’attaquant et le défenseur'}).click();
  await expect(sourceTitles.nth(0)).toHaveAttribute('id', 'simulation-source-own-title');
  await expect(sourceTitles.nth(0)).toContainText('Attaquant');
  await expect(tab.locator('#sim-attacker-ships-fighter-basic')).toHaveValue('2');
  await expect(tab.locator('#sim-defender-ships-fighter-basic')).toHaveValue('18');
  await expect(tab.locator('#simulation-research')).toBeVisible();
  await expect(tab.locator('#simulation-defenses')).toBeVisible();
  await expect(tab.locator('#simulation-result')).toHaveCount(0);
 } finally { await context.close(); }
});

test('Simulateur : sources datées, champs inconnus éditables et brouillon conservé', async ({page,browser},testInfo)=>{
 test.setTimeout(95000);
 const owner=await register(page);
 ships(owner);
 const otherPage=await browser.newPage();
 const target=await register(otherPage);
 await otherPage.close();
 const router=process.env.BROWSER_ROUTER;
 const snapshot={fleets:{'fighter:basic':{quantity:8}},defenses:[]};
 const reportInsert=(sender,planet,data)=>`INSERT INTO espionage_report (sender_id,target_id,result,created_at,probes_sent,intel_categories,detection_chance,sender_espionage_level,target_espionage_level,target_counter_espionage_level,snapshot) VALUES (${sender},${planet},'success','2026-11-20 17:42:00',1,2,3,0,0,0,'${JSON.stringify(data)}')`;
 runIsolatedSql(router,reportInsert(owner.member,target.planet,snapshot));
 runIsolatedSql(router,reportInsert(target.member,owner.planet,snapshot));
 const reportId=Number(queryIsolatedSql(router,`SELECT id FROM espionage_report WHERE sender_id=${owner.member}`));
 expect(Number.isSafeInteger(reportId) && reportId>0).toBe(true);
 const nav=page.getByRole('navigation',{name:'Étapes du simulateur'});
 await page.goto('/simulateur');
 await page.locator('#sim-attacker-ships-fighter-basic').fill('12');
 await expect(page.locator('#simulation-intel option')).toHaveCount(2);
 await page.locator('#simulation-intel').selectOption(String(reportId));
 page.once('dialog',dialog=>dialog.accept());
 await page.getByRole('button',{name:'Importer',exact:true}).click();
 await expect(page.locator('#sim-attacker-ships-fighter-basic')).toHaveValue('12');
 await expect(page.locator('#sim-defender-ships-fighter-basic')).toHaveValue('8');
 const swap = page.getByRole('button', {name:'Inverser l’attaquant et le défenseur'});
 await swap.click();
 const attackSource = page.locator('.simulation-wizard-source').first();
 await expect(attackSource.getByRole('link', {name:'Relire le rapport source'})).toBeVisible();
 await expect(attackSource.getByLabel('Choisir un rapport d’espionnage')).toHaveValue(String(reportId));
 await page.locator('#sim-attacker-ships-fighter-basic').fill('6');
 page.once('dialog', dialog => dialog.accept());
 await attackSource.getByRole('button', {name:'Importer', exact:true}).click();
 await expect(page.locator('#sim-attacker-ships-fighter-basic')).toHaveValue('8');
 await expect(page.locator('#sim-defender-ships-fighter-basic')).toHaveValue('12');
 await swap.click();
 await expect(page.locator('#sim-attacker-ships-fighter-basic')).toHaveValue('12');
 const reportLink=page.getByRole('link',{name:'Relire le rapport source'});
 await expect(reportLink).toBeVisible();
 expect(await reportLink.evaluate(link=>new URL(link.href).pathname)).toBe('/rapports/espionnage/'+reportId);
 await expect(page.locator('time').filter({hasText:'20/11/2026'})).toHaveAttribute('datetime',/^2026-11-20T/);
 page.once('dialog',dialog=>dialog.accept());
 await page.getByRole('button',{name:'Charger mes forces'}).click();
 await expect(page.locator('#sim-attacker-ships-fighter-basic')).toHaveValue('2');
 await expect(page.locator('#sim-defender-ships-fighter-basic')).toHaveValue('8');
 await nav.getByRole('link',{name:'Défenses',exact:true}).click();
 const defense=page.locator('[name^="forces[defender][defenses]"]').first();
 await expect(defense).toHaveValue('0');
 await nav.getByRole('link',{name:'Recherches',exact:true}).click();
 const unknown=page.locator('#simulation-research [name^="forces[defender][research]"]').first();
 const stillUnknown=page.locator('#simulation-research [name^="forces[defender][research]"]').nth(1);
 await expect(unknown).toHaveValue('');
 await expect(unknown).toHaveAttribute('placeholder','?');
 await unknown.fill('3');
 await expect(page.locator('#simulation-defender-research-source')).toContainText('hypothèse saisie');
 await expect(stillUnknown).toHaveValue('');
 for (const width of [1920,390]) {
  await page.setViewportSize({width,height:width===1920?1080:844});
  await expect.poll(()=>page.evaluate(()=>document.documentElement.scrollWidth)).toBeLessThanOrEqual(width);
  await unknown.scrollIntoViewIfNeeded();
  await page.screenshot({path:testInfo.outputPath('P13-wizard-intel-dark-'+width+'.png'),scale:'css',animations:'disabled'});
 }
 await nav.getByRole('link',{name:'Simulation',exact:true}).click();
 await page.getByRole('checkbox',{name:/Je confirme les hypothèses/}).check();
 await page.getByRole('button',{name:'Simuler 10 combats'}).click();
 await expect(page.locator('#simulation-result')).toBeVisible();
 await nav.getByRole('link',{name:'Recherches',exact:true}).click();
 await expect(unknown).toHaveValue('3');
 await expect(stillUnknown).toHaveValue('');
 expect(Number(queryIsolatedSql(router,'SELECT COUNT(*) FROM combat_event'))).toBe(0);
});

test('Simulateur : dix combats reproductibles, détails accessibles et aucune mutation',async({page,browser,baseURL},testInfo)=>{
 test.setTimeout(180000);
 const owner=await register(page),p=process.env.BROWSER_ROUTER,errors=[];
 page.on('pageerror',error=>errors.push(error.message));
 const stockQuery=`SELECT GROUP_CONCAT(CONCAT(resource_type,':',current_stock,':',last_updated_at) ORDER BY resource_type) FROM planet_resource WHERE planet_id=${owner.planet}`;
 const before=queryIsolatedSql(p,stockQuery);
 const calculate=async(tab,keyboard=false)=>{
  await tab.locator('#sim-attacker-ships-fighter-basic').fill('2');
  await tab.locator('#sim-defender-ships-fighter-basic').fill('1');
  await tab.getByRole('navigation',{name:'Étapes du simulateur'}).getByRole('link',{name:'Simulation',exact:true}).click();
  await tab.getByRole('checkbox',{name:/Je confirme les hypothèses/}).check();
  const button=tab.getByRole('button',{name:'Simuler 10 combats'});
  if(keyboard){await button.focus();await tab.keyboard.press('Enter');}else await button.click();
  const result=tab.locator('#simulation-result');
  await expect(result).toBeVisible();
  await expect(tab).toHaveURL(/#simulation-result$/);
  await expect(result.getByRole('heading',{name:'Estimation sur 10 combats'})).toBeInViewport();

 };
 await page.goto('/simulateur');await calculate(page,true);
 const result=page.locator('#simulation-result'),trials=result.locator('.simulation-trial');
 await expect(result.getByRole('heading',{name:'Estimation sur 10 combats'})).toBeVisible();
 await expect(trials).toHaveCount(10);
 const trialOverview=result.getByRole('table',{name:'Bilan de l’attaquant dans chaque combat'});
 await expect(trialOverview.locator('tbody tr')).toHaveCount(10);
 await page.setViewportSize({width:1920,height:1080});
 await result.evaluate(element=>element.scrollIntoView({block:'start',behavior:'instant'}));
 await page.screenshot({path:testInfo.outputPath('P21-resultat-reel-1920x1080.png'),animations:'disabled',scale:'css'});
 expect(await trials.evaluateAll(nodes=>nodes.map(node=>Number(node.dataset.simulationSeed)))).toEqual(Array.from({length:10},(_,index)=>42+index));
 const counts=(await result.locator('.simulation-outcomes dd:not(.simulation-outcome-sample)').allTextContents()).map(Number);
 expect(counts.reduce((total,count)=>total+count,0)).toBe(10);
 await expect(result.locator('.simulation-outcomes dt')).toHaveText(['Victoires de l’attaquant','Égalités','Défaites de l’attaquant','Combats non terminés']);
 const rowTotals=await trialOverview.locator('tbody tr').evaluateAll(rows=>rows.map(row=>[...row.querySelectorAll('td')].slice(1).map(cell=>Number(cell.textContent))));
 for(const [engaged,surviving,lost] of rowTotals) expect(engaged).toBe(surviving+lost);
 const guide=page.locator('.first-steps-popup-enhanced');
 if(await guide.getAttribute('open')===null) await guide.locator(':scope > summary').press('Enter');
 await expect(guide).toHaveAttribute('open','');
 await expect.poll(()=>page.evaluate(()=>{
  const result=document.querySelector('#simulation-result').getBoundingClientRect();
  const guide=document.querySelector('.first-steps-popup-enhanced .first-steps-popup-mount').getBoundingClientRect();
  return guide.top>=result.bottom;
 })).toBe(true);

 await expect(result.locator('strong').first()).toContainText(counts[0]*10+' %');
 await expect(result).toContainText('7 victoires donnent 70 %, sans garantir cette probabilité réelle');
 const first=await result.textContent(),trial=trials.first(),summary=trial.locator(':scope > summary');
 await summary.focus();await page.keyboard.press('Enter');await expect(trial).toHaveAttribute('open','');
 await expect(trial.getByRole('heading',{name:'Camp attaquant',exact:true})).toBeVisible();
 await expect(trial.getByRole('heading',{name:'Camp défenseur',exact:true})).toBeVisible();
 const campTables=trial.locator('.simulation-losses');
 await expect(campTables).toHaveCount(2);
 for(const table of await campTables.all()){
  await expect(table.locator('[data-simulation-unit="fighter"] th use')).toHaveAttribute('href', /#fleet$/);
  await expect(table.getByRole('columnheader')).toHaveText(['Unité','Engagés','Survivants','Pertes']);
  const unitTotals=await table.locator('tbody tr').evaluateAll(rows=>rows.map(row=>[...row.querySelectorAll('td')].map(cell=>Number(cell.textContent.replace('−','-')))));
  expect(unitTotals.length).toBeGreaterThan(0);
  for(const [initial,remaining,lost] of unitTotals){
   expect(lost).toBeLessThanOrEqual(0);
   expect(initial).toBe(remaining-lost);
  }
 }
 await trial.getByText('Structure détaillée des survivants',{exact:true}).focus();await page.keyboard.press('Space');
 await expect(trial.locator('ul').first()).toBeVisible();
 await summary.click();await expect(trial).not.toHaveAttribute('open','');
 await page.getByRole('checkbox',{name:/Je confirme les hypothèses/}).check();
 await page.getByRole('button',{name:'Simuler 10 combats'}).click();
 await expect(result.getByRole('heading',{name:'Estimation sur 10 combats'})).toBeInViewport();
 expect(await result.textContent()).toBe(first);
 expect(queryIsolatedSql(p,stockQuery)).toBe(before);
 expect(Number(queryIsolatedSql(p,'SELECT COUNT(*) FROM combat_event'))).toBe(0);
 for(const theme of ['dark','light']){
  await setTheme(page,theme);await page.goto('/simulateur');await calculate(page);
  await expect(page.locator('body')).toHaveAttribute('data-theme',theme);
  for(const width of [1920,1440,390,320]){
   await page.setViewportSize({width,height:width===1920 ? 1080 : (width===390 ? 844 : 900)});
   await expect.poll(()=>page.evaluate(()=>document.documentElement.scrollWidth)).toBeLessThanOrEqual(width);
   await result.evaluate(element=>element.scrollIntoView({block:'start',behavior:'instant'}));
   if(width===1920 || width===390) {
    const heading=result.getByRole('heading',{name:'Estimation sur 10 combats'});
    await expect(heading).toBeInViewport({ratio:1});
    await expect.poll(()=>heading.evaluate(element=>{
     const r=element.getBoundingClientRect();
     return element===document.elementFromPoint(r.left+8,r.top+r.height/2);
    })).toBe(true);
    await expect.poll(()=>result.locator('.simulation-outcome').first().evaluate(card=>{
     const label=card.querySelector('dt').getBoundingClientRect();
     const value=card.querySelector('dd').getBoundingClientRect();
     return value.top>=label.bottom;
    })).toBe(true);
    await page.screenshot({path:testInfo.outputPath(`P21-resultat-${theme}-${width}.png`),animations:'disabled',scale:'css'});
   }
   if(width===390) {
    expect((await new AxeBuilder({page}).include('#simulation-result').analyze()).violations).toEqual([]);
   }
  }
  expect((await new AxeBuilder({page}).include('main').analyze()).violations).toEqual([]);
 }
 const context=await browser.newContext({baseURL,storageState:await page.context().storageState(),javaScriptEnabled:false});
 try{const tab=await context.newPage();await tab.goto('/simulateur');await calculate(tab);await expect(tab.locator('.simulation-trial')).toHaveCount(10);await tab.getByRole('link',{name:'Voir le détail des combats'}).click();await expect(tab).toHaveURL(/#simulation-trial-1$/);const detail=tab.locator('#simulation-trial-1');await detail.focus();await tab.keyboard.press('Enter');await expect(tab.locator('.simulation-trial').first()).toHaveAttribute('open','');await tab.getByRole('link',{name:'Revenir au scénario'}).click();await expect(tab).toHaveURL(/#simulation-scenario$/);await expect(tab.locator('#sim-attacker-ships-fighter-basic')).toHaveValue('2');}finally{await context.close();}
 expect(queryIsolatedSql(p,stockQuery)).toBe(before);
 expect(Number(queryIsolatedSql(p,'SELECT COUNT(*) FROM combat_event'))).toBe(0);
 expect(errors).toEqual([]);
});

test('Simulateur volumineux : résultat privé au retour, sans tronquer les effectifs', async ({ page }) => {
 test.setTimeout(90000);
 const owner = await register(page);
 const router = process.env.BROWSER_ROUTER;
 const stock = () => queryIsolatedSql(router,
  `SELECT GROUP_CONCAT(CONCAT(resource_type,':',current_stock) ORDER BY resource_type) FROM planet_resource WHERE planet_id=${owner.planet}`);
 const before = stock();
 await page.goto('/simulateur');
 const attackers = page.locator('#sim-attacker-ships-probe-basic');
 await expect(attackers).toHaveAttribute('max', '1000000');
 await attackers.fill('6000');
 await page.locator('#sim-defender-ships-probe-basic').fill('1');
 const navigation = page.getByRole('navigation', { name: 'Étapes du simulateur' });
 await navigation.getByRole('link', { name: 'Défenses', exact: true }).click();
 await page.locator('#sim-defender-defenses-ground_turret_anti_light-basic').fill('1');
 await navigation.getByRole('link', { name: 'Simulation', exact: true }).click();
 await page.getByRole('checkbox', { name: /Je confirme les hypothèses/ }).check();
 const submitted = page.waitForResponse(response => response.request().method() === 'POST' && new URL(response.url()).pathname === '/simulateur');
 await page.getByRole('button', { name: 'Simuler 10 combats' }).click();
 const response = await submitted;
 if (response.status() !== 303) {
  await page.waitForLoadState('domcontentloaded');
  const warning = (await page.locator('.simulation-workspace .flash.error').allTextContents()).join(' ');
  throw new Error(`Le scénario volumineux n’a pas produit de tâche privée (HTTP ${response.status()}) : ${warning || 'aucun message de validation'}`);
 }
 await expect(page).toHaveURL(/\/simulateur\?job=[^#]+#simulation-result$/);
 const resultUrl = page.url();
 await page.goto('/simulateur');
 await page.goto(resultUrl);
 try {
  // La file traite plusieurs essais par livraison. Recharger réellement le document, pas seulement son ancre.
  await expect.poll(async () => {
   await page.reload({ waitUntil: 'domcontentloaded' });
   const panel = page.locator('#simulation-result');
   if (await page.locator('.simulation-trial').count() === 10) return '10 résultats';
   const content = await panel.textContent({ timeout: 3000 }).catch(() => 'Aucun panneau de résultat');
   return content.replace(/\s+/g, ' ').trim();
  }, { timeout: 60000, intervals: [1000, 2000, 3000] }).toBe('10 résultats');
 } catch (error) {
  // Ne journaliser que l’état du calcul de la fixture, jamais le scénario, le jeton ou les identifiants.
  const state = queryIsolatedSql(router, "SELECT CONCAT(status, ':', JSON_LENGTH(trials)) FROM player_combat_simulation_job");
  const pending = queryIsolatedSql(router, "SELECT COUNT(*) FROM outbox_message WHERE kind = 'combat_sim_trial'");
  throw new Error('Simulation asynchrone : état / essais en base = '+state+', messages à relayer = '+pending+'. '+error.message);
 }
 await expect(attackers).toHaveValue('6000');
 await expect(page.locator('#sim-defender-defenses-ground_turret_anti_light-basic')).toHaveValue('1');
 await expect(page.locator('#simulation-result')).toContainText('Structure restante par groupe de survivants');
 expect(stock()).toBe(before);
 expect(Number(queryIsolatedSql(router, 'SELECT COUNT(*) FROM combat_log'))).toBe(0);
});

test('Recherche : contenu littéral, pagination, rapports et non-lus préservés',async({page,browser,baseURL})=>{
 test.setTimeout(180000);const owner=await register(page);const otherContext=await browser.newContext({baseURL});const other=await otherContext.newPage();
 try{const peer=await register(other),p=process.env.BROWSER_ROUTER;
 const values=Array.from({length:130},(_,i)=>`(${peer.member},${owner.member},'Balise 100% _ numéro ${i} ${'Complément détaillé. '.repeat(60)}','2026-11-12 12:00:00')`);for(let offset=0;offset<values.length;offset+=25)runIsolatedSql(p,`INSERT INTO social_message(sender_id,recipient_id,content,created_at) VALUES ${values.slice(offset,offset+25).join(',')}`);
 await page.goto('/messages/recherche');await page.getByLabel('Contenu',{exact:true}).fill('100% _');await page.getByLabel('Auteur ou interlocuteur').fill(peer.name);await page.getByRole('button',{name:'Rechercher les messages'}).click();await expect(page.locator('.message-search-result')).toHaveCount(50);await page.locator('.message-search-result').first().getByText('Lire le message complet sans marquage',{exact:true}).click();await expect(page.locator('.message-search-result').first().locator('details p')).toBeVisible();await expect(page.locator('.message-search-result').first().locator('details p')).toContainText('Complément détaillé. '.repeat(50));await page.getByRole('link',{name:'Page suivante'}).click();await expect(page).toHaveURL(/page=2/);await expect(page.locator('.message-search-result')).toHaveCount(50);await page.goBack();await expect(page.getByLabel('Contenu',{exact:true})).toHaveValue('100% _');
 expect(Number(queryIsolatedSql(p,`SELECT COUNT(*) FROM social_message WHERE recipient_id=${owner.member} AND read_at IS NOT NULL`))).toBe(0);
 await other.goto('/messages/recherche?with='+owner.member);await expect(other.locator('.message-search-result')).toHaveCount(50);
 runIsolatedSql(p,`INSERT INTO espionage_report(sender_id,target_id,result,created_at,probes_sent,intel_categories,detection_chance,sender_espionage_level,target_espionage_level,target_counter_espionage_level,snapshot) VALUES(${owner.member},${peer.planet},'success','2026-11-12 12:00:00',1,1,5,0,0,0,'{}')`);
 await page.goto('/rapports');await page.getByLabel('Nom du participant').fill(owner.name);await page.getByLabel('Du',{exact:true}).fill('2026-11-12');await page.getByLabel('Au',{exact:true}).fill('2026-11-12');await page.getByRole('button',{name:'Rechercher les rapports'}).click();await page.locator('.report-row').first().click();await expect(page.locator('.report-back')).toHaveAttribute('href',/participant=/);await page.locator('.report-back').click();await expect(page.getByLabel('Nom du participant')).toHaveValue(owner.name);await expect(page.getByLabel('Du',{exact:true})).toHaveValue('2026-11-12');
 for(const theme of ['dark','light']){await setTheme(page,theme);await page.goto('/messages/recherche?q=100%25%20_');await expect(page.locator('body')).toHaveAttribute('data-theme',theme);for(const width of [1440,390,320]){await page.setViewportSize({width,height:900});await expect.poll(()=>page.evaluate(()=>document.documentElement.scrollWidth)).toBeLessThanOrEqual(width);}expect((await new AxeBuilder({page}).include('main').analyze()).violations).toEqual([]);}
 const noJs=await browser.newContext({baseURL,storageState:await page.context().storageState(),javaScriptEnabled:false});try{const tab=await noJs.newPage();await tab.goto('/messages/recherche');await tab.getByLabel('Contenu',{exact:true}).fill('100% _');await tab.getByRole('button',{name:'Rechercher les messages'}).click();await expect(tab.locator('.message-search-result')).toHaveCount(50);}finally{await noJs.close();}
 }finally{await otherContext.close();}
});

test('Compositions : le plafond de vingt résiste à deux créations simultanées',async({page,browser,baseURL})=>{
 test.setTimeout(150000);const owner=await register(page),p=process.env.BROWSER_ROUTER;
 runIsolatedSql(p,`INSERT INTO fleet_composition(owner_id,name,ships,request_key,version) VALUES ${Array.from({length:19},(_,i)=>`(${owner.member},'Composition ${i}','{"fighter":{"basic":1}}','${i.toString(16).padStart(32,'0')}',1)`).join(',')}`);
 const context=await browser.newContext({baseURL});try{const tab=await context.newPage();await tab.goto('/connexion');await tab.getByLabel('Adresse email').fill(owner.email);await tab.getByLabel('Mot de passe',{exact:true}).fill(owner.password);await tab.getByRole('button',{name:/Connexion/}).click();await tab.goto('/flottes/compositions/creer');await page.goto('/flottes/compositions/creer');
 const data=async pg=>pg.locator('main form').first().evaluate(form=>({_token:form.querySelector('[name="_token"]').value,request_key:form.querySelector('[name="request_key"]').value,id:'0',version:'0',name:'Concurrente','ships[fighter][basic]':'1'}));
 const a=await data(page),b=await data(tab);const submit=(pg,form)=>pg.evaluate(async data=>(await fetch('/flottes/compositions',{method:'POST',body:new URLSearchParams(data)})).status,form);const responses=await Promise.all([submit(page,a),submit(tab,b)]);expect(responses.sort()).toEqual([200,422]);expect(Number(queryIsolatedSql(p,`SELECT COUNT(*) FROM fleet_composition WHERE owner_id=${owner.member}`))).toBe(20);
 }finally{await context.close();}
});
