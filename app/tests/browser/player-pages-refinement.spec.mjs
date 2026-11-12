import { test, expect } from './support/isolated-test.mjs';
import AxeBuilder from '@axe-core/playwright';
import { runIsolatedSql, queryIsolatedSql } from './support/game-fixtures.mjs';

test('Pages validées : diplomatie, profil et inventaire, thèmes, clavier et écrans', async ({ page }, testInfo) => {
  test.setTimeout(150000);
  const suffix = crypto.randomUUID().slice(0, 8);
  const email = `refonte-${suffix}@example.test`, password = 'Browser-test-2026!';
  await page.goto('/inscription');
  await page.getByLabel('Adresse email').fill(email);
  await page.getByLabel('Nom de commandant').fill('Refonte-' + suffix);
  await page.getByLabel('Mot de passe (12 caractères minimum)', { exact: true }).fill(password);
  await page.getByLabel('Confirmer le mot de passe').fill(password);
  await page.getByRole('button', { name: 'Créer mon compte' }).click();
  await expect(page).toHaveURL(/connexion/);
  await page.getByLabel('Adresse email').fill(email);
  await page.getByLabel('Mot de passe', { exact: true }).fill(password);
  await page.getByRole('button', { name: /Connexion/ }).click();
  await page.locator('article.universe-card').first().getByRole('button').click();
  await expect(page).toHaveURL(/planetes\/\d+$/);
  for (const theme of ['dark', 'light']) {
    runIsolatedSql(process.env.BROWSER_ROUTER, `UPDATE player SET preferences=JSON_SET(CASE WHEN JSON_TYPE(preferences)='OBJECT' THEN preferences ELSE JSON_OBJECT() END,'$.theme','${theme}') WHERE email='${email}'`);
    for (const [route, heading, name] of [['/diplomatie','Diplomatie','diplomatie'],['/profil','Mon profil','profil'],['/boosts','Inventaire','inventaire']]) {
      await page.goto(route);
      await expect(page.getByRole('heading',{level:1,name:heading,exact:true})).toBeVisible();
      await expect(page.locator('body')).toHaveAttribute('data-theme',theme);
      for (const width of [1440, 834, 768, 390, 320]) {
        await page.setViewportSize({width,height:900});
        expect(await page.evaluate(()=>document.documentElement.scrollWidth)).toBeLessThanOrEqual(width);
        if (width===1440 || width===390) await page.screenshot({path:testInfo.outputPath(`${name}-${theme}-${width}.png`),fullPage:true,animations:'disabled'});
      }
      expect((await new AxeBuilder({page}).include('.page-refinement').withTags(['wcag2a','wcag2aa','wcag21aa']).analyze()).violations).toEqual([]);
    }
  }
  await page.goto('/profil');
  const passwordSummary=page.getByText('Changer de mot de passe — facultatif',{exact:true});
  await passwordSummary.focus();
  await page.keyboard.press('Enter');
  await expect(page.getByRole('group',{name:'Changer de mot de passe',exact:true})).toBeVisible();
  expect(await passwordSummary.evaluate(el=>getComputedStyle(el).outlineStyle)).not.toBe('none');
  await page.getByText('Supprimer mon compte',{exact:true}).focus();
  await page.keyboard.press('Space');
  await expect(page.getByRole('button',{name:'Supprimer définitivement mon compte'})).toBeVisible();
  await page.goto('/diplomatie');
  await page.getByLabel('Nom de votre nouvelle guilde').fill('Refonte-'+suffix);
  await page.getByLabel('Tag de la guilde',{exact:true}).fill(suffix.slice(0,5));
  await page.getByRole('button',{name:'Fonder une guilde'}).click();
  const manage=page.getByRole('link',{name:'Gérer la guilde',exact:true});
  await manage.focus();
  await page.keyboard.press('Enter');
  await expect(page).toHaveURL(/#guild-management$/);
  await expect(page.locator('#guild-management')).toBeFocused();
  await expect(page.getByRole('heading',{name:'Grades et permissions'})).toBeVisible();
  await expect(page.getByText('Consulter cet aperçu ne marque pas le canal comme lu.')).toBeVisible();
  expect((await new AxeBuilder({page}).include('.page-refinement').withTags(['wcag2a','wcag2aa','wcag21aa']).analyze()).violations).toEqual([]);
  await page.evaluate(()=>{document.activeElement?.blur();window.scrollTo({top:0,behavior:'instant'});});
  await page.screenshot({path:testInfo.outputPath('guilde-light-320.png'),fullPage:true});
  runIsolatedSql(process.env.BROWSER_ROUTER, `UPDATE universe u JOIN player_universe pu ON pu.universe_id=u.id JOIN player p ON p.id=pu.player_id SET u.game_settings=JSON_MERGE_PATCH(u.game_settings,'{"features":{"guilds_enabled":false}}') WHERE p.email='${email}'`);
  expect(Number(queryIsolatedSql(process.env.BROWSER_ROUTER, `SELECT COUNT(*) FROM universe u JOIN player_universe pu ON pu.universe_id=u.id JOIN player p ON p.id=pu.player_id WHERE p.email='${email}' AND JSON_UNQUOTE(JSON_EXTRACT(u.game_settings,'$.features.guilds_enabled'))='false'`))).toBe(1);
  await page.goto('/diplomatie');
  await expect(page.getByText('Les guildes sont désactivées dans cet univers.',{exact:false})).toBeVisible();
  await expect(page.getByRole('link',{name:'Gérer la guilde',exact:true})).toHaveCount(0);
  await page.getByRole('navigation',{name:'Sections de diplomatie'}).getByRole('link',{name:'Commandants',exact:true}).click();
  await expect(page.getByRole('heading',{name:'Relations entre commandants',exact:true})).toBeVisible();
});
