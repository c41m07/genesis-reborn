import { test, expect } from './support/isolated-test.mjs';
import { prepareOwnedColony, runIsolatedSql } from './support/game-fixtures.mjs';

async function signIn(page) {
  const id = crypto.randomUUID().slice(0, 8);
  const email = `ui-routes-${id}@example.test`;
  const password = 'Browser-test-2026!';
  await page.goto('/inscription');
  await page.getByLabel('Adresse email').fill(email);
  await page.getByLabel('Nom de commandant').fill(`UI-Routes-${id}`);
  await page.getByLabel('Mot de passe (12 caractères minimum)', { exact: true }).fill(password);
  await page.getByLabel('Confirmer le mot de passe').fill(password);
  await page.getByRole('button', { name: 'Créer mon compte' }).click();
  await expect(page).toHaveURL(/connexion/);
  await page.getByLabel('Adresse email').fill(email);
  await page.getByLabel('Mot de passe', { exact: true }).fill(password);
  await page.getByRole('button', { name: /Connexion/ }).click();
  const universeCard = page.locator('article.universe-card').first();
  await expect(universeCard).toBeVisible();
  await universeCard.getByRole('button').click();
  await expect(page).toHaveURL(/planetes\/\d+$/);
  return email;
}

test('Routes de navigation utilisables sans débordement aux trois tailles', async ({ page }) => {
  test.setTimeout(300000);
  const email = await signIn(page);
  prepareOwnedColony(process.env.BROWSER_ROUTER, email, { showLockedItems: true });
  const routes = await page.locator('.sidebar nav a').evaluateAll(links => [...new Set(links.map(link => new URL(link.href).pathname + new URL(link.href).search))]);
  expect(routes.length).toBeGreaterThanOrEqual(10);

  for (const theme of ['dark', 'light']) {
    runIsolatedSql(process.env.BROWSER_ROUTER, `UPDATE player SET preferences=JSON_SET(preferences, '$.theme', '${theme}') WHERE email='${email}'`);
  for (const width of [1440, 1024, 834, 390, 320]) {
    await page.setViewportSize({ width, height: 900 });
    for (const route of routes) {
      const response = await page.goto(route);
      expect(response?.status(), `GET ${route} à ${width}px`).toBeLessThan(400);
      const overflow = await page.evaluate(() => ({ document: document.documentElement.scrollWidth, offenders: [...document.querySelectorAll('body *')].map(el => { const r=el.getBoundingClientRect(); return {tag:el.tagName, cls:typeof el.className==='string'?el.className:'', left:Math.round(r.left), right:Math.round(r.right), width:Math.round(r.width), scroll:el.scrollWidth, client:el.clientWidth}; }).filter(x => x.right > innerWidth + 1 && x.width > 0).sort((a,b)=>b.right-a.right).slice(0,8) }));
      expect(overflow.document, `${route} déborde à ${width}px : ${JSON.stringify(overflow.offenders)}`).toBeLessThanOrEqual(width);
      await expect(page.locator('main#main')).toBeVisible();
      await expect(page.locator('body')).toHaveAttribute('data-theme', theme);
      if (route.startsWith('/diplomatie')) await expect(page.locator('.diplomacy-page .alliance-tabs>a')).toHaveCount(3);
      const checks = [
        ['.sidebar nav>a', 12],
        ['.catalog-card h3', width <= 740 ? 13 : 14],
        ['.catalog-card .secondary', width <= 740 ? 11 : 12],
        ['.catalog-card .requirement', 11],
        ['.progress-times', 11],
        ['.ui-tabs:not(.alliance-tabs):not(.page-refinement .ui-tabs)>a, .ui-tabs:not(.alliance-tabs)>button, .building-rank-tab', width <= 740 ? 11 : 12],
        ['.diplomacy-page .alliance-tabs>a', 14],
        ['.page-refinement .ui-tabs:not(.alliance-tabs)>a', 14],
        ['.catalog-card input[type="number"], .message-search input', width <= 1050 ? 16 : 14],
        ['.catalog-card label', 12],
        ['.resource-summary-item strong', 10],
        ['.mobile-bottom-nav>a', 10],
      ];
      const fonts = await page.evaluate(checks => checks.flatMap(([selector, size]) =>
        [...document.querySelectorAll(selector)].flatMap(element => {
          const bounds = element.getBoundingClientRect(), style = getComputedStyle(element);
          return bounds.width > 0 && bounds.height > 0 && style.visibility === 'visible'
            ? [{ selector, expected: `${size}px`, actual: style.fontSize }] : [];
        })
      ), checks);
      for (const font of fonts) {
        expect(font.actual, `${font.selector} : ${theme}, ${width}px`).toBe(font.expected);
      }
      if (route.includes('section=buildings')) {
        await page.screenshot({ path: `output/playwright/typography-${theme}-${width}.png`, animations: 'disabled' });
      }
    }
  }
  }
});
