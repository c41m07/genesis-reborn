import { test, expect } from './support/isolated-test.mjs';
import { execFileSync } from 'node:child_process';
import AxeBuilder from '@axe-core/playwright';
import { queryIsolatedSql, runIsolatedSql } from './support/game-fixtures.mjs';

const quote = value => "'" + String(value).replaceAll("'", "''") + "'";

async function admin(page) {
    page.setDefaultTimeout(15000);
    const suffix = crypto.randomUUID().slice(0, 8);
    const email = `universe-ui-${suffix}@example.test`;
    const password = crypto.randomUUID() + 'aA1!';
    await page.goto('/inscription');
    await page.getByLabel('Adresse email').fill(email);
    await page.getByLabel('Nom de commandant').fill(`UniversUI-${suffix}`);
    await page.getByLabel('Mot de passe (12 caractères minimum)', { exact: true }).fill(password);
    await page.getByLabel('Confirmer le mot de passe').fill(password);
    await page.getByRole('button', { name: 'Créer mon compte' }).click();
    await expect(page).toHaveURL(/connexion/);
    const project = process.env.BROWSER_ROUTER;
    execFileSync('docker', ['compose', '-p', project, '--env-file', '.env.local', 'exec', '-T', 'app', 'php', 'bin/console', 'app:admin:grant', email], { stdio: ['ignore', 'pipe', 'pipe'] });
    await page.getByLabel('Adresse email').fill(email);
    await page.getByLabel('Mot de passe', { exact: true }).fill(password);
    await page.getByRole('button', { name: /Connexion/ }).click();
    await expect(page).toHaveURL(/univers$/);
    const universe = Number(queryIsolatedSql(project, "SELECT id FROM universe WHERE slug='omega-1'"));
    return { email, universe };
}

