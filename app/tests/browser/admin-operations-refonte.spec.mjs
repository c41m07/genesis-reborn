import { test, expect } from './support/isolated-test.mjs';
import { execFileSync } from 'node:child_process';
import { readFile } from 'node:fs/promises';
import AxeBuilder from '@axe-core/playwright';
import { queryIsolatedSql, runIsolatedSql } from './support/game-fixtures.mjs';

const quote = value => "'" + String(value).replaceAll("'", "''") + "'";

async function admin(page) {
    page.setDefaultTimeout(15000);
    const suffix = crypto.randomUUID().slice(0, 8);
    const email = `ops-ui-${suffix}@example.test`;
    const password = crypto.randomUUID() + 'aA1!';
    await page.goto('/inscription');
    await page.getByLabel('Adresse email').fill(email);
    await page.getByLabel('Nom de commandant').fill(`OpsUI-${suffix}`);
    await page.getByLabel('Mot de passe (12 caractères minimum)', { exact: true }).fill(password);
    await page.getByLabel('Confirmer le mot de passe').fill(password);
    await page.getByRole('button', { name: 'Créer mon compte' }).click();
    const login = async () => {
        await page.goto('/connexion');
        await page.getByLabel('Adresse email').fill(email);
        await page.getByLabel('Mot de passe', { exact: true }).fill(password);
        await page.getByRole('button', { name: /Connexion/ }).click();
        await expect(page).toHaveURL(/univers$/);
    };
    await login();
    await page.goto('/admin/defis');
    await expect(page).toHaveURL(/acces-refuse/);
    execFileSync('docker', ['compose', '-p', process.env.BROWSER_ROUTER, '--env-file', '.env.local', 'exec', '-T', 'app', 'php', 'bin/console', 'app:admin:grant', email], { stdio: ['ignore', 'pipe', 'pipe'] });
    await page.context().clearCookies();
    await login();
    const universe = Number(queryIsolatedSql(process.env.BROWSER_ROUTER, "SELECT id FROM universe WHERE slug='omega-1'"));
    return { email, universe };
}




async function fixtures(page) {
    const own = await admin(page);
    const project = process.env.BROWSER_ROUTER;
    await page.locator('.universe-card').filter({ has: page.getByRole('button') }).first().getByRole('button').click();
    await expect(page).toHaveURL(/planetes\/\d+$/);
    const planet = Number(new URL(page.url()).pathname.split('/').pop());
    const member = Number(queryIsolatedSql(project, `SELECT membership.id FROM player_universe membership JOIN player p ON p.id=membership.player_id WHERE p.email=${quote(own.email)}`));
    const actor = Number(queryIsolatedSql(project, `SELECT id FROM player WHERE email=${quote(own.email)}`));
    const incidents = [true, false].map((authenticated, index) => `(${authenticated ? actor : 'NULL'},'admin_access_denied','admin_route',${index},${quote(JSON.stringify({ authenticated, method: 'GET', path: '/admin/ui-ops-'+index+'/'+'chemin'.repeat(30) }))},UTC_TIMESTAMP())`).join(',');
    runIsolatedSql(project, `INSERT INTO admin_audit_log (actor_id,action,target_type,target_id,details,created_at) VALUES ${incidents}`);
    runIsolatedSql(project, `INSERT INTO fleet (player_universe_id,home_planet_id,destination_id,mission,status,speed_ratio,cargo,reserved_fuel,orbit_fuel,orbit_duration_seconds,departure_at,arrival_at,travel_seconds,dispatch_token) VALUES (${member},${planet},${planet},'transport','traveling',1,'{"metal":100}',100,0,0,UTC_TIMESTAMP()-INTERVAL 2 HOUR,UTC_TIMESTAMP()-INTERVAL 1 HOUR,3600,'')`);
    const token = crypto.randomUUID();
    runIsolatedSql(project, `INSERT INTO outbox_message (kind,target_id,token,due_at) VALUES ('diagnostic_ui_ops',987654,${quote(token)},UTC_TIMESTAMP()+INTERVAL 1 HOUR)`);
    return { ...own, project, token, actor };
}

