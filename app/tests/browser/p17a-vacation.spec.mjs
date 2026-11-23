import AxeBuilder from '@axe-core/playwright';
import { test, expect } from './support/isolated-test.mjs';
import { queryIsolatedSql, runIsolatedSql } from './support/game-fixtures.mjs';

test('P17 A : consulter les restrictions et le refus natif sans activer les vacances', async ({ page, browser, baseURL }, info) => {
  test.setTimeout(120000);
  const id = crypto.randomUUID().slice(0,8);
  const email = 'p17a-' + id + '@example.test';
  const password = crypto.randomUUID() + 'aA1!';
  await page.goto('/inscription');
  await page.getByLabel('Adresse email').fill(email);
  await page.getByLabel('Nom de commandant').fill('Horizon-' + id);
  await page.getByLabel('Mot de passe (12 caractères minimum)',{exact:true}).fill(password);
  await page.getByLabel('Confirmer le mot de passe').fill(password);
  await page.getByRole('button',{name:'Créer mon compte'}).click();
  await page.getByLabel('Adresse email').fill(email);
  await page.getByLabel('Mot de passe',{exact:true}).fill(password);
  await page.getByRole('button',{name:/Connexion/}).click();
  await page.locator('.universe-card').filter({has:page.getByRole('heading',{name:/Omega/})}).getByRole('button').click();
  await expect(page).toHaveURL(/planetes\/\d+$/);
  const origin = Number(page.url().split('/').at(-1));
  expect(Number.isSafeInteger(origin) && origin > 0).toBe(true);
  const project = process.env.BROWSER_ROUTER;
  const vacationCount = () => queryIsolatedSql(project,'SELECT COUNT(*) FROM vacation_period');
  expect(vacationCount()).toBe('0');
  const attempted = [];
  const preventMutation = async route => {
    if (route.request().method()==='GET') return route.continue();
    attempted.push(route.request().method());
    return route.abort();
  };
  await page.route('**/profil/preferences/vacances/**',preventMutation);
  for (const blocked of [false,true]) {
    if (blocked) {
      // Même cause que le test Controller : un retour synthétique déjà en cours, sans départ de flotte ni worker.
      runIsolatedSql(project,"INSERT INTO fleet (player_universe_id,home_planet_id,status,mission,speed_ratio,cargo,reserved_fuel,orbit_fuel,orbit_duration_seconds,travel_seconds,dispatch_token) SELECT owner_id,id,'returning','transport',1,'{}',0,0,0,3600,'p17a-"+id+"' FROM planet WHERE id="+origin);
    }
    for(const theme of ['dark','light']){
      runIsolatedSql(project,`UPDATE player SET preferences=JSON_SET(CASE WHEN JSON_TYPE(preferences)='OBJECT' THEN preferences ELSE JSON_OBJECT() END,'$.theme','${theme}') WHERE email='${email}'`);
      for(const viewport of [{width:1920,height:1080},{width:390,height:844}]){
        await page.setViewportSize(viewport);
        const response=await page.goto('/profil/vacances');
        expect(response.status()).toBe(200);
        await expect(page.locator('body')).toHaveAttribute('data-theme',theme);
        await expect(page.getByRole('heading',{name:'Mode vacances',exact:true})).toBeVisible();
        const status = page.locator('.vacation-inactive-notice');
        const universe = page.getByRole('heading',{name:'Univers concerné',exact:true});
        await expect(status).toContainText('Mode vacances inactif');
        await expect(universe.locator('xpath=following-sibling::*[1]')).toContainText('Omega');
        await expect(page.getByRole('heading',{name:'Restrictions avant activation',exact:true})).toBeVisible();
        const rules=page.locator('#vacation-rules');
        await expect(rules.getByRole('listitem')).toHaveCount(5);
        for(const detail of ['Production et files suspendues','48 heures minimum','90 jours maximum','15 jours','Toutes vos flottes','inactivité']){
          await expect(rules).toContainText(detail);
        }
        const form=page.locator('form[action="/profil/preferences/vacances/activer"]');
        const confirmation=form.getByRole('checkbox');
        const submit=form.getByRole('button',{name:'Activer le mode vacances dans cet univers',exact:true});
        const safeBack=form.getByRole('link',{name:'Retour aux préférences',exact:true});
        await expect(safeBack).toHaveAttribute('href','/profil/preferences');
        if(blocked){
          await expect(page.locator('#vacation-start-blocker')).toContainText('Activation indisponible');
          await expect(page.locator('#vacation-start-blocker')).toContainText('Toutes vos flottes doivent être rentrées');
          await expect(submit).toHaveAttribute('aria-describedby','vacation-start-blocker');
          await expect(submit).toBeDisabled();
          await expect(confirmation).toHaveCount(0);
        }else{
          await expect(confirmation).not.toBeChecked();
          await expect(confirmation).toHaveAttribute('required','');
          await expect(submit).toBeEnabled();
          await confirmation.focus();
          await page.keyboard.press('Tab');
          await expect(submit).toBeFocused();
          await page.keyboard.press('Tab');
          await expect(safeBack).toBeFocused();
        }
        await expect.poll(()=>page.evaluate(()=>document.documentElement.scrollWidth)).toBeLessThanOrEqual(viewport.width);
        if(viewport.width===390){
          expect((await new AxeBuilder({page}).include('#vacances').analyze()).violations).toEqual([]);
        }
        await page.evaluate(()=>{document.activeElement?.blur();window.scrollTo({top:0,behavior:'instant'});});
        const state=blocked?'refuse':'inactif';
        await page.screenshot({path:info.outputPath(`P17A-${state}-${theme}-${viewport.width}.png`),animations:'disabled',scale:'css'});
        if(viewport.width===390){
          await form.evaluate(el=>el.scrollIntoView({block:'center',behavior:'instant'}));
          await page.screenshot({path:info.outputPath(`P17A-${state}-${theme}-390-commandes.png`),animations:'disabled',scale:'css'});
        }
        expect(vacationCount()).toBe('0');
      }
    }
  }
  const native = await browser.newContext({baseURL,storageState:await page.context().storageState(),javaScriptEnabled:false});
  try {
    const tab=await native.newPage();
    await tab.route('**/profil/preferences/vacances/**',preventMutation);
    await tab.goto('/profil/vacances');
    await expect(tab.locator('#vacation-start-blocker')).toContainText('Toutes vos flottes doivent être rentrées');
    await tab.getByRole('link',{name:'Retour aux préférences',exact:true}).press('Enter');
    await expect(tab).toHaveURL(/profil\/preferences$/);
  } finally { await native.close(); }
  expect(attempted).toEqual([]);
  expect(vacationCount()).toBe('0');
});
