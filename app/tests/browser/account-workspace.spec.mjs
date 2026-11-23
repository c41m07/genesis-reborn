import { test, expect } from './support/isolated-test.mjs';
import { queryIsolatedSql, runIsolatedSql } from './support/game-fixtures.mjs';
import AxeBuilder from '@axe-core/playwright';

async function verifyView(page, info, key, theme, width) {
  expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(width);
  for (const panel of await page.locator('.account-redesign .panel:visible').all()) {
    expect(await panel.evaluate(el => el.scrollWidth <= el.clientWidth + 1)).toBe(true);
  }
  if (width === 390) {
    expect((await new AxeBuilder({ page }).include('.account-redesign').analyze()).violations).toEqual([]);
  }
  if (width !== 320) {
    await page.evaluate(() => { document.activeElement?.blur(); window.scrollTo({ top: 0, behavior: 'instant' }); });
    await page.screenshot({ path: info.outputPath(`jeu-L4-A-${key}-${theme}-${width}.png`), fullPage: true, animations: 'disabled' });
    if (width === 390) await page.screenshot({ path: info.outputPath(`jeu-L4-A-${key}-${theme}-390-ecran.png`), animations: 'disabled' });
  }
}

async function verifyColumns(page, width) {
  const workspace = page.locator('.account-workspace');
  if (!await workspace.count()) return;
  const information = await workspace.locator(':scope > .panel').first().boundingBox();
  const confirmation = await workspace.locator(':scope > .account-confirmation').boundingBox();
  if (width === 1440) {
    expect(confirmation.x).toBeGreaterThanOrEqual(information.x + information.width);
    expect(Math.abs(confirmation.y - information.y)).toBeLessThanOrEqual(1);
  } else {
    expect(confirmation.y).toBeGreaterThanOrEqual(information.y + information.height);
  }
}