test('Univers B : fiches, création et gestion dans les deux thèmes et trois formats', async ({ page }, testInfo) => {
    test.setTimeout(180000);
    const user = await admin(page);
    const project = process.env.BROWSER_ROUTER;
    const multipliers = { building_time: 1, research_time: 1, ship_build_time: 1, resource_production: 1, travel_time: 1 };
    runIsolatedSql(project, `UPDATE universe SET name=${quote('UniversInterstellaireSansEspace'.repeat(4).slice(0, 100))},ends_at=UTC_TIMESTAMP()+INTERVAL 3 DAY,game_settings=${quote(JSON.stringify({ multipliers }))} WHERE id=${user.universe}`);
    const state = () => queryIsolatedSql(project, `SELECT name,status,game_settings,starts_at,ends_at FROM universe WHERE id=${user.universe}`);
    const before = state();
    for (const theme of ['dark', 'light']) {
        runIsolatedSql(project, `UPDATE player SET preferences=JSON_OBJECT('theme','${theme}') WHERE email=${quote(user.email)}`);
        for (const width of [1440, 768, 390]) {
            await page.setViewportSize({ width, height: 900 });
            for (const [index, route] of ['/admin/univers', '/admin/universes/nouveau', `/admin/universes/${user.universe}/gerer`].entries()) {
                expect((await page.goto(route)).status()).toBe(200);
                await expect(page.locator('body')).toHaveAttribute('data-theme', theme);
                await expect(page.locator('h1')).toHaveCount(1);
                await expect(page.locator('.page-contents')).toHaveCount(0);
                await expect.poll(() => page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(width);
                const layout = page.locator(['.admin-universes-grid', '.admin-universe-create-layout', '.admin-universe-manage-layout'][index]);
                expect(await layout.evaluate(element => getComputedStyle(element).gridTemplateColumns.split(' ').length)).toBe(width === 1440 ? 2 : 1);
                if (index === 0) {
                    await expect(page.locator(`#admin-universe-${user.universe} .admin-universe-alerts`)).toContainText('Fermeture dans moins de 7 jours');
                } else if (index === 1) {
                    await expect(page.getByRole('button', { name: 'Créer l’univers' })).toHaveAccessibleDescription(/ouvert dès sa création/);
                    await expect(page.getByRole('textbox', { name: 'Nom affiché' })).toBeVisible();
                } else {
                    await expect(page.locator('.admin-universe-summary')).toContainText('Normal');
                    await expect(page.locator('.admin-topbar')).toContainText('Univers : UniversInterstellaire');
                    await expect(page.locator('.admin-calendar-form')).toBeVisible();
                    await expect(page.locator('.admin-settings-form')).toBeVisible();
                    for (const checkbox of await page.locator('.admin-settings-form input[type="checkbox"]').all()) {
                        const bounds = await checkbox.boundingBox();
                        expect(bounds.width).toBe(18);
                        expect(bounds.height).toBe(18);
                    }
                    await page.locator('.admin-danger-zone summary').focus();
                    await page.keyboard.press('Enter');
                    await expect(page.getByRole('button', { name: /Supprimer définitivement/ })).toBeVisible();
                    const deletion = page.locator('.admin-danger-zone form');
                    await expect(deletion).toHaveAttribute('action', `/admin/universes/${user.universe}/delete`);
                    await expect(deletion.locator('input[name="_token"]')).toHaveValue(/.+/);
                    await page.locator('.admin-danger-zone summary').click();
                }
                const accessibility = await new AxeBuilder({ page }).include('#admin-main')
                    .withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa']).analyze();
                expect(accessibility.violations).toEqual([]);
                await page.evaluate(() => window.scrollTo({ top: 0, behavior: 'instant' }));
                await page.screenshot({ path: testInfo.outputPath(`univers-${index}-${theme}-${width}.png`), fullPage: true, animations: 'disabled' });
            }
        }
    }
    expect(state()).toBe(before);
    expect(queryIsolatedSql(project, "SELECT COUNT(*) FROM admin_audit_log WHERE action IN ('universe_settings_change','universe_schedule_change','universe_permanently_deleted')")).toBe('0');
});

test('Univers B : filtres paginés, création et calendrier sans JavaScript', async ({ page, browser }) => {
    test.setTimeout(150000);
    const user = await admin(page);
    const project = process.env.BROWSER_ROUTER;
    // Ces univers ne servent qu’à la pagination de la base éphémère du scénario.
    const rows = Array.from({ length: 52 }, (_, index) => `('refonte-${index}','UniversFiltre-${index}','closed','{}')`).join(',');
    runIsolatedSql(project, `INSERT INTO universe (slug,name,status,game_settings) VALUES ${rows}`);
    const context = await browser.newContext({ storageState: await page.context().storageState(), javaScriptEnabled: false, viewport: { width: 390, height: 900 } });
    const native = await context.newPage();
    native.setDefaultTimeout(15000);
    await native.goto('/admin/univers');
    await native.getByLabel('Rechercher un univers', { exact: true }).fill('UniversFiltre');
    await native.getByRole('combobox', { name: 'État', exact: true }).selectOption('closed');
    await native.getByRole('button', { name: 'Rechercher', exact: true }).click();
    await expect(native.locator('.admin-universes-grid .admin-universe-card')).toHaveCount(50);
    const next = native.locator('.admin-mission-pages').getByRole('link', { name: 'Page suivante', exact: true });
    const params = new URL(await next.getAttribute('href'), native.url()).searchParams;
    expect(params.get('q')).toBe('UniversFiltre');
    expect(params.get('status')).toBe('closed');
    await next.click();
    await expect(native.locator('.admin-universes-grid .admin-universe-card')).toHaveCount(2);
    await native.reload();
    await expect(native.getByLabel('Rechercher un univers', { exact: true })).toHaveValue('UniversFiltre');
    await native.getByLabel('Rechercher un univers', { exact: true }).fill('Aucun univers de ce nom');
    await native.getByRole('button', { name: 'Rechercher', exact: true }).click();
    await expect(native.getByText('Aucun univers ne correspond à cette recherche.', { exact: true }).first()).toBeVisible();
    await native.goto('/admin/universes/nouveau');
    await native.getByLabel('Nom affiché').fill('Univers natif B');
    await native.getByLabel('Code unique').fill('omega-1');
    await native.getByLabel('Nombre de galaxies').fill('1');
    await native.getByLabel('Systèmes par galaxie').fill('1');
    await native.getByRole('button', { name: /Créer l’univers/ }).click();
    await expect(native.locator('.admin-draft-notice')).toContainText('déjà ce code');
    await expect(native.getByLabel('Nom affiché')).toHaveValue('Univers natif B');
    const slug = 'natif-' + crypto.randomUUID().slice(0, 8);
    await native.getByLabel('Code unique').fill(slug);
    await native.getByRole('button', { name: /Créer l’univers/ }).click();
    const id = Number(queryIsolatedSql(project, `SELECT id FROM universe WHERE slug=${quote(slug)}`));
    await expect(native).toHaveURL(new RegExp(`/admin/universes/${id}/gerer$`));
    expect(queryIsolatedSql(project, `SELECT COUNT(*) FROM planet p JOIN solar_system s ON s.id=p.solar_system_id JOIN galaxy g ON g.id=s.galaxy_id WHERE g.universe_id=${id}`)).toBe('9');
    const calendar = native.locator('.admin-calendar-form');
    await calendar.getByLabel('Ouverture (UTC)', { exact: true }).fill('2030-05-12T09:30');
    await calendar.getByLabel('Fermeture planifiée (UTC)').fill('2030-05-11T09:30');
    await calendar.getByRole('button').click();
    await expect(calendar.locator('.admin-draft-notice')).toContainText('postérieure');
    await expect(calendar.getByLabel('Ouverture (UTC)', { exact: true })).toHaveValue('2030-05-12T09:30');
    const endDate = calendar.getByLabel('Fermeture planifiée (UTC)');
    const manageUrl = new URL(native.url());
    const action = '/admin/universes/' + id + '/schedule';
    async function saveCalendar(value) {
        await endDate.fill(value);
        await expect(endDate).toHaveValue(value);
        // L’URL de gestion ne change pas. Observer la soumission native et
        // le nouveau document avant de vérifier la transaction en base.
        const [post, document] = await Promise.all([
            native.waitForResponse((response) => {
                const request = response.request();
                return request.method() === 'POST' && new URL(response.url()).pathname === action;
            }),
            native.waitForResponse((response) => {
                const request = response.request();
                const submitted = request.redirectedFrom();
                return request.isNavigationRequest() && request.method() === 'GET'
                    && response.url() === manageUrl.href
                    && submitted?.method() === 'POST' && new URL(submitted.url()).pathname === action;
            }),
            calendar.getByRole('button', { name: 'Enregistrer le calendrier' }).click(),
        ]);
        expect(post.status()).toBe(302);
        const fields = new URLSearchParams(post.request().postData() ?? '');
        expect(fields.get('ends_at')).toBe(value);
        expect(document.status()).toBe(200);
        await native.waitForLoadState('load');
        await expect(native.locator('#admin-main .flash.success')).toHaveText('Calendrier de l’univers enregistré.');
        await expect(calendar.locator('.admin-draft-notice')).toHaveCount(0);
        await expect(endDate).toHaveValue(value);
    }

    await saveCalendar('2030-05-14T18:00');
    expect(queryIsolatedSql(project, `SELECT starts_at FROM universe WHERE id=${id}`)).toBe('2030-05-12 09:30:00');
    expect(queryIsolatedSql(project, `SELECT ends_at FROM universe WHERE id=${id}`)).toBe('2030-05-14 18:00:00');
    await saveCalendar('');
    expect(queryIsolatedSql(project, `SELECT ends_at IS NULL FROM universe WHERE id=${id}`)).toBe('1');
    expect(queryIsolatedSql(project, `SELECT starts_at FROM universe WHERE id=${id}`)).toBe('2030-05-12 09:30:00');
    const rules = native.locator('.admin-settings-form');
    // Playwright exclut les éléments noscript de sa recherche de texte habituelle.
    expect(await rules.locator('noscript').textContent()).toContain('Sans JavaScript, le profil ne remplit pas les champs');
    await rules.getByRole('combobox', { name: 'Profil de vitesse' }).selectOption('x3');
    for (const speed of await rules.locator('[data-admin-speed-target="multiplier"]').all()) {
        await expect(speed).toHaveValue('1');
        await speed.fill('3');
    }
    await rules.getByRole('button', { name: 'Enregistrer les règles' }).click();
    const settings = JSON.parse(queryIsolatedSql(project, `SELECT game_settings FROM universe WHERE id=${id}`));
    expect(settings.multipliers.resource_production).toBe(3);
    expect(settings.multipliers.building_time).toBeCloseTo(1 / 3, 8);
    await native.locator('.admin-danger-zone summary').click();
    await native.locator('.admin-danger-zone input[name="confirmation"]').fill('Autre univers');
    await native.getByRole('button', { name: /Supprimer définitivement/ }).click();
    await expect(native.locator('.flash.error')).toContainText('ne correspond pas');
    expect(queryIsolatedSql(project, `SELECT COUNT(*) FROM universe WHERE id=${id}`)).toBe('1');
    await context.close();
});
