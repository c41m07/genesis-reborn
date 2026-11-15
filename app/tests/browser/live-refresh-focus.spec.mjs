import { test, expect } from './support/isolated-test.mjs';

async function signIn(page) {
  const id = crypto.randomUUID().slice(0, 8);
  const email = 'live-focus-' + id + '@example.test';
  const password = 'Live-focus-2026!';
  await page.goto('/inscription');
  await page.getByLabel('Adresse email').fill(email);
  await page.getByLabel('Nom de commandant').fill('Live-Focus-' + id);
  await page.getByLabel('Mot de passe (12 caractères minimum)', { exact: true }).fill(password);
  await page.getByLabel('Confirmer le mot de passe').fill(password);
  await page.getByRole('button', { name: 'Créer mon compte' }).click();
  await expect(page).toHaveURL(/connexion/);
  await page.getByLabel('Adresse email').fill(email);
  await page.getByLabel('Mot de passe', { exact: true }).fill(password);
  await page.getByRole('button', { name: /Connexion/ }).click();
  await page.locator('article.universe-card').filter({ has: page.getByRole('heading', { name: /Turbo/ }) }).getByRole('button').click();
  await expect(page).toHaveURL(/planetes\/\d+$/);
  return Number(new URL(page.url()).pathname.split('/').at(-1));
}

test('Le rafraîchissement live conserve le focus puis reprend quand il quitte la zone', async ({ page }) => {
  const planetId = await signIn(page);
  await page.goto('/');
  await expect(page.locator('.dashboard-journal .dashboard-empty')).toContainText('Aucun événement d’absence à consulter');
  await page.waitForTimeout(300);

  const liveRoute = '/planetes/' + planetId;
  const waitForLiveResponse = () => page.waitForResponse(response => new URL(response.url()).pathname === liveRoute, { timeout: 10_000 });
  const manageLink = page.locator('[data-live-region="active-flights"]').getByRole('link', { name: /Gérer les flottes/ });
  await manageLink.focus();
  const linkHandle = await manageLink.elementHandle();

  const firstRefresh = waitForLiveResponse();
  await page.evaluate(() => {
    window.focusedLiveLink = document.activeElement;
    window.dispatchEvent(new CustomEvent('genesis:refresh'));
  });
  await firstRefresh;
  await expect.poll(() => page.evaluate(() => document.activeElement === window.focusedLiveLink)).toBe(true);

  const secondRefresh = waitForLiveResponse();
  await page.locator('.sidebar a.brand').focus();
  await secondRefresh;
  await expect.poll(() => linkHandle.evaluate(node => node.isConnected)).toBe(false);
});

test('Une réponse live tardive ne remplace jamais la plus récente', async ({ page }) => {
  await signIn(page);
  await expect(page.locator('.resource-strip')).toHaveClass(/resource-strip--collapsible/);
  await page.evaluate(() => {
    const originalFetch = window.fetch;
    window.reworkPending = [];
    window.reworkOffline = 0;
    document.addEventListener('genesis:offline', () => ++window.reworkOffline);
    window.fetch = (url, options) => {
      if (options?.headers?.['X-Genesis-Live'] !== '1') return originalFetch(url, options);
      return new Promise((resolve, reject) => window.reworkPending.push(version => {
        if (version === 'network-error') return reject(new Error('Erreur réseau synthétique'));
        const region = document.querySelector('[data-live-region="active-flights"]').cloneNode(true);
        region.dataset.reworkVersion = version;
        resolve(new Response(region.outerHTML, { headers: { 'Content-Type': 'text/html' } }));
      }));
    };
    window.dispatchEvent(new CustomEvent('genesis:refresh'));
  });
  await expect.poll(() => page.evaluate(() => window.reworkPending.length)).toBe(1);
  await page.evaluate(() => window.dispatchEvent(new CustomEvent('genesis:refresh')));
  await expect.poll(() => page.evaluate(() => window.reworkPending.length)).toBe(2);
  await page.evaluate(() => window.reworkPending[1]('recent'));
  await expect(page.locator('[data-live-region="active-flights"]')).toHaveAttribute('data-rework-version', 'recent');
  await page.evaluate(async () => {
    window.reworkPending[0]('obsolete');
    await new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve)));
  });
  await expect(page.locator('[data-live-region="active-flights"]')).toHaveAttribute('data-rework-version', 'recent');
  await page.evaluate(() => window.dispatchEvent(new CustomEvent('genesis:refresh')));
  await expect.poll(() => page.evaluate(() => window.reworkPending.length)).toBe(3);
  await page.evaluate(() => window.dispatchEvent(new CustomEvent('genesis:refresh')));
  await expect.poll(() => page.evaluate(() => window.reworkPending.length)).toBe(4);
  await page.evaluate(() => window.reworkPending[3]('latest'));
  await expect(page.locator('[data-live-region="active-flights"]')).toHaveAttribute('data-rework-version', 'latest');
  await page.evaluate(async () => {
    window.reworkPending[2]('network-error');
    await new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve)));
  });
  expect(await page.evaluate(() => window.reworkOffline)).toBe(0);
});
