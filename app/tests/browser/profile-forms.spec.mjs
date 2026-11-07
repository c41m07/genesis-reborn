import { test, expect } from './support/isolated-test.mjs';

test('Profil : erreur explicite, champs conservés, confirmation et reconnexion', async ({ page }, testInfo) => {
  const id = crypto.randomUUID().slice(0, 8);
  const email = `profile-${id}@example.test`;
  const password = 'Browser-test-2026!';
  const username = `Profile-${id}`;
  await page.goto('/inscription');
  await page.getByLabel('Adresse email').fill(email);
  await page.getByLabel('Nom de commandant').fill(username);
  await page.getByLabel('Mot de passe (12 caractères minimum)', { exact: true }).fill(password);
  await page.getByLabel('Confirmer le mot de passe').fill(password);
  await page.getByRole('button', { name: 'Créer mon compte' }).click();
  await expect(page).toHaveURL(/connexion/);
  const login = async () => {
    await page.getByLabel('Adresse email').fill(email);
    await page.getByLabel('Mot de passe', { exact: true }).fill(password);
    await page.getByRole('button', { name: /Connexion/ }).click();
  };
  await login();
  await page.goto('/profil');
  await expect(page.getByRole('group', { name: 'Identifiants', exact: true })).toBeVisible();
  await expect(page.getByRole('group', { name: 'Changer de mot de passe', exact: true })).toBeVisible();
  for (const width of [1920, 1440, 1280, 768, 390]) {
    await page.setViewportSize({ width, height: 900 });
    expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(width);
  }
  await page.screenshot({ path: testInfo.outputPath('profile-390.png'), fullPage: true, animations: 'disabled' });
  await page.getByLabel('Mot de passe actuel', { exact: true }).fill('Incorrect-password-2026!');
  await page.getByRole('button', { name: 'Enregistrer mon profil' }).click();
  await expect(page.getByRole('alert')).toContainText('Profil non enregistré');
  await expect(page.getByRole('alert')).toContainText('Mot de passe actuel incorrect');
  await expect(page.getByLabel('Adresse email')).toHaveValue(email);
  await expect(page.getByLabel('Nom de commandant')).toHaveValue(username);
  await page.getByLabel('Nom de commandant').fill(`${username}-Edit`);
  await page.getByLabel('Mot de passe actuel', { exact: true }).fill(password);
  await page.getByRole('button', { name: 'Enregistrer mon profil' }).click();
  await expect(page).toHaveURL(/connexion/);
  await expect(page.getByRole('status')).toContainText('Profil enregistré');
  await login();
  await page.goto('/profil');
  await expect(page.getByLabel('Nom de commandant')).toHaveValue(`${username}-Edit`);
  await page.getByRole('link', { name: 'Paramètres', exact: true }).click();
  await expect(page.getByRole('group', { name: 'Confort de lecture', exact: true })).toBeVisible();
  await page.screenshot({ path: testInfo.outputPath('preferences-dark-390.png'), fullPage: true, animations: 'disabled' });
});
