import { test, expect } from './support/isolated-test.mjs';

async function imageDownload(page, path, selector, expectedSource, maximumBytes, scroll = false) {
  const downloads = new Map();
  const captures = [];
  const record = response => {
    const url = new URL(response.url());
    if (url.origin !== new URL(page.url()).origin || !/[.](webp|png|svg)$/.test(url.pathname)) return;
    captures.push(response.body().then(bytes => downloads.set(url.pathname, bytes.byteLength)));
  };
  page.on('response', record);
  try {
    await page.goto(path);
    const item = page.locator(selector).first();
    await expect(item).toBeVisible();
    if (scroll) await item.scrollIntoViewIfNeeded();
    await item.evaluate(async image => {
      await image.decode();
      if (image.naturalWidth === 0) throw new Error('Image illisible');
    });
    const selected = await item.evaluate(image => new URL(image.currentSrc).pathname);
    expect(selected).toMatch(expectedSource);
    await Promise.all(captures);
    expect(downloads.get(selected)).toBeGreaterThan(0);
    expect(downloads.get(selected)).toBeLessThan(maximumBytes);
    const visible = await page.locator(selector).evaluateAll(images => images
      .filter(image => image.getBoundingClientRect().top < innerHeight && image.getBoundingClientRect().bottom > 0 && image.getClientRects().length > 0)
      .map(image => {
        const bounds = image.getBoundingClientRect();
        return { path: new URL(image.currentSrc).pathname, width: Math.round(bounds.width), height: Math.round(bounds.height) };
      }));
    const result = { viewport: page.viewportSize(), path, visible, downloads: Object.fromEntries(downloads) };
    if (process.env.GENESIS_MEASURE_IMAGE_BYTES === '1') console.log('IMAGES', JSON.stringify(result));
    return result;
  } finally {
    page.removeListener('response', record);
  }
}

test('Les visuels WebP et les cartes légères restent décodables sur les parcours publics et joueur', async ({ page, browser, baseURL }) => {
  const id = crypto.randomUUID().slice(0, 8);
  const email = 'art-' + id + '@example.test';
  const password = crypto.randomUUID() + crypto.randomUUID().toUpperCase();
  const authArtwork = /genesis-acces-commandement-(?:480|864)[.]webp$/;
  await imageDownload(page, '/connexion', '.entry-access-context img', authArtwork, 85_000);
  await imageDownload(page, '/inscription', '.entry-access-context img', authArtwork, 85_000);
  await expect(page.locator('.entry-access-context img')).toHaveAttribute('alt', '');
  await expect(page.locator('.entry-access-context img')).toHaveAttribute('loading', 'lazy');
  await imageDownload(page, '/', 'img[src*="ui-preview-galaxy.png"]', /ui-preview-galaxy[.]webp$/, 150_000, true);
  const galaxyZoom = page.locator('a[href*="ui-preview-galaxy.png"]');
  await expect(galaxyZoom).toHaveCount(1);
  await expect(galaxyZoom).toHaveAccessibleName(/Explorez la galaxie.*Ouvrir l’aperçu de la galaxie/);
  await expect(galaxyZoom.locator('img')).toHaveAttribute('loading', 'lazy');
  await page.goto('/inscription');
  await page.getByLabel('Adresse email').fill(email);
  await page.getByLabel('Nom de commandant').fill('Artwork-' + id);
  await page.getByLabel('Mot de passe (12 caractères minimum)', { exact: true }).fill(password);
  await page.getByLabel('Confirmer le mot de passe').fill(password);
  await page.getByRole('button', { name: 'Créer mon compte' }).click();
  await expect(page).toHaveURL(/connexion/);
  await page.getByLabel('Adresse email').fill(email);
  await page.getByLabel('Mot de passe', { exact: true }).fill(password);
  await page.getByRole('button', { name: /Connexion/ }).click();
  await page.locator('article.universe-card').first().getByRole('button').click();
  await expect(page).toHaveURL(/planetes[/][0-9]+$/);
  const colony = new URL(page.url()).pathname;
  const state = await page.context().storageState();
  for (const [width, height] of [[1366, 768], [390, 844]]) {
    const context = await browser.newContext({ baseURL, viewport: { width, height }, storageState: state, deviceScaleFactor: width === 390 ? 2 : 1 });
    try {
      const catalogue = await context.newPage();
      await imageDownload(catalogue, colony, 'img.colony-scene', /colony-overview[.]webp$/, 600_000);
      await imageDownload(catalogue, '/planetes', '.planet-index-art img', /planet-desert[.]webp$/, 270_000);
      await imageDownload(catalogue, colony + '?section=buildings', '.catalog-card:not([hidden]) .catalog-card-art img', /power_plant__basic-768[.]webp$/, 250_000);
      await imageDownload(catalogue, colony + '?section=technologies&showLockedItems=1', '.catalog-card:not([hidden]) .catalog-card-art img', /naval_engineering__basic-768[.]webp$/, 230_000);
    } finally {
      await context.close();
    }
  }
  const dense = await browser.newContext({ baseURL, viewport: { width: 390, height: 844 }, storageState: state, deviceScaleFactor: 3 });
  try {
    await imageDownload(await dense.newPage(), colony + '?section=buildings',
      '.catalog-card:not([hidden]) .catalog-card-art img', /power_plant__basic[.]webp$/, 650_000);
  } finally {
    await dense.close();
  }
});
