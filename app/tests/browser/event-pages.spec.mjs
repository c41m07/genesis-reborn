import { test, expect } from './support/isolated-test.mjs';
import AxeBuilder from '@axe-core/playwright';
import { runIsolatedSql, queryIsolatedSql } from './support/game-fixtures.mjs';

async function login(page, email, password) {
  await page.goto('/connexion');
  await page.getByLabel('Adresse email').fill(email);
  await page.getByLabel('Mot de passe', { exact: true }).fill(password);
  await page.getByRole('button', { name: /Connexion/ }).click();
}

test('Événements : pages, thèmes, navigation, anciens liens et accès sans JavaScript', async ({ page, browser, baseURL }, testInfo) => {
  test.setTimeout(150000);
  const id = crypto.randomUUID().slice(0, 8);
  const email = `event-pages-${id}@example.test`;
  const password = crypto.randomUUID() + 'aA1!';
  await page.goto('/inscription');
  await page.getByLabel('Adresse email').fill(email);
  await page.getByLabel('Nom de commandant').fill('Events-' + id);
  await page.getByLabel('Mot de passe (12 caractères minimum)', { exact: true }).fill(password);
  await page.getByLabel('Confirmer le mot de passe').fill(password);
  await page.getByRole('button', { name: 'Créer mon compte' }).click();
  await expect(page).toHaveURL(/connexion/);
  await login(page, email, password);
  await page.locator('article.universe-card').first().getByRole('button').click();
  const project = process.env.BROWSER_ROUTER;
  const universe = Number(queryIsolatedSql(project, `SELECT pu.universe_id FROM player_universe pu JOIN player p ON p.id=pu.player_id WHERE p.email='${email}'`));
  const name = 'Défi de navigation ' + id;
  for (const [suffix, start] of [['en cours', '-1 HOUR'], ['à venir', '1 DAY']]) {
    runIsolatedSql(project, `INSERT INTO game_event (universe_id,name,description,type,status,starts_at,ends_at,parameters,visible_to_players) VALUES (${universe},'Bonus ${id} ${suffix}','Une présentation lisible sur petit écran.','production_bonus','active',DATE_ADD(UTC_TIMESTAMP(),INTERVAL ${start}),DATE_ADD(UTC_TIMESTAMP(),INTERVAL 2 DAY),'{"percentage":25}',1)`);
  }
  for (const [key, status] of [['guided', 'active'], ['weekly', 'scheduled'], ['past', 'closed']]) {
    const configuration = JSON.stringify({ guided: key !== 'weekly', name: key === 'guided' ? name : `${key}-${id}`, reward: { kind: 'boost' } });
    runIsolatedSql(project, `INSERT INTO boost_event (event_key,template_key,universe_id,status,format,reward_mode,contribution_metric,duration_seconds,scheduled_at,started_at,ends_at,global_goal,total_contribution,configuration) VALUES ('${key}-${id}','exploration',${universe},'${status}','collective','participation','systems',3600,UTC_TIMESTAMP(),${status === 'active' ? 'UTC_TIMESTAMP()' : 'NULL'},DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 HOUR),10,3,'${configuration}')`);
  }
  const challengeId = Number(queryIsolatedSql(project, `SELECT id FROM boost_event WHERE event_key='guided-${id}'`));
  const detail = `/evenements/defis/${challengeId}`;
  const pages = [['/evenements', 'Événements et bonus'], ['/evenements/defis', 'Défis et rotations'], [detail, name]];
  for (const theme of ['dark', 'light']) {
    runIsolatedSql(project, `UPDATE player SET preferences=JSON_SET(CASE WHEN JSON_TYPE(preferences)='OBJECT' THEN preferences ELSE JSON_OBJECT() END,'$.theme','${theme}') WHERE email='${email}'`);
    for (const width of [1440, 390, 320]) {
      await page.setViewportSize({ width, height: 900 });
      for (const [url, title] of pages) {
        await page.goto(url);
        await expect(page.getByRole('heading', { level: 1, name: title, exact: true })).toBeVisible();
        await expect(page.locator('body')).toHaveAttribute('data-theme', theme);
        await expect(page.locator('meta[name="robots"]')).toHaveAttribute('content', 'noindex');
        await expect(page.locator('.sidebar a[href="/evenements"]')).toHaveAttribute('aria-current', 'page');
        if (width <= 600) await page.locator('.event-redesign .space-navigation-menu > summary').click();
        await expect(page.getByRole('navigation', { name: 'Navigation des événements', exact: true }).locator('[aria-current]')).toHaveCount(1);
        await expect(page.locator('.page-contents')).toHaveCount(0);
        await expect(page.locator('.event-drawer-title')).toBeVisible();
        await expect(page.locator('.event-drawer-count')).toBeVisible();
        await expect.poll(() => page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(width);
        const results = await new AxeBuilder({ page }).include('main').withTags(['wcag2a', 'wcag2aa', 'wcag21aa']).analyze();
        expect(results.violations).toEqual([]);
        if (width === 320 || width === 1440) await page.screenshot({ path: testInfo.outputPath(`events-${url.split('/').pop()}-${theme}-${width}.png`), fullPage: true });
      }
    }
  }
  await page.goto('/evenements');
  await page.locator('.event-redesign .space-navigation-menu > summary').click();
  const navigation = page.getByRole('navigation', { name: 'Navigation des événements', exact: true });
  await navigation.getByRole('link', { name: 'Défis et rotations', exact: true }).focus();
  await page.keyboard.press('Enter');
  await expect(page).toHaveURL(/\/evenements\/defis$/);
  await page.reload();
  await expect(page.getByRole('heading', { level: 1, name: 'Défis et rotations', exact: true })).toBeVisible();
  await page.goBack();
  await expect(page).toHaveURL(/\/evenements$/);
  await page.evaluate(() => { window.location.hash = 'page-section-0'; });
  await expect(page).toHaveURL(/\/evenements\/defis#challenges-list$/);
  await page.goto('/evenements?source=ancien#page-section-0');
  await expect(page).toHaveURL(/\/evenements\/defis\?source=ancien#challenges-list$/);
  await page.goto('/evenements#events-upcoming');
  await expect(page.locator('#events-upcoming')).toBeVisible();
  await expect(page).toHaveURL(/\/evenements#events-upcoming$/);

  // Une connexion native dans une session neuve conserve les protections CSRF habituelles.
  const context = await browser.newContext({ baseURL, javaScriptEnabled: false, viewport: { width: 390, height: 900 } });
  try {
    const plain = await context.newPage();
    await login(plain, email, password);
    await plain.goto('/evenements');
    await expect(plain.getByRole('heading', { name: 'Événements et bonus', exact: true })).toBeVisible();
    await plain.getByRole('navigation', { name: 'Navigation des événements', exact: true }).getByRole('link', { name: 'Défis et rotations', exact: true }).click();
    await plain.locator('main').getByRole('link', { name, exact: true }).click();
    await expect(plain.getByRole('heading', { level: 1, name, exact: true })).toBeVisible();
    await expect(plain.locator('.collective-challenge')).toContainText('Progression collective');
    await plain.getByRole('link', { name: 'Retour aux défis' }).click();
    await expect(plain).toHaveURL(/\/evenements\/defis$/);
  } finally { await context.close(); }
});
