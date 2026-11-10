import { test, expect } from './support/isolated-test.mjs';
import { execFileSync } from 'node:child_process';
import AxeBuilder from '@axe-core/playwright';
import { runIsolatedSql, queryIsolatedSql, grantSecondColony } from './support/game-fixtures.mjs';

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
  return { playerId, memberId, planetId, universeId };
}

async function post(page, url, form) {
  return page.evaluate(async ({url,form}) => (await fetch(url,{method:'POST',body:new URLSearchParams(form)})).status,{url,form});
}

function addBox(project,user,key='ui-box') {
  const payload={format:'small',label:'Petite boîte de ressources',resources:{metal:4000,nano:1000,hydrogen:1000,antimatter:200,electricity:2000}};
  runIsolatedSql(project,`INSERT INTO player_boost (player_universe_id,source_type,source_key,status,earned_at,kind,payload) VALUES (${user.memberId},'event','${key}-${user.memberId}','available',UTC_TIMESTAMP(),'resource_box','${JSON.stringify(payload)}')`);
  return Number(queryIsolatedSql(project,`SELECT id FROM player_boost WHERE source_key='${key}-${user.memberId}'`));
}

test('Boîte : contenu annoncé, choix de colonie, protection et ouverture unique',async({page,browser},testInfo)=>{
  test.setTimeout(150000);const peer=await browser.newPage();const other=await enter(peer);const own=await enter(page);const project=process.env.BROWSER_ROUTER;
  const email=queryIsolatedSql(project,`SELECT email FROM player WHERE id=${own.playerId}`);grantSecondColony(project,email);
  const second=Number(queryIsolatedSql(project,`SELECT p.id FROM planet p INNER JOIN solar_system s ON s.id=p.solar_system_id INNER JOIN galaxy g ON g.id=s.galaxy_id WHERE g.universe_id=${own.universeId} AND p.owner_id IS NULL AND p.npc_planet=0 ORDER BY p.id LIMIT 1`));
  runIsolatedSql(project,`UPDATE planet SET owner_id=${own.memberId},name='Colonie des boites' WHERE id=${second}`);
  runIsolatedSql(project,`INSERT INTO planet_resource (planet_id,resource_type,current_stock,production_rate,last_updated_at) SELECT ${second},resource_type,1000,0,UTC_TIMESTAMP() FROM planet_resource WHERE planet_id=${own.planetId} ON DUPLICATE KEY UPDATE current_stock=1000,production_rate=0,last_updated_at=UTC_TIMESTAMP()`);
  const id=addBox(project,own);await page.goto('/boosts');const card=page.locator(`.resource-box-card[data-box-id='${id}']`);await expect(card).toContainText('4 000,00');
  for(const theme of ['dark','light']) {
    runIsolatedSql(project,`UPDATE player SET preferences=JSON_SET(CASE WHEN JSON_TYPE(preferences)='OBJECT' THEN preferences ELSE JSON_OBJECT() END,'$.theme','${theme}') WHERE id=${own.playerId}`);
    for(const width of [1440,390]) {await page.setViewportSize({width,height:900});await page.reload();await expect(page.locator('body')).toHaveAttribute('data-theme',theme);expect(await page.evaluate(()=>document.documentElement.scrollWidth)).toBeLessThanOrEqual(width);expect((await new AxeBuilder({page}).withTags(['wcag2a','wcag2aa','wcag21aa']).analyze()).violations).toEqual([]);await page.screenshot({path:testInfo.outputPath(`box-${theme}-${width}.png`),fullPage:true});}
  }
  await card.getByRole('button').click();expect(queryIsolatedSql(project,`SELECT status FROM player_boost WHERE id=${id}`)).toBe('available');
  await card.getByLabel('Colonie de destination').selectOption(String(second));const form=card.locator('form');const action=await form.getAttribute('action');const data=await form.evaluate(f=>Object.fromEntries(new FormData(f)));
  expect(await post(page,action,{...data,_token:'invalid'})).toBe(403);
  await post(page,action,{...data,planet_id:String(other.planetId)});expect(queryIsolatedSql(project,`SELECT status FROM player_boost WHERE id=${id}`)).toBe('available');
  const stock=planet=>Number(queryIsolatedSql(project,`SELECT current_stock FROM planet_resource WHERE planet_id=${planet} AND resource_type='metal'`));const home=stock(own.planetId),before=stock(second);
  await form.getByRole('button').click();await expect(page.locator('.flash.success')).toContainText('Boîte ouverte');expect(stock(own.planetId)).toBe(home);const after=stock(second);expect(after).toBeGreaterThanOrEqual(before+4000);expect(after).toBeLessThan(before+4010);
  await post(page,action,data);expect(stock(second)).toBe(after);expect(queryIsolatedSql(project,`SELECT status FROM player_boost WHERE id=${id}`)).toBe('used');await peer.close();
});