test('L4 A : compte structuré, confirmations et états sensibles sur ordinateur et mobile', async ({ page }, info) => {
  test.setTimeout(300000);
  const suffix = crypto.randomUUID().slice(0, 8);
  const email = `account-a-${suffix}@example.test`;
  const password = crypto.randomUUID() + 'aA1!';
  const errors = [];
  page.on('pageerror', error => errors.push(error.message));
  await page.goto('/inscription');
  await page.getByLabel('Adresse email').fill(email);
  await page.getByLabel('Nom de commandant').fill('Horizon-' + suffix);
  await page.getByLabel('Mot de passe (12 caractères minimum)', { exact: true }).fill(password);
  await page.getByLabel('Confirmer le mot de passe').fill(password);
  await page.getByRole('button', { name: 'Créer mon compte' }).click();
  await page.getByLabel('Adresse email').fill(email);
  await page.getByLabel('Mot de passe', { exact: true }).fill(password);
  await page.getByRole('button', { name: /Connexion/ }).click();
  await page.setViewportSize({ width: 390, height: 900 });
  await page.goto('/profil/vacances');
  await expect(page.locator('#vacances-info')).toContainText('n’est pas disponible');
  await expect(page.locator('.account-redesign form')).toHaveCount(0);
  await verifyView(page, info, 'vacation-sans-univers', 'dark', 390);
  await page.goto('/univers');
  await page.locator('.universe-card').filter({ has: page.getByRole('heading', { name: /Omega/ }) }).getByRole('button').click();
  await expect(page).toHaveURL(/planetes\/\d+$/);
  const project = process.env.BROWSER_ROUTER;
  const member = Number(queryIsolatedSql(project, `SELECT current_player_universe_id FROM player WHERE email='${email}'`));
  const vacationEnabled = process.env.ACCOUNT_VACATION_ENABLED === '1';
  const routes = [['profile', '/profil'], ['preferences', '/profil/preferences'], ['vacation', '/profil/vacances'], ['delete', '/profil/supprimer']];
  const themeFor = theme => runIsolatedSql(project, `UPDATE player SET preferences=JSON_SET(CASE WHEN JSON_TYPE(preferences)='OBJECT' THEN preferences ELSE JSON_OBJECT() END,'$.theme','${theme}') WHERE email='${email}'`);
  for (const theme of ['dark', 'light']) {
    themeFor(theme);
    for (const width of [1440, 768, 390, 320]) {
      await page.setViewportSize({ width, height: 900 });
      for (const [key, url] of routes) {
        expect((await page.goto(url)).status()).toBe(200);
        await expect(page.locator('body')).toHaveAttribute('data-theme', theme);
        await expect(page.locator('.account-redesign nav [aria-current="page"]')).toHaveAttribute('href', url);
        await verifyColumns(page, width);
        if (key === 'profile') {
          const profile = page.locator('.account-profile-form');
          const sensitiveRail = page.getByRole('complementary', { name: 'Portée et actions sensibles' });
          await expect(profile).toHaveCount(1);
          await expect(sensitiveRail).toBeVisible();
          const [form, rail] = await Promise.all([profile.boundingBox(), sensitiveRail.boundingBox()]);
          if (width === 1440) {
            expect(rail.x).toBeGreaterThanOrEqual(form.x + form.width);
            expect(Math.abs(rail.y - form.y)).toBeLessThanOrEqual(1);
          } else {
            expect(rail.y).toBeGreaterThanOrEqual(form.y + form.height);
          }
          await expect(page.locator('.profile-password')).not.toHaveAttribute('open');
        }
        if (key === 'preferences') {
          await expect(page.locator('.account-preferences-form')).toHaveCount(1);
          await expect(page.locator('.account-preferences-form button[type="submit"], .account-preferences-save')).toHaveCount(1);
          if (width === 1440) {
            const shortcuts = page.getByRole('group', { name: 'Raccourcis de la galaxie' });
            const transports = page.getByRole('group', { name: 'Réception des transports' });
            await expect(shortcuts).toBeVisible();
            await expect(transports).toBeVisible();
            const [left, right] = await Promise.all([shortcuts.boundingBox(), transports.boundingBox()]);
            expect(right.x).toBeGreaterThanOrEqual(left.x + left.width);
          }
        }
        if (key === 'vacation') {
          if (vacationEnabled) {
            await expect(page.locator('#vacation-rules > li')).toHaveCount(5);
            await expect(page.getByLabel('J’ai lu ces conditions', { exact: false })).toBeVisible();
            await expect(page.locator('input[name="membership_id"]')).toHaveValue(String(member));
          } else {
            // Le lanceur navigateur courant désactive cette fonctionnalité ; son refus reste visible.
            await expect(page.locator('#vacances-info')).toContainText('n’est pas disponible');
            await expect(page.locator('.account-redesign form')).toHaveCount(0);
          }
        }
        await verifyView(page, info, key, theme, width);
        if (key === 'profile') {
          await page.locator('.profile-password > summary').click();
          await expect(page.getByLabel('Nouveau mot de passe (facultatif)', { exact: true })).toHaveValue('');
          await expect(page.getByLabel('Confirmer le nouveau mot de passe')).toHaveValue('');
          await verifyView(page, info, 'profile-password', theme, width);
        }
      }
    }
  }
  // Le minimum de 48 h est représenté par une période synthétique encore trop récente pour sortir.
  runIsolatedSql(project, `INSERT INTO vacation_period(membership_id,started_at,scheduled_end_at,ended_at) VALUES (${member},DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 HOUR),DATE_ADD(DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 HOUR),INTERVAL 90 DAY),NULL)`);
  for (const theme of ['dark', 'light']) {
    themeFor(theme);
    for (const width of [1440, 768, 390, 320]) {
      await page.setViewportSize({ width, height: 900 });
      await page.goto('/profil/vacances');
      await expect(page.getByText('Vacances actives', { exact: true })).toBeVisible();
      await expect(page.getByRole('button', { name: 'Terminer mes vacances dans cet univers' })).toBeDisabled();
      await expect(page.locator('#vacation-minimum')).toContainText('Sortie possible à partir');
      await verifyColumns(page, width);
      await verifyView(page, info, 'vacation-active', theme, width);
    }
  }
  await page.setViewportSize({ width: 390, height: 900 });
  await page.goto('/profil');
  const summary = page.locator('.account-redesign .space-navigation-menu > summary');
  await summary.focus();
  await page.keyboard.press('Enter');
  const link = page.getByRole('navigation', { name: 'Compte et préférences' }).getByRole('link', { name: 'Préférences', exact: true });
  await link.focus();
  expect(await link.evaluate(el => el.matches(':focus-visible') && getComputedStyle(el).outlineStyle !== 'none')).toBe(true);
  await page.keyboard.press('Enter');
  await expect(page).toHaveURL(/profil\/preferences$/);
  await page.reload();
  await expect(page.getByRole('group', { name: 'Affichage et horloge' })).toBeVisible();
  await page.goBack();
  await expect(page.locator('h1')).toHaveText('Mes identifiants');
  await page.getByLabel('Nom de commandant').fill('Horizon conservé');
  await page.getByLabel('Mot de passe actuel', { exact: true }).fill('Incorrect-password-2026!');
  const invalid = page.waitForResponse(r => r.request().method() === 'POST' && new URL(r.url()).pathname === '/profil');
  await page.getByRole('button', { name: 'Enregistrer mes identifiants' }).click();
  expect((await invalid).status()).toBe(422);
  await expect(page.locator('.account-redesign [role="alert"]')).toContainText('Mot de passe actuel incorrect');
  await expect(page.getByLabel('Adresse email')).toHaveValue(email);
  await expect(page.getByLabel('Nom de commandant')).toHaveValue('Horizon conservé');
  await expect(page.getByLabel('Mot de passe actuel', { exact: true })).toHaveValue('');
  await expect(page.locator('.profile-password')).toHaveAttribute('open');
  await verifyView(page, info, 'profile-erreur', 'light', 390);
  expect(errors).toEqual([]);
});


