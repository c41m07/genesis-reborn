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

test('P13 : scénario du simulateur avec deux camps et contrôles natifs', async ({page,browser,baseURL},testInfo)=>{
 test.setTimeout(180000);
 const owner=await register(page);
 ships(owner);
 for (const theme of ['dark','light']) {
  await setTheme(page,theme);
  for (const viewport of [{width:1920,height:1080},{width:390,height:844}]) {
   await page.setViewportSize(viewport);
   await page.goto('/simulateur');
   await expect(page.locator('body')).toHaveAttribute('data-theme',theme);
   const guide=page.locator('.first-steps-popup-enhanced');
   const toggle=guide.locator(':scope > summary');
   const checkbox=page.getByRole('checkbox',{name:/Je confirme les hypothèses/});
   const submit=page.getByRole('button',{name:'Simuler 10 combats'});
   await expect(toggle).toBeVisible();
   if (await guide.getAttribute('open')===null) await toggle.press('Enter');
   await expect(guide).toHaveAttribute('open','');
   const panel=guide.locator('.first-steps-popup-mount');
   await expect(panel).toBeVisible();
   const options=page.locator('.simulation-board-options');
   const panelRect=await panel.boundingBox();
   const optionsRect=await options.boundingBox();
   expect(panelRect.y).toBeGreaterThanOrEqual(optionsRect.y+optionsRect.height);
   for (const control of [checkbox, submit]) {
    await control.evaluate(element=>element.scrollIntoView({block:'center',behavior:'instant'}));
    await expect.poll(()=>control.evaluate(element=>{
     const r=element.getBoundingClientRect();
     const hit=document.elementFromPoint(r.x+r.width/2,r.y+r.height/2);
     return element===hit || element.contains(hit);
    })).toBe(true);
   }
   await page.screenshot({path:testInfo.outputPath('P13-guide-ouvert-'+theme+'-'+viewport.width+'.png'),scale:'css',animations:'disabled'});
   await toggle.press('Enter');
   await expect(guide).not.toHaveAttribute('open','');
   await expect(page.getByRole('heading',{name:'Simulateur de combat'})).toBeVisible();
   const attacker=page.locator('#sim-attacker-ships-fighter-basic');
   const defender=page.locator('#sim-defender-ships-fighter-basic');
   await attacker.fill('24');
   await defender.fill('18');
   await page.getByLabel('Graine de départ reproductible').fill('713040');
   await page.getByRole('checkbox',{name:/Je confirme les hypothèses/}).check();
   await expect(page.getByRole('button',{name:'Simuler 10 combats'})).toBeEnabled();
   await expect(page.locator('#simulation-result')).toHaveCount(0);
   await expect.poll(()=>page.evaluate(()=>document.documentElement.scrollWidth)).toBeLessThanOrEqual(viewport.width);
   await page.locator('.simulation-board-unit-scroll:visible').evaluateAll(elements=>elements.forEach(element=>element.scrollTo({top:0,left:0,behavior:'instant'})));
   await page.evaluate(()=>window.scrollTo({top:0,behavior:'instant'}));
   if (viewport.width===1920) {
    const attackerRound=await page.locator('#arrival-attacker').boundingBox();
    const defenderRound=await page.locator('#arrival-defender').boundingBox();
    expect(Math.abs(attackerRound.y-defenderRound.y)).toBeLessThanOrEqual(4);
    await expect(submit).toBeInViewport({ratio:1});
    const shipTables=page.locator('.strategic-grid section > details:first-of-type .simulation-board-unit-scroll');
    await expect(shipTables).toHaveCount(2);
    for (const table of await shipTables.all()) {
     expect(await table.evaluate(element=>{
      const first=element.querySelector('tbody input').getBoundingClientRect();
      const region=element.getBoundingClientRect();
      return first.top>=region.top && first.bottom<=region.bottom;
     })).toBe(true);
    }
   }
   await page.screenshot({path:testInfo.outputPath('P13-scenario-'+theme+'-'+viewport.width+'.png'),scale:'css',animations:'disabled'});
   if (viewport.width===390) {
    await page.getByRole('button',{name:'Simuler 10 combats'}).scrollIntoViewIfNeeded();
    await page.screenshot({path:testInfo.outputPath('P13-actions-'+theme+'-390.png'),scale:'css',animations:'disabled'});
   }
  }
 }
 const state=await page.context().storageState();
 const context=await browser.newContext({baseURL,storageState:state,javaScriptEnabled:false});
 try {
  const tab=await context.newPage();
  await tab.goto('/simulateur');
  const options=tab.locator('#simulation-scenario details').filter({hasText:'Recherches'}).first();
  const summary=options.locator('summary');
  await summary.focus();
  await tab.keyboard.press('Enter');
  await expect(options).toHaveAttribute('open','');
  const nativeImport=tab.getByRole('button',{name:'Charger mes forces'});
  await expect(nativeImport).toHaveAttribute('form','simulation-own-forces');
  const request=tab.waitForRequest(request=>new URL(request.url()).searchParams.get('own_planet')===String(owner.planet));
  await nativeImport.click();
  expect((await request).method()).toBe('GET');
  await expect(tab).toHaveURL(/own_planet=/);
  await expect(tab.locator('#sim-attacker-ships-fighter-basic')).toHaveValue('2');
 } finally { await context.close(); }
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
