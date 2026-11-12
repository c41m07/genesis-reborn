import { test, expect } from './support/isolated-test.mjs';
import AxeBuilder from '@axe-core/playwright';
import { prepareOwnedColony, revealVirginPlanet, runIsolatedSql, queryIsolatedSql } from './support/game-fixtures.mjs';

async function enter(page,prefix='Abandon') {
  const suffix=crypto.randomUUID().slice(0,8),email=prefix.toLowerCase()+'-'+suffix+'@example.test',username=prefix+'-'+suffix,password='Browser-test-2026!';
  await page.goto('/inscription');
  await page.getByLabel('Adresse email').fill(email);await page.getByLabel('Nom de commandant').fill(username);
  await page.getByLabel('Mot de passe (12 caractères minimum)',{exact:true}).fill(password);await page.getByLabel('Confirmer le mot de passe').fill(password);
  await page.getByRole('button',{name:'Créer mon compte'}).click();await expect(page).toHaveURL(/connexion/);
  await page.getByLabel('Adresse email').fill(email);await page.getByLabel('Mot de passe',{exact:true}).fill(password);await page.getByRole('button',{name:/Connexion/}).click();
  await page.locator('article.universe-card').first().getByRole('button').click();await expect(page).toHaveURL(/planetes\/\d+$/);
  const project=process.env.BROWSER_ROUTER,planetId=Number(page.url().split('/').pop());
  const memberId=Number(queryIsolatedSql(project,"SELECT current_player_universe_id FROM player WHERE email='"+email+"'"));
  const name=queryIsolatedSql(project,'SELECT name FROM planet WHERE id='+planetId);
  return {project,email,username,planetId,memberId,name};
}

async function prepare(page) {
  const user=await enter(page);prepareOwnedColony(user.project,user.email);
  const other=revealVirginPlanet(user.project,user.email);
  runIsolatedSql(user.project,"UPDATE planet SET owner_id="+user.memberId+",name='Colonie témoin',acquired_at=UTC_TIMESTAMP() WHERE id="+other.id);
  runIsolatedSql(user.project,"INSERT INTO player_technology (player_universe_id,slug,tier,level) VALUES ("+user.memberId+",'colonial_expansion','basic',1) ON DUPLICATE KEY UPDATE level=GREATEST(level,1)");
  runIsolatedSql(user.project,"INSERT INTO fleet (player_universe_id,home_planet_id,status,mission,speed_ratio,cargo,reserved_fuel,orbit_fuel,orbit_duration_seconds,travel_seconds,dispatch_token) SELECT "+user.memberId+","+user.planetId+",'idle','idle',1,'{}',0,0,0,0,'' WHERE NOT EXISTS (SELECT 1 FROM fleet WHERE home_planet_id="+user.planetId+" AND status='idle')");
  runIsolatedSql(user.project,"INSERT INTO fleet_ship (fleet_id,slug,tier,quantity) SELECT id,'fighter','basic',3 FROM fleet WHERE home_planet_id="+user.planetId+" AND status='idle' ON DUPLICATE KEY UPDATE quantity=3");
  runIsolatedSql(user.project,"INSERT INTO planet_building (planet_id,slug,tier,level) VALUES ("+user.planetId+",'mine_metal','basic',2) ON DUPLICATE KEY UPDATE level=2");
  runIsolatedSql(user.project,"INSERT INTO queue_item (planet_id,player_universe_id,queue_type,item_slug,tier,item_level,quantity,duration_seconds,started_at,queued_at,dispatch_token,paid_costs) VALUES ("+user.planetId+","+user.memberId+",'building','mine_metal','basic',3,1,86400,UTC_TIMESTAMP(),UTC_TIMESTAMP(),'"+crypto.randomUUID()+"','{\"metal\":500}')");
  const name='Colonie-'+('Étoile'.repeat(10));
  runIsolatedSql(user.project,"UPDATE planet SET name='"+name+"' WHERE id="+user.planetId);
  return {...user,other,name};
}

test('Dernière planète : bilan consultable au toucher, abandon indisponible',async({browser},testInfo)=>{
  const context=await browser.newContext({viewport:{width:390,height:844},hasTouch:true,isMobile:true});const page=await context.newPage();
  try {
    const user=await enter(page);await page.goto('/planetes');
    await page.getByRole('link',{name:'Préparer l’abandon de '+user.name,exact:true}).tap();
    await expect(page.getByRole('heading',{name:'Abandon indisponible',exact:true})).toBeVisible();
    await expect(page.getByRole('alert')).toContainText('au moins une planète dans cet univers');
    await expect(page.getByRole('button',{name:/Abandonner définitivement/})).toHaveCount(0);
    expect(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1)).toBeTruthy();
    await page.screenshot({path:testInfo.outputPath('derniere-colonie-mobile.png'),fullPage:true,animations:'disabled'});
    await page.getByRole('link',{name:'Retour à la colonie',exact:true}).tap();await expect(page).toHaveURL(new RegExp('/planetes/'+user.planetId+'$'));
  } finally { await context.close(); }
});

