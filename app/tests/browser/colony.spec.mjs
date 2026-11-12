import { test, expect } from './support/isolated-test.mjs';
import { execFileSync } from 'node:child_process';
import { prepareOwnedColony, completedFleetCount, accelerateQueuedItem, accelerateTravelingMission, revealVirginPlanet, grantSecondColony, queryIsolatedSql, runIsolatedSql } from './support/game-fixtures.mjs';
function accelerateShipyard(project,email) {
  const query = "UPDATE queue_item q INNER JOIN player_universe pu ON pu.id=q.player_universe_id INNER JOIN player p ON p.id=pu.player_id SET q.duration_seconds=1, q.started_at=UTC_TIMESTAMP()-INTERVAL 10 SECOND WHERE p.email='"+email+"' AND q.queue_type='shipyard' AND q.item_slug IN ('probe','colony_ship','transport')";
  execFileSync('docker',['compose','--project-name',project,'--env-file','.env.local','exec','-T','app','php','bin/console','dbal:run-sql',query],{encoding:'utf8',stdio:['ignore','pipe','pipe']});
}
async function sendFleetWithRealtime(page, form) {
  await Promise.all([
    page.waitForResponse(response => new URL(response.url()).pathname === '/.well-known/mercure' && response.status() === 200),
    form.getByRole('button', { name: /Envoyer la flotte/ }).click(),
  ]);
}
async function enter(page,prefix='E2E') {
  const id=crypto.randomUUID().slice(0,8), email=`${prefix.toLowerCase()}-${id}@example.test`,password='Browser-test-2026!';
  await page.goto('/inscription');
  await page.getByLabel('Adresse email').fill(email);await page.getByLabel('Nom de commandant').fill(`${prefix}-${id}`);
  await page.getByLabel('Mot de passe (12 caractères minimum)',{exact:true}).fill(password);await page.getByLabel('Confirmer le mot de passe').fill(password);
  await page.getByRole('button',{name:'Créer mon compte'}).click();await expect(page).toHaveURL(/connexion/);
  await page.getByLabel('Adresse email').fill(email);await page.getByLabel('Mot de passe',{exact:true}).fill(password);await page.getByRole('button',{name:/Connexion/}).click();
  await page.locator('article.universe-card').first().getByRole('button').click();await expect(page).toHaveURL(/planetes\/\d+$/);
  return {email,password,username:`${prefix}-${id}`};
}
test('Inscription, connexion, menus, ressources fixes et rendu responsive', async ({page}) => {
  const errors=[];page.on('pageerror',e=>errors.push(e.message));await enter(page);
  await expect(page.locator('.dashboard-metrics .metric-card')).toHaveCount(4);await expect(page.getByRole('heading',{name:'Mes planètes',exact:true})).toHaveCount(0);await expect(page.locator('.dashboard-activity')).toBeVisible();await expect(page.locator('.dashboard-secondary .flight-operations')).toBeVisible();await expect(page.locator('.dashboard-journal')).toBeVisible();
  await expect(page.locator('[data-resource-target="stock"]')).toHaveCount(5);
  await page.getByRole('link',{name:'Recherche',exact:true}).click();await expect(page.locator('#panel-technologies')).toBeVisible();await expect(page.locator('#panel-buildings')).toHaveCount(0);
  await page.getByRole('link',{name:'Chantier spatial',exact:true}).focus();await page.keyboard.press('Enter');await expect(page.locator('#panel-ships')).toBeVisible();
  await page.getByRole('link',{name:'Bâtiments',exact:true}).click();await expect(page.locator('#panel-buildings')).toBeVisible();
  await page.waitForLoadState('load');await page.evaluate(()=>window.scrollTo({top:1200,behavior:'instant'}));await expect.poll(()=>page.locator('.resource-strip').evaluate(el=>Math.round(el.getBoundingClientRect().top))).toBe(await page.locator('.topbar').evaluate(el=>Math.round(el.getBoundingClientRect().height)));
  await page.getByRole('link',{name:'Vue générale',exact:true}).click();await expect(page.locator('.section-tabs,.inventory-panel,.colony-catalog')).toHaveCount(0);
  await page.evaluate(async()=>{await document.fonts.ready;document.activeElement?.blur();window.scrollTo({top:0,behavior:'instant'})});
  await page.setViewportSize({width:390,height:844});await page.evaluate(()=>{document.activeElement?.blur();window.scrollTo({top:0,behavior:'instant'})});await expect.poll(()=>page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth)).toBe(true);await expect.poll(()=>page.locator('.sidebar').evaluate(el=>el.getBoundingClientRect().right)).toBeLessThanOrEqual(0);
  await expect(page.locator('#resource-details')).toBeHidden();
  await expect(page.locator('.resource-summary-item')).toHaveCount(5);
  for(const stock of await page.locator('[data-resource-strip-target="compactStock"]').all()) {const r=await stock.boundingBox();expect(r).not.toBeNull();expect(r.x).toBeGreaterThanOrEqual(0);expect(r.x+r.width).toBeLessThanOrEqual(390);}
  const resourceToggle=page.getByRole('button',{name:/Ressources de .* afficher ou masquer les détails/});
  await resourceToggle.click();await expect(resourceToggle).toHaveAttribute('aria-expanded','true');
  for(const stock of await page.locator('[data-resource-target="stock"]').all()) {const r=await stock.boundingBox();expect(r).not.toBeNull();expect(r.x).toBeGreaterThanOrEqual(0);expect(r.x+r.width).toBeLessThanOrEqual(390);}
  await resourceToggle.click();await expect(page.locator('#resource-details')).toBeHidden();

  await page.evaluate(()=>window.scrollTo({top:900,behavior:'instant'}));await expect.poll(()=>page.locator('.resource-strip').evaluate(el=>Math.round(el.getBoundingClientRect().top))).toBe(await page.locator('.topbar').evaluate(el=>Math.round(el.getBoundingClientRect().height)));await page.evaluate(()=>window.scrollTo({top:0,behavior:'instant'}));
  await page.getByRole('navigation',{name:'Navigation mobile'}).getByRole('button',{name:'Plus'}).click();await page.keyboard.press('Shift+Tab');await expect(page.getByRole('button',{name:'Déconnexion'})).toBeFocused();await page.keyboard.press('Tab');await expect(page.locator('.brand').first()).toBeFocused();await page.keyboard.press('Escape');await expect(page.getByRole('navigation',{name:'Navigation mobile'}).getByRole('button',{name:'Plus'})).toHaveAttribute('aria-expanded','false');await page.getByRole('navigation',{name:'Navigation mobile'}).getByRole('button',{name:'Plus'}).click();await page.getByRole('navigation',{name:'Navigation principale'}).getByRole('link',{name:'Galaxie',exact:true}).click();await page.getByRole('link',{name:'Carte classique',exact:true}).click();await expect(page.getByRole('heading',{name:'Galaxie',exact:true})).toBeVisible();await page.getByText(/Parcourir les autres systèmes/).click();await expect(page.getByText('Signal non cartographié').first()).toBeVisible();await page.getByRole('navigation',{name:'Navigation mobile'}).getByRole('button',{name:'Plus'}).click();await page.getByRole('link',{name:'Rapports',exact:true}).click();await expect(page.getByRole('heading',{name:'Rapports',exact:true})).toBeVisible();await expect(page.getByRole('heading',{name:'Aucun rapport dans cette catégorie'})).toBeVisible();expect(errors).toEqual([]);
});
test('Construction réelle, notification privée et actualisation sans rechargement', async ({page}) => {
  const eventRequests=[];page.on('requestfailed',r=>{if(r.url().includes('mercure')||r.url().includes('notifications'))eventRequests.push({url:r.url(),failure:r.failure()?.errorText});});page.on('response',r=>{if(r.url().includes('mercure')||r.url().includes('notifications'))eventRequests.push({url:r.url(),status:r.status()});});
  const {email}=await enter(page,'Live');prepareOwnedColony(process.env.BROWSER_ROUTER,email);await page.getByRole('link',{name:'Bâtiments',exact:true}).click();const card=()=>page.locator('article.catalog-card').filter({has:page.getByRole('heading',{name:'Mine à ciel ouvert',exact:true})});
  await card().getByRole('button',{name:/Améliorer .* niveau 1/}).click();accelerateQueuedItem(process.env.BROWSER_ROUTER,email,'building','mine_metal');await expect(page.locator('.event-toast')).toBeVisible({timeout:20000}).catch(error=>{console.log('Flux temps réel:',JSON.stringify(eventRequests));throw error;});
  await expect(page.locator('#building-tab-mine_metal-basic .building-rank-level')).toHaveText('1',{timeout:15000});
});
test('Chantier, estimation, colonisation, transport et exploration réels', async ({page}) => {
  test.setTimeout(320000);page.setDefaultTimeout(15000);const {email}=await enter(page,'Fleet');prepareOwnedColony(process.env.BROWSER_ROUTER,email,{showLockedItems:true});grantSecondColony(process.env.BROWSER_ROUTER,email);const origin=Number(page.url().split('/').pop());await page.getByRole('link',{name:'Bâtiments',exact:true}).click();
  runIsolatedSql(process.env.BROWSER_ROUTER, `INSERT INTO planet_building (planet_id,slug,tier,level) VALUES (${origin},'lab','basic',4),(${origin},'mine_metal','basic',2)`);
  runIsolatedSql(process.env.BROWSER_ROUTER, `INSERT INTO player_technology (player_universe_id,slug,tier,level) SELECT owner_id,'engineering_foundations','basic',5 FROM planet WHERE id=${origin} UNION ALL SELECT owner_id,'naval_engineering','basic',5 FROM planet WHERE id=${origin}`);
  await page.reload();
  const card=slug=>page.locator('#item-'+slug+'-basic');
  await card('shipyard').getByRole('button',{name:/Améliorer .* niveau 1/}).click();accelerateQueuedItem(process.env.BROWSER_ROUTER,email,'building','shipyard');await page.reload();await expect(page.locator('#building-tab-shipyard-basic .building-rank-level')).toHaveText('1',{timeout:30000});
  await card('shipyard').getByRole('button',{name:/Améliorer .* niveau 2/}).click();accelerateQueuedItem(process.env.BROWSER_ROUTER,email,'building','shipyard');await page.reload();await expect(page.locator('#building-tab-shipyard-basic .building-rank-level')).toHaveText('2',{timeout:30000});
  for(const name of ['probe','colony_ship','transport']) {
    await page.getByRole('link',{name:'Chantier spatial',exact:true}).click();await card(name).getByRole('button',{name:/Construire/}).click();
  }
  await page.getByRole('link',{name:'Chantier spatial',exact:true}).click();
  accelerateShipyard(process.env.BROWSER_ROUTER,email);
  await page.reload();
  for(const name of ['probe','colony_ship','transport']) await expect(page.locator('#building-tab-'+name+'-basic .building-rank-level')).toHaveText('1',{timeout:90000});
  await page.getByRole('link',{name:'Flottes et missions',exact:true}).click();
  let form=page.locator('.fleet-form form').first();
  await form.locator('[name="ships[probe]"]').fill('1');
  await form.getByRole('button',{name:/Choisir la destination/}).click();
  await form.locator('[name="mission"]').selectOption('defend');await form.getByLabel('Destination personnalisée', { exact: true }).check();await expect(form.locator('[name="coordinates"]')).toBeVisible();
  await form.locator('[name="mission"]').selectOption('orbit');await expect(form.locator('[name="orbit_hours"]')).toBeVisible();
  await page.goto('/carte');await page.getByText(/Parcourir les autres systèmes/).click();
  const unknownSystem=page.locator('.system-card.unknown-system').first();
  const destinationCoordinates=(await unknownSystem.locator(':scope > p.muted').first().innerText()).trim();
  await page.goto('/flottes');form=page.locator('.fleet-form form').first();
  await form.locator('[name="ships[probe]"]').fill('1');await form.getByRole('button',{name:/Choisir la destination/}).click();
  await form.locator('[name="mission"]').selectOption('explore');await form.getByLabel('Destination personnalisée', { exact: true }).check();await form.locator('[name="coordinates"]').fill(destinationCoordinates);await form.locator('[name="ratio"]').selectOption('0.7');
  await form.getByRole('button',{name:/Vérifier la mission/}).click();await form.getByRole('button',{name:'Estimer le trajet'}).click();await expect(form.locator('.flight-estimate')).toContainText('Carburant aller-retour');
  await sendFleetWithRealtime(page,form);await expect(page.locator('[data-status="traveling"]')).toHaveCount(1);accelerateTravelingMission(process.env.BROWSER_ROUTER,email,'explore');
  await expect.poll(async()=>completedFleetCount(process.env.BROWSER_ROUTER,email),{timeout:45000,intervals:[1000]}).toBe(1);
  await expect(page.locator('[data-active-fleet]')).toHaveCount(0, { timeout: 15000 });

  const target=revealVirginPlanet(process.env.BROWSER_ROUTER,email);
  await page.getByRole('link',{name:'Flottes et missions',exact:true}).click();form=page.locator('.fleet-form form').first();
  await form.locator('[name="ships[colony_ship]"]').fill('1');await form.getByRole('button',{name:/Choisir la destination/}).click();
  await form.locator('[name="mission"]').selectOption('colonize');await form.getByLabel('Destination personnalisée', { exact: true }).check();await form.locator('[name="coordinates"]').fill(target.coordinates);await form.locator('[name="ratio"]').selectOption('0.1');
  await form.getByRole('button',{name:/Vérifier la mission/}).click();await form.getByRole('button',{name:'Estimer le trajet'}).click();await expect(form.locator('.flight-estimate')).toContainText('Carburant aller-retour');
  await sendFleetWithRealtime(page,form);await expect(page.locator('[data-status="traveling"]')).toHaveCount(1);accelerateTravelingMission(process.env.BROWSER_ROUTER,email,'colonize');await expect.poll(()=>completedFleetCount(process.env.BROWSER_ROUTER,email),{timeout:45000,intervals:[1000]}).toBe(2);await expect(page.locator('[data-active-fleet]')).toHaveCount(0);
  await page.getByRole('link',{name:'Vue générale',exact:true}).click();await expect(page.getByLabel('Choisir une colonie')).toHaveValue(String(origin));
  await page.waitForLoadState('load');await expect(page.locator('#sidebar-colony option')).toHaveCount(2);await page.getByLabel('Choisir une colonie').selectOption(String(target.id));await expect(page).toHaveURL(new RegExp('/planetes/'+target.id+'$'));
  await page.getByRole('link',{name:'Recherche',exact:true}).click();await expect(page.locator('#panel-technologies')).toBeVisible();
  await page.waitForLoadState('load');await page.getByLabel('Choisir une colonie').selectOption(String(origin));await expect(page).toHaveURL(url=>url.pathname==='/planetes/'+origin&&url.search==='?section=technologies');await expect(page.locator('#panel-technologies')).toBeVisible();
  await page.getByRole('link',{name:'Vue générale',exact:true}).click();await expect(page).toHaveURL(new RegExp('/planetes/'+origin+'$'));
  await page.getByRole('link',{name:'Flottes et missions',exact:true}).click();form=page.locator('.fleet-form form').first();
  await form.locator('[name="ships[transport]"]').fill('1');await form.getByRole('button',{name:/Choisir la destination/}).click();await form.locator('[name="mission"]').selectOption('transport');await form.getByLabel('Destination personnalisée', { exact: true }).check();await form.locator('[name="coordinates"]').fill(target.coordinates);await form.locator('[name="ratio"]').selectOption('0.1');await form.locator('summary').click();await form.locator('[name="cargo[metal]"]').fill('123');await form.getByRole('button',{name:/Vérifier la mission/}).click();
  await form.getByRole('button',{name:/Envoyer la flotte/}).click();await expect(page.locator('[data-status="traveling"]')).toHaveCount(1);
  await page.getByRole('link',{name:'Vue générale',exact:true}).click();await expect(page.locator('[data-active-fleet]')).toHaveCount(1);
  const bar=page.locator('[data-active-fleet] [role="progressbar"]');const progress=Number(await bar.getAttribute('aria-valuenow'));expect(Number.isFinite(progress)).toBe(true);expect(progress).toBeGreaterThanOrEqual(0);expect(progress).toBeLessThanOrEqual(100);accelerateTravelingMission(process.env.BROWSER_ROUTER,email,'transport');
  await page.evaluate(()=>window.scrollTo({top:0,behavior:'instant'}));
  await expect.poll(()=>queryIsolatedSql(process.env.BROWSER_ROUTER,"SELECT status FROM fleet INNER JOIN player_universe membership ON membership.id = fleet.player_universe_id INNER JOIN player ON player.id = membership.player_id WHERE player.email = '"+email+"' AND mission = 'transport' ORDER BY fleet.id DESC LIMIT 1"),{timeout:45000,intervals:[1000]}).toBe('returning');await page.goto('/flottes');await expect(page.locator('[data-status="returning"]')).toHaveCount(1);accelerateTravelingMission(process.env.BROWSER_ROUTER,email,'transport');
  await page.getByRole('link',{name:'Flottes et missions',exact:true}).click();await expect.poll(async()=>{await page.reload();return completedFleetCount(process.env.BROWSER_ROUTER,email);},{timeout:45000,intervals:[1000]}).toBe(3);await expect(page.locator('[data-active-fleet]')).toHaveCount(0);
  const discovered=queryIsolatedSql(process.env.BROWSER_ROUTER,"SELECT COUNT(*) FROM player_discovery discovery INNER JOIN player_universe membership ON membership.id = discovery.player_universe_id INNER JOIN player ON player.id = membership.player_id INNER JOIN solar_system system ON system.id = discovery.solar_system_id INNER JOIN galaxy ON galaxy.id = system.galaxy_id WHERE player.email = '"+email+"' AND CONCAT(REPLACE(galaxy.name, 'Galaxie ', ''), ':', FLOOR(system.coord_y / 3) * 10 + FLOOR(system.coord_x / 3) + 1) = '"+destinationCoordinates+"'");expect(discovered).toBe('1');await page.getByRole('link',{name:'Galaxie',exact:true}).click();await page.getByRole('link',{name:'Carte classique',exact:true}).click();await expect(page.getByText(/Parcourir les autres systèmes/)).toBeVisible();
});
test('Alliances réciproques, guilde, transfert et dissolution depuis deux comptes',async({browser})=>{
  test.setTimeout(90000);const a=await browser.newPage(),b=await browser.newPage();
  try {
    const alice=await enter(a,'Leader','Omega'),bob=await enter(b,'Member','Omega');
    await a.goto('/diplomatie?tab=relations&q='+encodeURIComponent(bob.username));await b.goto('/diplomatie?tab=relations');
    const commander=(page,name)=>page.locator('.commander-card').filter({has:page.getByRole('heading',{name,exact:true})});
    await commander(a,bob.username).getByRole('button',{name:'Proposer une alliance'}).click();
    await b.reload();await commander(b,alice.username).getByRole('button',{name:"Accepter l'alliance"}).click();
    await expect(commander(b,alice.username)).toContainText('Allié');await a.goto('/diplomatie?tab=relations');await expect(commander(a,bob.username)).toContainText('Allié');
    await a.goto('/diplomatie');const name=`Orbite-${crypto.randomUUID().slice(0,8)}`;await a.getByLabel('Nom de votre nouvelle guilde').fill(name);await a.getByRole('button',{name:'Fonder une guilde'}).click();
    await b.goto('/diplomatie?tab=diplomacy&q='+encodeURIComponent(name));await b.locator('.guild-card').filter({has:b.getByRole('heading',{name,exact:true})}).getByRole('button',{name:/Rejoindre/}).click();
    await a.goto('/diplomatie');await a.getByText('Gérer le commandement',{exact:true}).click();await a.getByLabel('Nouveau commandant').selectOption({label:bob.username});await a.getByRole('button',{name:'Transférer le commandement'}).click();
    await expect(a.getByRole('button',{name:'Quitter la guilde'})).toBeVisible();await a.getByRole('button',{name:'Quitter la guilde'}).click();
    await b.goto('/diplomatie');await b.getByText('Gérer le commandement',{exact:true}).click();await b.getByRole('button',{name:'Dissoudre la guilde'}).click();
    await expect(b.getByRole('button',{name:'Fonder une guilde'})).toBeVisible();await b.goto('/diplomatie?tab=relations');await expect(commander(b,alice.username)).toContainText('Allié');
  } finally {await a.close();await b.close();}
});
test('Files intégrées : commande active, supplément, durée totale et annulation',async({page})=>{
  const {email}=await enter(page,'Queue');runIsolatedSql(process.env.BROWSER_ROUTER, `UPDATE universe u JOIN player_universe pu ON pu.universe_id=u.id JOIN player p ON p.id=pu.player_id SET u.game_settings=JSON_SET(CASE WHEN JSON_TYPE(u.game_settings)='OBJECT' THEN u.game_settings ELSE JSON_OBJECT() END,'$.multipliers.building_time',10) WHERE p.email='${email}'`);await page.getByRole('link',{name:'Bâtiments',exact:true}).click();const card=()=>page.locator('article.catalog-card').filter({has:page.getByRole('heading',{name:'Mine à ciel ouvert',exact:true})});
  await card().getByRole('button',{name:/Améliorer .* niveau 1/}).click();await card().getByRole('button',{name:/Améliorer .* niveau 2/}).click();
  const row=page.locator('.scene-operations [data-queue-type="building"]');await expect(row.locator('.queue-extra')).toHaveText('+1');
  const operation=row.locator('[data-controller="progress"]');const end=Date.parse(await operation.getAttribute('data-progress-end-value')),total=Date.parse(await operation.getAttribute('data-progress-total-value'));expect(total).toBeGreaterThan(end);
  await expect(row.getByText('File complète ·',{exact:false})).toBeVisible();const bar=row.getByRole('progressbar');const start=Number(await bar.getAttribute('aria-valuenow'));await expect.poll(async()=>Number(await bar.getAttribute('aria-valuenow')),{timeout:10000}).toBeGreaterThan(start);
  await page.evaluate(()=>{document.activeElement?.blur();window.scrollTo({top:0,behavior:'instant'})});
  await row.locator('summary').click();await row.getByRole('button',{name:/Annuler Mine à ciel ouvert.*et ses dépendances/}).first().click();
  await expect(page.locator('.scene-operations [data-queue-type="building"]')).toContainText('Aucune commande en cours');await expect(page.locator('.queue-extra')).toHaveCount(0);
});
test('Bénéfices des niveaux, arbre technologique et prérequis visuels',async({page})=>{
  const {email}=await enter(page,'Info');prepareOwnedColony(process.env.BROWSER_ROUTER,email,{showLockedItems:true});await page.getByRole('link',{name:'Bâtiments',exact:true}).click();
  await expect(page.getByRole('heading',{name:'Extracteur · Énergie',exact:true})).toHaveCount(0);
  const central=page.locator('#item-power_plant-basic');await expect(central.locator('.card-benefits')).toContainText('Production');await expect(central.locator('.card-benefits')).toContainText(/\+\d/);

  await page.getByRole('link',{name:'Recherche',exact:true}).click();await expect(page.locator('.technology-tree')).toHaveCount(0);await page.getByRole('link',{name:'Technologie',exact:true}).click();await expect(page.locator('.tech-node:visible').first()).toBeVisible();const propulsion=page.locator('#tech-vector_propulsion-basic');await expect(propulsion.locator('summary')).toContainText('Niveau');await expect(propulsion.locator('summary')).toContainText('Verrouillé');await propulsion.locator('summary').click();await expect(page.locator('#technology-detail')).toContainText('Génie civil');await expect(page.locator('#technology-detail')).toContainText('Laboratoire');
});

