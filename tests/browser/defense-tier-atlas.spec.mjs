import { test, expect } from './support/isolated-test.mjs';

test('Les défenses affichent les trois illustrations de rang sur desktop et mobile', async ({ page }) => {
  test.setTimeout(180000);
  const id=crypto.randomUUID().slice(0,8),email='defense-'+id+'@example.test',password='Browser-test-2026!';
  await page.goto('/inscription');
  await page.getByLabel('Adresse email').fill(email);
  await page.getByLabel('Nom de commandant').fill('Defense-'+id);
  await page.getByLabel('Mot de passe (12 caractères minimum)',{exact:true}).fill(password);
  await page.getByLabel('Confirmer le mot de passe').fill(password);
  await page.getByRole('button',{name:'Créer mon compte'}).click();
  await expect(page).toHaveURL(/connexion/);
  await page.getByLabel('Adresse email').fill(email);
  await page.getByLabel('Mot de passe',{exact:true}).fill(password);
  await page.getByRole('button',{name:/Connexion/}).click();
  await page.locator('article.universe-card').filter({has:page.getByRole('heading',{name:/Turbo/})}).getByRole('button').click();
  await expect(page).toHaveURL(/planetes\/\d+$/);
  const planetPath=new URL(page.url()).pathname;
  await page.goto('/profil/preferences');
  await page.getByLabel('Afficher les bâtiments, technologies, vaisseaux et défenses verrouillés').check();
  await page.getByRole('button',{name:'Enregistrer les paramètres'}).click();
  await page.setViewportSize({width:1440,height:1000});
  await page.goto(planetPath+'?section=defenses');
  for(const [tier,position] of [['basic','0% 50%'],['intermediate','50% 50%'],['superior','100% 50%']]){
    const art=page.locator('#item-ground_turret-'+tier+' .generated-defense-art');
    await expect(art).toBeVisible();
    await expect(art).toHaveCSS('background-image',/defenses-atlas\.png/);
    await expect(art).toHaveCSS('background-position',position);
  }
  await expect.poll(()=>page.evaluate(async()=>{const i=new Image();i.src='/art/defenses-atlas.png';try{await i.decode();return i.naturalWidth>0}catch{return false}})).toBe(true);
  await expect.poll(()=>page.evaluate(()=>document.documentElement.scrollWidth)).toBeLessThanOrEqual(1440);
  await page.screenshot({path:'docs/validation/defense-tiers-atlas-desktop.png',fullPage:true});
  await page.setViewportSize({width:390,height:844});
  await page.reload();
  await expect.poll(()=>page.evaluate(()=>document.documentElement.scrollWidth)).toBeLessThanOrEqual(390);
  for(const tier of ['basic','intermediate','superior'])await expect(page.locator('#item-ground_turret-'+tier+' .generated-defense-art')).toBeVisible();
  await page.screenshot({path:'docs/validation/defense-tiers-atlas-mobile.png',fullPage:true});
});
