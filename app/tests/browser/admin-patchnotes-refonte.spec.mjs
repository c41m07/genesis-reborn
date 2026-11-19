import { test, expect } from './support/isolated-test.mjs';
import { execFileSync } from 'node:child_process';
import AxeBuilder from '@axe-core/playwright';
import { queryIsolatedSql, runIsolatedSql } from './support/game-fixtures.mjs';

const quote = value => "'" + String(value).replaceAll("'", "''") + "'";

async function admin(page) {
    page.setDefaultTimeout(15000);
    const suffix = crypto.randomUUID().slice(0, 8);
    const email = `patchnote-ui-${suffix}@example.test`;
    const password = crypto.randomUUID() + 'aA1!';
    await page.goto('/inscription');
    await page.getByLabel('Adresse email').fill(email);
    await page.getByLabel('Nom de commandant').fill(`PatchnoteUI-${suffix}`);
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




function fixture(project, suffix = '', count = 1) {
    runIsolatedSql(project, `INSERT INTO patch_note (title,version,summary,content,status,published_at,created_at,updated_at) VALUES ${Array.from({ length: count }, (_, index) => `('Note${suffix} ${index}','Alpha-test','Résumé de validation','AMÉLIORATIONS\\n• Contenu <script>alert(1)</script> conservé en texte.','draft',NULL,UTC_TIMESTAMP(),UTC_TIMESTAMP())`).join(',')}`);
    return Number(queryIsolatedSql(project, 'SELECT MAX(id) FROM patch_note'));
}

test('Patchnotes A : quatre pages lisibles, thèmes, clavier et contenus longs', async ({ page }, testInfo) => {
    test.setTimeout(180000);
    const own = await admin(page);
    const project = process.env.BROWSER_ROUTER;
    const id = fixture(project);
    runIsolatedSql(project, `UPDATE patch_note SET title='${'Galaxie'.repeat(22)}',summary='${'Résumé long conservé. '.repeat(20)}' WHERE id=${id}`);
    for (const theme of ['dark', 'light']) {
        runIsolatedSql(project, `UPDATE player SET preferences=JSON_OBJECT('theme','${theme}') WHERE email=${quote(own.email)}`);
        for (const width of [1440, 768, 390]) {
            await page.setViewportSize({ width, height: 900 });
            for (const [index, route] of ['/admin/patchnotes', '/admin/patchnotes/nouvelle', `/admin/patchnotes/${id}`, `/admin/patchnotes/${id}/supprimer`].entries()) {
                expect((await page.goto(route)).status()).toBe(200);
                await expect(page.locator('h1')).toHaveCount(1);
                await expect(page.locator('body')).toHaveAttribute('data-theme', theme);
                await expect(page.locator('.page-contents, .page-editor')).toHaveCount(0);
                await expect.poll(() => page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(width);
                if (index === 1 || index === 2) {
                    await expect(page.locator('.admin-patchnote-form')).toHaveCount(1);
                    await expect(page.locator('.admin-patchnote-form section')).toHaveCount(2);
                    await expect(page.locator('#note-content')).toBeVisible();
                }
                expect((await new AxeBuilder({ page }).include('#admin-main').analyze()).violations).toEqual([]);
                await page.screenshot({ path: testInfo.outputPath(`patchnotes-${index}-${theme}-${width}.png`), fullPage: true, animations: 'disabled' });
            }
        }
    }
    expect(queryIsolatedSql(project, `SELECT COUNT(*) FROM patch_note WHERE id=${id}`)).toBe('1');
    expect(queryIsolatedSql(project, "SELECT COUNT(*) FROM admin_audit_log WHERE target_type='patch_note'")).toBe('0');
    await page.goto('/admin/patchnotes');
    const open = page.getByRole('link', { name: /^Modifier la patchnote/ });
    await open.focus();
    await page.keyboard.press('Enter');
    await expect(page).toHaveURL(new RegExp(`/admin/patchnotes/${id}$`));
    await page.reload();
    await expect(page.getByLabel('Titre', { exact: true })).toHaveValue('Galaxie'.repeat(22));
    await page.goBack();
    await expect(page.locator('.admin-patchnote-list article')).toHaveCount(1);
});

test('Patchnotes A sans JavaScript : filtres, pagination, erreur et suppression confirmée', async ({ page, browser }, testInfo) => {
    test.setTimeout(180000);
    await admin(page);
    const project = process.env.BROWSER_ROUTER;
    const id = fixture(project, ' pagination', 52);
    const context = await browser.newContext({ storageState: await page.context().storageState(), javaScriptEnabled: false, viewport: { width: 390, height: 900 } });
    try {
        const native = await context.newPage();
        await native.goto('/admin/patchnotes?status=draft&q=pagination');
        await expect(native.locator('.admin-patchnote-list article')).toHaveCount(50);
        await native.getByRole('link', { name: 'Page suivante', exact: true }).click();
        await expect(native.locator('.admin-patchnote-list article')).toHaveCount(2);
        await native.reload();
        expect(new URL(native.url()).searchParams.get('q')).toBe('pagination');
        expect(new URL(native.url()).searchParams.get('status')).toBe('draft');
        await native.getByLabel('Recherche', { exact: true }).fill('aucun-résultat');
        await native.getByRole('button', { name: 'Filtrer', exact: true }).click();
        await expect(native.locator('.admin-patchnote-list')).toContainText('Aucune patchnote');
        await native.screenshot({ path: testInfo.outputPath('patchnotes-vide-390.png'), fullPage: true });
        await native.goto(`/admin/patchnotes/${id}`);
        const form = native.locator('.admin-patchnote-form');
        const action = await form.getAttribute('action');
        const token = await form.locator('[name="_token"]').inputValue();
        const values = { _token: token, title: '', version: 'Alpha-erreur', summary: 'Résumé conservé', content: 'Contenu conservé', status: 'published', publishedAt: '2030-02-28T12:15' };
        // Le résolveur Chromium ne s’applique pas au client HTTP de Playwright.
        const url = new URL(action, native.url());
        const cookie = (await context.cookies(native.url())).map(item => `${item.name}=${item.value}`).join('; ');
        const response = await native.request.post(`http://127.0.0.1${url.pathname}`, { form: values, headers: { host: url.host, cookie } });
        expect(response.status()).toBe(422);
        expect(await response.text()).toContain('Résumé conservé');
        expect(queryIsolatedSql(project, `SELECT version FROM patch_note WHERE id=${id}`)).toBe('Alpha-test');
        await native.getByLabel('Version / tag').fill('Alpha-native');
        await native.getByRole('button', { name: 'Enregistrer la patchnote', exact: true }).click();
        await expect(native.getByRole('status')).toContainText('Patchnote mise à jour');
        await native.goto(`/admin/patchnotes/${id}/supprimer`);
        expect(queryIsolatedSql(project, `SELECT COUNT(*) FROM patch_note WHERE id=${id}`)).toBe('1');
        await native.getByRole('link', { name: 'Annuler et revenir à la fiche', exact: true }).click();
        await expect(native).toHaveURL(new RegExp(`/admin/patchnotes/${id}$`));
        await native.goBack();
        await native.getByRole('button', { name: 'Supprimer définitivement', exact: true }).click();
        await expect(native.getByRole('status')).toContainText('Patchnote supprimée');
        expect(queryIsolatedSql(project, `SELECT COUNT(*) FROM patch_note WHERE id=${id}`)).toBe('0');
        expect(queryIsolatedSql(project, "SELECT COUNT(*) FROM admin_audit_log WHERE action='patchnote_deleted'")).toBe('1');
        const oldCreate = await native.goto('/admin/patchnotes/nouvelle');
        expect(oldCreate.status()).toBe(200);
        await native.getByLabel('Titre', { exact: true }).fill('Création native');
        await native.getByLabel('Version / tag').fill('Alpha-création');
        await native.getByLabel('Résumé', { exact: true }).fill('Résumé natif');
        await native.getByLabel('Contenu détaillé').fill('Contenu natif');
        await native.getByRole('button', { name: 'Créer la patchnote', exact: true }).click();
        await expect(native.getByRole('status')).toContainText('Patchnote créée');
    } finally {
        await context.close();
    }
});
