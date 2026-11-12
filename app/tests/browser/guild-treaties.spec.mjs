import { test, expect } from './support/isolated-test.mjs';
import AxeBuilder from '@axe-core/playwright';

async function register(page, name, email) {
  await page.goto('/inscription');
  await page.getByLabel('Adresse email').fill(email);
  await page.getByLabel('Nom de commandant').fill(name);
  await page.getByLabel('Mot de passe (12 caractères minimum)', { exact: true }).fill('Guild-ranks-2026!');
  await page.getByLabel('Confirmer le mot de passe').fill('Guild-ranks-2026!');
  await page.getByRole('button', { name: 'Créer mon compte' }).click();
  await expect(page).toHaveURL(/connexion/);
  await page.getByLabel('Adresse email').fill(email);
  await page.getByLabel('Mot de passe', { exact: true }).fill('Guild-ranks-2026!');
  await page.getByRole('button', { name: /Connexion/ }).click();
  await page.locator('article.universe-card').filter({ has: page.getByRole('heading', { name: /Omega/ }) }).getByRole('button').click();
}

async function guildCard(page, name) {
  await page.goto('/diplomatie?tab=diplomacy&q=' + encodeURIComponent(name));
  return page.getByRole('region', { name: 'Résultats de recherche de guildes' }).locator('.guild-card');
}

test('Les trois traités ont deux signatures, des droits distincts et une paix bilatérale', async ({ page, browser, baseURL }) => {
  test.setTimeout(120000);
  const id = crypto.randomUUID().slice(0, 8);
  const guildA = 'TraiteA-' + id;
  const guildB = 'TraiteB-' + id;
  const otherContext = await browser.newContext({ baseURL });
  const other = await otherContext.newPage();
  try {
    for (const [current, name] of [[page, guildA], [other, guildB]]) {
      await register(current, name, name.toLowerCase() + '@example.test');
      await current.goto('/diplomatie');
      await current.getByLabel('Nom de votre nouvelle guilde').fill(name);await current.getByLabel('Tag de la guilde', { exact:true }).fill(crypto.randomUUID().replaceAll('-', '').slice(0,5).toUpperCase());
      await current.getByRole('button', { name: 'Fonder une guilde' }).click();
      await expect(current.locator('.guild-hub-hero')).toContainText(name);
    }
    let cardA = await guildCard(page, guildB);
    await cardA.getByLabel('Traité à proposer').selectOption('non_aggression');
    await cardA.getByRole('button', { name: 'Proposer le traité' }).focus();
    await page.keyboard.press('Enter');
    await expect(cardA).toContainText('proposition envoyée');
    await expect(cardA.getByRole('button', { name: 'Accepter le traité' })).toHaveCount(0);
    const stale = await cardA.locator('form').filter({ hasText: 'Retirer la proposition' }).evaluate(form => Object.fromEntries(new FormData(form)));
    let cardB = await guildCard(other, guildA);
    await cardB.getByRole('button', { name: 'Accepter le traité' }).click();
    await expect(cardB.locator('.badge')).toHaveText('Pacte de non-agression');
    const staleResponse = await page.evaluate(async form => (await fetch('/diplomatie', {
      method: 'POST', body: new URLSearchParams({ ...form, treaty_action: 'decline' }),
    })).text(), stale);
    expect(staleResponse).toContain('La relation a changé');

    cardA = await guildCard(page, guildB);
    await expect(cardA.locator('.badge')).toHaveText('Pacte de non-agression');
    await cardA.getByLabel('Traité à proposer').selectOption('defense');
    await cardA.getByRole('button', { name: 'Proposer le traité' }).click();
    await expect(cardA.locator('.badge')).toHaveText('Pacte de non-agression');
    cardB = await guildCard(other, guildA);
    await cardB.getByRole('button', { name: 'Accepter le traité' }).click();
    await expect(cardB.locator('.badge')).toHaveText('Pacte de défense');
    await expect(cardB).toContainText('accord du propriétaire');
    await expect(cardB).toContainText('Aucun droit supplémentaire de transport');

    cardA = await guildCard(page, guildB);
    await cardA.getByLabel('Traité à proposer').selectOption('allies');
    await cardA.getByRole('button', { name: 'Proposer le traité' }).click();
    cardB = await guildCard(other, guildA);
    await cardB.getByRole('button', { name: 'Accepter le traité' }).click();
    await expect(cardB.locator('.badge')).toHaveText('Alliés');
    await cardB.getByText('Déclarer ennemis', { exact: true }).click();
    await cardB.getByRole('button', { name: 'Confirmer l’hostilité' }).click();
    await expect(cardB.locator('.badge')).toHaveText('Ennemis');
    await cardB.getByRole('button', { name: 'Proposer la paix' }).click();
    await expect(cardB.locator('.badge')).toHaveText('Ennemis');
    await expect(cardB.getByRole('button', { name: 'Accepter la paix' })).toHaveCount(0);
    cardA = await guildCard(page, guildB);
    await cardA.getByRole('button', { name: 'Refuser la proposition' }).click();
    await expect(cardA.locator('.badge')).toHaveText('Ennemis');
    await cardA.getByRole('button', { name: 'Proposer la paix' }).click();
    cardB = await guildCard(other, guildA);
    await cardB.getByRole('button', { name: 'Accepter la paix' }).click();
    await expect(cardB.locator('.badge')).toHaveText('Neutre');
    await cardB.getByText('Historique des traités · 10 dernières actions maximum', { exact: true }).click();
    await expect(cardB).toContainText('Signature');
    await expect(cardB).toContainText('Refus ou retrait');
    await expect(cardB.locator('ol li')).toHaveCount(10);
    for (const width of [1440, 768, 390]) {
      await other.setViewportSize({ width, height: 900 });
      await expect.poll(() => other.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(width);
      const result = await new AxeBuilder({ page: other }).include('.guild-card').withTags(['wcag2a', 'wcag2aa', 'wcag21aa']).analyze();
      expect(result.violations).toEqual([]);
    }
  } finally {
    await otherContext.close();
  }
});
