import { test, expect } from './support/isolated-test.mjs';

test('La fiche de la mine possède un seul défilement et rend le focus après Échap', async ({ page }) => {
  const id = crypto.randomUUID().slice(0, 8);
  const email = 'mine-dialog-' + id + '@example.test';
  const password = crypto.randomUUID() + crypto.randomUUID().toUpperCase();
  await page.goto('/inscription');
  await page.getByLabel('Adresse email').fill(email);
  await page.getByLabel('Nom de commandant').fill('Mine-' + id);
  await page.getByLabel('Mot de passe (12 caractères minimum)', { exact: true }).fill(password);
  await page.getByLabel('Confirmer le mot de passe').fill(password);
  await page.getByRole('button', { name: 'Créer mon compte' }).click();
  await expect(page).toHaveURL(/connexion/);
  await page.getByLabel('Adresse email').fill(email);
  await page.getByLabel('Mot de passe', { exact: true }).fill(password);
  await page.getByRole('button', { name: /Connexion/ }).click();
  await page.locator('article.universe-card').first().getByRole('button').click();
  await expect(page).toHaveURL(/planetes\/\d+$/);
  await page.goto(new URL(page.url()).pathname + '?section=buildings');
  const mine = page.locator('#item-mine_metal-basic');
  const trigger = mine.getByRole('button', { name: 'Informations sur Mine à ciel ouvert', exact: true });
  const dialog = mine.getByRole('dialog', { name: 'Mine à ciel ouvert', exact: true });
  for (const [width, height] of [[1366, 650], [390, 590]]) {
    await page.setViewportSize({ width, height });
    await trigger.focus();
    await page.keyboard.press('Enter');
    await expect(dialog).toBeVisible();
    const scrollOwners = await dialog.evaluate(element => [element, ...element.querySelectorAll('*')]
      .filter(node => /auto|scroll/.test(getComputedStyle(node).overflowY) && node.scrollHeight > node.clientHeight + 1)
      .map(node => node.className));
    expect(scrollOwners).toEqual(['catalog-info-dialog']);
    const bounds = await dialog.boundingBox();
    expect(bounds.height).toBeLessThan(height);
    expect(bounds.width).toBeLessThanOrEqual(width);
    await page.keyboard.press('End');
    await expect.poll(() => dialog.evaluate(element => Math.abs(element.scrollHeight - element.clientHeight - element.scrollTop))).toBeLessThanOrEqual(1);
    await page.keyboard.press('Escape');
    await expect(dialog).toHaveCount(0);
    await expect(trigger).toBeFocused();
  }
});