for(const touch of [false,true]) test('Abandon confirmé : pertes, trace et contexte '+(touch?'au toucher':'au clavier'),async({browser},testInfo)=>{
  test.setTimeout(120000);
  const context=await browser.newContext({viewport:touch?{width:390,height:844}:{width:1440,height:1000},hasTouch:touch,isMobile:touch});const page=await context.newPage();
  try {
    const user=await prepare(page);await page.goto('/planetes/'+user.planetId+'/abandonner');
    await expect(page.getByRole('heading',{name:'Abandonner '+user.name,exact:true})).toBeVisible();
    await expect(page.getByRole('heading',{name:'Bilan des pertes',exact:true})).toBeVisible();
    const panel=page.locator('.colony-abandon-panel');
    await expect(page.locator('.resource-planet strong')).toBeVisible();
    const nameBox=await page.locator('.resource-planet strong').boundingBox(),planetBox=await page.locator('.resource-planet').boundingBox();
    expect(nameBox.x+nameBox.width).toBeLessThanOrEqual(planetBox.x+planetBox.width+1);
    await expect(page.locator('.resource-planet strong')).toHaveText(user.name);
    await expect(page.locator('.resource-planet strong')).toHaveAttribute('title',user.name);
    for(const theme of ['dark','light']) {
      await page.evaluate(value=>document.body.dataset.theme=value,theme);
      expect((await new AxeBuilder({page}).include('.colony-abandon-panel').analyze()).violations.filter(v=>['serious','critical'].includes(v.impact))).toEqual([]);
    }
    await page.evaluate(()=>document.body.dataset.theme='dark');
    expect(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1)).toBeTruthy();
    const input=page.getByLabel('Retapez le nom de la colonie pour confirmer',{exact:true});
    await input.fill('Nom incorrect');
    await page.getByRole('button',{name:'Abandonner définitivement '+user.name,exact:true}).click();
    await expect(page.getByRole('alert')).toContainText('Le nom saisi ne correspond pas');
    expect(queryIsolatedSql(user.project,'SELECT owner_id FROM planet WHERE id='+user.planetId)).toBe(String(user.memberId));
    await input.fill(user.name);
    const confirm=page.getByRole('button',{name:'Abandonner définitivement '+user.name,exact:true});
    if(!touch) {
      await confirm.focus();await expect(confirm).toBeFocused();
      expect(await confirm.evaluate(e=>e.matches(':focus-visible') && getComputedStyle(e).outlineStyle==='solid' && parseFloat(getComputedStyle(e).outlineWidth)>0)).toBeTruthy();
    }
    const body=await page.locator('.abandon-confirmation').evaluate(form=>Object.fromEntries(new FormData(form)));
    await page.evaluate(()=>window.scrollTo(0,0));
    await page.screenshot({path:testInfo.outputPath(touch?'abandon-mobile.png':'abandon-bureau.png'),fullPage:true,animations:'disabled'});
    if(touch) await confirm.tap();else await page.keyboard.press('Enter');
    await expect(page.getByRole('status').filter({hasText:'Colonie abandonnée.'})).toBeVisible();
    await expect(page).toHaveURL(new RegExp('/planetes#planet-'+user.other.id+'$'));
    expect(queryIsolatedSql(user.project,'SELECT owner_id IS NULL AND npc_planet=0 FROM planet WHERE id='+user.planetId)).toBe('1');
    expect(queryIsolatedSql(user.project,'SELECT COUNT(*) FROM planet_resource WHERE planet_id='+user.planetId)).toBe('0');
    expect(queryIsolatedSql(user.project,'SELECT COUNT(*) FROM queue_item WHERE planet_id='+user.planetId)).toBe('0');
    expect(queryIsolatedSql(user.project,"SELECT COUNT(*) FROM admin_audit_log WHERE action='player_colony_abandoned' AND target_id="+user.planetId)).toBe('1');
    expect(await page.locator('#sidebar-colony').inputValue()).toBe(String(user.other.id));
    const replay=await page.evaluate(async({id,body})=>(await fetch('/planetes/'+id+'/abandonner',{method:'POST',body:new URLSearchParams(body)})).status,{id:user.planetId,body});
    expect(replay).toBe(404);
    expect(queryIsolatedSql(user.project,"SELECT COUNT(*) FROM admin_audit_log WHERE action='player_colony_abandoned' AND target_id="+user.planetId)).toBe('1');
  } finally { await context.close(); }
});

