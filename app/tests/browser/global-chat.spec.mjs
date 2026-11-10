import { test, expect } from './support/isolated-test.mjs';
import AxeBuilder from '@axe-core/playwright';
import { queryIsolatedSql } from './support/game-fixtures.mjs';

async function registerAndJoin(page, prefix, universe = 'Turbo') {
  const id = crypto.randomUUID().slice(0, 8);
  const email = prefix.toLowerCase() + '-' + id + '@example.test';
  const username = prefix + '-' + id;
  const password = 'Browser-test-2026!';
  await page.goto('/inscription');
  await page.getByLabel('Adresse email').fill(email);
  await page.getByLabel('Nom de commandant').fill(username);
  await page.getByLabel('Mot de passe (12 caractères minimum)', { exact: true }).fill(password);
  await page.getByLabel('Confirmer le mot de passe').fill(password);
  await page.getByRole('button', { name: 'Créer mon compte' }).click();
  await expect(page).toHaveURL(/connexion/);
  await page.getByLabel('Adresse email').fill(email);
  await page.getByLabel('Mot de passe', { exact: true }).fill(password);
  await page.getByRole('button', { name: /Connexion/ }).click();
  await page.locator('article.universe-card').filter({ has: page.getByRole('heading', { name: new RegExp(universe) }) }).getByRole('button').click();
  await expect(page).toHaveURL(/planetes\/\d+$/);
  return username;
}


test('Global : échanges sans notifications, badges ni compteurs, clavier et accessibilité', async ({ page, browser }) => {
  test.setTimeout(120000);
  const readerContext = await browser.newContext({ baseURL: process.env.PLAYWRIGHT_BASE_URL });
  const reader = await readerContext.newPage();
  await registerAndJoin(page, 'Global-Sender');
  await registerAndJoin(reader, 'Global-Reader');
  const project = process.env.BROWSER_ROUTER;
  const count = (table) => Number(queryIsolatedSql(project, 'SELECT COUNT(*) FROM ' + table));
  const notifications = count('notification');
  const outbox = Number(queryIsolatedSql(project, "SELECT COUNT(*) FROM outbox_message WHERE kind='notification'"));

  await page.goto('/messages');
  const globalLink = page.getByRole('navigation', { name: 'Conversations' }).getByRole('link', { name: /^Global/ });
  await expect(globalLink).toBeVisible();
  await globalLink.focus();
  await expect(globalLink).toBeFocused();
  await page.keyboard.press('Enter');
  await expect(page).toHaveURL(/messages\?channel=global$/);
  const content = 'Bonjour Global ' + crypto.randomUUID();
  await page.getByLabel('Message pour le canal Global').fill(content);
  await page.keyboard.press('Tab');
  await expect(page.getByRole('button', { name: 'Envoyer', exact: true })).toBeFocused();
  await page.keyboard.press('Enter');
  await expect(page.locator('.message-thread')).toContainText(content);

  await reader.goto('/messages?channel=global');
  await expect(reader.locator('.message-thread')).toContainText(content);
  await expect(reader.locator('.inbox-heading .badge')).toHaveCount(0);
  await expect(reader.locator('body')).toHaveAttribute('data-menu-notifications-messages-value', '0');
  await expect(reader.locator('[data-message-notifications-target="badge"]:visible')).toHaveCount(0);
  await expect(reader.locator('a[href="/messages?channel=global"]')).toHaveAttribute('aria-current', 'page');
  await expect(reader.locator('a[href="/messages?channel=global"] .message-unread-dot')).toHaveCount(0);
  const unread = await reader.evaluate(async () => (await fetch('/messages/non-lus?channel=global')).json());
  expect(unread.unread).toBe(0);
  expect(count('notification')).toBe(notifications);
  expect(Number(queryIsolatedSql(project, "SELECT COUNT(*) FROM outbox_message WHERE kind='notification'"))).toBe(outbox);

  for (const width of [1440, 390]) {
    await reader.setViewportSize({ width, height: 900 });
    await expect(reader.getByLabel('Message pour le canal Global')).toBeVisible();
    expect(await reader.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
    const results = await new AxeBuilder({ page: reader }).include('.inbox-layout').withTags(['wcag2a', 'wcag2aa', 'wcag21aa']).analyze();
    expect(results.violations).toEqual([]);
  }
  await readerContext.close();
});