test('La préférence révèle les rangs verrouillés du catalogue', async ({page}) => {
  const {email}=await enter(page, 'Ranks');
  const colonyUrl = page.url();
  queryIsolatedSql(process.env.BROWSER_ROUTER, "UPDATE player SET preferences=JSON_OBJECT('theme','dark','timezone','Europe/Paris') WHERE email='"+email+"'");
  const preference = page.getByRole('checkbox', {name: /Afficher les bâtiments, technologies, vaisseaux et défenses verrouillés/});
  await page.goto('/profil/preferences');
  await page.getByLabel('Thème de l’interface').selectOption('light');
  await preference.check();
  const saveResponse = page.waitForResponse(response => response.url().includes('/profil/preferences') && response.request().method()==='POST');
  await page.getByRole('button', {name: 'Enregistrer les paramètres'}).click();
  expect((await saveResponse).status()).toBe(302);
  await expect(page.locator('body')).toHaveAttribute('data-theme', 'light');
  await expect(page.getByRole('checkbox', {name: /Afficher les bâtiments, technologies, vaisseaux et défenses verrouillés/})).toBeChecked();
  await page.goto(colonyUrl);
  await page.getByRole('link', {name: 'Recherche', exact: true}).click();
  await expect(page.getByRole('switch', {name:'Afficher les éléments verrouillés'})).toHaveAttribute('aria-checked','true');
  await expect(page.locator('#building-tab-weapon_calibration-intermediate')).toBeVisible();
  await page.locator('#building-tab-weapon_calibration-intermediate').click();
  await expect(page.locator('#item-weapon_calibration-intermediate')).toBeVisible();
  await expect(page.locator('#building-tab-weapon_calibration-superior')).toBeVisible();
  await page.locator('#building-tab-weapon_calibration-superior').click();
  await expect(page.locator('#item-weapon_calibration-superior')).toBeVisible();
  await expect(page.locator('#item-weapon_calibration-intermediate')).toHaveClass(/locked/);

  await page.getByRole('link', {name: 'Chantier spatial', exact: true}).click();
  await expect(page.locator('#building-tab-fighter-intermediate')).toBeVisible();
  await page.locator('#building-tab-fighter-intermediate').click();
  await expect(page.locator('#item-fighter-intermediate')).toBeVisible();
  await expect(page.locator('#building-tab-fighter-superior')).toBeVisible();
  await page.locator('#building-tab-fighter-superior').click();
  await expect(page.locator('#item-fighter-superior')).toBeVisible();
  await expect(page.locator('#item-fighter-intermediate')).toHaveClass(/locked/);

  await page.getByRole('link', {name: 'Bâtiments', exact: true}).click();
  await expect(page.locator('#building-tab-mine_metal-intermediate')).toBeVisible();
  await page.locator('#building-tab-mine_metal-intermediate').click();
  await expect(page.locator('#item-mine_metal-intermediate')).toBeVisible();
  await expect(page.locator('#building-tab-mine_metal-superior')).toBeVisible();
  await page.locator('#building-tab-mine_metal-superior').click();
  await expect(page.locator('#item-mine_metal-superior')).toBeVisible();

});
