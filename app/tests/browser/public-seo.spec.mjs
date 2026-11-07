import { test, expect } from './support/isolated-test.mjs';
import AxeBuilder from '@axe-core/playwright';

test('Pages publiques : métadonnées initiales, clavier, contrastes et petits écrans', async ({ page }, testInfo) => {
  for (const path of ['/', '/inscription', '/connexion']) {
    const response = await page.goto(path);
    const html = await response.text();
    expect(html).toContain(path === '/connexion' ? 'name="robots" content="noindex"' : 'property="og:image"');
    await expect(page.locator('main h1')).toHaveCount(1);
    if (path !== '/connexion') {
      await page.keyboard.press('Tab');
      await expect(page.getByRole('link', { name: 'Aller au contenu' })).toBeFocused();
      await page.keyboard.press('Enter');
      await page.keyboard.press('Tab');
      await expect(page.locator('main a, main input').first()).toBeFocused();
      const outline = await page.locator(':focus').evaluate(el => getComputedStyle(el).outlineStyle);
      expect(outline).toBe('solid');
      const image = await page.locator('meta[property="og:image"]').getAttribute('content');
      const result = await page.evaluate(async path => {
        const response = await fetch(path);
        return { ok: response.ok, type: response.headers.get('content-type') };
      }, new URL(image).pathname);
      expect(result.ok).toBeTruthy();
      expect(result.type).toContain('image/');
    }
    for (const width of [1440, 390]) {
      await page.setViewportSize({ width, height: 900 });
      expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(width);
      const results = await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21aa']).analyze();
      expect(results.violations).toEqual([]);
      await page.screenshot({ path: testInfo.outputPath(`public-${path.replaceAll('/', '') || 'home'}-${width}.png`), fullPage: true });
    }
  }
});
