import { test, expect } from './support/isolated-test.mjs';

async function registerAndJoin(page, prefix) {
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
  await page.locator('article.universe-card').filter({ has: page.getByRole('heading', { name: /Turbo/ }) }).getByRole('button').click();
  await expect(page).toHaveURL(/planetes\/\d+$/);
  return username;
}

test('Classement : tris, contact direct et échange de messages privés', async ({ page, browser }) => {
  test.setTimeout(90000);
  const recipientPage = await browser.newPage();
  const senderName = await registerAndJoin(page, 'Rank-Sender');
  const recipientName = await registerAndJoin(recipientPage, 'Rank-Recipient');

  await page.goto('/classement');
  await expect(page.getByRole('table', { name: /Classement de l’univers Turbo/ })).toBeVisible();
  const recipientRow = page.getByRole('row').filter({ hasText: recipientName });
  await expect(recipientRow.getByRole('link', { name: 'Contacter ' + recipientName })).toBeVisible();
  await page.getByRole('link', { name: 'Flotte et défenses', exact: true }).click();
  await expect(page.getByRole('columnheader', { name: /Flotte et défenses/ })).toHaveAttribute('aria-sort', 'descending');
  await page.getByRole('region', { name: 'Classement défilant horizontalement' }).getByRole('link', { name: 'Planètes', exact: true }).click();
  await expect(page.getByRole('columnheader', { name: 'Planètes' })).toHaveAttribute('aria-sort', 'descending');

  const body = 'Message depuis le classement ' + crypto.randomUUID();
  await page.getByRole('link', { name: 'Contacter ' + recipientName }).click();
  await expect(page).toHaveURL(/messages\?with=\d+/);
  await expect(page.getByRole('heading', { name: 'Conversation avec ' + recipientName, exact: true })).toBeVisible();
  await page.getByLabel('Votre message').fill(body);
  await page.getByRole('button', { name: 'Envoyer', exact: true }).click();
  await expect(page.getByRole('status')).toContainText('Message privé envoyé');
  await expect(page.locator('.message-thread')).toContainText(body);

  await recipientPage.goto('/messages');
  await recipientPage.getByRole('link', { name: new RegExp(senderName) }).click();
  await expect(recipientPage.locator('.message-thread')).toContainText(body);
  await recipientPage.close();
});
