import { test, expect } from './support/isolated-test.mjs';
import AxeBuilder from '@axe-core/playwright';
import { queryIsolatedSql, runIsolatedSql } from './support/game-fixtures.mjs';

test('Profil : erreur explicite, champs conservés, confirmation et reconnexion', async ({ page }, testInfo) => {
  const id = crypto.randomUUID().slice(0, 8);
  const email = `profile-${id}@example.test`;
  const password = 'Browser-test-2026!';
  const username = `Profile-${id}`;
  await page.goto('/inscription');
  await page.getByLabel('Adresse email').fill(email);
  await page.getByLabel('Nom de commandant').fill(username);
  await page.getByLabel('Mot de passe (12 caractères minimum)', { exact: true }).fill(password);
  await page.getByLabel('Confirmer le mot de passe').fill(password);
  await page.getByRole('button', { name: 'Créer mon compte' }).click();
  await expect(page).toHaveURL(/connexion/);
  const login = async () => {
    await page.getByLabel('Adresse email').fill(email);
    await page.getByLabel('Mot de passe', { exact: true }).fill(password);
    await page.getByRole('button', { name: /Connexion/ }).click();
  };
  await login();
  await page.goto('/profil');
  await expect(page.getByRole('group', { name: 'Identifiants', exact: true })).toBeVisible();
  await page.getByText('Changer de mot de passe — facultatif', { exact: true }).click();
  await expect(page.getByRole('group', { name: 'Changer de mot de passe', exact: true })).toBeVisible();
  for (const width of [1920, 1440, 1280, 768, 390]) {
    await page.setViewportSize({ width, height: 900 });
    expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(width);
  }
  await page.screenshot({ path: testInfo.outputPath('profile-390.png'), fullPage: true, animations: 'disabled' });
  await page.getByLabel('Mot de passe actuel', { exact: true }).fill('Incorrect-password-2026!');
  await page.getByRole('button', { name: 'Enregistrer mes identifiants' }).click();
  await expect(page.getByRole('alert')).toContainText('Profil non enregistré');
  await expect(page.getByRole('alert')).toContainText('Mot de passe actuel incorrect');
  await expect(page.getByLabel('Adresse email')).toHaveValue(email);
  await expect(page.getByLabel('Nom de commandant')).toHaveValue(username);
  await page.getByLabel('Nom de commandant').fill(`${username}-Edit`);
  await page.getByLabel('Mot de passe actuel', { exact: true }).fill(password);
  await page.getByRole('button', { name: 'Enregistrer mes identifiants' }).click();
  await expect(page).toHaveURL(/connexion/);
  await expect(page.getByRole('status')).toContainText('Profil enregistré');
  await login();
  await page.goto('/profil');
  await expect(page.getByLabel('Nom de commandant')).toHaveValue(`${username}-Edit`);
  await page.getByRole('link', { name: 'Paramètres', exact: true }).click();
  await expect(page.getByRole('group', { name: 'Confort de lecture', exact: true })).toBeVisible();
  await page.screenshot({ path: testInfo.outputPath('preferences-dark-390.png'), fullPage: true, animations: 'disabled' });
});


