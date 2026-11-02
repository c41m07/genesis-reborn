import { test, expect } from './support/isolated-test.mjs';

async function signIn(page) {
  const id = crypto.randomUUID().slice(0, 8);
  const email = `a11y-${id}@example.test`;
  const password = 'Browser-test-2026!';
  await page.goto('/inscription');
  await page.getByLabel('Adresse email').fill(email);
  await page.getByLabel('Nom de commandant').fill(`A11y-${id}`);
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

function accessibilityNames(elements) {
  return elements.evaluateAll(nodes => nodes.flatMap(element => {
    const style = getComputedStyle(element);
    const rect = element.getBoundingClientRect();
    if (style.display === 'none' || style.visibility === 'hidden' || rect.width === 0 || rect.height === 0 || element.closest('details:not([open])')) return [];
    const labelledBy = element.getAttribute('aria-labelledby')?.split(/\s+/).map(id => document.getElementById(id)?.textContent ?? '').join(' ').trim();
    const direct = element.getAttribute('aria-label') || element.getAttribute('title') || element.innerText?.trim() || element.textContent?.trim();
    const contentName = [...element.querySelectorAll('img[alt],svg title')].map(node => node.getAttribute('alt') || node.textContent).join(' ').trim();
    const formName = element.labels?.length ? [...element.labels].map(label => label.innerText.trim()).join(' ') : '';
    const name = labelledBy || direct || contentName || formName;
    return name ? [] : [{ tag: element.tagName.toLowerCase(), type: element.getAttribute('type'), html: element.outerHTML.slice(0, 240) }];
  }));
}

test('Liens, boutons et champs visibles possèdent un nom accessible', async ({ page }) => {
  await signIn(page);
  const routes = await page.locator('.sidebar nav a').evaluateAll(links => [...new Set(links.map(link => new URL(link.href).pathname + new URL(link.href).search))]);
  const unlabeled = [];
  for (const route of routes) {
    await page.goto(route);
    unlabeled.push({ route, controls: await accessibilityNames(page.locator('a[href],button,input:not([type=hidden]),select,textarea')) });
  }
  const missing = unlabeled.filter(item => item.controls.length);
  expect(missing, JSON.stringify(missing, null, 2)).toEqual([]);
});
