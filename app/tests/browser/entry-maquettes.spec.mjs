import { test, expect } from './support/isolated-test.mjs';
import AxeBuilder from '@axe-core/playwright';

// Captures alignées sur le viewport de la maquette, sans recadrage ni page entière.
async function capture(page, testInfo, label, width, height) {
  await page.setViewportSize({ width, height });
  await page.evaluate(() => document.fonts.ready);
  const actual = await page.evaluate(() => [innerWidth, innerHeight, devicePixelRatio]);
  expect(actual).toEqual([width, height, 1]);
  await expect.poll(() => page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(width);
  await page.screenshot({
    path: testInfo.outputPath(label + '-' + width + '.png'),
    animations: 'disabled',
    scale: 'css',
  });
}

test('Maquette publique : proportions de l’accueil, connexion et mode étroit', async ({ page }, testInfo) => {
  await page.setViewportSize({ width: 1920, height: 1080 });
  await page.goto('/');
  const hero = page.locator('.entry-home--audit .entry-hero');
  const box = await hero.boundingBox();
  expect(box.x).toBeGreaterThanOrEqual(74);
  expect(box.x).toBeLessThanOrEqual(105);
  expect(box.y).toBeLessThan(135);
  expect(box.height).toBeGreaterThan(540);
  const preview = await hero.locator('.public-hero-preview').boundingBox();
  const intro = await page.locator('.entry-game-introduction').boundingBox();
  const galaxy = await page.locator('.public-previews article').boundingBox();
  expect(preview.x).toBeGreaterThan(900);
  expect(preview.width).toBeGreaterThan(650);
  expect(galaxy.x).toBeGreaterThan(intro.x + intro.width);
  expect(Math.abs(galaxy.y - intro.y)).toBeLessThan(4);
  await expect(page.getByRole('link', { name: 'Ouvrir l’aperçu de la galaxie' })).toHaveAttribute('href', /ui-preview-galaxy[.]png$/);
  await capture(page, testInfo, '01-accueil', 1920, 1080);
  await capture(page, testInfo, '01-accueil', 390, 844);

  await page.setViewportSize({ width: 1920, height: 1080 });
  await page.goto('/connexion');
  await expect(page.getByRole('heading', { level: 1, name: 'Reprendre le commandement' })).toBeVisible();
  const form = await page.locator('.entry-access-panel').boundingBox();
  const scene = await page.locator('.entry-access-context').boundingBox();
  expect(form.x).toBeGreaterThan(135);
  expect(form.x).toBeLessThan(190);
  expect(form.width).toBeGreaterThan(550);
  expect(form.width).toBeLessThan(650);
  expect(scene.x).toBeGreaterThan(form.x + form.width);
  expect(scene.height).toBeGreaterThan(700);
  expect(Math.abs(scene.y - form.y)).toBeLessThan(4);
  await capture(page, testInfo, '02-connexion', 1920, 1080);
  await page.setViewportSize({ width: 390, height: 844 });
  await expect(page.locator('.entry-access-context')).toBeHidden();
  await expect(page.getByRole('button', { name: 'Connexion' })).toBeVisible();
  await capture(page, testInfo, '02-connexion', 390, 844);
});

test('Documents publics : sommaires, textes conservés et ancres natives sans JavaScript', async ({ browser, baseURL }, testInfo) => {
  const context = await browser.newContext({ baseURL, javaScriptEnabled: false, viewport: { width: 1920, height: 1080 } });
  try {
    const page = await context.newPage();
    for (const [route, first, sections] of [
      ['/mentions-legales', 'Le site et son éditeur', 4],
      ['/cgu', 'Présentation du service', 6],
      ['/confidentialite', 'Responsable et contact', 6],
      ['/cookies', 'Connexion et sécurité', 4],
    ]) {
      const response = await page.goto(route);
      expect(response.status()).toBe(200);
      const toc = page.getByRole('navigation', { name: 'Dans cette page' });
      const article = page.locator('.legal-content');
      await expect(toc.locator('li a')).toHaveCount(sections);
      const targets = await toc.locator('li a').evaluateAll(links => links.map(link => link.getAttribute('href')));
      for (const target of targets) await expect(article.locator(target)).toHaveCount(1);
      const [left, right] = await Promise.all([toc.boundingBox(), article.boundingBox()]);
      expect(left.x + left.width).toBeLessThan(right.x);
      await expect(article.getByRole('heading', { name: 'Information provisoire et incomplète' })).toBeVisible();
      await toc.getByRole('link', { name: first }).click();
      expect(new URL(page.url()).hash).toBe(targets[0]);
      await expect(article.getByRole('heading', { name: first, exact: true })).toBeVisible();
      await toc.getByRole('link', { name: 'Revenir en haut' }).click();
      expect(new URL(page.url()).hash).toBe('#legal-title');
      if (route === '/confidentialite') {
        await page.goto(route);
        await capture(page, testInfo, '06-confidentialite', 1920, 1080);
        await page.setViewportSize({ width: 390, height: 844 });
        const [before, after] = await Promise.all([toc.boundingBox(), article.boundingBox()]);
        expect(before.y + before.height).toBeLessThan(after.y);
        await capture(page, testInfo, '06-confidentialite', 390, 844);
        await page.setViewportSize({ width: 1920, height: 1080 });
      }
    }
  } finally {
    await context.close();
  }
  const contextAxe = await browser.newContext({ baseURL, viewport: { width: 390, height: 844 } });
  try {
    const page = await contextAxe.newPage();
    await page.goto('/confidentialite');
    const { violations } = await new AxeBuilder({ page })
      .include('.legal-page--audit')
      .withTags(['wcag2a', 'wcag2aa', 'wcag21aa', 'wcag22aa'])
      .analyze();
    expect(violations).toEqual([]);
  } finally {
    await contextAxe.close();
  }
});