test('Compte : suppression confirmée, mot de passe, empires oubliés et sessions fermées', async ({ page, browser }, testInfo) => {
  test.setTimeout(120000);
  const id=crypto.randomUUID().slice(0,8), email=`delete-${id}@example.test`, password='Browser-test-2026!';
  await page.goto('/inscription');
  await page.getByLabel('Adresse email').fill(email);await page.getByLabel('Nom de commandant').fill(`Delete-${id}`);
  await page.getByLabel('Mot de passe (12 caractères minimum)',{exact:true}).fill(password);await page.getByLabel('Confirmer le mot de passe').fill(password);
  await page.getByRole('button',{name:'Créer mon compte'}).click();await expect(page).toHaveURL(/connexion/);
  const login=async p=>{await p.goto('/connexion');await p.getByLabel('Adresse email').fill(email);await p.getByLabel('Mot de passe',{exact:true}).fill(password);await p.getByRole('button',{name:/Connexion/}).click();await expect(p).toHaveURL(/univers$/);};
  await login(page);await page.locator('article.universe-card').first().getByRole('button').click();await expect(page).toHaveURL(/planetes\/\d+$/);
  const project=process.env.BROWSER_ROUTER;
  const playerId=Number(queryIsolatedSql(project,`SELECT id FROM player WHERE email='${email}'`));
  const second=await browser.newPage();await login(second);await second.goto('/profil');
  await page.goto('/profil');
  await page.getByRole('navigation',{name:'Compte et préférences'}).getByRole('link',{name:'Suppression du compte',exact:true}).click();
  const form=page.locator('form[action="/profil/supprimer"]');
  for (const width of [1440,390]) {
    await page.setViewportSize({width,height:900});await expect(form.getByRole('button')).toBeVisible();
    expect(await page.evaluate(()=>document.documentElement.scrollWidth)).toBeLessThanOrEqual(width);
    expect((await new AxeBuilder({page}).withTags(['wcag2a','wcag2aa','wcag21aa']).analyze()).violations).toEqual([]);
  }
  await page.screenshot({path:testInfo.outputPath('account-delete-390.png'),fullPage:true});
  const data=await form.evaluate(f=>Object.fromEntries(new FormData(f)));
  const submit=async fields=>page.evaluate(async form=>(await fetch('/profil/supprimer',{method:'POST',body:new URLSearchParams(form)})).status,fields);
  expect(await submit({...data,password,confirmed:'1',_token:'invalid'})).toBe(403);
  await submit({...data,password});
  expect(queryIsolatedSql(project,`SELECT email FROM player WHERE id=${playerId}`)).toBe(email);
  await form.getByLabel('Mot de passe pour supprimer le compte').fill(password);
  await form.getByRole('button').click();await expect(page).toHaveURL(/profil\/supprimer$/);
  await form.getByLabel('Je confirme la suppression définitive',{exact:false}).check();
  await form.getByLabel('Mot de passe pour supprimer le compte').fill('Wrong-password-2026!');await form.getByRole('button').click();
  await expect(page.getByRole('alert')).toContainText('Mot de passe actuel incorrect');
  await expect(page).toHaveURL(/profil\/supprimer$/);
  await expect(form.getByRole('button')).toBeVisible();
  expect(queryIsolatedSql(project,`SELECT email FROM player WHERE id=${playerId}`)).toBe(email);
  await form.getByLabel('Mot de passe pour supprimer le compte').fill(password);await form.getByLabel('Je confirme la suppression définitive',{exact:false}).check();
  await form.getByRole('button').focus();await page.keyboard.press('Enter');await expect(page).toHaveURL(/connexion/);
  await expect(page.getByRole('status')).toContainText('Votre compte a été supprimé');
  expect(Number(queryIsolatedSql(project,`SELECT COUNT(*) FROM player_universe WHERE player_id=${playerId}`))).toBe(0);
  expect(Number(queryIsolatedSql(project,`SELECT COUNT(*) FROM player WHERE id=${playerId} AND locked_at IS NOT NULL AND email LIKE '%@deleted.invalid'`))).toBe(1);
  await second.goto('/profil');await expect(second).toHaveURL(/connexion/);await second.close();
});


async function enterAccount(page) {
  const id=crypto.randomUUID().slice(0,8), email=`account-${id}@example.test`, password='Browser-test-2026!';
  await page.goto('/inscription');await page.getByLabel('Adresse email').fill(email);
  await page.getByLabel('Nom de commandant').fill('Account-'+id);
  await page.getByLabel('Mot de passe (12 caractères minimum)',{exact:true}).fill(password);
  await page.getByLabel('Confirmer le mot de passe').fill(password);
  await page.getByRole('button',{name:'Créer mon compte'}).click();
  await page.getByLabel('Adresse email').fill(email);await page.getByLabel('Mot de passe',{exact:true}).fill(password);
  await page.getByRole('button',{name:/Connexion/}).click();
  await page.locator('article.universe-card').first().getByRole('button').click();
  await expect(page).toHaveURL(/planetes\/\d+$/);
  return {email,password,project:process.env.BROWSER_ROUTER};
}

