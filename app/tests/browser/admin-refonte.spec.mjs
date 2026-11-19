import { test, expect } from './support/isolated-test.mjs';
import { execFileSync } from 'node:child_process';
import AxeBuilder from '@axe-core/playwright';
import { queryIsolatedSql, runIsolatedSql } from './support/game-fixtures.mjs';

async function enter(page) {
    const suffix = crypto.randomUUID().slice(0, 8);
    const email = `console-${suffix}@example.test`;
    const password = crypto.randomUUID() + 'aA1!';
    await page.goto('/inscription');
    await page.getByLabel('Adresse email').fill(email);
    await page.getByLabel('Nom de commandant').fill(`Console-${suffix}`);
    await page.getByLabel('Mot de passe (12 caractères minimum)', { exact: true }).fill(password);
    await page.getByLabel('Confirmer le mot de passe').fill(password);
    await page.getByRole('button', { name: 'Créer mon compte' }).click();
    await expect(page).toHaveURL(/connexion/);
    execFileSync('docker', ['compose', '-p', process.env.BROWSER_ROUTER, '--env-file', '.env.local',
        'exec', '-T', 'app', 'php', 'bin/console', 'app:admin:grant', email], { stdio: ['ignore', 'pipe', 'pipe'] });
    await page.getByLabel('Adresse email').fill(email);
    await page.getByLabel('Mot de passe', { exact: true }).fill(password);
    await page.getByRole('button', { name: /Connexion/ }).click();
    return email;
}

