import { test, expect } from './support/isolated-test.mjs';
import { execFileSync } from 'node:child_process';
import AxeBuilder from '@axe-core/playwright';
import { queryIsolatedSql, runIsolatedSql } from './support/game-fixtures.mjs';

async function registerAndLogin(page) {
  const id = crypto.randomUUID().slice(0, 8);
  const email = 'admin-ui-' + id + '@example.test';
  const password = crypto.randomUUID() + 'aA1!';
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
  await expect(page.getByRole('heading', { name: 'Bâtiments', exact: true })).toBeVisible();
  const breadcrumbs = page.getByRole('navigation', { name: 'Fil d’Ariane' });
  await expect(breadcrumbs).toContainText('Univers et activité');
  await expect(breadcrumbs.locator('[aria-current="page"]')).toHaveText('Catalogue');
  await expect(page.locator('.admin-universe-navigation a')).toHaveCount(4);
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
  await page.getByRole('link', { name: /^Modifier / }).first().click();
  const firstEntry = page.locator('section.panel').first();
  const cost = firstEntry.locator('form').first().locator('input[type="number"]').first();
  const original = Number(await cost.inputValue());
  await cost.fill(String(original + 1));
  await firstEntry.getByLabel('Motif de la modification').fill('Recette navigateur isolée');
  await firstEntry.getByRole('checkbox', { name: /Je confirme cette modification/ }).check();
  await firstEntry.getByRole('button', { name: 'Enregistrer cet équilibrage' }).click();
  await expect(page.locator('.flash[role="status"]')).toContainText('Équilibrage enregistré');
  await firstEntry.getByLabel('Motif du retour aux références').fill('Fin de recette isolée');
  await firstEntry.getByRole('checkbox', { name: /Je confirme le retour/ }).check();
  await firstEntry.getByRole('button', { name: 'Restaurer les valeurs de référence' }).click();
  await expect(page.locator('.flash[role="status"]')).toContainText('Équilibrage enregistré');

  for (const width of [1440, 768, 390]) {
    await page.setViewportSize({ width, height: 900 });
    await expect.poll(() => page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(width);
  }
});

test('Catalogue B : comparaison des rangs, thèmes et formulaires natifs des quatre familles', async ({ page, browser }, testInfo) => {
  test.setTimeout(180000);
  const { email, password } = await registerAndLogin(page);
  const project = process.env.BROWSER_ROUTER;
  execFileSync('docker', ['compose', '-p', project, '--env-file', '.env.local', 'exec', '-T', 'app', 'php', 'bin/console', 'app:admin:grant', email], { stdio: ['ignore', 'pipe', 'pipe'] });
  await page.context().clearCookies();
  await page.goto('/connexion');
  await page.getByLabel('Adresse email').fill(email);
  await page.getByLabel('Mot de passe', { exact: true }).fill(password);
  await page.getByRole('button', { name: /Connexion/ }).click();
  const universe = Number(queryIsolatedSql(project, "SELECT id FROM universe WHERE slug='omega-1'"));
  const catalogue = `/admin/universes/${universe}/catalogue`;
  const before = queryIsolatedSql(project, `SELECT game_settings FROM universe WHERE id=${universe}`);
  for (const theme of ['dark', 'light']) {
    runIsolatedSql(project, `UPDATE player SET preferences=JSON_OBJECT('theme','${theme}') WHERE email='${email}'`);
    for (const width of [1440, 768, 390]) {
      await page.setViewportSize({ width, height: 900 });
      for (const [index, route] of [catalogue, `${catalogue}/buildings/power_plant`].entries()) {
        expect((await page.goto(route)).status()).toBe(200);
        await expect(page.locator('h1')).toHaveCount(1);
        await expect(page.locator('.page-contents')).toHaveCount(0);
        await expect(page.locator('body')).toHaveAttribute('data-theme', theme);
        await expect.poll(() => page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(width);
        const grid = page.locator(index === 0 ? '.admin-catalogue-grid' : '.admin-catalogue-costs');
        expect(await grid.evaluate(element => getComputedStyle(element).gridTemplateColumns.split(' ').length)).toBe(width === 1440 ? (index === 0 ? 2 : 3) : 1);
        const accessibility = await new AxeBuilder({ page }).include('#admin-main').analyze();
        expect(accessibility.violations).toEqual([]);
        await page.screenshot({ path: testInfo.outputPath(`catalogue-${index}-${theme}-${width}.png`), fullPage: true, animations: 'disabled' });
      }
    }
  }
  expect(queryIsolatedSql(project, `SELECT game_settings FROM universe WHERE id=${universe}`)).toBe(before);

  const context = await browser.newContext({ storageState: await page.context().storageState(), javaScriptEnabled: false, viewport: { width: 390, height: 900 } });
  const native = await context.newPage();
  for (const [family, slug, fields] of [['buildings', 'power_plant', 14], ['technologies', 'automated_defense_repair', 15], ['ships', 'fighter', 28], ['defenses', 'ground_turret_anti_light', 21]]) {
    await native.goto(`${catalogue}/${family}/${slug}`);
    const form = native.locator('.admin-catalogue-editor');
    await expect(form.locator('input[type="number"]')).toHaveCount(fields);
    await expect(form.getByRole('group')).toHaveCount(3);
    const cost = form.getByRole('group', { name: 'Basique', exact: true }).getByRole('spinbutton', { name: 'Métal', exact: true });
    await expect(cost).toHaveAttribute('step', '1');
    await expect(cost).toHaveAttribute('aria-describedby', /-range$/);
    const original = Number(await cost.inputValue());
    await cost.fill(String(original + 1));
    await form.getByLabel('Motif de la modification').fill('Recette sans JavaScript');
    await form.getByRole('checkbox', { name: /Je confirme cette modification/ }).check();
    await form.getByRole('button', { name: 'Enregistrer cet équilibrage' }).click();
    await expect(native.locator('.flash[role="status"]')).toContainText('Équilibrage enregistré');
    await expect(cost).toHaveValue(String(original + 1));
    await native.reload();
    await expect(cost).toHaveValue(String(original + 1));
    await native.getByLabel('Motif du retour aux références').fill('Fin de recette sans JavaScript');
    await native.getByRole('checkbox', { name: /Je confirme le retour/ }).check();
    await native.getByRole('button', { name: 'Restaurer les valeurs de référence' }).click();
    await expect(cost).toHaveValue(String(original));
    await expect(native.locator('.flash[role="status"]')).toContainText('Équilibrage enregistré');
  }
  await native.goto(catalogue);
  await native.getByRole('searchbox', { name: 'Rechercher dans toutes les familles' }).fill('Chasseur');
  await native.getByRole('button', { name: 'Rechercher', exact: true }).click();
  await expect(native.locator('.admin-catalogue-grid')).toContainText('Chasseur de patrouille');
  await native.reload();
  await expect(native.getByRole('searchbox')).toHaveValue('Chasseur');
  await native.getByRole('link', { name: 'Modifier Chasseur de patrouille', exact: true }).click();
  await native.goBack();
  await expect(native.getByRole('searchbox')).toHaveValue('Chasseur');
  await native.getByRole('searchbox').fill('Aucun résultat catalogue B');
  await native.getByRole('button', { name: 'Rechercher', exact: true }).click();
  await expect(native.getByRole('status')).toHaveText('Aucune entrée ne correspond à cette recherche.');
  await context.close();
});
