import { test, expect } from './support/isolated-test.mjs';
import AxeBuilder from '@axe-core/playwright';
import { queryIsolatedSql } from './support/game-fixtures.mjs';

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
  await expect(page.getByRole('group', { name: 'Changer de mot de passe', exact: true })).toBeVisible();
  for (const width of [1920, 1440, 1280, 768, 390]) {
    await page.setViewportSize({ width, height: 900 });
    expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(width);
  }
  await page.screenshot({ path: testInfo.outputPath('profile-390.png'), fullPage: true, animations: 'disabled' });
  await page.getByLabel('Mot de passe actuel', { exact: true }).fill('Incorrect-password-2026!');
  await page.getByRole('button', { name: 'Enregistrer mon profil' }).click();
  await expect(page.getByRole('alert')).toContainText('Profil non enregistré');
  await expect(page.getByRole('alert')).toContainText('Mot de passe actuel incorrect');
  await expect(page.getByLabel('Adresse email')).toHaveValue(email);
  await expect(page.getByLabel('Nom de commandant')).toHaveValue(username);
  await page.getByLabel('Nom de commandant').fill(`${username}-Edit`);
  await page.getByLabel('Mot de passe actuel', { exact: true }).fill(password);
  await page.getByRole('button', { name: 'Enregistrer mon profil' }).click();
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
  await form.getByRole('button').click();await expect(page).toHaveURL(/profil$/);
  await form.getByLabel('Je confirme la suppression définitive',{exact:false}).check();
  await form.getByLabel('Mot de passe pour supprimer le compte').fill('Wrong-password-2026!');await form.getByRole('button').click();
  await expect(page.getByRole('alert')).toContainText('Mot de passe actuel incorrect');
  expect(queryIsolatedSql(project,`SELECT email FROM player WHERE id=${playerId}`)).toBe(email);
  await form.getByLabel('Mot de passe pour supprimer le compte').fill(password);await form.getByLabel('Je confirme la suppression définitive',{exact:false}).check();
  await form.getByRole('button').focus();await page.keyboard.press('Enter');await expect(page).toHaveURL(/connexion/);
  await expect(page.getByRole('status')).toContainText('Votre compte a été supprimé');
  expect(Number(queryIsolatedSql(project,`SELECT COUNT(*) FROM player_universe WHERE player_id=${playerId}`))).toBe(0);
  expect(Number(queryIsolatedSql(project,`SELECT COUNT(*) FROM player WHERE id=${playerId} AND locked_at IS NOT NULL AND email LIKE '%@deleted.invalid'`))).toBe(1);
  await second.goto('/profil');await expect(second).toHaveURL(/connexion/);await second.close();
});