test('Abandon : sonde invisible et dépendance externe bloquent sans divulguer de flotte',async({browser})=>{
  test.setTimeout(120000);
  const context=await browser.newContext(),peerContext=await browser.newContext();
  const page=await context.newPage(),peerPage=await peerContext.newPage();
  try {
    const user=await prepare(page),peer=await enter(peerPage,'HiddenProbe');
    runIsolatedSql(user.project,"INSERT INTO fleet (player_universe_id,home_planet_id,destination_id,status,mission,speed_ratio,cargo,reserved_fuel,orbit_fuel,orbit_duration_seconds,travel_seconds,departure_at,arrival_at,dispatch_token) VALUES ("+peer.memberId+","+peer.planetId+","+user.planetId+",'traveling','spy',1,'{}',0,0,0,3600,UTC_TIMESTAMP(),DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 HOUR),'"+crypto.randomUUID()+"')");
    await page.goto('/planetes/'+user.planetId+'/abandonner');
    await expect(page.getByRole('alert')).toContainText('Une opération en cours');
    await expect(page.locator('.colony-abandon-panel')).not.toContainText(peer.username);
    await expect(page.locator('.colony-abandon-panel')).not.toContainText(peer.name);
    await expect(page.getByRole('button',{name:/Abandonner définitivement/})).toHaveCount(0);
    runIsolatedSql(user.project,"UPDATE fleet SET status='completed' WHERE player_universe_id="+peer.memberId+" AND mission='spy'");
    runIsolatedSql(user.project,"INSERT INTO queue_item (planet_id,player_universe_id,queue_type,item_slug,tier,item_level,quantity,duration_seconds,started_at,queued_at,dispatch_token,paid_costs) VALUES ("+user.planetId+","+user.memberId+",'research','tactical_intelligence','basic',1,1,86400,UTC_TIMESTAMP(),UTC_TIMESTAMP(),'"+crypto.randomUUID()+"','{\"metal\":500}')");
    runIsolatedSql(user.project,"INSERT INTO queue_item (planet_id,player_universe_id,queue_type,item_slug,tier,item_level,quantity,duration_seconds,queued_at,dispatch_token,depends_on_id,paid_costs) SELECT "+user.other.id+","+user.memberId+",'research','tactical_intelligence','basic',2,1,86400,UTC_TIMESTAMP(),'"+crypto.randomUUID()+"',id,'{\"metal\":500}' FROM queue_item WHERE planet_id="+user.planetId+" AND queue_type='research' LIMIT 1");
    await page.reload();await expect(page.getByRole('heading',{name:'Commandes dépendantes d’autres colonies',exact:true})).toBeVisible();
    await expect(page.getByRole('link',{name:/Colonie témoin :/})).toBeVisible();
    await expect(page.getByRole('button',{name:/Abandonner définitivement/})).toHaveCount(0);
  } finally { await context.close();await peerContext.close(); }
});


test('Deux abandons simultanés conservent une planète et une seule trace',async({browser})=>{
  test.setTimeout(120000);
  const first=await browser.newContext(),second=await browser.newContext();
  const page=await first.newPage(),otherPage=await second.newPage();
  try {
    const user=await prepare(page);
    await otherPage.goto('/connexion');await otherPage.getByLabel('Adresse email').fill(user.email);
    await otherPage.getByLabel('Mot de passe',{exact:true}).fill('Browser-test-2026!');
    await otherPage.getByRole('button',{name:/Connexion/}).click();await expect(otherPage).not.toHaveURL(/connexion/);
    await page.goto('/planetes/'+user.planetId+'/abandonner');
    await otherPage.goto('/planetes/'+user.other.id+'/abandonner');
    await expect(page.locator('.colony-abandon-panel')).toContainText('aucun nouveau kit de ressources');
    const submissions=await Promise.all([page,otherPage].map(async current=>{
      const form=current.locator('.abandon-confirmation');
      return form.evaluate(element=>{
        const body=Object.fromEntries(new FormData(element));
        body.name=element.closest('main').querySelector('#abandon-name-help strong').textContent.trim();
        return {url:element.action,body};
      });
    }));
    const results=await Promise.all([page,otherPage].map((current,index)=>current.evaluate(async submission=>{
      const response=await fetch(submission.url,{method:'POST',body:new URLSearchParams(submission.body)});
      return response.status;
    },submissions[index])));
    expect(results).toEqual([200,200]);
    expect(queryIsolatedSql(user.project,'SELECT COUNT(*) FROM planet WHERE owner_id='+user.memberId)).toBe('1');
    expect(queryIsolatedSql(user.project,"SELECT COUNT(*) FROM admin_audit_log WHERE action='player_colony_abandoned'")).toBe('1');
  } finally { await first.close();await second.close(); }
});
