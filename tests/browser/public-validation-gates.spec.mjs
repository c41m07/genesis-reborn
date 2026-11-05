import { test, expect } from './support/isolated-test.mjs';
import AxeBuilder from '@axe-core/playwright';

const widths = [1920, 1440, 1280, 768, 390];
const tags = ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa', 'best-practice'];

test('Validation 404 : rendu, cinq largeurs, contraste, clavier et retour réel', async ({ page }, testInfo) => {
  const response = await page.goto('/connexion/page-ui-inconnue');
  expect(response.status()).toBe(404);
  await expect(page.getByRole('heading', { level: 1, name: 'Page introuvable' })).toBeVisible();
  for (const width of widths) {
    await page.setViewportSize({ width, height: 900 });
    expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(width);
    const action = page.getByRole('link', { name: 'Retour à l’accueil' });
    expect((await action.boundingBox()).height).toBeGreaterThanOrEqual(44);
    if ([1440, 390].includes(width)) await page.screenshot({ path: testInfo.outputPath(`validated-404-${width}.png`), animations: 'disabled' });
    if ([1440, 768, 390].includes(width)) {
      const report = await new AxeBuilder({ page }).withTags(tags).analyze();
      expect(report.violations).toEqual([]);
    }
  }
  const contrasts = await page.evaluate(() => {
    const rgb = value => value.match(/[\d.]+/g).slice(0, 3).map(Number);
    const luminance = color => rgb(color).map(value => value / 255).map(value => value <= .04045 ? value / 12.92 : ((value + .055) / 1.055) ** 2.4).reduce((sum, value, index) => sum + value * [.2126, .7152, .0722][index], 0);
    const background = getComputedStyle(document.querySelector('main')).backgroundColor;
    return [...document.querySelectorAll('main h1, main p, main .brand, main .action')].map(element => {
      const style = getComputedStyle(element);
      const bg = element.matches('.action') ? style.backgroundColor : background;
      const a = luminance(style.color), b = luminance(bg);
      return { text: element.textContent.trim(), ratio: (Math.max(a, b) + .05) / (Math.min(a, b) + .05) };
    });
  });
  for (const contrast of contrasts) expect(contrast.ratio, contrast.text).toBeGreaterThanOrEqual(4.5);
  await testInfo.attach('404-contrasts.json', { body: JSON.stringify(contrasts, null, 2), contentType: 'application/json' });
  await page.keyboard.press('Tab');
  await expect(page.getByRole('link', { name: 'Aller au contenu principal' })).toBeFocused();
  await page.keyboard.press('Enter');
  await expect(page.locator('main')).toBeFocused();
  await page.keyboard.press('Tab');
  await expect(page.getByRole('link', { name: 'Retour à l’accueil' })).toBeFocused();
  expect(await page.getByRole('link', { name: 'Retour à l’accueil' }).evaluate(element => getComputedStyle(element).outlineStyle)).toBe('solid');
  await page.keyboard.press('Enter');
  await expect(page).toHaveURL(/connexion$/);
  await expect(page.getByRole('heading', { name: 'Reprendre le commandement' })).toBeVisible();
});

test('Validation lien invalide : erreurs, deux reprises, clavier et sans JS', async ({ page, browser }, testInfo) => {
  const route = '/mot-de-passe/reinitialiser/jeton-invalide';
  const response = await page.goto(route);
  expect(response.status()).toBe(400);
  expect(response.headers()['referrer-policy']).toBe('no-referrer');
  expect(response.headers()['cache-control']).toContain('no-store');
  await expect(page.getByRole('heading', { level: 1, name: 'Lien expiré ou invalide' })).toBeVisible();
  for (const width of widths) {
    await page.setViewportSize({ width, height: 900 });
    expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(width);
    for (const link of await page.locator('.auth-card a').all()) expect((await link.boundingBox()).height).toBeGreaterThanOrEqual(44);
    if ([1440, 390].includes(width)) await page.screenshot({ path: testInfo.outputPath(`validated-reset-invalid-${width}.png`), animations: 'disabled' });
    if ([1440, 768, 390].includes(width)) {
      const report = await new AxeBuilder({ page }).withTags(tags).analyze();
      expect(report.violations).toEqual([]);
    }
  }
  const requestLink = page.getByRole('link', { name: 'Demander un nouveau lien' });
  let reached = false;
  for (let step = 0; step < 12; step++) {
    await page.keyboard.press('Tab');
    if (await requestLink.evaluate(element => element === document.activeElement)) { reached = true; break; }
  }
  expect(reached).toBe(true);
  await page.keyboard.press('Enter');
  await expect(page).toHaveURL(/mot-de-passe\/oublie$/);
  await expect(page.getByRole('button', { name: 'Envoyer le lien' })).toBeVisible();
  await page.goto(route);
  await page.getByRole('link', { name: 'Retour à la connexion' }).click();
  await expect(page).toHaveURL(/connexion$/);
  const context = await browser.newContext({ javaScriptEnabled: false, viewport: { width: 390, height: 900 } });
  const fallback = await context.newPage();
  expect((await fallback.goto(route)).status()).toBe(400);
  await fallback.getByRole('link', { name: 'Demander un nouveau lien' }).click();
  await expect(fallback).toHaveURL(/mot-de-passe\/oublie$/);
  await context.close();
});
