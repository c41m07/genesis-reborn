import { test, expect } from './support/isolated-test.mjs';

async function enter(page) {
  const id = crypto.randomUUID().slice(0, 8);
  const email = 'spy-ui-' + id + '@example.test';
  const password = 'Browser-test-2026!';
  await page.goto('/inscription');
  await page.getByLabel('Adresse email').fill(email);
  await page.getByLabel('Nom de commandant').fill('Spy-UI-' + id);
  await page.getByLabel('Mot de passe (12 caractères minimum)', { exact: true }).fill(password);
  await page.getByLabel('Confirmer le mot de passe').fill(password);
  await page.getByRole('button', { name: 'Créer mon compte' }).click();
  await expect(page).toHaveURL(/connexion/);
  await page.getByLabel('Adresse email').fill(email);
  await page.getByLabel('Mot de passe', { exact: true }).fill(password);
  await page.getByRole('button', { name: /Connexion/ }).click();
  await page.locator('article.universe-card').filter({ has: page.getByRole('heading', { name: /Turbo/ }) }).getByRole('button').click();
  await expect(page).toHaveURL(/planetes\/\d+$/);
}

test('Espionnage PNJ : envoyer une sonde et consulter le rapport privé', async ({ page }) => {
  test.setTimeout(180000);
  await enter(page);
  const card = name => page.locator('article.catalog-card').filter({ has: page.getByRole('heading', { name, exact: true }) });
  await page.getByRole('link', { name: 'Bâtiments', exact: true }).click();
  await card('Chantier spatial').getByRole('button', { name: /Améliorer au niveau 1/ }).click();
  await expect(card('Chantier spatial').locator('.badge')).toContainText('NIVEAU 1', { timeout: 30000 });
  await page.getByRole('link', { name: 'Chantier spatial', exact: true }).click();
  await card('Sonde exploratrice').getByRole('button', { name: 'Construire', exact: true }).click();
  await expect(card('Sonde exploratrice').locator('.badge')).toContainText('1 EN RÉSERVE', { timeout: 60000 });

  await page.getByRole('link', { name: 'Flottes et missions', exact: true }).click();
  const form = page.locator('.fleet-form form').first();
  await form.locator('[name="ships[probe]"]').fill('1');
  await form.getByRole('button', { name: /Choisir la destination/ }).click();
  await form.locator('[name="mission"]').selectOption('spy');
  const targets = await form.locator('[name="planet"] option').evaluateAll(options => options.map(option => ({ value: option.value, text: option.textContent || '' })));
  const target = targets.find(option => option.text.includes('PNJ'));
  expect(target, 'une planète PNJ connue doit être disponible').toBeTruthy();
  await form.locator('[name="planet"]').selectOption(target.value);
  await form.getByRole('button', { name: /Vérifier la mission/ }).click();
  await form.getByRole('button', { name: 'Estimer le trajet' }).click();
  await expect(form.locator('.flight-estimate')).toContainText('Carburant aller-retour');
  await form.getByRole('button', { name: /Envoyer la flotte/ }).click();
  await expect(page.locator('[data-status="traveling"]')).toHaveCount(1);
  await expect.poll(() => page.locator('[data-status="completed"]').count(), { timeout: 90000, intervals: [1000] }).toBe(1);

  await page.getByRole('link', { name: 'Rapports', exact: true }).click();
  const reportLink = page.locator('a[href*="/rapports/espionnage/"]').first();
  await expect(reportLink).toBeVisible();
  await page.screenshot({ path: 'docs/validation/rapports-espionnage-etat.png', fullPage: true });
  const reportName = (await reportLink.locator('.report-copy strong').textContent()).replace('Renseignements · ', '').trim();
  await reportLink.click();
  await expect(page.getByRole('heading', { name: reportName, exact: true })).toBeVisible();
  await expect(page.getByRole('status')).toContainText(/Sondes détectées|catégorie.*renseignements obtenue/);
  await expect(page.getByRole('link', { name: 'Retour aux rapports' })).toBeVisible();
  await page.screenshot({ path: 'docs/validation/rapport-espionnage-detail.png', fullPage: true });
  const reportUrl = page.url();
  await page.setViewportSize({ width: 390, height: 844 });
  await page.goto(reportUrl);
  await expect.poll(() => page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(390);
  await page.evaluate(() => window.scrollTo(0, 0));
  await page.screenshot({ path: 'docs/validation/rapport-espionnage-mobile.png', fullPage: true });
});
