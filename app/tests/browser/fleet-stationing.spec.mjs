import { test, expect } from './support/isolated-test.mjs';
import AxeBuilder from '@axe-core/playwright';
import { prepareOwnedColony, revealVirginPlanet, runIsolatedSql, queryIsolatedSql, accelerateTravelingMission } from './support/game-fixtures.mjs';

async function prepare(page) {
  const suffix=crypto.randomUUID().slice(0,8),email=`station-${suffix}@example.test`,password=crypto.randomUUID()+'aA1!';
  await page.goto('/inscription');await page.getByLabel('Adresse email').fill(email);await page.getByLabel('Nom de commandant').fill('Station-'+suffix);
  await page.getByLabel('Mot de passe (12 caractères minimum)',{exact:true}).fill(password);await page.getByLabel('Confirmer le mot de passe').fill(password);
  await page.getByRole('button',{name:'Créer mon compte'}).click();await expect(page).toHaveURL(/connexion/);
  await page.getByLabel('Adresse email').fill(email);await page.getByLabel('Mot de passe',{exact:true}).fill(password);await page.getByRole('button',{name:/Connexion/}).click();
  await page.locator('article.universe-card').first().getByRole('button').click();await expect(page).toHaveURL(/planetes\/\d+$/);
  const project=process.env.BROWSER_ROUTER,origin=Number(page.url().split('/').at(-1));prepareOwnedColony(project,email);
  const own=revealVirginPlanet(project,email),member=Number(queryIsolatedSql(project,`SELECT owner_id FROM planet WHERE id=${origin}`));
  runIsolatedSql(project,`UPDATE planet SET owner_id=${member},name='Nouvelle base',acquired_at=UTC_TIMESTAMP() WHERE id=${own.id}`);
  runIsolatedSql(project,`INSERT IGNORE INTO planet_resource(planet_id,resource_type,current_stock,production_rate,last_updated_at) SELECT ${own.id},resource_type,0,0,UTC_TIMESTAMP() FROM planet_resource WHERE planet_id=${origin}`);
  runIsolatedSql(project,`INSERT INTO fleet(player_universe_id,home_planet_id,status,mission,speed_ratio,cargo,reserved_fuel,orbit_fuel,orbit_duration_seconds,travel_seconds,dispatch_token) VALUES(${member},${origin},'idle','idle',1,'{}',0,0,0,0,'')`);
  runIsolatedSql(project,`INSERT INTO fleet_ship(fleet_id,slug,tier,quantity) SELECT id,'transport','basic',4 FROM fleet WHERE home_planet_id=${origin} AND status='idle'`);
  return {email,project,origin,own,member};
}

async function send(page,data) {
  await page.goto('/flottes?mission=station');const form=page.locator('.fleet-form form').first();
  await form.locator('[name="ships[transport]"]').fill('2');await form.getByRole('button',{name:/Choisir la destination/}).click();
  await expect(form.locator('input[value="owned"][name="destination_mode"]')).toBeChecked();
  await expect(form.locator('input[value="npc"][name="destination_mode"]')).toBeDisabled();
  await form.locator('select[name="owned_destination"]').selectOption(String(data.own.id));
  await form.locator('details[data-fleet-fields-target="cargo"] summary').click();await form.locator('[name="cargo[metal]"]').fill('100');
  await form.getByRole('button',{name:/Vérifier la mission/}).click();await expect(form.locator('.flight-estimate')).toContainText('Carburant aller-retour');
  await expect(form.getByRole('button',{name:/Envoyer la flotte/})).toBeEnabled();await form.getByRole('button',{name:/Envoyer la flotte/}).click();
  await expect(page.getByText(/Flotte envoyée/)).toBeVisible();
  return Number(queryIsolatedSql(data.project,`SELECT id FROM fleet WHERE player_universe_id=${data.member} AND mission='station' AND status='traveling'`));
}

test('Stationnement : nouvelle base, réserve à quai, déchargement et rendu accessible',async({page},testInfo)=>{
  const data=await prepare(page),id=await send(page,data);
  accelerateTravelingMission(data.project,data.email,'station');
  await expect.poll(()=>queryIsolatedSql(data.project,`SELECT status FROM fleet WHERE id=${id}`),{timeout:20000}).toBe('completed');
  await page.goto('/planetes/'+data.own.id);await page.goto('/flottes');
  await expect(page.getByRole('heading',{name:'Réserve embarquée à quai',exact:true})).toBeVisible();
  expect(Number(queryIsolatedSql(data.project,`SELECT SUM(stored_fuel) FROM fleet WHERE home_planet_id=${data.own.id} AND status='idle'`))).toBeGreaterThan(0);
  for(const viewport of [{width:1440,height:1000},{width:390,height:844}]) {
    await page.setViewportSize(viewport);await page.evaluate(()=>window.scrollTo({top:0,behavior:'instant'}));
    expect(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1)).toBeTruthy();
    expect((await new AxeBuilder({page}).include('main').analyze()).violations.filter(v=>['serious','critical'].includes(v.impact))).toEqual([]);
    await page.screenshot({path:testInfo.outputPath('stationnement-'+viewport.width+'.png'),fullPage:true,animations:'disabled'});
  }
  const unload=page.getByRole('button',{name:'Décharger le carburant',exact:true});await unload.focus();await expect(unload).toBeFocused();
  await page.keyboard.press('Enter');await expect(page.getByText(/Déchargement effectué/)).toBeVisible();
  expect(Number(queryIsolatedSql(data.project,`SELECT SUM(quantity) FROM fleet_ship`))).toBe(4);
});

test('Attente : accessible malgré l’origine perdue, reprise tactile après devis',async({browser},testInfo)=>{
  const context=await browser.newContext({viewport:{width:390,height:844},hasTouch:true,isMobile:true});const page=await context.newPage();
  try {
    const data=await prepare(page),id=await send(page,data);
    runIsolatedSql(data.project,`UPDATE planet SET owner_id=NULL,acquired_at=NULL WHERE owner_id=${data.member}`);
    accelerateTravelingMission(data.project,data.email,'station');
    await expect.poll(()=>queryIsolatedSql(data.project,`SELECT status FROM fleet WHERE id=${id}`),{timeout:20000}).toBe('station_waiting');
    runIsolatedSql(data.project,`UPDATE planet SET owner_id=${data.member},acquired_at=UTC_TIMESTAMP() WHERE id=${data.own.id}`);
    await page.goto('/flottes');await page.getByRole('link',{name:'Reprendre le stationnement de la flotte '+id,exact:true}).tap();
    await page.getByLabel('Colonie de destination',{exact:true}).selectOption(String(data.own.id));await page.getByRole('button',{name:'Calculer le trajet',exact:true}).tap();
    await expect(page.getByRole('button',{name:'Confirmer la reprise',exact:true})).toBeVisible();
    expect((await new AxeBuilder({page}).include('main').analyze()).violations.filter(v=>['serious','critical'].includes(v.impact))).toEqual([]);
    expect(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1)).toBeTruthy();
    await page.screenshot({path:testInfo.outputPath('reprise-stationnement-mobile.png'),fullPage:true,animations:'disabled'});
    await page.getByRole('button',{name:'Confirmer la reprise',exact:true}).tap();await expect(page.getByText('Reprise du stationnement enregistrée.',{exact:true})).toBeVisible();expect(queryIsolatedSql(data.project,`SELECT status FROM fleet WHERE id=${id}`)).toBe('completed');
  } finally {await context.close();}
});