test('P07 : réglages enregistrés et vacances inactives', async ({ page, browser, baseURL }, info) => {
  test.setTimeout(120000);
  const id = crypto.randomUUID().slice(0, 8);
  const email = 'p07-' + id + '@example.test';
  const password = crypto.randomUUID() + 'aA1!';
  await page.goto('/inscription');
  await page.getByLabel('Adresse email').fill(email);
  await page.getByLabel('Nom de commandant').fill('Cadet-' + id);
  await page.getByLabel('Mot de passe (12 caractères minimum)', {exact:true}).fill(password);
  await page.getByLabel('Confirmer le mot de passe').fill(password);
  await page.getByRole('button', {name:'Créer mon compte'}).click();
  await page.getByLabel('Adresse email').fill(email);
  await page.getByLabel('Mot de passe', {exact:true}).fill(password);
  await page.getByRole('button', {name:/Connexion/}).click();
  await page.locator('.universe-card').filter({has:page.getByRole('heading',{name:/Omega/})}).getByRole('button').click();
  await expect(page).toHaveURL(/planetes\/\d+$/);
  const project = process.env.BROWSER_ROUTER;
  const member = Number(queryIsolatedSql(project, `SELECT current_player_universe_id FROM player WHERE email='${email}'`));
  expect(member).toBeGreaterThan(0);
  const vacations = () => queryIsolatedSql(project, `SELECT COUNT(*) FROM vacation_period WHERE membership_id=${member}`);
  const route = '/profil/preferences';
  for (const theme of ['dark', 'light']) {
    for (const viewport of [{width:1920,height:1080},{width:390,height:844}]) {
      await page.setViewportSize(viewport);
      await page.goto(route);
      const settings = page.locator('.account-preferences-form');
      const guide = page.locator('.first-steps-preference-form');
      await expect(guide.getByLabel('Afficher le guide Premiers pas', {exact:true})).toBeChecked();
      await expect(page.locator('.account-board-rail')).toContainText('Pas de vacances actives');
      await settings.getByLabel('Thème de l’interface').selectOption(theme);
      await settings.getByLabel('Fuseau horaire de l’horloge').selectOption('Europe/Paris');
      for (const action of ['espionnage', 'colonisation']) {
        await settings.getByLabel(new RegExp('Vitesse par défaut.*'+action)).selectOption({label:'100 %'});
      }
      await settings.getByLabel('Accepter les ressources envoyées par').selectOption('allies');
      const submitted = page.waitForResponse(r=>r.request().method()==='POST' && new URL(r.url()).pathname===route);
      await settings.getByRole('button',{name:'Enregistrer les paramètres',exact:true}).press('Enter');
      expect((await submitted).status()).toBe(302);
      await expect(page.locator('body')).toHaveAttribute('data-theme',theme);
      const confirmation = page.getByText('Préférences enregistrées.', {exact:true});
      await expect(confirmation).toBeVisible();
      await expect(settings.getByRole('status').filter({ hasText:'Préférences enregistrées.' })).toHaveCount(1);
      const settingsBox = await settings.getByRole('button',{name:'Enregistrer les paramètres',exact:true}).boundingBox();
      const confirmationBox = await confirmation.boundingBox();
      expect(confirmationBox.y).toBeGreaterThanOrEqual(settingsBox.y+settingsBox.height);
      await expect(page.locator('.account-vacation-summary--inactive')).toContainText('Pas de vacances actives');
      if (viewport.width === 390) {
        expect((await new AxeBuilder({page}).include('.account-preferences-confirmation').include('.account-vacation-summary').analyze()).violations).toEqual([]);
      }
      await expect.poll(()=>page.evaluate(()=>document.documentElement.scrollWidth)).toBeLessThanOrEqual(viewport.width);
      await page.evaluate(()=>window.scrollTo({top:0,behavior:'instant'}));
      if (viewport.width === 1920) {
        await expect(confirmation).toBeInViewport({ ratio:1 });
      }
      await page.screenshot({path:info.outputPath(`P07-preferences-${theme}-${viewport.width}.png`),animations:'disabled',scale:'css'});
      if (viewport.width===390) {
        await confirmation.evaluate(element=>element.scrollIntoView({block:'center',behavior:'instant'}));
        await page.screenshot({path:info.outputPath(`P07-confirmation-${theme}-390.png`),animations:'disabled',scale:'css'});
      }
      await expect(guide.getByLabel('Afficher le guide Premiers pas',{exact:true})).toBeChecked();
      expect(vacations()).toBe('0');
    }
  }
  expect(queryIsolatedSql(project,`SELECT JSON_EXTRACT(preferences,'$.theme') FROM player WHERE email='${email}'`)).toBe('"light"');
  const native = await browser.newContext({baseURL,storageState:await page.context().storageState(),javaScriptEnabled:false});
  try {
    const tab = await native.newPage();
    await tab.goto(route);
    const guide = tab.locator('.first-steps-preference-form');
    await guide.getByLabel('Afficher le guide Premiers pas', {exact:true}).uncheck();
    await guide.getByRole('button',{name:'Enregistrer le réglage du guide',exact:true}).press('Enter');
    await expect(tab.getByText(/Guide désactivé/)).toBeVisible();
    await expect(tab.locator('.account-preferences-confirmation')).toHaveCount(0);
    await expect(tab.locator('body')).toHaveAttribute('data-theme','light');
    await guide.getByLabel('Afficher le guide Premiers pas',{exact:true}).check();
    await guide.getByRole('button',{name:'Enregistrer le réglage du guide',exact:true}).press('Enter');
    await expect(tab.getByText(/Guide réactivé/)).toBeVisible();
    await expect(tab.locator('.account-preferences-confirmation')).toHaveCount(0);
    await tab.getByRole('link',{name:'Examiner les restrictions',exact:true}).press('Enter');
    await expect(tab).toHaveURL(/profil\/vacances$/);
    await expect(tab.getByRole('heading',{name:'Mode vacances',exact:true})).toBeVisible();
  } finally { await native.close(); }
  expect(vacations()).toBe('0');
});


