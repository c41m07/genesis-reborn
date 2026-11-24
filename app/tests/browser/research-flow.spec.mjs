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
  await page.getByRole('navigation', { name: 'Navigation principale', exact: true }).getByRole('link', { name: 'Bâtiments', exact: true }).click(); await lab().getByRole('button', { name: /Améliorer .* niveau 1/ }).click();
  await expect(page.locator('#item-lab-basic .catalogue-level-badge')).toHaveText('Niv. 1', { timeout: 30000 });
  await page.getByRole('navigation', { name: 'Navigation principale', exact: true }).getByRole('link', { name: 'Recherche', exact: true }).click(); await expect(page.locator('.technology-tree')).toHaveCount(0);
  const research = page.locator('#item-engineering_foundations-basic');
  await expect(research.getByRole('button', { name: /Rechercher le niveau 1/ })).toBeVisible(); await research.getByRole('button', { name: /Rechercher le niveau 1/ }).click();
  const queue = page.locator('.scene-operation[data-queue-type="research"]');
  const summary = queue.locator(':scope > summary');
  await expect(summary).toContainText('Génie civil colonial');
  const bar = summary.getByRole('progressbar'), first = Number(await bar.getAttribute('aria-valuenow'));
  await expect.poll(async () => Number(await bar.getAttribute('aria-valuenow')), { timeout: 10000 }).toBeGreaterThan(first);
  await expect.poll(async () => { await page.reload(); return (await page.locator('#item-engineering_foundations-basic .catalogue-level-badge').textContent()).trim(); }, { timeout: 30000, intervals: [500, 1000, 2000] }).toBe('Niv. 1');
  await expect(summary).toContainText('File disponible');
  await expect(summary.getByText('Génie civil colonial')).toHaveCount(0);
  if (!await queue.evaluate(element => element.open)) await summary.press('Enter');
  await expect(queue.getByText('Aucune recherche en cours', { exact: true })).toBeVisible();
  await page.getByRole('navigation', { name: 'Navigation principale', exact: true }).getByRole('link', { name: 'Guide technologique', exact: true }).click(); await expect(page.locator('#tech-engineering_foundations-basic')).toContainText('Niveau 1 / 25');
  await page.setViewportSize({ width: 390, height: 844 });
  await expect.poll(() => page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
  await expect(page.locator('.technology-tree')).toBeVisible();
  await expect(page.locator('#tech-engineering_foundations-basic')).toBeVisible();
});

test('La file Recherche vide garde le lien du Guide accessible sans masquer Premiers pas', async ({ page }) => {
  await page.setViewportSize({ width: 390, height: 844 });
  await enter(page);
  const colony = new URL(page.url()).pathname;
  await page.goto(colony + '?section=technologies');
  const queue = page.locator('.catalog-queue-ribbon [data-queue-type="research"]');
  await expect(queue).toHaveAttribute('data-queue-occupied', 'false');
  await queue.locator(':scope > summary').press('Enter');
  await expect(queue.getByText('Aucune recherche en cours', { exact: true })).toBeVisible();
  const helper = page.locator('.app-frame > .first-steps-popup-enhanced[open]');
  await expect(helper.locator('.first-steps-popup-mount')).toBeVisible();
  const guide = queue.getByRole('link', { name: 'Voir le guide technologique', exact: true });
  await guide.scrollIntoViewIfNeeded();
  expect(await guide.evaluate(el => {
    const r = el.getBoundingClientRect();
    return [0.25, 0.75].every(y => el.contains(document.elementFromPoint(r.x + r.width / 2, r.y + y * r.height)));
  })).toBe(true);
  const [action, help] = await Promise.all([guide.boundingBox(), helper.boundingBox()]);
  expect(help.y).toBeGreaterThanOrEqual(action.y + action.height);
  await guide.focus();
  await expect(guide).toBeFocused();
  await page.keyboard.press('Enter');
  await expect(page).toHaveURL(/technologies$/);
});
