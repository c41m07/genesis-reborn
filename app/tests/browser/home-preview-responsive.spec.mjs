import { test, expect } from '@playwright/test';

test('L’accueil adapte le poids de son aperçu de colonie et conserve le PNG de détail', async ({ browser, baseURL }) => {
  let originalBytes;
  const variants = [];
  for (const settings of [
    { width: 390, height: 844, deviceScaleFactor: 1, expected: 432 },
    { width: 390, height: 844, deviceScaleFactor: 2, expected: 720 },
    { width: 1366, height: 768, deviceScaleFactor: 1, expected: 720 },
    { width: 1366, height: 768, deviceScaleFactor: 2, expected: 1440 },
  ]) {
    const { width, height, deviceScaleFactor, expected } = settings;
    const context = await browser.newContext({ baseURL, viewport: { width, height }, deviceScaleFactor });
    try {
      const page = await context.newPage();
      const transfer = page.waitForResponse(response => /ui-preview-colony-[0-9]+[.]webp$/.test(response.url()));
      await page.goto('/');
      const image = page.getByRole('img', { name: /Vue générale d’une colonie de démonstration/ });
      await expect(image).toBeVisible();
      await expect(image).toHaveAttribute('width', '1440');
      await expect(image).toHaveAttribute('height', '1000');
      await expect(image.locator('xpath=ancestor::a')).toHaveAttribute('href', /ui-preview-colony[.]png$/);
      await expect(page.locator('img[src*="ui-preview-galaxy.png"]')).toHaveAttribute('loading', 'lazy');
      const response = await transfer;
      expect(response.url()).toMatch(new RegExp('/ui-preview-colony-' + expected + '[.]webp$'));
      if (originalBytes === undefined) {
        originalBytes = await page.evaluate(async () => {
          const result = await fetch('/art/ui-preview-colony.png');
          if (!result.ok) throw new Error('Le PNG de détail doit rester disponible');
          return (await result.arrayBuffer()).byteLength;
        });
      }
      const bytes = (await response.body()).byteLength;
      expect(bytes).toBeGreaterThan(0);
      expect(bytes).toBeLessThan(originalBytes);
      const measurement = await image.evaluate(async element => {
        await element.decode();
        return { source: element.currentSrc, width: Math.round(element.getBoundingClientRect().width) };
      });
      expect(measurement.source).toBe(response.url());
      variants.push({ viewport: width, dpr: deviceScaleFactor, renderedWidth: measurement.width, variant: expected, bytes });
    } finally {
      await context.close();
    }
  }
  expect(variants[0].bytes).toBeLessThan(variants[2].bytes);
  console.log('Aperçu colonie, octets des réponses HTTP :', JSON.stringify({ originalBytes, variants }));
});
