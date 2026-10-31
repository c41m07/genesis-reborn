import { test, expect } from '@playwright/test';
async function enter(page,prefix='E2E',universe='Turbo') {
  const id=crypto.randomUUID().slice(0,8), email=`${prefix.toLowerCase()}-${id}@example.test`,password='Browser-test-2026!';
  await page.goto('/inscription');
  await page.getByLabel('Adresse email').fill(email);await page.getByLabel('Nom de commandant').fill(`${prefix}-${id}`);
  await page.getByLabel('Mot de passe (12 caractères minimum)',{exact:true}).fill(password);await page.getByLabel('Confirmer le mot de passe').fill(password);
  await page.getByRole('button',{name:'Créer mon compte'}).click();await expect(page).toHaveURL(/connexion/);
  await page.getByLabel('Adresse email').fill(email);await page.getByLabel('Mot de passe',{exact:true}).fill(password);await page.getByRole('button',{name:/Connexion/}).click();
  await page.locator('article.universe-card').filter({has:page.getByRole('heading',{name:new RegExp(universe)})}).getByRole('button').click();await expect(page).toHaveURL(/planetes\/\d+$/);
  return {email,password,username:`${prefix}-${id}`};
}
test('Inscription, connexion, menus, ressources fixes et rendu responsive', async ({page}) => {
  const errors=[];page.on('pageerror',e=>errors.push(e.message));await enter(page);
  await expect(page.locator('[data-resource-target="stock"]')).toHaveCount(5);
  await page.getByRole('link',{name:'Recherche',exact:true}).click();await expect(page.locator('#panel-technologies')).toBeVisible();await expect(page.locator('#panel-buildings')).toHaveCount(0);
  await page.getByRole('link',{name:'Chantier spatial',exact:true}).focus();await page.keyboard.press('Enter');await expect(page.locator('#panel-ships')).toBeVisible();
  await page.getByRole('link',{name:'Bâtiments',exact:true}).click();await expect(page.locator('#panel-buildings')).toBeVisible();
  await page.evaluate(()=>window.scrollTo({top:1200,behavior:'instant'}));await expect.poll(()=>page.locator('.resource-strip').evaluate(el=>Math.round(el.getBoundingClientRect().top))).toBe(68);
  await page.getByRole('link',{name:'Vue générale',exact:true}).click();await expect(page.locator('.section-tabs,.inventory-panel,.colony-catalog')).toHaveCount(0);
  await page.evaluate(async()=>{await document.fonts.ready;document.activeElement?.blur();window.scrollTo({top:0,behavior:'instant'})});await page.screenshot({path:'docs/validation/colonie-desktop.png',fullPage:true});await page.screenshot({path:'docs/validation/colonie-apercu.png'});
  await page.setViewportSize({width:390,height:844});await page.evaluate(()=>{document.activeElement?.blur();window.scrollTo({top:0,behavior:'instant'})});await expect.poll(()=>page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth)).toBe(true);await expect.poll(()=>page.locator('.sidebar').evaluate(el=>el.getBoundingClientRect().right)).toBeLessThanOrEqual(0);
  for(const stock of await page.locator('[data-resource-target="stock"]').all()) {const r=await stock.boundingBox();expect(r.x).toBeGreaterThanOrEqual(0);expect(r.x+r.width).toBeLessThanOrEqual(390);}
  await page.screenshot({path:'docs/validation/colonie-mobile.png',fullPage:true});await page.screenshot({path:'docs/validation/mobile-apercu.png'});
  await page.evaluate(()=>window.scrollTo({top:900,behavior:'instant'}));await expect.poll(()=>page.locator('.resource-strip').evaluate(el=>Math.round(el.getBoundingClientRect().top))).toBe(60);await page.evaluate(()=>window.scrollTo({top:0,behavior:'instant'}));
  await page.getByRole('button',{name:'Ouvrir le menu'}).click();await page.keyboard.press('Escape');await expect(page.getByRole('button',{name:'Ouvrir le menu'})).toHaveAttribute('aria-expanded','false');await page.getByRole('button',{name:'Ouvrir le menu'}).click();await page.getByRole('link',{name:'Carte stellaire',exact:true}).click();await expect(page.getByRole('heading',{name:'Carte stellaire',exact:true})).toBeVisible();await expect(page.getByText('Signal non cartographié').first()).toBeVisible();expect(errors).toEqual([]);
});
test('Construction réelle, notification privée et actualisation sans rechargement', async ({page}) => {
  await enter(page,'Live');await page.getByRole('link',{name:'Bâtiments',exact:true}).click();const card=()=>page.locator('article.catalog-card').filter({has:page.getByRole('heading',{name:'Extracteur · Métal',exact:true})});
  await card().getByRole('button',{name:/Améliorer au niveau 1/}).click();await expect(page.locator('.event-toast')).toBeVisible({timeout:20000});
  await expect(card().locator('.badge')).toContainText('NIVEAU 1',{timeout:15000});
});
test('Chantier, estimation, colonisation, transport et exploration réels', async ({page}) => {
  test.setTimeout(320000);await enter(page,'Fleet');const origin=Number(page.url().split('/').pop());await page.getByRole('link',{name:'Bâtiments',exact:true}).click();
  const card=name=>page.locator('article.catalog-card').filter({has:page.getByRole('heading',{name,exact:true})});
  await card('Chantier spatial').getByRole('button',{name:/Améliorer au niveau 1/}).click();await expect(card('Chantier spatial').locator('.badge')).toContainText('NIVEAU 1',{timeout:30000});
  for(const name of ['Sonde exploratrice','Vaisseau colonie','Transporteur']) {
    await page.getByRole('link',{name:'Chantier spatial',exact:true}).click();await card(name).getByRole('button',{name:'Construire',exact:true}).click();
  }
  await page.getByRole('link',{name:'Chantier spatial',exact:true}).click();
  for(const name of ['Sonde exploratrice','Vaisseau colonie','Transporteur']) await expect(card(name).locator('.badge')).toContainText('1 EN RÉSERVE',{timeout:90000});
  await page.getByRole('link',{name:'Flottes',exact:true}).click();
  let form=page.locator('.fleet-form form').first();
  await form.locator('[name="mission"]').selectOption('explore');await form.locator('[name="ships[probe]"]').fill('1');
  const earlySystems=await form.locator('[name="system"] option').evaluateAll(options=>options.map(o=>({value:o.value,text:o.textContent})));
  const earlyOrigin=await page.locator('.fleet-form').first().evaluate(el=>[Number(el.dataset.systemX),Number(el.dataset.systemY)]);
  const earlyCandidates=earlySystems.map(s=>{const xy=s.text.match(/\(([-\d.]+), ([-\d.]+)\)/);return {...s,distance:Math.hypot(Number(xy[1])-earlyOrigin[0],Number(xy[2])-earlyOrigin[1])};}).filter(s=>s.distance>0).sort((a,b)=>a.distance-b.distance);
  const earlyDestination=earlyCandidates[0];await form.locator('[name="system"]').selectOption(earlyDestination.value);await form.locator('[name="ratio"]').selectOption('0.7');
  await form.getByRole('button',{name:'Estimer le trajet'}).click();await expect(form.locator('.flight-estimate')).toContainText('Carburant aller-retour');
  await form.getByRole('button',{name:/Envoyer la flotte/}).click();await expect(page.locator('[data-status="traveling"]')).toHaveCount(1);
  await expect.poll(async()=>await page.locator('[data-status="completed"]').count(),{timeout:160000,intervals:[1000]}).toBe(1);
  await page.getByRole('link',{name:'Flottes',exact:true}).click();form=page.locator('.fleet-form form').first();
  const available=await form.locator('select[name="planet"] option').allTextContents();const targetIndex=available.findIndex(t=>t.includes('libre'));expect(targetIndex).toBeGreaterThanOrEqual(0);
  const target=Number(await form.locator('select[name="planet"] option').nth(targetIndex).getAttribute('value'));
  await page.goto('/carte');await page.locator(`a[href="/flottes?mission=colonize&planet=${target}"]`).click();form=page.locator('.fleet-form form').first();await expect(form.locator('[name="mission"]')).toHaveValue('colonize');await expect(form.locator('[name="planet"]')).toHaveValue(String(target));
  await form.locator('[name="mission"]').selectOption('colonize');await form.locator('[name="planet"]').selectOption(String(target));await form.locator('[name="ships[colony_ship]"]').fill('1');
  await form.getByRole('button',{name:'Estimer le trajet'}).click();await expect(form.locator('.flight-estimate')).toContainText('Carburant aller-retour');
  await form.getByRole('button',{name:/Envoyer la flotte/}).click();await expect(page.locator('[data-status="completed"]')).toHaveCount(2,{timeout:60000});
  await page.getByRole('link',{name:'Vue générale',exact:true}).click();await expect(page.getByLabel('Choisir une colonie')).toHaveValue(String(origin));
  await expect(page.locator('#sidebar-colony option')).toHaveCount(2);await page.getByLabel('Choisir une colonie').selectOption(String(target));await expect(page).toHaveURL(new RegExp(`/planetes/${target}$`));
  await page.getByRole('link',{name:'Recherche',exact:true}).click();await expect(page.locator('#panel-technologies')).toBeVisible();
  await page.getByLabel('Choisir une colonie').selectOption(String(origin));await expect(page).toHaveURL(new RegExp(`/planetes/${origin}\\?section=technologies`));await expect(page.locator('#panel-technologies')).toBeVisible();
  await page.getByRole('link',{name:'Vue générale',exact:true}).click();await expect(page).toHaveURL(new RegExp(`/planetes/${origin}$`));
  await page.getByRole('link',{name:'Flottes',exact:true}).click();
  form=page.locator('.fleet-form form').first();await form.locator('[name="mission"]').selectOption('transport');await form.locator('[name="planet"]').selectOption(String(target));await form.locator('[name="ships[transport]"]').fill('1');await form.locator('summary').click();await form.locator('[name="cargo[metal]"]').fill('123');
  await form.getByRole('button',{name:/Envoyer la flotte/}).click();await expect(page.locator('[data-status="traveling"]')).toHaveCount(1);
  await page.getByRole('link',{name:'Vue générale',exact:true}).click();await expect(page.locator('[data-active-fleet]')).toHaveCount(1);
  const bar=page.locator('[data-active-fleet] [role="progressbar"]');const initial=Number(await bar.getAttribute('aria-valuenow'));await expect.poll(async()=>Number(await bar.getAttribute('aria-valuenow'))).toBeGreaterThan(initial);
  await page.evaluate(()=>window.scrollTo({top:0,behavior:'instant'}));await page.screenshot({path:'docs/validation/colonie-multicolonies-vol.png',fullPage:true});
  await page.getByRole('link',{name:'Flottes',exact:true}).click();
  await expect.poll(async()=>await page.locator('[data-status="completed"]').count(),{timeout:80000,intervals:[1000]}).toBe(3);
  const destination=earlyDestination;await page.getByRole('link',{name:'Carte stellaire',exact:true}).click();await expect(page.locator('.system-card').filter({hasText:destination.text.split(' (')[0]}).locator('.badge')).toContainText('EXPLORÉ');
  await page.screenshot({path:'docs/validation/carte.png',fullPage:true});
});
test('Alliances réciproques, guilde, transfert et dissolution depuis deux comptes',async({browser})=>{
  test.setTimeout(90000);const a=await browser.newPage(),b=await browser.newPage();
  try {
    const alice=await enter(a,'Leader','Omega'),bob=await enter(b,'Member','Omega');
    await a.goto('/diplomatie');await b.goto('/diplomatie');
    const commander=(page,name)=>page.locator('.commander-card').filter({has:page.getByRole('heading',{name,exact:true})});
    await commander(a,bob.username).getByRole('button',{name:'Proposer une alliance'}).click();
    await b.reload();await commander(b,alice.username).getByRole('button',{name:"Accepter l'alliance"}).click();
    await expect(commander(b,alice.username)).toContainText('Allié');await a.reload();await expect(commander(a,bob.username)).toContainText('Allié');
    const name=`Orbite-${crypto.randomUUID().slice(0,8)}`;await a.getByLabel('Nom de votre nouvelle guilde').fill(name);await a.getByRole('button',{name:'Fonder une guilde'}).click();
    await b.reload();await b.locator('.guild-card').filter({has:b.getByRole('heading',{name,exact:true})}).getByRole('button',{name:/Rejoindre/}).click();
    await a.reload();await a.getByText('Gérer le commandement',{exact:true}).click();await a.getByLabel('Nouveau commandant').selectOption({label:bob.username});await a.getByRole('button',{name:'Transférer le commandement'}).click();
    await expect(a.getByRole('button',{name:'Quitter la guilde'})).toBeVisible();await a.getByRole('button',{name:'Quitter la guilde'}).click();
    await b.reload();await b.getByText('Gérer le commandement',{exact:true}).click();await b.screenshot({path:'docs/validation/diplomatie.png',fullPage:true});await b.getByRole('button',{name:'Dissoudre la guilde'}).click();
    await expect(b.getByRole('button',{name:'Fonder une guilde'})).toBeVisible();await expect(commander(b,alice.username)).toContainText('Allié');
  } finally {await a.close();await b.close();}
});
test('Files intégrées : commande active, supplément, durée totale et annulation',async({page})=>{
  await enter(page,'Queue','Omega');await page.getByRole('link',{name:'Bâtiments',exact:true}).click();const card=()=>page.locator('article.catalog-card').filter({has:page.getByRole('heading',{name:'Extracteur · Métal',exact:true})});
  await card().getByRole('button',{name:/Améliorer au niveau 1/}).click();await card().getByRole('button',{name:/Améliorer au niveau 2/}).click();
  const row=page.locator('.colony-banner [data-queue-type="building"]');await expect(row.locator('.queue-extra')).toHaveText('+1');
  const operation=row.locator('[data-controller="progress"]');const end=Date.parse(await operation.getAttribute('data-progress-end-value')),total=Date.parse(await operation.getAttribute('data-progress-total-value'));expect(total).toBeGreaterThan(end);
  await expect(row.getByText('File complète ·',{exact:false})).toBeVisible();const bar=row.getByRole('progressbar');const start=Number(await bar.getAttribute('aria-valuenow'));await expect.poll(async()=>Number(await bar.getAttribute('aria-valuenow')),{timeout:10000}).toBeGreaterThan(start);
  await page.evaluate(()=>{document.activeElement?.blur();window.scrollTo({top:0,behavior:'instant'})});await page.screenshot({path:'docs/validation/files-integrees.png',fullPage:true});
  await row.locator('summary').click();await row.getByRole('button',{name:'Annuler Extracteur · Métal et ses dépendances'}).first().click();
  await expect(page.locator('.colony-banner [data-queue-type="building"]')).toContainText('Aucune commande en cours');await expect(page.locator('.queue-extra')).toHaveCount(0);
});
test('Bénéfices des niveaux, arbre technologique et prérequis visuels',async({page})=>{
  await enter(page,'Info');await page.getByRole('link',{name:'Bâtiments',exact:true}).click();
  await expect(page.getByRole('heading',{name:'Extracteur · Énergie',exact:true})).toHaveCount(0);
  const central=page.locator('.catalog-card').filter({has:page.getByRole('heading',{name:'Centrale énergétique',exact:true})});await expect(central.locator('.card-benefits')).toContainText('Production');await expect(central.locator('.card-benefits')).toContainText('→');
  await page.screenshot({path:'docs/validation/benefices-batiments.png',fullPage:true});
  await page.getByRole('link',{name:'Recherche',exact:true}).click();await expect(page.locator('.tech-node')).toHaveCount(8);await expect(page.locator('#tech-advanced_propulsion .tech-prerequisites')).toContainText('Ingénierie');await expect(page.locator('#tech-advanced_propulsion .tech-prerequisites')).toContainText('Laboratoire');await page.screenshot({path:'docs/validation/arbre-technologique.png',fullPage:true});
});