test('P06 : mes identifiants et refus sans modification du compte', async ({ page, browser, baseURL }, info) => {
  test.setTimeout(120000);
  const id = crypto.randomUUID().slice(0,8);
  const email = 'p06-' + id + '@example.test';
  const name = 'Horizon-' + id;
  const password = crypto.randomUUID() + 'aA1!';
  await page.goto('/inscription');
  await page.getByLabel('Adresse email').fill(email);
  await page.getByLabel('Nom de commandant').fill(name);
  await page.getByLabel('Mot de passe (12 caractères minimum)',{exact:true}).fill(password);
  await page.getByLabel('Confirmer le mot de passe').fill(password);
  await page.getByRole('button',{name:'Créer mon compte'}).click();
  await page.getByLabel('Adresse email').fill(email);
  await page.getByLabel('Mot de passe',{exact:true}).fill(password);
  await page.getByRole('button',{name:/Connexion/}).click();
  await page.locator('.universe-card').filter({has:page.getByRole('heading',{name:/Omega/})}).getByRole('button').click();
  await expect(page).toHaveURL(/planetes\/\d+$/);
  const project = process.env.BROWSER_ROUTER;
  const accountName = () => queryIsolatedSql(project,`SELECT username FROM player WHERE email='${email}'`);
  expect(accountName()).toBe(name);
  for (const theme of ['dark','light']) {
    runIsolatedSql(project,`UPDATE player SET preferences=JSON_SET(CASE WHEN JSON_TYPE(preferences)='OBJECT' THEN preferences ELSE JSON_OBJECT() END,'$.theme','${theme}') WHERE email='${email}'`);
    for (const viewport of [{width:1920,height:1080},{width:390,height:844}]) {
      await page.setViewportSize(viewport);
      await page.goto('/profil');
      const form = page.locator('.account-profile-form');
      const rail = page.getByRole('complementary',{name:'Portée et actions sensibles'});
      await expect(page.locator('body')).toHaveAttribute('data-theme',theme);
      await expect(form.locator('.profile-password')).not.toHaveAttribute('open');
      await expect(form.locator('.profile-password-hint')).toContainText('conserver votre mot de passe');
      const validation = form.locator('.profile-validation-notice');
      await expect(validation).toContainText('Toute modification enregistrée ferme cette session');
      const save = form.getByRole('button',{name:'Enregistrer mes identifiants'});
      const saveBounds = await save.boundingBox();
      const noticeBounds = await validation.boundingBox();
      expect(noticeBounds.y).toBeGreaterThanOrEqual(saveBounds.y+saveBounds.height);
      const scope = rail.locator('.profile-scope-card').locator('dl > div').first();
      const scopeTerm = await scope.locator('dt').boundingBox();
      const scopeDescription = await scope.locator('dd').boundingBox();
      expect(scopeDescription.y).toBeGreaterThanOrEqual(scopeTerm.y+scopeTerm.height);
      if (viewport.width===390) {
        expect((await new AxeBuilder({page}).include('.profile-password-hint').include('.profile-validation-notice').include('.profile-danger-card').analyze()).violations).toEqual([]);
      }
      await expect(rail.getByRole('link',{name:'Voir la confirmation'})).toHaveAttribute('href','/profil/supprimer');
      await expect(form.getByRole('link',{name:'Voir la confirmation'})).toHaveCount(0);
      await expect.poll(()=>page.evaluate(()=>document.documentElement.scrollWidth)).toBeLessThanOrEqual(viewport.width);
      await page.evaluate(()=>window.scrollTo({top:0,behavior:'instant'}));
      if (viewport.width===1920) {
        await expect(validation).toBeInViewport({ratio:1});
      }
      await page.screenshot({path:info.outputPath(`P06-identifiants-${theme}-${viewport.width}.png`),scale:'css',animations:'disabled'});
      if (viewport.width===390) {
        await form.getByRole('button',{name:'Enregistrer mes identifiants'}).scrollIntoViewIfNeeded();
        await page.screenshot({path:info.outputPath(`P06-action-${theme}-390.png`),scale:'css',animations:'disabled'});
      }
      await form.getByLabel('Nom de commandant').fill('Texte conservé');
      const input = form.getByLabel('Mot de passe actuel',{exact:true});
      await input.fill('Incorrect-synthetique-2026!');
      const rejected = page.waitForResponse(r=>r.request().method()==='POST' && new URL(r.url()).pathname==='/profil');
      await form.getByRole('button',{name:'Enregistrer mes identifiants'}).press('Enter');
      expect((await rejected).status()).toBe(422);
      await expect(form.getByLabel('Nom de commandant')).toHaveValue('Texte conservé');
      await expect(form.getByLabel('Adresse email')).toHaveValue(email);
      await expect(input).toHaveValue('');
      await expect(form.getByRole('alert')).toContainText('Profil non enregistré.');
      await expect(form.locator('.profile-password')).toHaveAttribute('open','');
      expect(accountName()).toBe(name);
      await page.screenshot({path:info.outputPath(`P06-refus-${theme}-${viewport.width}.png`),scale:'css',animations:'disabled'});
    }
  }
  const context = await browser.newContext({baseURL,storageState:await page.context().storageState(),javaScriptEnabled:false});
  try {
    const tab = await context.newPage();
    await tab.goto('/profil');
    const summary = tab.locator('.profile-password > summary');
    await summary.press('Enter');
    await expect(tab.locator('.profile-password')).toHaveAttribute('open','');
    await summary.press('Enter');
    await expect(tab.locator('.profile-password')).not.toHaveAttribute('open');
  } finally { await context.close(); }
});


