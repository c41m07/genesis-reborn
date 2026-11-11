import { test, expect } from './support/isolated-test.mjs';
import AxeBuilder from '@axe-core/playwright';
import { runIsolatedSql } from './support/game-fixtures.mjs';

async function enter(page, name, email) {
  await page.goto('/inscription');
  await page.getByLabel('Adresse email').fill(email);
  await page.getByLabel('Nom de commandant').fill(name);
  await page.getByLabel('Mot de passe (12 caractères minimum)', { exact: true }).fill('Personal-browser-2026!');
  await page.getByLabel('Confirmer le mot de passe').fill('Personal-browser-2026!');
  await page.getByRole('button', { name: 'Créer mon compte' }).click();
  await expect(page).toHaveURL(/connexion/);
  await page.getByLabel('Adresse email').fill(email);
  await page.getByLabel('Mot de passe', { exact: true }).fill('Personal-browser-2026!');
  await page.getByRole('button', { name: /Connexion/ }).click();
  await page.locator('.universe-card').filter({ has: page.getByRole('heading', { name: /Turbo/ }) }).getByRole('button').click();
}

test('Rivalité personnelle sans guilde, paix bilatérale et nouvelle alliance', async ({ page, browser, baseURL }) => {
  test.setTimeout(150000);
  const id = crypto.randomUUID().slice(0, 8);
  const actorName = 'RivalA-' + id;
  const targetName = 'RivalB-' + id;
  const targetContext = await browser.newContext({ baseURL });
  const targetPage = await targetContext.newPage();
  const relations = async currentPage => {
    await currentPage.goto('/diplomatie?tab=relations');
    return currentPage.locator('#alliance-members .commander-card').first();
  };
  try {
    await enter(page, actorName, 'rivala-' + id + '@example.test');
    await enter(targetPage, targetName, 'rivalb-' + id + '@example.test');
    await page.goto('/diplomatie?tab=relations&q=' + encodeURIComponent(targetName));
    await page.locator('.commander-card').first().getByRole('button', { name: 'Proposer une alliance', exact: true }).click();
    let targetCard = await relations(targetPage);
    await targetCard.getByRole('button', { name: "Accepter l'alliance", exact: true }).click();
    let actorCard = await relations(page);
    await expect(actorCard).toContainText('Allié');
    await actorCard.getByText('Déclarer une rivalité', { exact: true }).focus();
    await page.keyboard.press('Enter');
    await expect(actorCard.getByRole('button', { name: 'Confirmer la rivalité' })).toBeVisible();
    await page.keyboard.press('Tab');
    await expect(actorCard.getByRole('button', { name: 'Confirmer la rivalité' })).toBeFocused();
    await page.keyboard.press('Enter');
    await expect(actorCard).toContainText('Rival personnel');
    targetCard = await relations(targetPage);
    await expect(targetCard).toContainText('Rival personnel');
    runIsolatedSql(process.env.BROWSER_ROUTER, "UPDATE leaderboard_snapshot SET updated_at=DATE_SUB(UTC_TIMESTAMP(), INTERVAL 2 DAY)");
    await page.goto('/classement');
    await expect(page.getByRole('row').filter({ hasText: targetName })).toHaveClass(/ranking-row--enemy/);
    actorCard = await relations(page);
    await actorCard.getByRole('button', { name: 'Proposer la paix', exact: true }).click();
    await expect(page).toHaveURL(url => url.searchParams.get('tab') === 'relations');
    actorCard = await relations(page);
    await expect(actorCard).toContainText('Proposition de paix envoyée');
    await expect(actorCard.getByRole('button', { name: 'Accepter la paix' })).toHaveCount(0);
    const token = await actorCard.locator('input[name="_token"]').inputValue();
    const target = await actorCard.locator('input[name="target"]').inputValue();
    const forbidden = await page.evaluate(async form => {
      const response = await fetch('/diplomatie?tab=relations', { method: 'POST', body: new URLSearchParams(form) });
      return response.text();
    }, { _token: token, target, action: 'break' });
    expect(forbidden).toContain('La rivalité ne peut se terminer');
    targetCard = await relations(targetPage);
    await expect(targetCard).toContainText('Proposition de paix reçue');
    await targetCard.getByRole('button', { name: 'Refuser la paix', exact: true }).click();
    await expect(targetPage).toHaveURL(url => url.searchParams.get('tab') === 'relations');
    targetCard = await relations(targetPage);
    await expect(targetCard).toContainText('Rival personnel');
    await targetCard.getByRole('button', { name: 'Proposer la paix', exact: true }).click();
    await expect(targetPage).toHaveURL(url => url.searchParams.get('tab') === 'relations');
    actorCard = await relations(page);
    await actorCard.getByRole('button', { name: 'Accepter la paix', exact: true }).click();
    await expect(page).toHaveURL(url => url.searchParams.get('tab') === 'relations');
    actorCard = await relations(page);
    await expect(actorCard).toContainText('Relation personnelle terminée');
    await expect(actorCard.getByRole('button', { name: "Accepter l'alliance", exact: true })).toHaveCount(0);
    for (const width of [1440, 390]) {
      await page.setViewportSize({ width, height: 900 });
      await expect.poll(() => page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(width);
      const result = await new AxeBuilder({ page }).include('#alliance-members').withTags(['wcag2a', 'wcag2aa', 'wcag21aa']).analyze();
      expect(result.violations).toEqual([]);
    }
    await actorCard.getByRole('button', { name: 'Proposer une alliance', exact: true }).click();
    targetCard = await relations(targetPage);
    await targetCard.getByRole('button', { name: "Accepter l'alliance", exact: true }).click();
    actorCard = await relations(page);
    await expect(actorCard).toContainText('Allié');
  } finally {
    await targetContext.close();
  }
});