test('Exploitation A : sept pages, tableaux et fiches, thèmes et confidentialité', async ({ page }, testInfo) => {
    test.setTimeout(240000);
    const own = await fixtures(page);
    const before = queryIsolatedSql(own.project, 'SELECT id,status,dispatch_token,cargo FROM fleet ORDER BY id');
    const legalRoutes = ['cgu', 'mentions-legales', 'confidentialite', 'cookies'].map(slug => '/admin/juridique/'+slug);
    for (const theme of ['dark', 'light']) {
        runIsolatedSql(own.project, `UPDATE player SET preferences=JSON_OBJECT('theme','${theme}') WHERE email=${quote(own.email)}`);
        for (const width of [1440, 768, 390]) {
            await page.setViewportSize({ width, height: 900 });
            for (const [index, route] of ['/admin/securite', '/admin/exploitation', '/admin/journal', ...legalRoutes].entries()) {
                const response = await page.goto(route);
                expect(response.status()).toBe(200);
                await expect(page.locator('h1')).toHaveCount(1);
                await expect(page.locator('body')).toHaveAttribute('data-theme', theme);
                await expect(page.locator('.page-contents')).toHaveCount(0);
                await expect.poll(() => page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(width);
                if (index < 3) {
                    const row = page.locator('.admin-ops-table tbody tr').first();
                    await expect(row).toBeVisible();
                    await expect(row).toHaveCSS('display', width === 1440 ? 'table-row' : 'block');
                    await expect(row.locator('th, td').first()).toBeVisible();
                }
                if (index === 1) {
                    await page.getByText('Consulter les relais en attente', { exact: true }).click();
                    await expect(page.locator('.admin-operations details')).toContainText('diagnostic_ui_ops');
                    await expect(page.locator('.admin-operations')).not.toContainText(own.token);
                }
                if (index >= 3) {
                    expect(response.headers()['x-robots-tag']).toBe('noindex, nofollow');
                    expect(response.headers()['cache-control']).toContain('no-store');
                    await expect(page.locator('nav[aria-label="Brouillons juridiques"] a[aria-current]')).toHaveCount(1);
                    await expect(page.locator('main')).toContainText('texte non publié');
                }
                expect((await new AxeBuilder({ page }).include('#admin-main').analyze()).violations).toEqual([]);
                await page.evaluate(() => {
                    if (document.activeElement instanceof HTMLElement) document.activeElement.blur();
                    window.scrollTo({ top: 0, left: 0, behavior: 'instant' });
                });
                await expect.poll(() => page.evaluate(() => window.scrollY)).toBe(0);
                await page.screenshot({ path: testInfo.outputPath(`exploitation-${index}-${theme}-${width}.png`), fullPage: true, animations: 'disabled' });
            }
        }
    }
    expect(queryIsolatedSql(own.project, 'SELECT id,status,dispatch_token,cargo FROM fleet ORDER BY id')).toBe(before);
    await page.goto('/admin/securite');
    const query = page.getByLabel('Rechercher', { exact: true });
    await query.fill('ui-ops');
    await page.getByLabel('Origine', { exact: true }).selectOption('no');
    await expect(page.locator('#admin-security tr[data-admin-filter-target="row"]:visible')).toHaveCount(1);
    await query.fill('aucun-résultat');
    await expect(page.locator('#admin-security [data-admin-filter-target="empty"]')).toBeVisible();
});

test('Exploitation A : CSV complet avec détails fermés, recherche et scénario natif', async ({ page, browser }, testInfo) => {
    test.setTimeout(180000);
    const own = await fixtures(page);
    const values = Array.from({ length: 52 }, (_, index) => `('ui_ops_csv','=synthetic',${index},'{"marker":"csv-${index}","literal":"=SOMME(1)"}',UTC_TIMESTAMP())`).join(',');
    runIsolatedSql(own.project, `INSERT INTO admin_audit_log (action,target_type,target_id,details,created_at) VALUES ${values}`);
    for (const width of [1440, 390]) {
        await page.setViewportSize({ width, height: 900 });
        await page.goto('/admin/journal?action=ui_ops_csv');
        await expect(page.locator('#admin-audit tr[data-admin-filter-target="row"]')).toHaveCount(50);
        await expect(page.locator('#admin-audit tbody details[open]')).toHaveCount(0);
        const pending = page.waitForEvent('download');
        await page.getByRole('button', { name: 'Exporter les lignes visibles en CSV', exact: true }).click();
        const csv = await readFile(await (await pending).path(), 'utf8');
        expect(csv.split('\r\n').filter(Boolean)).toHaveLength(51);
        expect(csv).toContain('literal');
        expect(csv).toContain("'=synthetic");
        expect(csv).not.toContain('Déplier les détails');
    }
    const query = page.getByLabel('Rechercher dans le journal', { exact: true });
    await query.fill('csv-51');
    await expect(page.locator('#admin-audit tr[data-admin-filter-target="row"]')).toHaveCount(1);
    await expect(query).toBeFocused();
    const detail = page.locator('#admin-audit tbody summary');
    await detail.focus();
    await page.keyboard.press('Enter');
    await expect(page.locator('.admin-audit-details')).toContainText('csv-51');
    await page.route('**/admin/journal?*', route => route.fulfill({ status: 503, body: 'Indisponible' }));
    await query.fill('erreur-conservée');
    await expect(page.locator('[data-admin-list-target="error"]')).toBeVisible();
    await expect(query).toHaveValue('erreur-conservée');
    await page.unroute('**/admin/journal?*');
    const context = await browser.newContext({ storageState: await page.context().storageState(), javaScriptEnabled: false, viewport: { width: 390, height: 900 } });
    try {
        const native = await context.newPage();
        await native.goto('/admin/journal?action=ui_ops_csv');
        await expect(native.getByRole('button', { name: 'Exporter les lignes visibles en CSV', exact: true })).toBeHidden();
        await native.getByRole('link', { name: 'Page suivante', exact: true }).click();
        await expect(native.locator('#admin-audit tr[data-admin-filter-target="row"]')).toHaveCount(2);
        await native.reload();
        expect(new URL(native.url()).searchParams.get('action')).toBe('ui_ops_csv');
        const pending = native.waitForEvent('download');
        await native.getByRole('link', { name: 'Exporter tous les résultats en CSV', exact: true }).click();
        const csv = await readFile(await (await pending).path(), 'utf8');
        expect(csv.split('\n').filter(Boolean)).toHaveLength(53);
        expect(csv).toContain('csv-0');
        expect(csv).toContain('csv-51');
        await native.getByLabel('Rechercher dans le journal', { exact: true }).fill('aucun-résultat');
        await native.getByRole('button', { name: 'Rechercher', exact: true }).click();
        await expect(native.locator('[data-admin-filter-target="empty"]')).toBeVisible();
        await native.screenshot({ path: testInfo.outputPath('journal-vide-390.png'), fullPage: true });
        await native.goto('/admin/securite');
        await expect(native.locator('#admin-security .admin-search')).toBeHidden();
        const review = native.locator('#admin-security tbody tr').filter({ hasText: 'ui-ops-0' });
        const incident = new URL(await review.locator('form').getAttribute('action'), native.url()).pathname.split('/').at(-2);
        await review.getByRole('button', { name: 'Marquer examinée', exact: true }).click();
        expect(queryIsolatedSql(own.project, `SELECT COUNT(*) FROM admin_audit_log WHERE id=${incident}`)).toBe('1');
        expect(queryIsolatedSql(own.project, `SELECT COUNT(*) FROM admin_audit_log WHERE action='admin_access_reviewed' AND target_id=${incident}`)).toBe('1');
        await native.goto('/admin/juridique/cookies');
        await native.getByRole('link', { name: 'Politique de confidentialité', exact: true }).click();
        await expect(native.locator('h1')).toHaveText('Politique de confidentialité');
        await native.reload();
        await native.goBack();
        await expect(native.locator('h1')).toHaveText('Cookies et connexion');
    } finally {
        await context.close();
    }
});