test('Boîte : stockage insuffisant et vacances conservent objet et ressources',async({page})=>{
  test.setTimeout(90000);const own=await enter(page);const project=process.env.BROWSER_ROUTER;const id=addBox(project,own);
  runIsolatedSql(project,`UPDATE planet_resource SET current_stock=200000,last_updated_at=UTC_TIMESTAMP() WHERE planet_id=${own.planetId} AND resource_type='metal'`);
  await page.goto('/boosts');const card=page.locator('.resource-box-card');await card.getByLabel('Colonie de destination').selectOption(String(own.planetId));
  const data=await card.locator('form').evaluate(f=>Object.fromEntries(new FormData(f)));const action=await card.locator('form').getAttribute('action');
  const before=queryIsolatedSql(project,`SELECT current_stock FROM planet_resource WHERE planet_id=${own.planetId} ORDER BY resource_type`);
  await card.getByRole('button').click();await expect(page.locator('.flash.error')).toContainText('La boîte reste intacte');expect(queryIsolatedSql(project,`SELECT status FROM player_boost WHERE id=${id}`)).toBe('available');expect(queryIsolatedSql(project,`SELECT current_stock FROM planet_resource WHERE planet_id=${own.planetId} ORDER BY resource_type`)).toBe(before);
  runIsolatedSql(project,`INSERT INTO vacation_period (membership_id,started_at,scheduled_end_at,ended_at) VALUES (${own.memberId},UTC_TIMESTAMP()-INTERVAL 1 MINUTE,UTC_TIMESTAMP()+INTERVAL 1 DAY,NULL)`);
  await page.reload();await expect(card).toContainText('Ouverture bloquée pendant les vacances');await expect(card.getByRole('button')).toHaveCount(0);
  await post(page,action,data);expect(queryIsolatedSql(project,`SELECT status FROM player_boost WHERE id=${id}`)).toBe('available');expect(queryIsolatedSql(project,`SELECT current_stock FROM planet_resource WHERE planet_id=${own.planetId} ORDER BY resource_type`)).toBe(before);
});

test('Admin : formats plafonnés, saisie conservée et modification journalisée',async({page},testInfo)=>{
  test.setTimeout(90000);const own=await enter(page,true);const project=process.env.BROWSER_ROUTER;await page.goto(`/admin/universes/${own.universeId}/boites`);
  const metal=page.locator('[name="templates[small][metal]"]');await expect(metal).toHaveValue('4000');await metal.fill('20001');await page.getByLabel('Motif du changement').fill('Essai de plafond');await page.getByRole('checkbox').check();await page.getByRole('button',{name:'Enregistrer les modèles de boîtes'}).click();
  await expect(page.locator('.flash.error')).toContainText('dépasse le plafond');await expect(metal).toHaveValue('20001');await metal.fill('4500');await page.getByRole('checkbox').check();await page.getByRole('button',{name:'Enregistrer les modèles de boîtes'}).click();
  await expect(page.locator('.flash.success')).toContainText('Modèles et plafonds enregistrés');await expect(metal).toHaveValue('4500');expect(Number(queryIsolatedSql(project,`SELECT COUNT(*) FROM admin_audit_log WHERE action='resource_box_settings' AND target_id=${own.universeId}`))).toBe(1);
  await page.setViewportSize({width:390,height:900});expect(await page.evaluate(()=>document.documentElement.scrollWidth)).toBeLessThanOrEqual(390);expect((await new AxeBuilder({page}).withTags(['wcag2a','wcag2aa','wcag21aa']).analyze()).violations).toEqual([]);await page.screenshot({path:testInfo.outputPath('box-settings-390.png'),fullPage:true});
});
