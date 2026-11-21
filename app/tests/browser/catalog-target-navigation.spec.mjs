import {test,expect} from './support/isolated-test.mjs';
import {runIsolatedSql} from './support/game-fixtures.mjs';

test('Liens ciblés : cadre temporaire, animations réduites et événements fixes',async({page})=>{
 test.setTimeout(150000);
 const id=crypto.randomUUID().slice(0,8),email=`target-${id}@example.test`,password='Browser-test-2026!';
 await page.goto('/inscription');await page.getByLabel('Adresse email').fill(email);await page.getByLabel('Nom de commandant').fill(`Target-${id}`);await page.getByLabel('Mot de passe (12 caractères minimum)',{exact:true}).fill(password);await page.getByLabel('Confirmer le mot de passe').fill(password);await page.getByRole('button',{name:'Créer mon compte'}).click();await expect(page).toHaveURL(/connexion/);
 await page.getByLabel('Adresse email').fill(email);await page.getByLabel('Mot de passe',{exact:true}).fill(password);await page.getByRole('button',{name:/Connexion/}).click();await page.locator('.universe-card').first().getByRole('button').click();await expect(page).toHaveURL(/planetes\/\d+$/);
 const url=new URL(page.url()).pathname;
 runIsolatedSql(process.env.BROWSER_ROUTER,`INSERT INTO game_event(name,description,type,status,starts_at,ends_at,parameters,visible_to_players) VALUES('Événement de navigation','','production_bonus','active',UTC_TIMESTAMP()-INTERVAL 1 HOUR,UTC_TIMESTAMP()+INTERVAL 1 HOUR,'{"percentage":0}',1)`);
 await page.setViewportSize({width:1440,height:1000});
 for(const [section,slug] of [['buildings','storage_metal'],['technologies','weapons_engineering'],['ships','probe'],['defenses','ground_turret_anti_light']]){
  await page.goto(`${url}?section=${section}&showLockedItems=1&focusItem=1#item-${slug}-basic`);
  const card=page.locator(`[data-building-catalog-target="family"][data-slug="${slug}"]`);await expect(card).toHaveClass(/catalog-card--targeted/);await expect(card).toHaveCSS('outline-width','3px');
  await expect.poll(async()=>{const box=await card.boundingBox(),bar=await page.locator('.resource-event-stack').boundingBox();return box.y>=bar.y+bar.height;}).toBe(true);
  const stack=page.locator('.resource-event-stack');await expect(stack).toHaveCSS('position','sticky');const event=page.locator('.topbar > .event-drawer-wrap');const before=await event.boundingBox();await page.evaluate(()=>window.scrollBy({top:200,behavior:'instant'}));await expect.poll(async()=>Math.abs((await event.boundingBox()).y-before.y)).toBeLessThan(2);
  await expect(card).not.toHaveClass(/catalog-card--targeted/,{timeout:7000});
 }
 await page.emulateMedia({reducedMotion:'reduce'});await page.goto(`${url}?section=buildings&focusItem=1#item-mine_metal-basic`);const reduced=page.locator('[data-building-catalog-target="family"][data-slug="mine_metal"]');await expect(reduced).toHaveClass(/catalog-card--targeted/);await expect(reduced).toHaveCSS('animation-name','none');
 await page.goto('/profil/preferences');await page.getByLabel('Thème de l’interface').selectOption('light');await page.getByRole('button',{name:'Enregistrer les paramètres'}).click();await page.goto(`${url}?section=buildings&focusItem=1#item-mine_metal-basic`);await expect(page.locator('body')).toHaveAttribute('data-theme','light');await expect(reduced).toHaveClass(/catalog-card--targeted/);await expect(reduced).toHaveCSS('animation-name','none');
 const planetId=Number(url.split('/').at(-1));
 const setMetalStock = amount => runIsolatedSql(process.env.BROWSER_ROUTER,
  'UPDATE planet_resource SET current_stock='+amount+' WHERE planet_id='+planetId+" AND resource_type='metal'");
 const metalLink=page.locator('[data-live-region="overview-attention"]').getByRole('link',{name:'Métal',exact:true});
 // Le lien est une alerte de stockage plein, et non un invariant d'une inscription.
 setMetalStock(5000);
 await page.goto(url);
 await expect(page.locator('body')).toHaveAttribute('data-theme','light');
 await expect(metalLink).toHaveCount(0);
 setMetalStock(10000);
 await page.reload();
 await expect(page.locator('body')).toHaveAttribute('data-theme','light');
 await expect(metalLink).toHaveAttribute('href',url+'?section=buildings#item-storage_metal-basic');
 await metalLink.click();
 await expect(page.locator('[data-building-catalog-target="family"][data-slug="storage_metal"]')).toHaveClass(/catalog-card--targeted/);
});