test('Compte : quatre pages, états actifs, thèmes, historique et anciens liens', async ({page},testInfo) => {
  test.setTimeout(180000);const {email,project}=await enterAccount(page);
  const routes=[['/profil','Mes identifiants'],['/profil/preferences','Préférences'],['/profil/vacances','Mode vacances'],['/profil/supprimer','Suppression du compte']];
  for (const theme of ['dark','light']) {
    runIsolatedSql(project,`UPDATE player SET preferences=JSON_SET(CASE WHEN JSON_TYPE(preferences)='OBJECT' THEN preferences ELSE JSON_OBJECT() END,'$.theme','${theme}') WHERE email='${email}'`);
    for (const width of [1440,390,320]) {
      await page.setViewportSize({width,height:900});
      for (const [url,title] of routes) {
        expect((await page.goto(url)).status()).toBe(200);await expect(page.locator('h1')).toHaveText(title);
        await expect(page.locator('body')).toHaveAttribute('data-theme',theme);
        await expect(page.locator('.sidebar a[href="/profil"]')).toHaveClass(/active/);
        if (width <= 600) await page.locator('.account-redesign .space-navigation-menu > summary').click();
        const current=page.getByRole('navigation',{name:'Compte et préférences'}).locator('[aria-current="page"]');
        await expect(current).toHaveCount(1);await expect(current).toHaveAttribute('href',url);
        expect(await page.evaluate(()=>document.documentElement.scrollWidth)).toBeLessThanOrEqual(width);
        expect((await new AxeBuilder({page}).include('main').analyze()).violations).toEqual([]);
      }
      await page.screenshot({path:testInfo.outputPath(`account-delete-${theme}-${width}.png`),fullPage:true});
    }
  }
  await page.goto('/profil');
  await page.locator('.account-redesign .space-navigation-menu > summary').click();
  await page.getByRole('navigation',{name:'Compte et préférences'}).getByRole('link',{name:'Mode vacances',exact:true}).focus();
  await page.keyboard.press('Enter');await expect(page).toHaveURL(/profil\/vacances$/);
  await page.reload();await expect(page.locator('#vacances-info')).toBeVisible();await page.goBack();await expect(page.locator('h1')).toHaveText('Mes identifiants');
  for (const hash of ['vacances-info','vacances','vacation-heading']) {
    await page.goto('/profil/preferences#'+hash);await expect(page).toHaveURL(new RegExp('/profil/vacances#'+hash+'$'));
  }
  await page.goto('/profil#account-delete-title');await expect(page).toHaveURL(/profil\/supprimer#account-delete-title$/);
  await page.goto('/profil?delete=1');await expect(page).toHaveURL(/profil\/supprimer$/);
});

test('Compte sans JavaScript : préférences communes, vacances et suppression confirmée', async ({page,browser}) => {
  const {email,password,project}=await enterAccount(page);
  const member=Number(queryIsolatedSql(project,`SELECT current_player_universe_id FROM player WHERE email='${email}'`));
  runIsolatedSql(project,`INSERT INTO vacation_period (membership_id,started_at,scheduled_end_at,ended_at) VALUES (${member},UTC_TIMESTAMP()-INTERVAL 3 DAY,UTC_TIMESTAMP()+INTERVAL 10 DAY,NULL)`);
  // Une connexion avec JS mémorise la protection double-submit : le parcours natif se reconnecte dans une session neuve.
  const context=await browser.newContext({baseURL:new URL(page.url()).origin,javaScriptEnabled:false,viewport:{width:390,height:900}});
  try {
    const plain=await context.newPage();await plain.goto('/connexion');
    await plain.getByLabel('Adresse email').fill(email);await plain.getByLabel('Mot de passe',{exact:true}).fill(password);
    await plain.getByRole('button',{name:/Connexion/}).click();await expect(plain).toHaveURL(/univers$/);
    await plain.goto('/profil/preferences');
    await expect(plain.locator('#vacances-info')).toHaveCount(0);
    await plain.getByLabel('Thème de l’interface').selectOption('light');
    const saved=plain.waitForResponse(r=>r.request().method()==='POST'&&new URL(r.url()).pathname==='/profil/preferences');
    await plain.getByRole('button',{name:'Enregistrer les paramètres'}).click();
    expect((await saved).status(),await plain.locator('.form-error').allTextContents()).toBe(302);
    await expect(plain.locator('body')).toHaveAttribute('data-theme','light');
    const nav=plain.getByRole('navigation',{name:'Compte et préférences'});
    await nav.getByRole('link',{name:'Mode vacances',exact:true}).click();
    await expect(plain.locator('.vacation-universe-scope')).toContainText('univers');
    await expect(plain.getByText('Vacances actives',{exact:true})).toBeVisible();
    await plain.getByRole('button',{name:'Terminer mes vacances dans cet univers'}).click();
    await expect(plain.getByRole('status')).toContainText('Vacances terminées');
    expect(Number(queryIsolatedSql(project,`SELECT COUNT(*) FROM vacation_period WHERE membership_id=${member} AND ended_at IS NOT NULL`))).toBe(1);
    await nav.getByRole('link',{name:'Suppression du compte',exact:true}).click();
    await expect(plain).toHaveURL(/profil\/supprimer$/);
    await plain.getByLabel('Mot de passe pour supprimer le compte').fill(password);
    await plain.getByLabel('Je confirme la suppression définitive',{exact:false}).check();
    await plain.getByRole('button',{name:'Supprimer définitivement mon compte'}).click();
    await expect(plain).toHaveURL(/connexion/);await expect(plain.getByRole('status')).toContainText('Votre compte a été supprimé');
  } finally {await context.close();}
});
