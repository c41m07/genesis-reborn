import { test, expect } from './support/isolated-test.mjs';

test('L’état Espionnage sans flotte conduit au chantier réel', async ({ page, browser }) => {
  const id = crypto.randomUUID().slice(0, 8);
  const email = 'fleet-empty-' + id + '@example.test';
  const password = 'Browser-test-2026!';

  await page.goto('/inscription');
  await page.getByLabel('Adresse email').fill(email);
  await page.getByLabel('Nom de commandant').fill('Empty-' + id);
  await page.getByLabel('Mot de passe (12 caractères minimum)', { exact: true }).fill(password);
  await page.getByLabel('Confirmer le mot de passe').fill(password);
  await page.getByRole('button', { name: 'Créer mon compte' }).click();
  await expect(page).toHaveURL(/connexion/);
  await page.getByLabel('Adresse email').fill(email);
  await page.getByLabel('Mot de passe', { exact: true }).fill(password);
  await page.getByRole('button', { name: /Connexion/ }).click();
  await page.locator('article.universe-card').filter({ has: page.getByRole('heading', { name: /Turbo/ }) }).getByRole('button').click();

  await page.goto('/flottes/preparer?mission=spy');
  await expect(page.getByRole('heading', { name: 'Journal des missions' })).toHaveCount(0);
  await expect(page.getByRole('heading', { name: 'Espionnage', level: 1 })).toBeVisible();
  await expect(page.getByRole('heading', { name: 'Aucune sonde exploratrice disponible' })).toBeVisible();

  for(const width of [1440,390]) {
    await page.setViewportSize({width,height:844});
    const heading=await page.locator('[data-live-region="mission-summary"] > .section-heading').boundingBox();
    const empty=await page.locator('[data-live-region="mission-summary"] > ul').boundingBox();
    expect(empty.y).toBeGreaterThanOrEqual(heading.y+heading.height);
    await expect(page.locator('[data-live-region="mission-summary"] > ul')).toContainText('cette colonie');
  }
  await page.setViewportSize({ width: 390, height: 844 });
  await expect.poll(() => page.locator('.sidebar').evaluate(element => element.getBoundingClientRect().right)).toBeLessThanOrEqual(0);
  await expect(page.locator('.sidebar')).toHaveAttribute('aria-hidden', 'true');
  await expect.poll(() => page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(390);

  const shipyard = page.getByRole('link', { name: /Accéder au chantier spatial/ });
  await expect(shipyard).toHaveAttribute('href', /planetes\/\d+\?section=ships/);
  let shipyardFocused = false;
  for (let index = 0; index < 50; index++) {
    await page.keyboard.press('Tab');
    if (await page.evaluate(() => document.activeElement?.getAttribute('href')) === await shipyard.getAttribute('href')) {
      shipyardFocused = true;
      break;
    }
  }
  expect(shipyardFocused).toBe(true);
  await expect(shipyard).toBeFocused();
  await expect.poll(() => shipyard.evaluate(element => element.matches(':focus-visible'))).toBe(true);

  await page.keyboard.press('Enter');
  await expect(page).toHaveURL(/planetes\/\d+\?section=ships/);
  await expect(page.getByRole('heading', { name: /Chantier spatial/ })).toBeVisible();

  const colony = new URL(page.url()).pathname;
  // Le raccourci Espionnage révèle déjà les verrouillés ; vérifier aussi l’ouverture directe.
  await page.goto(colony+'?section=ships');
  const catalog = page.locator('#panel-ships');
  const help = catalog.locator('[data-shipyard-empty]');
  const assembly = help.getByRole('link', { name: 'Voir le chantier d’assemblage' });
  const reveal = help.getByRole('link', { name: 'Voir les vaisseaux verrouillés' });
  await expect(help.getByRole('status')).toContainText('Aucun vaisseau débloqué sur cette colonie.');
  await expect(assembly).toHaveAttribute('href', colony+'?section=buildings&showLockedItems=1&focusItem=1#item-shipyard-basic');
  await expect(reveal).toHaveAttribute('href', colony+'?section=ships&showLockedItems=1');

  const emptyFilter = catalog.locator('[data-catalog-empty]');
  const filters = catalog.getByRole('navigation', { name: 'Filtrer le catalogue' });
  await filters.locator('button:not([data-category="all"])').first().click();
  await expect(help).toBeHidden();
  await expect(emptyFilter).toBeVisible();
  await filters.locator('button[data-category="all"]').click();
  await expect(help).toBeVisible();
  await expect(emptyFilter).toBeHidden();
  const visibility = catalog.getByRole('switch', { name: 'Afficher les éléments verrouillés' });
  await visibility.click();
  await expect(visibility).toHaveAttribute('aria-checked', 'true');
  await expect(help).toBeHidden();
  await expect(catalog.locator('#item-fighter-basic')).toBeVisible();
  await visibility.click();
  await expect(help).toBeVisible();

  const noScript = await browser.newContext({ javaScriptEnabled: false, viewport: { width: 390, height: 844 }, storageState: await page.context().storageState() });
  try {
    const native = await noScript.newPage();
    await native.goto(colony+'?section=ships');
    const nativeHelp = native.locator('[data-shipyard-empty]');
    await expect(nativeHelp).toBeVisible();
    await nativeHelp.getByRole('link', { name: 'Voir les vaisseaux verrouillés' }).click();
    await expect(nativeHelp).toBeHidden();
    const requirements = native.locator('#item-fighter-basic .catalog-prerequisites');
    const requiredBuilding = requirements.getByRole('link').first();
    await expect(requirements).not.toHaveAttribute('open');
    await requirements.getByText('Prérequis manquants', { exact: true }).click();
    await expect(requiredBuilding).toBeVisible();
    await expect(requiredBuilding).toHaveAttribute('href', /#item-/);
    await native.goto(colony+'?section=ships');
    await nativeHelp.getByRole('link', { name: 'Voir le chantier d’assemblage' }).click();
    await expect(native).toHaveURL(new RegExp(colony+'[?].*#item-shipyard-basic$'));
    await expect(native.locator('#item-shipyard-basic')).toBeVisible();
  } finally {
    await noScript.close();
  }

  const menuToggle = page.getByRole('button', { name: 'Plus' });
  await menuToggle.click();
  await expect(menuToggle).toHaveAttribute('aria-expanded', 'true');
  await expect(page.locator('.sidebar')).toHaveAttribute('aria-hidden', 'false');
  await expect.poll(() => page.locator('.sidebar').evaluate(element => element.getBoundingClientRect().left)).toBe(0);

  await page.keyboard.press('Escape');
  await expect(menuToggle).toHaveAttribute('aria-expanded', 'false');
  await expect(page.locator('.sidebar')).toHaveAttribute('aria-hidden', 'true');
  await expect.poll(() => page.locator('.sidebar').evaluate(element => element.getBoundingClientRect().right)).toBeLessThanOrEqual(0);
  await expect(menuToggle).toBeFocused();
});
