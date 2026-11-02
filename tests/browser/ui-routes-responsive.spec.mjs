import { test, expect } from './support/isolated-test.mjs';

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
  await page.locator('article.universe-card').filter({ has: page.getByRole('heading', { name: /Turbo/ }) }).getByRole('button').click();
  await expect(page).toHaveURL(/planetes\/\d+$/);
}

test('Routes de navigation utilisables sans débordement aux trois tailles', async ({ page }) => {
  await signIn(page);
  const routes = await page.locator('.sidebar nav a').evaluateAll(links => [...new Set(links.map(link => new URL(link.href).pathname + new URL(link.href).search))]);
  expect(routes.length).toBeGreaterThanOrEqual(10);

  for (const width of [1440, 768, 390]) {
    await page.setViewportSize({ width, height: 900 });
    for (const route of routes) {
      const response = await page.goto(route);
      expect(response?.status(), `GET ${route} à ${width}px`).toBeLessThan(400);
      const overflow = await page.evaluate(() => ({ document: document.documentElement.scrollWidth, offenders: [...document.querySelectorAll('body *')].map(el => { const r=el.getBoundingClientRect(); return {tag:el.tagName, cls:typeof el.className==='string'?el.className:'', left:Math.round(r.left), right:Math.round(r.right), width:Math.round(r.width), scroll:el.scrollWidth, client:el.clientWidth}; }).filter(x => x.right > innerWidth + 1 && x.width > 0).sort((a,b)=>b.right-a.right).slice(0,8) }));
      expect(overflow.document, `${route} déborde à ${width}px : ${JSON.stringify(overflow.offenders)}`).toBeLessThanOrEqual(width);
      await expect(page.locator('main#main')).toBeVisible();
    }
  }
});
