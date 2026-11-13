import { test, expect } from './support/isolated-test.mjs';
import AxeBuilder from '@axe-core/playwright';
import { runIsolatedSql } from './support/game-fixtures.mjs';

test('Parcours : comparaison, économie, rubriques et aperçus réels', async ({page,browser},testInfo) => {
  test.setTimeout(180000);
  const id=crypto.randomUUID().slice(0,8),email=`parcours-${id}@example.test`,password='Browser-test-2026!';
  await page.goto('/inscription');
  await page.getByLabel('Adresse email').fill(email);
  await page.getByLabel('Nom de commandant').fill('Apercu-'+id);
  await page.getByLabel('Mot de passe (12 caractères minimum)',{exact:true}).fill(password);
  await page.getByLabel('Confirmer le mot de passe').fill(password);
  await page.getByRole('button',{name:'Créer mon compte'}).click();
  await page.getByLabel('Adresse email').fill(email);
  await page.getByLabel('Mot de passe',{exact:true}).fill(password);
  await page.getByRole('button',{name:/Connexion/}).click();
  await page.locator('article.universe-card').first().getByRole('button',{name:/Créer mon empire/}).click();
  await expect(page).toHaveURL(/planetes\/\d+$/);
  const home=page.url(), accessResults=[];
  await page.goto('/guildes/projets');
  await page.getByRole('link',{name:'Rejoindre une guilde',exact:true}).click();
  await expect(page).toHaveURL(/diplomatie\?tab=join/);
  await page.goto(home);
  await expect(page.getByRole('heading',{name:/Bilan économique/})).toBeVisible();
  await expect(page.getByRole('link',{name:/Explorer la galaxie/})).toHaveAttribute('href','/carte-v2');
  await page.setViewportSize({width:1440,height:1000});
  if(process.env.GENERATE_PUBLIC_PREVIEWS==='1') {
    await page.screenshot({path:'app/public/art/ui-preview-colony.png'});
    await page.goto('/carte-v2');
    await expect(page.locator('.gv2-canvas--galaxy')).toBeVisible();
    await page.screenshot({path:'app/public/art/ui-preview-galaxy.png'});
  }
  await page.goto(home+'?section=buildings');
  const build=page.locator('#item-mine_metal-basic').getByRole('button',{name:/Améliorer au niveau/});
  await build.click(); await build.click();
  const queue=page.locator('[data-queue-type="building"]');
  await queue.locator('summary').click();
  await queue.getByRole('link',{name:/Préparer l’annulation/}).first().click();
  await expect(page.getByRole('heading',{name:'Commandes concernées'})).toBeVisible();
  await expect(page.locator('main')).toContainText('Dépendance annulée');
  await page.getByRole('button',{name:/Confirmer l’annulation/}).click();
  for(const theme of ['dark','light']) {
    runIsolatedSql(process.env.BROWSER_ROUTER,`UPDATE player SET preferences=JSON_SET(CASE WHEN JSON_TYPE(preferences)='OBJECT' THEN preferences ELSE JSON_OBJECT() END,'$.theme','${theme}') WHERE email='${email}'`);
    for(const width of [1440,390]) {
      await page.setViewportSize({width,height:900});
      for(const path of [new URL(home).pathname,'/planetes?view=table','/flottes','/boosts','/evenements','/profil','/profil/preferences','/diplomatie']) {
        await page.goto(path);
        await expect(page.locator('body')).toHaveAttribute('data-theme',theme);
        await expect(page.locator('main h1')).toBeVisible();
        expect(await page.evaluate(()=>document.documentElement.scrollWidth)).toBeLessThanOrEqual(width);
        if(path==='/planetes?view=table') {
          await expect(page.locator('.colony-comparison tbody tr')).toHaveCount(1);
          await expect(page.locator('.colony-comparison tbody td').first()).toContainText('/');
          await page.locator('.colony-comparison a').first().focus();
          await page.keyboard.press('Enter');
          await expect(page).toHaveURL(home);
          await page.goBack();
          await expect(page).toHaveURL(/view=table/);
        }
        if(path==='/flottes') {
          await expect(page.locator('.fleet-page-layout--no-idle')).toBeVisible();
          await page.goto('/flottes?mission=spy');
          await expect(page.locator('.fleet-empty-state').getByRole('link',{name:/chantier/i})).toHaveAttribute('href', /#item-probe-basic/);
          await page.goto(path);
        }
        if(path==='/profil/preferences') {
          await expect(page.locator('#vacances-info')).toBeVisible();
          await expect(page.getByRole('navigation',{name:'Compte et préférences'}).getByRole('link',{name:'Vacances',exact:true})).toHaveAttribute('href','/profil/preferences#vacances-info');
        }
        const audit=await new AxeBuilder({page}).include('main').analyze();
        if(audit.violations.length) accessResults.push({path,theme,width,violations:audit.violations});
      }
      await page.goto(home);
      await page.screenshot({path:testInfo.outputPath(`overview-${theme}-${width}.png`),fullPage:true});
    }
  }
  expect(accessResults).toEqual([]);
  const anonymous=await browser.newContext({viewport:{width:390,height:900}});
  try {
    const publicPage=await anonymous.newPage(); await publicPage.goto(new URL('/',home).href);
    await expect(publicPage.locator('meta[name="description"]')).toHaveAttribute('content','Genesis Reborn, jeu de stratégie spatiale par navigateur : bâtis ta colonie, explore des systèmes inconnus et forge ton empire.');
    await expect(publicPage.locator('meta[property="og:image"]')).toHaveAttribute('content',/^https?:\/\//);
    await expect(publicPage.locator('.public-previews img')).toHaveCount(2);
    await expect(publicPage.getByRole('heading',{name:'Développez votre empire',exact:true})).toBeVisible();
    await expect(publicPage.getByRole('heading',{name:'Choisissez votre univers',exact:true})).toHaveCount(0);
    expect(await publicPage.evaluate(()=>document.documentElement.scrollWidth)).toBeLessThanOrEqual(390);
    expect((await new AxeBuilder({page:publicPage}).include('main').analyze()).violations).toEqual([]);
  } finally {await anonymous.close();}
  const context=await browser.newContext({storageState:await page.context().storageState(),javaScriptEnabled:false,viewport:{width:390,height:900}});
  try {
    const noScript=await context.newPage();
    await noScript.goto(new URL('/planetes?view=table',home).href);
    await expect(noScript.locator('.colony-comparison')).toBeVisible();
    await noScript.goto(new URL('/wiki?q=colonisation',home).href);
    await expect(noScript.locator('.wiki-chapter').first()).toContainText(/colonisation/i);
  } finally {await context.close();}
});
