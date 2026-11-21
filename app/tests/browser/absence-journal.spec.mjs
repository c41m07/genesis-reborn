import { test, expect } from './support/isolated-test.mjs';
import { runIsolatedSql } from './support/game-fixtures.mjs';

test.use({ hasTouch: true, viewport: { width: 390, height: 844 } });

test('Journal : cinq entrées, acquittement à la lecture et repli clavier sans JavaScript', async ({ page, browser, baseURL }) => {
  const id = crypto.randomUUID().slice(0, 8);
  const email = 'details-' + id + '@example.test';
  const password = crypto.randomUUID();
  await page.goto('/inscription');
  await page.getByLabel('Adresse email').fill(email);
  await page.getByLabel('Nom de commandant').fill('Details-' + id);
  await page.getByLabel('Mot de passe (12 caractères minimum)', { exact: true }).fill(password);
  await page.getByLabel('Confirmer le mot de passe').fill(password);
  await page.getByRole('button', { name: 'Créer mon compte' }).click();
  await expect(page).toHaveURL(/connexion/);
  await page.getByLabel('Adresse email').fill(email);
  await page.getByLabel('Mot de passe', { exact: true }).fill(password);
  await page.getByRole('button', { name: /Connexion/ }).click();
  await page.locator('article.universe-card').filter({ has: page.getByRole('heading', { name: /Turbo/ }) }).getByRole('button').click();
  await expect(page).toHaveURL(/planetes\/\d+$/);

  await page.waitForLoadState('load');
  const overview = page.url();
  const requests = [], errors = [];
  page.on('request', request => { if (request.url().includes('/planetes/')) requests.push({ url: request.url(), live: request.headers()['x-genesis-live'] }); });
  page.on('pageerror', error => errors.push(error.message));
  const member = Number(await page.locator('body').getAttribute('data-menu-notifications-membership-value'));
  expect(Number.isSafeInteger(member) && member > 0).toBe(true);
  const insert = (message, kind) => runIsolatedSql(process.env.BROWSER_ROUTER,
    "INSERT INTO notification (recipient_id,message,created_at,journal_kind) VALUES (" + member + ",'" + message + "',UTC_TIMESTAMP()," + kind + ")");
  const initial = Array.from({ length: 8 }, (_, i) => 'Activity-' + id + '-' + i);
  const late = 'Late-' + id;
  for (const message of initial) insert(message, "'arrival'");
  insert('History-' + id, 'NULL');

  const refresh = async () => {
    const refreshed = page.waitForResponse(response => response.url() === overview && response.request().headers()['x-genesis-live'] === '1');
    await page.evaluate(() => window.dispatchEvent(new CustomEvent('genesis:refresh', { detail: { journal: true } })));
    await refreshed.catch(error => { console.log('Actualisation du journal :', JSON.stringify({ requests, errors })); throw error; });
  };
  await refresh();
  const notices = page.locator('[data-journal-notice]');
  await expect(notices).toHaveCount(0);

  const posts = [];
  page.on('request', req => { if (req.method() === 'POST' && req.url().endsWith('/journal/consulter')) posts.push(req.url()); });
  await page.goto(overview);
  await expect(notices).toHaveCount(5);
  await expect(notices.first()).toContainText(initial.at(-1));
  await expect(page.locator('.dashboard-journal')).toContainText('5 des 8 événements');
  await expect(page.locator('.dashboard-journal')).not.toContainText('History-' + id);
  await expect(notices.first()).not.toBeInViewport();
  expect(posts).toHaveLength(0);

  // Une autre consultation de la page libère une arrivée sans modifier le lot capturé par l’onglet.
  insert(late, "'arrival'");
  const native = await browser.newContext({
    baseURL, javaScriptEnabled: false, storageState: await page.context().storageState(),
    viewport: { width: 390, height: 844 },
  });
  const fallback = await native.newPage();
  await fallback.goto(overview);
  await expect(fallback.locator('[data-journal-notice]').first()).toContainText(late);
  const ack = page.waitForResponse(r => r.request().method() === 'POST' && r.url().endsWith('/journal/consulter'));
  await page.bringToFront();
  await notices.first().scrollIntoViewIfNeeded();
  const response = await ack;
  expect(await response.json()).toEqual({ unread: true });
  await expect(page.locator('.dashboard-journal [role="status"]')).toHaveText('Journal consulté.');
  await expect(notices).toHaveCount(5);
  await refresh();
  await expect(notices).toHaveCount(5);
  await expect(notices.first()).toContainText(initial.at(-1));
  expect(posts).toHaveLength(1);

  try {
    await fallback.reload();
    await expect(fallback.locator('[data-journal-notice]')).toHaveCount(1);
    await expect(fallback.locator('[data-journal-notice]')).toContainText(late);
    const consult = fallback.getByRole('button', { name: 'Marquer le journal comme consulté' });
    await consult.focus();
    expect(await consult.evaluate(el => el.matches(':focus-visible'))).toBe(true);
    await fallback.keyboard.press('Enter');
    await expect(fallback.locator('[data-journal-notice]')).toHaveCount(0);
    await expect(fallback.locator('.dashboard-journal')).toContainText('Aucun événement d’absence à consulter');
  } finally {
    await native.close();
  }
  await page.waitForLoadState('load');
  const overflow = await page.evaluate(() => [...document.querySelectorAll('body *')].map(el => ({ tag: el.tagName, class: el.className, right: el.getBoundingClientRect().right, left: el.getBoundingClientRect().left })).filter(el => el.right > window.innerWidth + 1).slice(0, 12));
  if (overflow.length) console.log('Débordement après chargement :', JSON.stringify(overflow));
  await expect.poll(() => page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
});
