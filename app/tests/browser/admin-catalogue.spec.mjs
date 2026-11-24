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

test('Administration catalogue: accès, édition, sauvegarde et restauration responsive', async ({ page }, testInfo) => {
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
  await page.setViewportSize({ width: 1920, height: 1080 });

  await page.getByRole('link', { name: /Équilibrer les catalogues de cet univers/ }).first().click();
  await expect(page.getByRole('heading', { name: 'Bâtiments', exact: true })).toBeVisible();
  const breadcrumbs = page.getByRole('navigation', { name: 'Fil d’Ariane' });
  await expect(breadcrumbs).toContainText('Univers et activité');
  await expect(breadcrumbs.locator('[aria-current="page"]')).toHaveText('Catalogue');
  await expect(page.locator('.admin-universe-navigation a')).toHaveCount(4);
  await expect(breadcrumbs.getByRole('link', { name: 'Univers', exact: true })).toHaveAttribute('href', '/admin/univers');
  const browse = page.getByRole('link', { name: 'Aperçu de Mine à ciel ouvert', exact: true });
  await browse.focus();
  await page.keyboard.press('Enter');
  const selection = page.getByRole('complementary', { name: 'Mine à ciel ouvert' });
  await expect(selection.getByRole('link', { name: 'Ouvrir la fiche' })).toHaveAttribute('href', /\/buildings\/mine_metal$/);
  await expect(selection.getByRole('link', { name: 'Consulter l’historique' })).toHaveAttribute('href', /\/buildings\/mine_metal\/historique$/);
  await expect(page.locator('.admin-catalogue-composition')).toHaveClass(/--with-selection/);
  await expect.poll(() => page.locator('.admin-catalogue-composition').evaluate(element => getComputedStyle(element).gridTemplateColumns.split(' ').length)).toBe(2);
  await page.evaluate(() => { window.scrollTo(0, 0); return document.fonts.ready; });
  await page.screenshot({ path: testInfo.outputPath('catalogue-rail-1920x1080.png'), scale: 'css', animations: 'disabled' });
  await selection.getByRole('link', { name: 'Fermer l’aperçu' }).click();
  await expect(selection).toHaveCount(0);

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

  await page.getByRole('link', { name: /Consulter l’historique et comparer les versions/ }).click();
  const history = page.url();
  const universe = Number(new URL(history).pathname.match(/universes[/]([0-9]+)/)[1]);
  const settings = queryIsolatedSql(project, `SELECT game_settings FROM universe WHERE id=${universe}`);
  await page.getByRole('link', { name: 'Préparer la restauration de l’état après cette trace' }).last().click();
  await expect(page.locator('.core-admin-universe')).toContainText('Univers :');
  await expect(page.getByRole('navigation', { name: 'Fil d’Ariane' })).toContainText('Préparer une restauration');
  const tables = page.locator('.core-admin-restoration table');
  await expect(tables).toHaveCount(1);
  await expect(tables.getByRole('columnheader')).toHaveText(['Élément','Actuel','Proposé']);
  await expect(tables.getByRole('rowheader', { name: 'Durée' })).toBeVisible();
  await expect(tables.getByRole('rowheader', { name: 'Champs modifiables' })).toBeVisible();
  await expect(page.getByText('Aperçu non publié', { exact: true })).toBeVisible();
  const modes = page.getByRole('navigation', { name: 'Présentation de la restauration' });
  await modes.getByRole('link', { name: 'Vue linéaire' }).click();
  await expect(page.locator('.admin-restoration-layout')).toHaveCSS('grid-template-columns', /^\d+(?:\.\d+)?px$/);
  await expect(page.locator('form.admin-catalogue-restore')).toHaveCount(1);
  await expect(page.getByLabel('Motif de restauration')).toHaveValue('');
  await page.evaluate(() => document.fonts.ready);
  await page.screenshot({ path: testInfo.outputPath('restauration-lineaire-1920x1080.png'), scale: 'css', animations: 'disabled' });
  await modes.getByRole('link', { name: 'Vue côte à côte' }).click();
  await expect(page.getByLabel('Motif de restauration')).toHaveValue('');
  for (const theme of ['dark', 'light']) {
    runIsolatedSql(project, `UPDATE player SET preferences=JSON_OBJECT('theme','${theme}') WHERE email='${email}'`);
    await page.reload();
    await expect(page.locator('body')).toHaveAttribute('data-theme', theme);
    for (const width of [1920, 1440, 768, 390]) {
      const height = width === 1920 ? 1080 : 900;
      await page.setViewportSize({ width, height });
      await expect.poll(() => page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(width);
      const layout = page.locator('.admin-restoration-layout');
      await expect.poll(() => layout.evaluate(element => getComputedStyle(element).gridTemplateColumns.split(' ').length)).toBe(width >= 1440 ? 2 : 1);
      await expect(layout.getByRole('heading', { name: 'Valeurs à réappliquer' })).toBeVisible();
      const confirmation = layout.getByRole('checkbox', { name: /Je confirme ces valeurs/ });
      await expect(confirmation).toBeVisible();
      await expect(confirmation).toHaveCSS('width', '18px');
      await expect(layout.getByRole('button', { name: 'Publier ces valeurs historiques' })).toBeVisible();
      if (width === 1920 || width === 390) {
        const accessibility = await new AxeBuilder({ page }).include('#admin-main').analyze();
        expect(accessibility.violations).toEqual([]);
        await page.evaluate(() => document.fonts.ready);
        await page.screenshot({ path: testInfo.outputPath(`restauration-${theme}-${width}x${height}.png`), scale: 'css', animations: 'disabled' });
      }
    }
  }
  await page.getByRole('link', { name: 'Annuler et revenir à l’historique' }).click();
  await expect(page).toHaveURL(history);
  expect(queryIsolatedSql(project, `SELECT game_settings FROM universe WHERE id=${universe}`)).toBe(settings);
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
    for (const width of [1920, 1440, 768, 390]) {
      const height = width === 1920 ? 1080 : 900;
      await page.setViewportSize({ width, height });
      for (const [index, route] of [catalogue, `${catalogue}/buildings/power_plant`].entries()) {
        expect((await page.goto(route)).status()).toBe(200);
        await expect(page.locator('h1')).toHaveCount(1);
        await expect(page.locator('.page-contents')).toHaveCount(0);
        await expect(page.locator('body')).toHaveAttribute('data-theme', theme);
        await expect.poll(() => page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(width);
        if (index === 0) {
          const table = page.getByRole('table', { name: 'Fiches du catalogue' });
          await expect(table.getByRole('columnheader')).toHaveText(['Fiche', 'Famille', 'Rangs disponibles', 'Ouvrir']);
          const row = table.getByRole('row').filter({ has: page.getByRole('link', { name: 'Modifier Mine à ciel ouvert', exact: true }) });
          await expect(row.getByRole('rowheader')).toContainText('Mine à ciel ouvert');
          await expect(row.getByRole('listitem')).toHaveText([/^Basique : Mine à ciel ouvert$/, /^Intermédiaire : .+/, /^Supérieur : .+/]);
          await expect.poll(() => row.evaluate(element => getComputedStyle(element).display)).toBe(width <= 760 ? 'block' : 'table-row');
        } else {
          const grid = page.locator('.admin-catalogue-costs');
          await expect.poll(() => grid.evaluate(element => getComputedStyle(element).gridTemplateColumns.split(' ').length)).toBe(width >= 1440 ? 3 : 1);
        }
        const accessibility = await new AxeBuilder({ page }).include('#admin-main').analyze();
        expect(accessibility.violations).toEqual([]);
        if (width === 1920 || width === 390) {
          await page.evaluate(() => document.fonts.ready);
          await page.screenshot({ path: testInfo.outputPath(`catalogue-${index}-${theme}-${width}x${height}.png`), scale: 'css', animations: 'disabled' });
        }
      }
    }
  }
  expect(queryIsolatedSql(project, `SELECT game_settings FROM universe WHERE id=${universe}`)).toBe(before);

  const context = await browser.newContext({ storageState: await page.context().storageState(), javaScriptEnabled: false, viewport: { width: 390, height: 900 } });
  const native = await context.newPage();
  for (const [family, slug, fields] of [['buildings', 'power_plant', 14], ['technologies', 'automated_defense_repair', 15], ['ships', 'fighter', 28], ['defenses', 'ground_turret_anti_light', 21]]) {
    await native.goto(`${catalogue}/${family}/${slug}`);
    const form = native.locator('.admin-catalogue-editor');
    await expect(form.locator('input[type="number"][name^="values["]')).toHaveCount(fields);
    await expect(form.getByRole('group')).toHaveCount(4);
    for (const rank of ['Basique', 'Intermédiaire', 'Supérieur']) {
      await expect(form.getByRole('group', { name: rank, exact: true }).getByRole('spinbutton', { name: 'Métal', exact: true })).toBeVisible();
    }
    const quote = form.getByRole('group', { name: 'Prévisualiser un devis sans enregistrer', exact: true });
    await expect(quote.getByLabel('Rang du devis', { exact: true })).toBeVisible();
    const quantityField = ['buildings', 'technologies'].includes(family) ? 'Niveau du devis' : 'Quantité du devis';
    await expect(quote.getByRole('spinbutton', { name: quantityField, exact: true })).toBeVisible();
    await expect(quote.getByRole('button', { name: 'Comparer le devis', exact: true })).toBeVisible();
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
  await expect(native.getByRole('table', { name: 'Fiches du catalogue' })).toContainText('Chasseur de patrouille');
  await native.getByRole('link', { name: 'Aperçu de Chasseur de patrouille', exact: true }).click();
  const selection = native.getByRole('complementary', { name: 'Chasseur de patrouille' });
  await expect(selection).toBeVisible();
  await expect(native.getByRole('searchbox')).toHaveValue('Chasseur');
  await selection.getByRole('link', { name: 'Fermer l’aperçu' }).click();
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
