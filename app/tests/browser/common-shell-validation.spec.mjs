import { test, expect } from './support/isolated-test.mjs';
import AxeBuilder from '@axe-core/playwright';
import { runIsolatedSql } from './support/game-fixtures.mjs';

async function enter(page) {
  const suffix = crypto.randomUUID().slice(0, 8);
  const email = `common-shell-${suffix}@example.test`;
  const password = crypto.randomUUID() + 'aA1!';
  await page.goto('/inscription');
  await page.getByLabel('Adresse email').fill(email);
  await page.getByLabel('Nom de commandant').fill('Socle-' + suffix);
  await page.getByLabel('Mot de passe (12 caractères minimum)', { exact: true }).fill(password);
  await page.getByLabel('Confirmer le mot de passe').fill(password);
  await page.getByRole('button', { name: 'Créer mon compte' }).click();
  await page.getByLabel('Adresse email').fill(email);
  await page.getByLabel('Mot de passe', { exact: true }).fill(password);
  await page.getByRole('button', { name: /Connexion/ }).click();
  await page.locator('article.universe-card').first().getByRole('button').click();
  await expect(page).toHaveURL(/planetes\/\d+$/);
  return { email, home: page.url() };
}

test('Socle commun : menus et panneau événement au clavier, deux thèmes et parcours natif', async ({ page, browser, baseURL }, info) => {
  test.setTimeout(150000);
  const { email, home } = await enter(page);
  // La recette du tiroir ne dépend ni d’une rotation active ni d’un scénario précédent.
  runIsolatedSql(process.env.BROWSER_ROUTER, `INSERT INTO game_event(name,description,type,status,starts_at,ends_at,parameters,visible_to_players) VALUES('Annonce fictive du socle','','announcement','active',UTC_TIMESTAMP()-INTERVAL 1 HOUR,UTC_TIMESTAMP()+INTERVAL 1 HOUR,'{}',1)`);
  for (const theme of ['dark', 'light']) {
    runIsolatedSql(process.env.BROWSER_ROUTER, `UPDATE player SET preferences=JSON_SET(CASE WHEN JSON_TYPE(preferences)='OBJECT' THEN preferences ELSE JSON_OBJECT() END,'$.theme','${theme}') WHERE email='${email}'`);
    for (const width of [1440, 768, 390, 320]) {
      await page.setViewportSize({ width, height: 900 });
      await page.goto(home);
      await expect(page.locator('body')).toHaveAttribute('data-theme', theme);
      const drawer = page.locator('.event-drawer');
      const summary = drawer.locator('summary');
      await summary.focus();
      await page.keyboard.press('Enter');
      await expect(drawer).toHaveAttribute('open', '');
      await expect(drawer.getByRole('link', { name: 'Voir les événements', exact: true })).toBeVisible();
      expect((await new AxeBuilder({ page }).include('.header-context-rail').analyze()).violations).toEqual([]);
      await page.screenshot({ path: info.outputPath(`evenements-${theme}-${width}.png`), animations: 'disabled' });
      await page.keyboard.press('Escape');
      await expect(drawer).not.toHaveAttribute('open');
      await expect(summary).toBeFocused();
      await summary.click();
      await page.locator('.server-clock').click();
      await expect(drawer).not.toHaveAttribute('open');

      if (width < 1021) {
        const toggle = width <= 740
          ? page.getByRole('navigation', { name: 'Navigation mobile' }).getByRole('button', { name: 'Plus', exact: true })
          : page.locator('.topbar [data-navigation-target="toggle"]');
        await toggle.click();
        await expect(toggle).toHaveAttribute('aria-expanded', 'true');
        const first = page.locator('#command-navigation .brand');
        await expect(first).toBeFocused();
        await page.keyboard.press('Shift+Tab');
        await expect(page.getByRole('button', { name: 'Déconnexion', exact: true })).toBeFocused();
        await page.keyboard.press('Tab');
        await expect(first).toBeFocused();
        await page.screenshot({ path: info.outputPath(`menu-${theme}-${width}.png`), animations: 'disabled' });
        await page.keyboard.press('Escape');
        await expect(toggle).toBeFocused();
        await expect(toggle).toHaveAttribute('aria-expanded', 'false');
        await expect(page.locator('#command-navigation')).toHaveAttribute('aria-hidden', 'true');
      }
      await expect.poll(() => page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(width);
    }
  }

  const state = await test.step('Conserver la session du parcours natif', () => page.context().storageState());
  for (const theme of ['dark', 'light']) {
    runIsolatedSql(process.env.BROWSER_ROUTER, `UPDATE player SET preferences=JSON_SET(preferences,'$.theme','${theme}') WHERE email='${email}'`);
    const context = await test.step('Ouvrir le navigateur sans JavaScript', () => browser.newContext({ baseURL, storageState: state, javaScriptEnabled: false }));
    try {
      const native = await context.newPage();
      native.setDefaultTimeout(15000);
      native.setDefaultNavigationTimeout(15000);
      for (const width of [1440, 768, 390, 320]) {
        await native.setViewportSize({ width, height: 900 });
        await test.step(`Menu natif ${theme} à ${width} px`, async () => {
          await native.goto(home);
          await expect(native.locator('body')).toHaveAttribute('data-theme', theme);
          await expect(native.locator('#command-navigation')).toBeVisible();
          await expect(native.locator('.header-technology-goal')).toHaveAttribute('href', '/technologies');
          await expect(native.locator('.header-technology-goal')).toContainText('Choisir un objectif');
          await native.screenshot({ path: info.outputPath(`menu-natif-${theme}-${width}.png`), animations: 'disabled' });
          await native.locator('.event-drawer > summary').click();
          await native.locator('.event-drawer').getByRole('link', { name: 'Voir les événements', exact: true }).click();
          await expect(native).toHaveURL(/\/evenements$/);
          await native.locator('#command-navigation').getByRole('link', { name: 'Planètes', exact: true }).click();
          await expect(native).toHaveURL(/\/planetes$/);
          await expect.poll(() => native.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(width);
        });
      }
    } finally {
      await context.close();
    }
  }
});

test('Erreurs autonomes : aperçu Symfony 403/404/500, confidentialité, secours et clavier', async ({ page }, info) => {
  test.setTimeout(90000);
  await enter(page);
  const errors = [[403, 'Accès refusé'], [404, 'Page introuvable'], [500, 'Une erreur est survenue']];
  for (const [status, title] of errors) {
    for (const width of [1440, 768, 390, 320]) {
      await page.setViewportSize({ width, height: 900 });
      // L’aperçu natif exerce le véritable gabarit sans provoquer de panne métier.
      const response = await page.goto('/_error/' + status);
      expect(response.status()).toBe(status);
      await expect(page.getByRole('heading', { level: 1 })).toHaveText(title);
      await expect(page.locator('main')).toHaveAttribute('aria-labelledby', 'error-title');
      await expect(page.locator('.brand img')).toHaveAttribute('alt', '');
      await expect(page.locator('script,link[rel="stylesheet"],.sidebar,.resource-strip')).toHaveCount(0);
      await expect(page.locator('body')).not.toContainText('This is a sample exception');
      await expect(page.locator('body')).not.toContainText('/app/');
      await page.keyboard.press('Tab');
      await expect(page.getByRole('link', { name: 'Aller au contenu principal', exact: true })).toBeFocused();
      await page.keyboard.press('Enter');
      await page.keyboard.press('Tab');
      await expect(page.getByRole('link', { name: 'Retour à l’accueil', exact: true })).toBeFocused();
      await expect.poll(() => page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(width);
      expect((await new AxeBuilder({ page }).analyze()).violations).toEqual([]);
      await page.screenshot({ path: info.outputPath(`erreur-${status}-${width}.png`), animations: 'disabled' });
    }
  }
});

test('Socle privé : abonnement lié à l’empire et présence refusée si le contexte est périmé', async ({ page }) => {
  await enter(page);
  const membership = Number(await page.locator('body').getAttribute('data-player-activity-membership-value'));
  const token = await page.locator('body').getAttribute('data-player-activity-token-value');
  expect(membership).toBeGreaterThan(0);
  // Garder l’origine et la session réelles, y compris pour les domaines locaux du navigateur.
  const subscription = await page.evaluate(async () => {
    const response = await fetch('/notifications/abonnement', { headers: { Accept: 'application/json' } });
    const { url } = await response.json();
    return { status: response.status, cacheControl: response.headers.get('cache-control'), url };
  });
  expect(subscription.status).toBe(200);
  expect(subscription.cacheControl).toContain('private');
  expect(subscription.cacheControl).toContain('no-store');
  const { url } = subscription;
  const topic = new URL(url, page.url()).searchParams.get('topic');
  expect(topic).toMatch(new RegExp('/players/' + membership + '/events$'));

  const sample = form => page.evaluate(async values => {
    const response = await fetch('/activite/presence', { method: 'POST', body: new URLSearchParams(values) });
    return { status: response.status, cacheControl: response.headers.get('cache-control') };
  }, form);
  expect((await sample({ _token: 'invalid', membership, idle_seconds: 0 })).status).toBe(403);
  expect((await sample({ _token: token, membership: membership + 999999, idle_seconds: 0 })).status).toBe(409);
  expect((await sample({ _token: token, membership, idle_seconds: 300 })).status).toBe(422);
  expect((await sample({ _token: token, membership, idle_seconds: -1 })).status).toBe(422);
  const valid = await sample({ _token: token, membership, idle_seconds: 0 });
  expect(valid.status).toBe(204);
  expect(valid.cacheControl).toContain('no-store');
  await expect(page).toHaveURL(/planetes\/\d+$/);
});
