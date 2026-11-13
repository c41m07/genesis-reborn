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
 await page.goto('/flottes/compositions');await page.getByLabel('Nom de la composition',{exact:true}).fill('Escorte personnelle');await page.locator('#composition-fighter-basic').fill('5');await page.getByRole('button',{name:'Enregistrer la composition'}).click();await expect(page.locator('.flash.success')).toBeVisible();
 await page.getByRole('link',{name:'Vérifier et charger',exact:true}).click();await expect(page.locator('main')).toContainText('Des vaisseaux manquent');await expect(page.locator('tbody tr').first()).toContainText('3');
 const url=page.url();const noJsContext=await browser.newContext({baseURL,storageState:await page.context().storageState(),javaScriptEnabled:false,viewport:{width:390,height:900}});
 try {const noJs=await noJsContext.newPage();await noJs.goto(url);await noJs.getByRole('link',{name:'Accepter les quantités disponibles et préparer'}).click();await expect(noJs.locator('[name="ships[fighter]"]')).toHaveValue('2');await expect(noJs.locator('[name="require_exact_composition"]')).toHaveValue('1');}finally{await noJsContext.close();}
 await page.getByRole('link',{name:'Accepter les quantités disponibles et préparer'}).click();await expect(page.locator('[name="ships[fighter]"]')).toHaveValue('2');
 expect(Number(queryIsolatedSql(process.env.BROWSER_ROUTER,"SELECT COUNT(*) FROM fleet WHERE status='traveling'"))).toBe(0);
 await page.goto('/flottes/compositions');await page.getByRole('link',{name:'Modifier Escorte personnelle'}).click();await page.getByLabel('Nom de la composition',{exact:true}).fill('X'.repeat(80));await page.getByRole('button',{name:'Enregistrer la composition'}).click();await expect(page.getByRole('heading',{name:'X'.repeat(80)})).toBeVisible();
 for(const theme of ['dark','light']){await setTheme(page,theme);await page.goto('/flottes/compositions');await expect(page.locator('body')).toHaveAttribute('data-theme',theme);for(const width of [1440,390,320]){await page.setViewportSize({width,height:900});await expect.poll(()=>page.evaluate(()=>document.documentElement.scrollWidth)).toBeLessThanOrEqual(width);}expect((await new AxeBuilder({page}).include('main').analyze()).violations).toEqual([]);await page.screenshot({path:testInfo.outputPath(`compositions-${theme}.png`),fullPage:true});}
});

test('Simulateur : résultat reproductible, thèmes, clavier, mobile et aucune mutation',async({page,browser,baseURL},testInfo)=>{
 test.setTimeout(180000);const owner=await register(page);const p=process.env.BROWSER_ROUTER;const errors=[];page.on('pageerror',e=>errors.push(e.message));
 const before=queryIsolatedSql(p,`SELECT GROUP_CONCAT(CONCAT(resource_type,':',current_stock,':',last_updated_at) ORDER BY resource_type) FROM planet_resource WHERE planet_id=${owner.planet}`);
 await page.goto('/simulateur');await page.locator('#sim-attacker-ships-fighter-basic').fill('2');await page.locator('#sim-defender-ships-fighter-basic').fill('1');await page.getByRole('checkbox',{name:/Je confirme les hypothèses/}).check();await page.getByRole('button',{name:'Calculer un combat illustratif'}).focus();await page.keyboard.press('Enter');await expect(page.locator('#simulation-result')).toBeVisible();const first=await page.locator('#simulation-result').innerText();
 await page.getByRole('checkbox',{name:/Je confirme les hypothèses/}).check();await page.getByRole('button',{name:'Calculer un combat illustratif'}).click();expect(await page.locator('#simulation-result').innerText()).toBe(first);
 expect(queryIsolatedSql(p,`SELECT GROUP_CONCAT(CONCAT(resource_type,':',current_stock,':',last_updated_at) ORDER BY resource_type) FROM planet_resource WHERE planet_id=${owner.planet}`)).toBe(before);expect(Number(queryIsolatedSql(p,'SELECT COUNT(*) FROM combat_event'))).toBe(0);
 for(const theme of ['dark','light']){await setTheme(page,theme);await page.goto('/simulateur');await expect(page.locator('body')).toHaveAttribute('data-theme',theme);for(const width of [1440,390,320]){await page.setViewportSize({width,height:900});await expect.poll(()=>page.evaluate(()=>document.documentElement.scrollWidth)).toBeLessThanOrEqual(width);}expect((await new AxeBuilder({page}).include('main').analyze()).violations).toEqual([]);await page.screenshot({path:testInfo.outputPath(`simulateur-${theme}.png`),fullPage:true});}
 const context=await browser.newContext({baseURL,storageState:await page.context().storageState(),javaScriptEnabled:false});try{const tab=await context.newPage();await tab.goto('/simulateur');await tab.locator('#sim-attacker-ships-fighter-basic').fill('1');await tab.getByRole('checkbox',{name:/Je confirme les hypothèses/}).check();await tab.getByRole('button',{name:'Calculer un combat illustratif'}).click();await expect(tab.locator('#simulation-result')).toBeVisible();}finally{await context.close();}
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
 const context=await browser.newContext({baseURL});try{const tab=await context.newPage();await tab.goto('/connexion');await tab.getByLabel('Adresse email').fill(owner.email);await tab.getByLabel('Mot de passe',{exact:true}).fill(owner.password);await tab.getByRole('button',{name:/Connexion/}).click();await tab.goto('/flottes/compositions');await page.goto('/flottes/compositions');
 const data=async pg=>pg.locator('main form').first().evaluate(form=>({_token:form.querySelector('[name="_token"]').value,request_key:form.querySelector('[name="request_key"]').value,id:'0',version:'0',name:'Concurrente','ships[fighter][basic]':'1'}));
 const a=await data(page),b=await data(tab);const submit=(pg,form)=>pg.evaluate(async data=>(await fetch('/flottes/compositions',{method:'POST',body:new URLSearchParams(data)})).status,form);const responses=await Promise.all([submit(page,a),submit(tab,b)]);expect(responses.sort()).toEqual([200,422]);expect(Number(queryIsolatedSql(p,`SELECT COUNT(*) FROM fleet_composition WHERE owner_id=${owner.member}`))).toBe(20);
 }finally{await context.close();}
});
