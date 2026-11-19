import { test, expect } from './support/isolated-test.mjs';
import { execFileSync } from 'node:child_process';
import AxeBuilder from '@axe-core/playwright';
import { queryIsolatedSql, runIsolatedSql } from './support/game-fixtures.mjs';

const quote = value => "'" + String(value).replaceAll("'", "''") + "'";

async function admin(page) {
    page.setDefaultTimeout(15000);
    const suffix = crypto.randomUUID().slice(0, 8);
    const email = `case-ui-${suffix}@example.test`;
    const password = crypto.randomUUID() + 'aA1!';
    await page.goto('/inscription');
    await page.getByLabel('Adresse email').fill(email);
    await page.getByLabel('Nom de commandant').fill(`CaseUI-${suffix}`);
    await page.getByLabel('Mot de passe (12 caractères minimum)', { exact: true }).fill(password);
    await page.getByLabel('Confirmer le mot de passe').fill(password);
    await page.getByRole('button', { name: 'Créer mon compte' }).click();
    await expect(page).toHaveURL(/connexion/);
    execFileSync('docker', ['compose', '-p', process.env.BROWSER_ROUTER, '--env-file', '.env.local',
        'exec', '-T', 'app', 'php', 'bin/console', 'app:admin:grant', email], { stdio: ['ignore', 'pipe', 'pipe'] });
    await page.getByLabel('Adresse email').fill(email);
    await page.getByLabel('Mot de passe', { exact: true }).fill(password);
    await page.getByRole('button', { name: /Connexion/ }).click();
    await page.locator('article.universe-card').filter({ has: page.getByRole('heading', { name: /Omega/ }) }).getByRole('button').click();
    await expect(page).toHaveURL(/planetes\/\d+$/);
    const project = process.env.BROWSER_ROUTER;
    const [player, membership, universe, planet] = queryIsolatedSql(project, `SELECT p.id,pu.id,pu.universe_id,pl.id FROM player p JOIN player_universe pu ON pu.id=p.current_player_universe_id JOIN planet pl ON pl.owner_id=pu.id WHERE p.email=${quote(email)} LIMIT 1`).split('\t').map(Number);
    runIsolatedSql(project, `INSERT INTO fleet (player_universe_id,home_planet_id,status,mission,speed_ratio,cargo,reserved_fuel,orbit_fuel,orbit_duration_seconds,travel_seconds,dispatch_token) VALUES (${membership},${planet},'idle','transport',1,'{}',0,0,0,0,'')`);
    const fleet = Number(queryIsolatedSql(project, 'SELECT MAX(id) FROM fleet'));
    return { player, membership, universe, fleet };
}

