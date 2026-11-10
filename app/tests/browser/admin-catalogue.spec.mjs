import { test, expect } from './support/isolated-test.mjs';
import { execFileSync } from 'node:child_process';

async function registerAndLogin(page) {
  const id = crypto.randomUUID().slice(0, 8);
  const email = 'admin-ui-' + id + '@example.test';
  const password = 'Browser-test-2026!';
  await page.goto('/inscription');
  await page.getByLabel('Adresse email').fill(email);
  await page.getByLabel('Nom de commandant').fill('Admin-UI-' + id);
  await page.getByLabel('Mot de passe (12 caractères minimum)', { exact: true }).fill(password);
  await page.getByLabel('Confirmer le mot de passe').fill(password);
  await page.getByRole('button', { name: 'Créer mon compte' }).click();
  await expect(page).toHaveURL(/connexion/);
  await page.getByLabel('Adresse email').fill(email);
  await page.getByLabel('Mot de passe', { exact: true }).fill(password);
  await page.getByRole('button', { name: /Connexion/ }).click();
  await expect(page.locator('article.universe-card').first()).toBeVisible();
  return { email, password };
}

test('Administration catalogue: accès, édition, sauvegarde et restauration responsive', async ({ page }) => {
  const { email, password } = await registerAndLogin(page);
  await page.goto('/admin');
  await expect(page).toHaveURL(/acces-refuse/);
  const project = process.env.BROWSER_ROUTER;
  expect(project).toMatch(/^genesis-browser-test-[0-9]+-[0-9]+$/);
  execFileSync('docker', ['compose', '--project-name', project, '--env-file', '.env.local', 'exec', '-T', 'app', 'php', 'bin/console', 'app:admin:grant', email], { encoding: 'utf8', stdio: ['ignore', 'pipe', 'pipe'] });
  await page.context().clearCookies();
  await page.goto('/connexion');
  await page.getByLabel('Adresse email').fill(email);
  await page.getByLabel('Mot de passe', { exact: true }).fill(password);
  await page.getByRole('button', { name: /Connexion/ }).click();
  await page.goto('/admin');
  await expect(page.locator('#command-navigation')).toHaveCount(0);
  await expect(page.getByRole('heading', { name: 'Vue d’ensemble', exact: true })).toBeVisible();
  await expect(page.getByRole('navigation', { name: 'Console d’administration' })).toBeVisible();
  await page.getByRole('navigation', { name: 'Console d’administration' }).getByRole('link', { name: 'Univers et activité', exact: true }).click();
  await page.getByRole('navigation', { name: 'Console d’administration' }).getByRole('link', { name: 'Univers', exact: true }).click();
  await expect(page.getByRole('link', { name: /Équilibrer les catalogues de cet univers/ }).first()).toBeVisible();
  await page.setViewportSize({ width: 1440, height: 1000 });

  await page.getByRole('link', { name: /Équilibrer les catalogues de cet univers/ }).first().click();
  await expect(page.getByRole('heading', { name: 'Équilibrage des catalogues', exact: true })).toBeVisible();
  const breadcrumbs = page.getByRole('navigation', { name: 'Fil d’Ariane' });
  await expect(breadcrumbs).toContainText('Univers et activité');
  await expect(breadcrumbs.locator('[aria-current="page"]')).toHaveText('Catalogue');
  await expect(page.locator('.admin-universe-navigation a')).toHaveCount(3);
  await expect(breadcrumbs.getByRole('link', { name: 'Univers', exact: true })).toHaveAttribute('href', '/admin/univers');

  const unnamed = await page.locator('a[href],button,input:not([type="hidden"]),select,textarea').evaluateAll(nodes => nodes.flatMap(element => {
    const style = getComputedStyle(element);
    const rect = element.getBoundingClientRect();
    if (style.display === 'none' || style.visibility === 'hidden' || rect.width === 0 || rect.height === 0 || element.closest('details:not([open])')) return [];
    const labelledBy = element.getAttribute('aria-labelledby')?.split(/\s+/).map(id => document.getElementById(id)?.textContent ?? '').join(' ').trim();
    const direct = element.getAttribute('aria-label') || element.getAttribute('title') || element.innerText?.trim() || element.textContent?.trim();
    const content = [...element.querySelectorAll('img[alt],svg title')].map(node => node.getAttribute('alt') || node.textContent).join(' ').trim();
    const labels = element.labels?.length ? [...element.labels].map(label => label.innerText.trim()).join(' ') : '';
    const name = labelledBy || direct || content || labels;
    return name ? [] : [{ tag: element.tagName.toLowerCase(), id: element.id, labels: [...(element.labels || [])].map(label => label.textContent), html: element.outerHTML.slice(0, 200) }];
  }));
  expect(unnamed).toEqual([]);
  const firstEntry = page.locator('details.panel').first();
  await firstEntry.locator('summary').click();
  const cost = firstEntry.locator('form').first().locator('input[type="number"]').first();
  const original = Number(await cost.inputValue());
  await cost.fill(String(original + 1));
  await firstEntry.getByRole('button', { name: 'Enregistrer cet équilibrage' }).click();
  await expect(page.locator('.flash[role="status"]')).toContainText('Équilibrage enregistré');
  await page.locator('details.panel').first().locator('summary').click();
  await page.locator('details.panel').first().getByRole('button', { name: 'Restaurer les valeurs de référence' }).click();
  await expect(page.locator('.flash[role="status"]')).toContainText('Équilibrage enregistré');

  for (const width of [1440, 768, 390]) {
    await page.setViewportSize({ width, height: 900 });
    await expect.poll(() => page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(width);
  }
});
