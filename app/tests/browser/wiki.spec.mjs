import { test, expect } from './support/isolated-test.mjs';
import AxeBuilder from '@axe-core/playwright';
import { readFileSync } from 'node:fs';
import { runIsolatedSql } from './support/game-fixtures.mjs';

test('Wiki : accès, guide, sources, clavier et mobile dans les deux thèmes', async ({ page, browser }, testInfo) => {
  test.setTimeout(150000);
  await page.goto('/wiki');
  await expect(page).toHaveURL(/connexion/);
  const id = crypto.randomUUID().slice(0, 8), email = `wiki-${id}@example.test`;
  await page.goto('/inscription');
  await page.getByLabel('Adresse email').fill(email);
  await page.getByLabel('Nom de commandant').fill(`Wiki-${id}`);
  await page.getByLabel('Mot de passe (12 caractères minimum)', { exact: true }).fill('Browser-test-2026!');
  await page.getByLabel('Confirmer le mot de passe').fill('Browser-test-2026!');
  await page.getByRole('button', { name: 'Créer mon compte' }).click();
  await expect(page).toHaveURL(/connexion/);
  await page.getByLabel('Adresse email').fill(email);
  await page.getByLabel('Mot de passe', { exact: true }).fill('Browser-test-2026!');
  await page.getByRole('button', { name: /Connexion/ }).click();
  await expect(page).toHaveURL(/\/wiki$/);
  await expect(page.getByRole('heading', { name: 'Wiki du jeu', exact: true })).toBeVisible();
  await page.goto('/univers');
  await expect(page.locator('article.universe-card').first()).toBeVisible();
  await page.locator('article.universe-card').first().getByRole('button').click();
  await expect(page).toHaveURL(/planetes\/\d+$/);
  const home = page.url();
  await expect(page.getByRole('link', { name: 'Comprendre le jeu : consulter le wiki', exact: true })).toHaveCount(0);
  await page.getByRole('link', { name: 'Consulter le wiki', exact: true }).click();
  await expect(page.getByRole('heading', { name: 'Wiki du jeu', exact: true })).toBeVisible();
  const guide = JSON.parse(readFileSync('docs/wiki.json', 'utf8'));
  for (const section of guide) {
    await expect(page.locator(`#wiki-${section.id}`)).toContainText(section.title);
    for (const text of section.paragraphs) await expect(page.locator(`#wiki-${section.id}`)).toContainText(text);
    for (const step of section.steps ?? []) await expect(page.locator(`#wiki-${section.id}`)).toContainText(step);
    for (const calculation of section.calculations ?? []) {
      const detail = page.locator(`#wiki-${section.id} details`).filter({ has: page.getByText(calculation.title, { exact: true }) });
      await expect(detail).not.toHaveAttribute('open', '');
      await detail.locator('summary').focus();
      await page.keyboard.press('Enter');
      await expect(detail).toHaveAttribute('open', '');
      await expect(detail.locator('.wiki-formula')).toHaveText(calculation.formula);
      await expect(detail).toContainText(calculation.example);
      await page.keyboard.press('Space');
      await expect(detail).not.toHaveAttribute('open', '');
    }
  }
  await page.getByRole('navigation', { name: 'Sommaire du wiki' }).getByRole('link', { name: 'Flottes et exploration' }).focus();
  await page.keyboard.press('Enter');
  await expect(page).toHaveURL(/#wiki-missions$/);
  for (const source of ['regles', 'decisions']) {
    await page.goto(`/wiki/references/${source}`);
    await expect(page.locator('.wiki-reference')).toHaveText(readFileSync(`docs/${source === 'regles' ? 'REGLES' : 'DECISIONS'}.md`, 'utf8'));
    await expect(page.locator('.wiki-reference script')).toHaveCount(0);
    await expect.poll(() => page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(1280);
  }
  const invalid = await page.goto('/wiki/references/inconnu');
  expect(invalid.status()).toBe(404);
  await page.goto(home);
  await page.getByRole('link', { name: 'Consulter le wiki', exact: true }).click();
  for (const theme of ['dark', 'light']) {
    runIsolatedSql(process.env.BROWSER_ROUTER, `UPDATE player SET preferences=JSON_SET(CASE WHEN JSON_TYPE(preferences)='OBJECT' THEN preferences ELSE JSON_OBJECT() END, '$.theme', '${theme}') WHERE email='${email}'`);
    await page.goto('/wiki');
    await expect(page.locator('body')).toHaveAttribute('data-theme', theme);
    await page.locator('.wiki-calculation summary').first().focus();
    await expect(page.locator('.wiki-calculation summary').first()).toBeFocused();
    for (const summary of await page.locator('.wiki-calculation summary').all()) await summary.click();
    for (const width of [1440, 768, 390]) {
      await page.setViewportSize({ width, height: 900 });
      await expect.poll(() => page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(width);
      await page.evaluate(() => window.scrollTo({ top: 0, behavior: 'instant' }));
      await page.screenshot({ path: testInfo.outputPath(`wiki-${theme}-${width}.png`) });
    }
    const audit = await new AxeBuilder({ page }).include('main').analyze();
    expect(audit.violations).toEqual([]);
    for (const source of ['regles', 'decisions']) {
      await page.goto(`/wiki/references/${source}`);
      await expect.poll(() => page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(390);
      const referenceAudit = await new AxeBuilder({ page }).include('main').analyze();
      expect(referenceAudit.violations).toEqual([]);
    }
    await page.goto('/wiki');
  }
  await page.evaluate(() => document.activeElement?.blur());
  await page.screenshot({ path: testInfo.outputPath('wiki-mobile.png'), fullPage: true });
  const noScriptContext = await browser.newContext({
    storageState: await page.context().storageState(),
    javaScriptEnabled: false,
    viewport: { width: 390, height: 900 },
  });
  try {
    const noScriptPage = await noScriptContext.newPage();
    await noScriptPage.goto(new URL('/wiki', home).href);
    await expect(noScriptPage.getByRole('heading', { name: 'Wiki du jeu', exact: true })).toBeVisible();
    const calculation = noScriptPage.locator('#wiki-calcul-production details').first();
    await calculation.locator('summary').focus();
    await noScriptPage.keyboard.press('Enter');
    await expect(calculation.locator('.wiki-formula')).toBeVisible();
    await noScriptPage.getByRole('navigation', { name: 'Sommaire du wiki' }).getByRole('link', { name: 'Flottes et exploration' }).click();
    await expect(noScriptPage).toHaveURL(/#wiki-missions$/);
    await expect.poll(() => noScriptPage.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(390);
  } finally {
    await noScriptContext.close();
  }
});
