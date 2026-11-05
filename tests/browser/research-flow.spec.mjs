import { test, expect } from './support/isolated-test.mjs';
import { prepareOwnedColony } from './support/game-fixtures.mjs';
async function enter(page) {
  const id = crypto.randomUUID().slice(0, 8), email = `research-${id}@example.test`, password = 'Browser-test-2026!';
  await page.goto('/inscription');
  await page.getByLabel('Adresse email').fill(email); await page.getByLabel('Nom de commandant').fill(`Research-${id}`);
  await page.getByLabel('Mot de passe (12 caractères minimum)', { exact: true }).fill(password); await page.getByLabel('Confirmer le mot de passe').fill(password);
  await page.getByRole('button', { name: 'Créer mon compte' }).click(); await expect(page).toHaveURL(/connexion/);
  await page.getByLabel('Adresse email').fill(email); await page.getByLabel('Mot de passe', { exact: true }).fill(password); await page.getByRole('button', { name: /Connexion/ }).click();
  await page.locator('article.universe-card').filter({ has: page.getByRole('heading', { name: /Turbo/ }) }).getByRole('button').click(); await expect(page).toHaveURL(/planetes\/\d+$/);
  return email;
}
test('Laboratoire débloque une recherche, la file progresse et le résultat est appliqué', async ({ page }) => {
  test.setTimeout(90000); const email = await enter(page);
  // Le laboratoire achevé reste consultable quand le niveau suivant est verrouillé.
  prepareOwnedColony(process.env.BROWSER_ROUTER, email, { showLockedItems: true });
  const lab = () => page.locator('#item-lab-basic');
  await page.getByRole('link', { name: 'Bâtiments', exact: true }).click(); await lab().getByRole('button', { name: /Améliorer .* niveau 1/ }).click();
  await expect(lab().locator('.badge')).toContainText('NIVEAU 1', { timeout: 30000 });
  await page.getByRole('link', { name: 'Recherche', exact: true }).click(); await expect(page.locator('.technology-tree')).toHaveCount(0);
  const research = page.locator('#item-engineering_foundations-basic');
  await expect(research.getByRole('button', { name: /Rechercher .* niveau 1/ })).toBeVisible(); await research.getByRole('button', { name: /Rechercher .* niveau 1/ }).click();
  const queue = page.locator('.scene-operation[data-queue-type="research"]'); await expect(queue.locator('.operation-name')).toContainText('Génie civil colonial');
  const bar = queue.getByRole('progressbar'), first = Number(await bar.getAttribute('aria-valuenow'));
  await expect.poll(async () => Number(await bar.getAttribute('aria-valuenow')), { timeout: 10000 }).toBeGreaterThan(first);
  await expect.poll(async () => { await page.reload(); return research.locator('.badge').textContent(); }, { timeout: 30000, intervals: [500, 1000, 2000] }).toContain('NIVEAU 1');
  await expect(queue.locator('.operation-idle')).toContainText('Aucune commande en cours'); await page.getByRole('link', { name: 'Technologie', exact: true }).click(); await expect(page.locator('#tech-engineering_foundations-basic')).toContainText('Niveau 1 / 25');
  await page.setViewportSize({ width: 390, height: 844 });
  await expect.poll(() => page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
  await expect(page.locator('.technology-tree')).toBeVisible();
  await expect(page.locator('#tech-engineering_foundations-basic')).toBeVisible();
});