test('Dossiers A : liste, création et suivi lisibles dans les deux thèmes', async ({ page }, testInfo) => {
    test.setTimeout(180000);
    const user = await admin(page);
    const project = process.env.BROWSER_ROUTER;
    const heading = 'DossierInterstellaireSansEspace'.repeat(5).slice(0, 120);
    const context = `membership_id=${user.membership}&fleet_id=${user.fleet}`;
    await page.goto('/admin/dossiers/nouveau?' + context);
    await page.getByLabel('Titre du dossier').fill(heading);
    await page.getByLabel('Observations initiales').fill('Première ligne\n<script>window.caseExecuted=true</script>\nTexte à conserver.');
    await page.getByRole('button', { name: 'Créer le dossier', exact: true }).click();
    await expect(page).toHaveURL(/admin\/dossiers\/\d+$/);
    const id = Number(page.url().split('/').pop());
    await page.getByLabel('État du dossier').selectOption('investigating');
    await page.getByLabel('Nouvelle note').fill('Première note conservée.\n' + 'TexteSansEspace'.repeat(35));
    await page.getByRole('button', { name: 'Enregistrer le suivi' }).click();
    const noteBefore = queryIsolatedSql(project, `SELECT body FROM admin_case_note WHERE case_file_id=${id}`);
    const auditBefore = queryIsolatedSql(project, `SELECT COUNT(*) FROM admin_audit_log WHERE action IN ('admin_case_create','admin_case_update')`);
    for (const theme of ['dark', 'light']) {
        runIsolatedSql(project, `UPDATE player SET preferences=JSON_OBJECT('theme','${theme}') WHERE id=${user.player}`);
        for (const width of [1440, 768, 390]) {
            await page.setViewportSize({ width, height: 900 });
            for (const [index, route] of [`/admin/dossiers?membership=${user.membership}&fleet=${user.fleet}`, '/admin/dossiers/nouveau?' + context, `/admin/dossiers/${id}`].entries()) {
                expect((await page.goto(route)).status()).toBe(200);
                await expect(page.locator('body')).toHaveAttribute('data-theme', theme);
                await expect(page.locator('h1')).toHaveCount(1);
                await expect(page.locator('.page-contents')).toHaveCount(0);
                await expect.poll(() => page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(width);
                if (index === 0) {
                    await expect(page.locator('.admin-case-scope')).toContainText(`participation n° ${user.membership}`);
                    await expect(page.locator('.admin-case-scope')).toContainText(`flotte n° ${user.fleet}`);
                    await expect(page.locator(`a[href='/admin/dossiers/${id}']`)).toHaveCount(1);
                } else {
                    expect((await page.locator('main textarea').boundingBox()).height).toBeGreaterThanOrEqual(200);
                    if (index === 2) {
                        await expect(page.locator('.admin-case-text').first()).toContainText('<script>window.caseExecuted=true</script>');
                        expect(await page.evaluate(() => window.caseExecuted)).toBeUndefined();
                        const columns = await page.locator('.admin-case-detail-layout').evaluate(element => getComputedStyle(element).gridTemplateColumns.split(' ').length);
                        expect(columns).toBe(width === 1440 ? 2 : 1);
                        await expect(page.getByRole('link', { name: 'Fiche joueur', exact: true })).toHaveAttribute('href', `/admin/joueurs/${user.player}`);
                        await expect(page.getByRole('link', { name: `Inspecter la flotte n° ${user.fleet}` })).toHaveAttribute('href', `/admin/flottes/${user.fleet}`);
                    }
                }
                const analysis = await new AxeBuilder({ page }).include('#admin-main')
                    .withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa']).analyze();
                expect(analysis.violations).toEqual([]);
                await page.evaluate(() => window.scrollTo({ top: 0, behavior: 'instant' }));
                await page.screenshot({ path: testInfo.outputPath(`dossiers-${index}-${theme}-${width}.png`), fullPage: true, animations: 'disabled' });
            }
        }
    }
    expect(queryIsolatedSql(project, `SELECT body FROM admin_case_note WHERE case_file_id=${id}`)).toBe(noteBefore);
    expect(queryIsolatedSql(project, `SELECT COUNT(*) FROM admin_audit_log WHERE action IN ('admin_case_create','admin_case_update')`)).toBe(auditBefore);
});

test('Dossiers A : création refusée sans perte et filtres paginés sans JavaScript', async ({ page, browser }) => {
    test.setTimeout(120000);
    const user = await admin(page);
    const project = process.env.BROWSER_ROUTER;
    const otherUniverse = Number(queryIsolatedSql(project, `SELECT id FROM universe WHERE id<>${user.universe} LIMIT 1`));
    const native = await browser.newContext({ javaScriptEnabled: false, viewport: { width: 390, height: 900 }, storageState: await page.context().storageState() });
    const fallback = await native.newPage();
    fallback.setDefaultTimeout(15000);
    await fallback.goto(`/admin/dossiers/nouveau?membership_id=${user.membership}&fleet_id=${user.fleet}`);
    await fallback.getByLabel('Titre du dossier').fill('Contexte à vérifier');
    await fallback.getByLabel('Observations initiales').fill('Ces observations doivent rester après le refus.');
    await fallback.getByLabel('Univers concerné').selectOption(String(otherUniverse));
    const response = fallback.waitForResponse(response => response.url().includes('/admin/dossiers/nouveau') && response.request().method() === 'POST');
    await fallback.getByRole('button', { name: 'Créer le dossier', exact: true }).click();
    expect((await response).status()).toBe(422);
    await expect(fallback.getByRole('alert')).toContainText('doivent correspondre');
    await expect(fallback.getByLabel('Titre du dossier')).toHaveValue('Contexte à vérifier');
    await expect(fallback.getByLabel('Observations initiales')).toHaveValue('Ces observations doivent rester après le refus.');
    expect(queryIsolatedSql(project, 'SELECT COUNT(*) FROM admin_case')).toBe('0');
    await fallback.getByLabel('Univers concerné').selectOption(String(user.universe));
    await fallback.getByRole('button', { name: 'Créer le dossier', exact: true }).click();
    await expect(fallback).toHaveURL(/admin\/dossiers\/\d+$/);
    await expect(fallback.getByText('Aucune note de suivi.', { exact: true })).toBeVisible();
    await expect(fallback.locator('.admin-case-text').first()).toHaveText('Ces observations doivent rester après le refus.');
    const rows = Array.from({ length: 52 }, (_, index) => `(${user.universe},${user.membership},${user.fleet},'DossierFiltre-${index}','Lecture synthétique','open','${index.toString(16).padStart(32, '0')}',UTC_TIMESTAMP(),UTC_TIMESTAMP())`).join(',');
    runIsolatedSql(project, `INSERT INTO admin_case (universe_id,membership_id,fleet_id,title,description,status,creation_key,created_at,updated_at) VALUES ${rows}`);
    await fallback.goto(`/admin/dossiers?membership=${user.membership}&fleet=${user.fleet}`);
    await fallback.getByLabel('Recherche', { exact: true }).fill('DossierFiltre');
    await fallback.locator('.feedback-admin-filters').getByLabel('Univers').selectOption(String(user.universe));
    await fallback.getByRole('button', { name: 'Filtrer les dossiers' }).click();
    await expect(fallback.locator('.admin-case-list > li')).toHaveCount(50);
    const next = fallback.getByRole('link', { name: 'Suivant', exact: true });
    const params = new URL(await next.getAttribute('href'), fallback.url()).searchParams;
    for (const [key, value] of Object.entries({ q: 'DossierFiltre', membership: String(user.membership), fleet: String(user.fleet), universe: String(user.universe) })) expect(params.get(key)).toBe(value);
    await next.click();
    await expect(fallback.locator('.admin-case-list > li')).toHaveCount(2);
    await fallback.reload();
    await expect(fallback.getByLabel('Recherche', { exact: true })).toHaveValue('DossierFiltre');
    await fallback.getByLabel('Recherche', { exact: true }).fill('Aucun dossier correspondant');
    await fallback.getByRole('button', { name: 'Filtrer les dossiers' }).click();
    await expect(fallback.getByText('Aucun dossier ne correspond aux filtres.', { exact: true })).toBeVisible();
    await native.close();
});
