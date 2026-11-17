import { test, expect } from './support/isolated-test.mjs';
import { execFileSync } from 'node:child_process';
import AxeBuilder from '@axe-core/playwright';
import { queryIsolatedSql, runIsolatedSql } from './support/game-fixtures.mjs';

async function admin(page) {
  const suffix=crypto.randomUUID().slice(0,8), email=`admin-pages-${suffix}@example.test`, password=crypto.randomUUID()+'aA1!';
  await page.goto('/inscription');await page.getByLabel('Adresse email').fill(email);await page.getByLabel('Nom de commandant').fill(`AdminPages-${suffix}`);
  await page.getByLabel('Mot de passe (12 caractères minimum)',{exact:true}).fill(password);await page.getByLabel('Confirmer le mot de passe').fill(password);
  await page.getByRole('button',{name:'Créer mon compte'}).click();await expect(page).toHaveURL(/connexion/);
  execFileSync('docker',['compose','-p',process.env.BROWSER_ROUTER,'--env-file','.env.local','exec','-T','app','php','bin/console','app:admin:grant',email],{stdio:['ignore','pipe','pipe']});
  await page.getByLabel('Adresse email').fill(email);await page.getByLabel('Mot de passe',{exact:true}).fill(password);await page.getByRole('button',{name:/Connexion/}).click();
  const universe=Number(queryIsolatedSql(process.env.BROWSER_ROUTER,"SELECT id FROM universe WHERE slug='omega-1'"));
  return {email,password,universe};
}

test('Pages administratives : liens directs, thèmes, clavier, ancres et rendu responsive',async({page},testInfo)=>{
  test.setTimeout(180000);
  const {email,universe}=await admin(page),base=`/admin/universes/${universe}`;
  const routes=['/admin/univers','/admin/universes/nouveau',`${base}/gerer`,`${base}/catalogue/buildings`,`${base}/catalogue/technologies`,`${base}/catalogue/ships`,`${base}/catalogue/defenses`,`${base}/catalogue/ships/fighter`,'/admin/events','/admin/events/nouveau','/admin/projets-guilde','/admin/projets-guilde/nouveau',`/admin/rotations?universe=${universe}`,`/admin/rotations/nouveau?universe=${universe}`,`/admin/rotations/${universe}/exploration/modifier`];
  for(const theme of ['dark','light']) {
    runIsolatedSql(process.env.BROWSER_ROUTER,`UPDATE player SET preferences=JSON_SET(CASE WHEN JSON_TYPE(preferences)='OBJECT' THEN preferences ELSE JSON_OBJECT() END,'$.theme','${theme}') WHERE email='${email}'`);
    for(const width of [1440,390]) {
      await page.setViewportSize({width,height:900});
      for(const [index,route] of routes.entries()) {
        expect((await page.goto(route)).status()).toBe(200);
        await expect(page.locator('body')).toHaveAttribute('data-theme',theme);
        await expect(page.locator('h1')).toHaveCount(1);
        await expect(page.locator('.admin-primary-link[aria-current="location"]')).toHaveCount(1);
        await expect(page.locator('.page-contents')).toHaveCount(0);
        await expect.poll(()=>page.evaluate(()=>document.documentElement.scrollWidth)).toBeLessThanOrEqual(width);
        if(width===390 && [1,2,7,9,11,14].includes(index)) {
          const result=await new AxeBuilder({page}).include('#admin-main').withTags(['wcag2a','wcag2aa','wcag21a','wcag21aa','wcag22aa']).analyze();
          expect(result.violations).toEqual([]);
          await page.screenshot({path:testInfo.outputPath(`admin-${index}-${theme}-${width}.png`),fullPage:true,animations:'disabled'});
        }
      }
    }
  }
  await page.goto(`${base}/catalogue#ships-fighter`);await expect(page).toHaveURL(new RegExp(`${base}/catalogue/ships/fighter#ships-fighter$`));
  await page.goto('/admin/univers#admin-universe-'+universe);await expect(page).toHaveURL(new RegExp(`${base}/gerer#admin-universe-${universe}$`));
  const all=page.getByRole('navigation',{name:'Pages de cette rubrique'}).getByRole('link',{name:'Liste des univers',exact:true});
  await all.focus();await expect(all).toBeFocused();await page.keyboard.press('Enter');await expect(page).toHaveURL(/\/admin\/univers$/);
  await page.goBack();await expect(page).toHaveURL(new RegExp(`${base}/gerer#admin-universe-${universe}$`));await page.reload();await expect(page.getByRole('heading',{level:1})).toContainText('Gérer');
});

test('Modèles de rotations : édition périmée, univers isolés et création native sans JS',async({page,browser,baseURL})=>{
  test.setTimeout(120000);
  const {email,password,universe}=await admin(page),project=process.env.BROWSER_ROUTER;
  await page.goto(`/admin/rotations/${universe}/exploration/modifier`);
  const stale=await page.context().newPage();await stale.goto(page.url());
  await page.getByLabel('Nom de la rotation',{exact:true}).fill('Rotation modifiée par navigation');
  await page.getByLabel('Motif de l’enregistrement',{exact:true}).fill('Édition vérifiée');
  await page.getByRole('button',{name:'Enregistrer le modèle',exact:true}).click();
  await expect(page.getByRole('status')).toContainText('Modèle modifié');
  await stale.getByLabel('Nom de la rotation',{exact:true}).fill('Saisie périmée conservée');
  await stale.getByLabel('Motif de l’enregistrement',{exact:true}).fill('Ancienne fiche');
  await stale.getByRole('button',{name:'Enregistrer le modèle',exact:true}).click();
  await expect(stale.getByRole('alert')).toContainText('Ce modèle a changé');
  await expect(stale.getByLabel('Nom de la rotation',{exact:true})).toHaveValue('Saisie périmée conservée');
  const context=await browser.newContext({baseURL,javaScriptEnabled:false,viewport:{width:390,height:900}}),native=await context.newPage();
  await native.goto('/connexion');await native.getByLabel('Adresse email').fill(email);await native.getByLabel('Mot de passe',{exact:true}).fill(password);await native.getByRole('button',{name:/Connexion/}).click();
  await native.goto(`/admin/rotations/nouveau?universe=${universe}`);await native.getByLabel('Nom de la rotation').fill('Rotation native');
  await native.getByLabel('Objectif suivi').selectOption('exploration');await native.getByLabel('Format de la rotation').selectOption('individual');
  await native.getByLabel('Attribution du boost').selectOption('all_contributors');await native.getByLabel('Motif de l’enregistrement').fill('Création sans JavaScript');
  await native.getByRole('button',{name:'Créer la rotation hebdomadaire'}).click();await expect(native.getByRole('status')).toContainText('Modèle créé');
  const definitions=JSON.parse(queryIsolatedSql(project,`SELECT JSON_EXTRACT(game_settings,'$.weekly_rotations') FROM universe WHERE id=${universe}`));
  const created=definitions.find(item=>item.name==='Rotation native');expect(created).toBeTruthy();
  const other=Number(queryIsolatedSql(project,"SELECT id FROM universe WHERE slug='turbo-1'"));
  expect((await native.goto(`/admin/rotations/${other}/${created.key}/modifier`)).status()).toBe(404);
  await native.goto('/admin/projets-guilde');await native.getByRole('link',{name:'Créer un modèle de projet',exact:true}).click();await expect(native.getByLabel('Nom du projet',{exact:true})).toBeVisible();
  await context.close();await stale.close();
});
