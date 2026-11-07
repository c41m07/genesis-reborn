import { test, expect } from './support/isolated-test.mjs';
import AxeBuilder from '@axe-core/playwright';
import { runIsolatedSql, queryIsolatedSql } from './support/game-fixtures.mjs';

test.use({ timezoneId: 'Pacific/Honolulu' });
test('Fermeture : vues joueur, date serveur, actualisation, échéance, mobile et droits', async ({ page }, testInfo) => {
  test.setTimeout(150000);
  await page.goto('/univers/1/fermeture');
  await expect(page).toHaveURL(/connexion/);
  await page.addInitScript(() => { Date.now = () => 0; });
  const id = crypto.randomUUID().slice(0, 8), email = `closing-${id}@example.test`;
  await page.goto('/inscription');
  await page.getByLabel('Adresse email').fill(email);
  await page.getByLabel('Nom de commandant').fill(`Closing-${id}`);
  await page.getByLabel('Mot de passe (12 caractères minimum)', { exact: true }).fill('Browser-test-2026!');
  await page.getByLabel('Confirmer le mot de passe').fill('Browser-test-2026!');
  await page.getByRole('button', { name: 'Créer mon compte' }).click();
  await expect(page).toHaveURL(/connexion/);
  await page.getByLabel('Adresse email').fill(email);
  await page.getByLabel('Mot de passe', { exact: true }).fill('Browser-test-2026!');
  await page.getByRole('button', { name: /Connexion/ }).click();
  await page.goto('/univers');
  await page.locator('.universe-card').filter({ has: page.getByRole('button') }).first().getByRole('button').click();
  await expect(page).toHaveURL(/planetes\/\d+$/);
  const home = page.url(), project = process.env.BROWSER_ROUTER;
  const universe = Number(queryIsolatedSql(project, `SELECT pu.universe_id FROM player_universe pu JOIN player p ON p.id=pu.player_id WHERE p.email='${email}'`));
  const update = value => runIsolatedSql(project, `UPDATE universe SET ends_at=${value} WHERE id=${universe}`);
  update('DATE_ADD(UTC_TIMESTAMP(), INTERVAL 2 HOUR)');
  await page.goto(home);
  const banner = page.locator('.universe-closing');
  await expect(banner).toBeVisible();
  await expect(banner).toContainText('Fermeture dans');
  await expect(banner.getByRole('timer')).toContainText(/1 h 59 min/);
  await expect(banner).toContainText('UTC');
  const response = await page.evaluate(async url => {
    const response = await fetch(url);
    return { cache: response.headers.get('cache-control'), data: await response.json() };
  }, `/univers/${universe}/fermeture`);
  expect(response.cache).toContain('no-store');
  expect(response.data.endsAt).toMatch(/(?:Z|[+-]\d{2}:\d{2})$/);
  expect(await page.evaluate(async () => (await fetch('/univers/99999999/fermeture')).status)).toBe(404);
  for (const theme of ['dark', 'light']) {
    runIsolatedSql(project, `UPDATE player SET preferences=JSON_SET(CASE WHEN JSON_TYPE(preferences)='OBJECT' THEN preferences ELSE JSON_OBJECT() END, '$.theme', '${theme}') WHERE email='${email}'`);
    await page.goto(home);
    for (const width of [1440, 768, 390]) {
      await page.setViewportSize({ width, height: 900 });
      await expect.poll(() => page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(width);
    }
    expect((await new AxeBuilder({ page }).include('.universe-closing').analyze()).violations).toEqual([]);
    await page.screenshot({ path: testInfo.outputPath(`closing-${theme}-mobile.png`), fullPage: true });
  }
  await page.goto('/univers');
  const card = page.locator('.universe-card').filter({ has: page.locator(`[data-universe-closing-url-value="/univers/${universe}/fermeture"]`) });
  await expect(card.locator('.universe-closing')).toBeVisible();
  await page.goto(home);
  const refresh = async () => {
    const [response] = await Promise.all([page.waitForResponse(response => response.url().endsWith(`/univers/${universe}/fermeture`)), page.evaluate(() => document.dispatchEvent(new Event('visibilitychange')))]);
    await response.finished();
  };
  update('DATE_ADD(UTC_TIMESTAMP(), INTERVAL 3 SECOND)');
  await refresh();
  await expect(banner).toContainText('Échéance de fermeture atteinte', { timeout: 10000 });
  await expect(banner.getByRole('timer')).toHaveText('');
  update('NULL');
  await refresh();
  await expect(banner).toBeHidden();
  update('DATE_ADD(UTC_TIMESTAMP(), INTERVAL 2 DAY)');
  await refresh();
  await expect(banner).toBeHidden();
  update('DATE_ADD(UTC_TIMESTAMP(), INTERVAL 1 HOUR)');
  await refresh();
  await expect(banner).toBeVisible();
  await page.route('**/univers/*/fermeture', route => route.abort());
  await page.evaluate(() => document.dispatchEvent(new Event('visibilitychange')));
  await expect(banner).toContainText('Actualisation indisponible');
  await expect(banner.getByRole('timer')).not.toHaveText('');
});