test('P17 : consulter la suppression et revenir sans aucune soumission', async ({ page, browser, baseURL }, info) => {
  test.setTimeout(120000);
  const id = crypto.randomUUID().slice(0,8);
  const email = 'p17-' + id + '@example.test';
  const name = 'Horizon-' + id;
  const password = crypto.randomUUID() + 'aA1!';
  await page.goto('/inscription');
  await page.getByLabel('Adresse email').fill(email);
  await page.getByLabel('Nom de commandant').fill(name);
  await page.getByLabel('Mot de passe (12 caractères minimum)',{exact:true}).fill(password);
  await page.getByLabel('Confirmer le mot de passe').fill(password);
  await page.getByRole('button',{name:'Créer mon compte'}).click();
  await page.getByLabel('Adresse email').fill(email);
  await page.getByLabel('Mot de passe',{exact:true}).fill(password);
  await page.getByRole('button',{name:/Connexion/}).click();
  await page.locator('.universe-card').filter({has:page.getByRole('heading',{name:/Omega/})}).getByRole('button').click();
  await expect(page).toHaveURL(/planetes\/\d+$/);
  const project=process.env.BROWSER_ROUTER;
  const account=()=>queryIsolatedSql(project,`SELECT username FROM player WHERE email='${email}'`);
  expect(account()).toBe(name);
  const attempted=[];
  const preventDeletion=async route=>{
    if(route.request().method()==='GET') return route.continue();
    attempted.push(route.request().method());
    return route.abort();
  };
  await page.route('**/profil/supprimer',preventDeletion);
  for(const theme of ['dark','light']){
    runIsolatedSql(project,`UPDATE player SET preferences=JSON_SET(CASE WHEN JSON_TYPE(preferences)='OBJECT' THEN preferences ELSE JSON_OBJECT() END,'$.theme','${theme}') WHERE email='${email}'`);
    for(const viewport of [{width:1920,height:1080},{width:390,height:844}]){
      await page.setViewportSize(viewport);
      await page.goto('/profil');
      await page.getByRole('link',{name:'Voir la confirmation',exact:true}).press('Enter');
      await expect(page).toHaveURL(/profil\/supprimer$/);
      await expect(page.locator('body')).toHaveAttribute('data-theme',theme);
      const warning=page.locator('.account-deletion-page .sensitive-actions');
      const form=page.locator('.account-delete-form');
      await expect(warning).toContainText('Action définitive · tous les univers');
      await expect(warning).toContainText('empires oubliés');
      await expect(warning).toContainText('historiques de jeu et messages sont conservés');
      await expect(form).toContainText('tous les univers');
      await expect(form).toHaveAttribute('method','post');
      const field=form.getByLabel('Mot de passe pour supprimer le compte',{exact:true});
      const confirmation=form.getByRole('checkbox');
      const submit=form.getByRole('button',{name:'Supprimer définitivement mon compte',exact:true});
      await expect(field).toHaveValue('');
      await expect(field).toHaveAttribute('required','');
      await expect(confirmation).not.toBeChecked();
      await expect(confirmation).toHaveAttribute('required','');
      await field.focus();
      await page.keyboard.press('Tab');
      await expect(confirmation).toBeFocused();
      await page.keyboard.press('Tab');
      await expect(submit).toBeFocused();
      await page.evaluate(()=>{document.activeElement?.blur();window.scrollTo({top:0,behavior:'instant'});});
      await expect.poll(()=>page.evaluate(()=>document.documentElement.scrollWidth)).toBeLessThanOrEqual(viewport.width);
      await page.screenshot({path:info.outputPath(`P17-B-confirmation-${theme}-${viewport.width}.png`),animations:'disabled',scale:'css'});
      if(viewport.width===390){
        await submit.evaluate(el=>el.scrollIntoView({block:'center',behavior:'instant'}));
        await page.screenshot({path:info.outputPath(`P17-B-commandes-${theme}-390.png`),animations:'disabled',scale:'css'});
      }
      expect(account()).toBe(name);
    }
  }
  const native=await browser.newContext({baseURL,storageState:await page.context().storageState(),javaScriptEnabled:false});
  try{
    const tab=await native.newPage();
    await tab.route('**/profil/supprimer',preventDeletion);
    await tab.goto('/profil/supprimer');
    await expect(tab.getByRole('heading',{name:'Suppression du compte',exact:true})).toBeVisible();
    const menu=tab.locator('.space-navigation-menu > summary');
    if(await menu.isVisible()) await menu.press('Enter');
    await tab.getByRole('navigation',{name:'Compte et préférences'}).getByRole('link',{name:'Mes identifiants',exact:true}).press('Enter');
    await expect(tab).toHaveURL(/profil$/);
  } finally { await native.close(); }
  expect(attempted).toEqual([]);
  expect(account()).toBe(name);
});
