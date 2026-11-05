import { test, expect } from './support/isolated-test.mjs';

async function enter(page) {
  const id = crypto.randomUUID().slice(0, 8), email = `theme-${id}@example.test`, password = 'Browser-test-2026!';
  await page.goto('/inscription');
  await page.getByLabel('Adresse email').fill(email);
  await page.getByLabel('Nom de commandant').fill(`Theme-${id}`);
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

test('Le thème clair couvre les principaux écrans stratégiques sans débordement', async ({ page }) => {
  test.setTimeout(360000);
  await enter(page);
  await expect(page.locator('body')).toHaveAttribute('data-theme', 'dark');

  const colonyPath = new URL(page.url()).pathname;
  await page.goto('/profil/preferences');
  await page.getByLabel('Thème de l’interface').selectOption('light');
  await page.getByRole('button', { name: 'Enregistrer les paramètres' }).click();
  await expect(page.locator('body')).toHaveAttribute('data-theme', 'light');
  await page.setViewportSize({ width: 1440, height: 1000 });
  const screens = [
    ['/planetes', 'Planètes', 'planetes-theme.png'],
    ['/carte', 'Galaxie', 'carte-theme.png'],
    ['/flottes', 'Flottes et missions', 'flottes-theme.png'],
    ['/flottes?mission=spy', 'Espionnage', 'espionnage-composer-theme.png'],
    ['/diplomatie', 'Alliances', 'diplomatie-theme.png'],
    ['/messages', 'Messages', 'messages-theme.png'],
    ['/rapports', 'Rapports', 'rapports-theme.png'],
    ['/classement', 'Classement', 'classement-theme.png'],
    ['/univers', 'Les univers', 'univers-theme.png'],
    ['/profil', 'Mon profil', 'profil-theme.png'],
    ['/profil/preferences', 'Paramètres', 'preferences-theme.png'],
  ];
  const colonyScreens = [
    ['?section=buildings', 'Bâtiments.', 'batiments-theme.png'],
    ['?section=technologies', 'Recherche.', 'recherche-theme.png'],
    ['?section=ships', 'Chantier spatial.', 'chantier-theme.png'],
    ['?section=defenses', 'Défenses.', 'defenses-theme.png'],
  ];
  screens.push(...colonyScreens.map(([query, heading, capture]) => [colonyPath + query, heading, capture]));
  for (const [route, heading, capture] of screens) {
    const response = await page.goto(route);
    expect(response?.status(), route).toBeLessThan(400);
    await expect(page.getByRole('heading', { name: heading, exact: true })).toBeVisible();
    if (route.includes('?section=')) {
      await expect(page.locator('.colony-catalog-workspace')).toBeVisible();
      await expect(page.locator('.colony-catalog-aside .scene-operations')).toBeVisible();
    }
    if (route === '/flottes') await expect(page.getByRole('navigation', { name: 'Navigation principale' }).getByRole('link', { name: 'Flottes et missions' })).toHaveAttribute('aria-current', 'page');
    if (route === '/flottes?mission=spy') await expect(page.getByRole('navigation', { name: 'Navigation principale' }).getByRole('link', { name: 'Flottes et missions' })).toHaveAttribute('aria-current', 'page');
    await expect.poll(() => page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(1440);
    if (route === '/rapports') {
      for (const category of ['Combats', 'Économie', 'Espionnage', 'Tous']) {
        const tab = page.getByRole('navigation', { name: 'Filtrer les rapports' }).getByRole('link', { name: category, exact: true });
        await tab.click();
        await expect(tab).toHaveAttribute('aria-current', 'page');
      }
    }
  }
  await page.setViewportSize({ width: 390, height: 844 });
  const mobileRoutes = [
    ...screens.filter(([route]) => !route.includes('?section=')).map(([route, , capture]) => [route, capture.replace('.png', '-mobile.png')]),
    ...colonyScreens.map(([query, , capture]) => [colonyPath + query, capture.replace('.png', '-mobile.png')]),
  ];
  for (const [route, capture] of mobileRoutes) {
    await page.goto(route);
    await expect.poll(() => page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(390);
    const mobileNav = page.getByRole('navigation', { name: 'Navigation mobile' });
    await expect(mobileNav).toBeVisible();
    if (route === '/carte') {
      await mobileNav.getByRole('button', { name: 'Plus' }).click();
      await expect(page.locator('#command-navigation')).toBeVisible();
      await page.keyboard.press('Escape');
    }

  }
});


test('Les pages publiques utilisent le thème spatial sombre et gardent leurs formulaires', async ({ browser }) => {
  const context = await browser.newContext({ viewport: { width: 1440, height: 1000 } });
  const page = await context.newPage();
  for (const [route, capture] of [['/connexion', 'connexion-theme.png'], ['/inscription', 'inscription-theme.png'], ['/mot-de-passe/oublie', 'mot-de-passe-theme.png']]) {
    const response = await page.goto(route);
    expect(response?.status(), route).toBeLessThan(400);
    await expect(page.locator('main#main')).toBeVisible();
    await expect(page.locator('body')).toHaveAttribute('data-theme', 'dark');

  }
  await context.close();
});
