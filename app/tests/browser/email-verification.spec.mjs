import { test, expect } from './support/isolated-test.mjs';
import { execFileSync } from 'node:child_process';

test.use({ verifyRegisteredAccounts: false });

test('Un nouveau compte confirme son adresse avant de se connecter', async ({ page }) => {
  const suffix = crypto.randomUUID().slice(0, 8);
  const email = `verification-${suffix}@example.test`;
  const password = 'Browser-Verification-2026!';
  await page.goto('/inscription');
  await page.getByLabel('Adresse email').fill(email);
  await page.getByLabel('Nom de commandant').fill(`Verify-${suffix}`);
  await page.getByLabel('Mot de passe (12 caractères minimum)', { exact: true }).fill(password);
  await page.getByLabel('Confirmer le mot de passe').fill(password);
  await page.getByRole('button', { name: 'Créer mon compte' }).click();
  await expect(page).toHaveURL(/connexion$/);
  await page.getByLabel('Adresse email').fill(email);
  await page.getByLabel('Mot de passe', { exact: true }).fill(password);
  await page.getByRole('button', { name: /Connexion/ }).click();
  await expect(page.getByRole('alert')).toContainText('Connexion impossible');

  // Lien connu uniquement dans la base jetable ; aucun envoi externe nécessaire.
  const token = 'BrowserVerification_0123456789abcdef0123456789';
  const query = `UPDATE player_email_verification_token t JOIN player p ON p.id=t.player_id SET t.token_hash=SHA2('${token}',256) WHERE p.email='${email}'`;
  execFileSync('docker', ['compose', '-p', process.env.BROWSER_ROUTER, '--env-file', '.env.local', 'exec', '-T', 'app', 'php', 'bin/console', 'dbal:run-sql', query], { stdio: 'pipe' });
  await page.goto('/inscription/confirmer/' + token);
  await page.getByRole('button', { name: 'Confirmer mon adresse' }).click();
  await expect(page.getByRole('status')).toContainText('Adresse email confirmée');
  await page.getByLabel('Adresse email').fill(email);
  await page.getByLabel('Mot de passe', { exact: true }).fill(password);
  await page.getByRole('button', { name: /Connexion/ }).click();
  await expect(page.locator('article.universe-card').first()).toBeVisible();
});