test('Console A : thèmes, champs complets, clavier et lecture sans débordement', async ({ page }, testInfo) => {
    test.setTimeout(240000);
    const email = await enter(page);
    const longName = 'CommandantInterstellaireSansEspace';
    const longEmail = 'commandant.interstellaire.au.courriel.tres.long@example.test';
    runIsolatedSql(process.env.BROWSER_ROUTER, `INSERT INTO player (username,email,password,roles,preferences)
        VALUES ('${longName}','${longEmail}','unused','[]','{}')`);
    const routes = ['/admin', '/admin/rubriques/players', '/admin/rubriques/universe',
        '/admin/rubriques/events', '/admin/rubriques/ops', '/admin/joueurs'];
    for (const theme of ['dark', 'light']) {
        runIsolatedSql(process.env.BROWSER_ROUTER, `UPDATE player SET preferences=JSON_OBJECT('theme','${theme}') WHERE email='${email}'`);
        for (const width of [1440, 768, 390]) {
            await page.setViewportSize({ width, height: 900 });
            for (const [index, route] of routes.entries()) {
                expect((await page.goto(route)).status()).toBe(200);
                await expect(page.locator('body')).toHaveAttribute('data-theme', theme);
                await expect(page.locator('.page-contents')).toHaveCount(0);
                await expect(page.locator('h1')).toHaveCount(1);
                await expect.poll(() => page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(width);
                // Une table masquée dans un conteneur défilant ne suffit pas : chaque valeur doit tenir dans sa cellule.
                if (route === '/admin/joueurs') {
                    const row = page.locator('tbody tr').filter({ hasText: longEmail });
                    await expect(row.getByRole('rowheader')).toContainText(longName);
                    await expect(row.locator('td')).toHaveCount(5);
                    expect(await row.locator('.admin-cell-value').evaluateAll(values => values.every(value =>
                        value.scrollWidth <= value.parentElement.clientWidth))).toBe(true);
                    const own = page.locator('tbody tr').filter({ hasText: email });
                    await expect(own.locator('form')).toHaveCount(0);
                    if (width === 1440) {
                        const columns = await row.locator('td').evaluateAll(cells => cells.map(cell => cell.getBoundingClientRect().x));
                        expect(new Set(columns).size).toBe(5);
                    } else {
                        expect(await row.locator('td[data-label="Courriel"]').evaluate(cell => getComputedStyle(cell).display)).toBe('grid');
                        const rowWidth = (await row.boundingBox()).width;
                        expect((await row.getByRole('rowheader').boundingBox()).width).toBeGreaterThanOrEqual(rowWidth - 2);
                        const tableWidth = (await page.locator('.admin-players-table').boundingBox()).width;
                        expect((await page.locator('.admin-players-table caption').boundingBox()).width).toBeGreaterThanOrEqual(tableWidth - 2);
                    }
                }
                if (route === '/admin') {
                    const wide = await page.locator('.admin-home-wide').evaluateAll(panels => panels.map(panel => panel.getBoundingClientRect().width));
                    const gridWidth = await page.locator('.admin-home-grid').evaluate(grid => grid.clientWidth);
                    expect(wide.every(width => Math.abs(width - gridWidth) < 2)).toBe(true);
                    await page.locator('.admin-home-audit summary').focus();
                    await page.keyboard.press('Enter');
                    await expect(page.locator('.admin-home-audit')).toHaveAttribute('open', '');
                }
                if (width !== 768) {
                    const analysis = await new AxeBuilder({ page }).include('#admin-main')
                        .withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa']).analyze();
                    expect(analysis.violations).toEqual([]);
                    await page.evaluate(() => window.scrollTo({ top: 0, behavior: 'instant' }));
                    await page.screenshot({ path: testInfo.outputPath(`console-${index}-${theme}-${width}.png`), fullPage: true, animations: 'disabled' });
                }
            }
        }
    }
    await page.goto('/admin/joueurs');
    await page.getByRole('searchbox', { name: 'Rechercher', exact: true }).focus();
    await page.keyboard.press('Tab');
    await expect(page.getByLabel('État', { exact: true })).toBeFocused();
    expect(await page.getByLabel('État', { exact: true }).evaluate(field => getComputedStyle(field).outlineStyle)).toBe('solid');
});

test('Console A : erreur de recherche lisible, saisie conservée et filtres sans JavaScript', async ({ page, browser }) => {
    test.setTimeout(120000);
    const email = await enter(page);
    await page.goto('/admin/joueurs');
    const initialRows = await page.locator('tbody').textContent();
    await page.route('**/admin/joueurs?*', route => route.fulfill({ status: 503, body: 'Indisponible' }));
    const search = page.getByRole('searchbox', { name: 'Rechercher', exact: true });
    await search.fill('recherche-conservee');
    await expect(page.getByRole('alert')).toContainText('La recherche a échoué');
    await expect(search).toHaveValue('recherche-conservee');
    await expect(search).toBeFocused();
    await expect(page.locator('tbody')).toHaveText(initialRows);
    await page.unroute('**/admin/joueurs?*');
    const fallback = await browser.newContext({ javaScriptEnabled: false, viewport: { width: 390, height: 900 }, storageState: await page.context().storageState() });
    const nativePage = await fallback.newPage();
    await nativePage.goto('/admin/joueurs');
    await nativePage.getByRole('searchbox', { name: 'Rechercher', exact: true }).fill(email);
    await nativePage.getByLabel('État', { exact: true }).selectOption('active');
    await nativePage.getByLabel('Rôle', { exact: true }).selectOption('admin');
    await nativePage.getByRole('button', { name: 'Rechercher', exact: true }).click();
    await expect(nativePage.locator('tbody tr')).toHaveCount(1);
    await expect(nativePage.locator('tbody')).toContainText(email);
    expect(new URL(nativePage.url()).searchParams.get('role')).toBe('admin');
    expect(new URL(nativePage.url()).searchParams.get('status')).toBe('active');
    await nativePage.reload();
    await expect(nativePage.getByRole('searchbox', { name: 'Rechercher', exact: true })).toHaveValue(email);
    await nativePage.getByRole('searchbox', { name: 'Rechercher', exact: true }).fill('aucun-compte-correspondant');
    await nativePage.getByRole('button', { name: 'Rechercher', exact: true }).click();
    await expect(nativePage.locator('tbody')).toContainText('Aucun compte joueur.');
    expect(queryIsolatedSql(process.env.BROWSER_ROUTER, "SELECT COUNT(*) FROM admin_audit_log WHERE action IN ('player_promote_admin','player_suspend')")).toBe('0');
    await fallback.close();
});
