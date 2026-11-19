import { test, expect } from './support/isolated-test.mjs';
import { execFileSync } from 'node:child_process';
import AxeBuilder from '@axe-core/playwright';
import { queryIsolatedSql, runIsolatedSql } from './support/game-fixtures.mjs';

const quote = value => "'" + String(value).replaceAll("'", "''") + "'";

async function admin(page) {
    page.setDefaultTimeout(15000);
    const suffix = crypto.randomUUID().slice(0, 8);
    const email = `project-ui-${suffix}@example.test`;
    const password = crypto.randomUUID() + 'aA1!';
    await page.goto('/inscription');
    await page.getByLabel('Adresse email').fill(email);
    await page.getByLabel('Nom de commandant').fill(`ProjectUI-${suffix}`);
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
    await page.goto('/admin/projets-guilde');
    await expect(page).toHaveURL(/acces-refuse/);
    execFileSync('docker', ['compose', '-p', process.env.BROWSER_ROUTER, '--env-file', '.env.local', 'exec', '-T', 'app', 'php', 'bin/console', 'app:admin:grant', email], { stdio: ['ignore', 'pipe', 'pipe'] });
    await page.context().clearCookies();
    await login();
    const universe = Number(queryIsolatedSql(process.env.BROWSER_ROUTER, "SELECT id FROM universe WHERE slug='omega-1'"));
    return { email, universe };
}


const stages = [{ name: 'Relais initial', costs: { metal: 2000, nano: 500 } }, { name: 'Réseau étendu', costs: { metal: 3000, nano: 1000 } }];

function insertModel(project, universe, name, enabled = false) {
    runIsolatedSql(project, `INSERT INTO guild_project_definition (universe_id,name,description,stages,minimums,enabled,version,created_at,updated_at) VALUES (${universe},${quote(name)},${quote('Construire ensemble un réseau de relais. '.repeat(25))},${quote(JSON.stringify(stages))},'{"metal":1000,"nano":300}',${enabled ? 1 : 0},1,UTC_TIMESTAMP(),UTC_TIMESTAMP())`);
    return Number(queryIsolatedSql(project, `SELECT id FROM guild_project_definition WHERE name=${quote(name)}`));
}

test('Projets A : liste et formulaires dans les deux thèmes, cinq ressources lisibles', async ({ page }, testInfo) => {
    test.setTimeout(180000);
    const user = await admin(page);
    const project = process.env.BROWSER_ROUTER;
    const name = 'ReseauInterstellaireSansEspace'.repeat(5).slice(0, 120);
    const id = insertModel(project, user.universe, name, true);
    insertModel(project, user.universe, 'Chantier collectif');
    const before = queryIsolatedSql(project, 'SELECT id,name,stages,minimums,enabled,version FROM guild_project_definition ORDER BY id');
    for (const theme of ['dark', 'light']) {
        runIsolatedSql(project, `UPDATE player SET preferences=JSON_OBJECT('theme','${theme}') WHERE email=${quote(user.email)}`);
        for (const width of [1440, 768, 390]) {
            await page.setViewportSize({ width, height: 900 });
            for (const [index, route] of ['/admin/projets-guilde', '/admin/projets-guilde/nouveau', `/admin/projets-guilde/${id}/modifier`].entries()) {
                expect((await page.goto(route)).status()).toBe(200);
                await expect(page.locator('h1')).toHaveCount(1);
                await expect(page.locator('body')).toHaveAttribute('data-theme', theme);
                await expect(page.locator('.page-contents')).toHaveCount(0);
                await expect(page.locator('.admin-primary-link[aria-current]')).toHaveCount(1);
                await expect.poll(() => page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(width);
                if (index === 0) {
                    await expect(page.locator('.admin-project-list article')).toHaveCount(2);
                    await expect(page.locator('.admin-project-list')).toContainText('au maximum 5 récompense');
                } else {
                    const resources = page.locator('.admin-project-resources').first();
                    expect(await resources.evaluate(element => getComputedStyle(element).gridTemplateColumns.split(' ').length)).toBe(width === 1440 ? 5 : width === 768 ? 2 : 1);
                    await expect(page.locator('textarea')).toHaveCount(1);
                    if (index === 2) {
                        await expect(page.getByLabel('Univers', { exact: true })).toBeDisabled();
                        await expect(page.getByLabel('Description', { exact: true })).toHaveValue('Construire ensemble un réseau de relais. '.repeat(25));
                    }
                }
                expect((await new AxeBuilder({ page }).include('#admin-main').analyze()).violations).toEqual([]);
                await page.screenshot({ path: testInfo.outputPath(`projets-${index}-${theme}-${width}.png`), fullPage: true, animations: 'disabled' });
            }
        }
    }
    expect(queryIsolatedSql(project, 'SELECT id,name,stages,minimums,enabled,version FROM guild_project_definition ORDER BY id')).toBe(before);
});

test('Projets A sans JavaScript : paliers, erreurs, version et pagination préservés', async ({ page, browser }, testInfo) => {
    test.setTimeout(180000);
    const user = await admin(page);
    const project = process.env.BROWSER_ROUTER;
    const context = await browser.newContext({ storageState: await page.context().storageState(), javaScriptEnabled: false, viewport: { width: 390, height: 900 } });
    try {
        const native = await context.newPage();
        await native.goto('/admin/projets-guilde');
        await expect(native.locator('.admin-project-list')).toContainText('Aucun modèle de projet.');
        await native.screenshot({ path: testInfo.outputPath('projets-vide-390.png'), fullPage: true });
        await native.getByRole('link', { name: 'Créer un modèle de projet', exact: true }).click();
        await expect(native.getByRole('button', { name: 'Retirer le dernier palier', exact: true })).toBeDisabled();
        await expect(native.getByLabel('Disponible pour de nouveaux lancements')).not.toBeChecked();
        // L’ajout ne doit ni exiger un brouillon complet ni enregistrer le modèle.
        await native.getByRole('button', { name: 'Ajouter un palier', exact: true }).click();
        await expect(native.getByLabel('Nom du palier 2', { exact: true })).toBeFocused();
        expect(queryIsolatedSql(project, 'SELECT COUNT(*) FROM guild_project_definition')).toBe('0');
        await native.getByLabel('Nom du projet', { exact: true }).fill('Réseau natif A');
        await native.getByLabel('Description', { exact: true }).fill('Description conservée après refus.');
        await native.getByLabel('Univers', { exact: true }).selectOption(String(user.universe));
        for (const [index, stage] of stages.entries()) {
            await native.getByLabel(`Nom du palier ${index + 1}`, { exact: true }).fill(stage.name);
            await native.getByLabel(`Métal · palier ${index + 1}`, { exact: true }).fill(String(stage.costs.metal));
            await native.getByLabel(`Nano · palier ${index + 1}`, { exact: true }).fill(String(stage.costs.nano));
        }
        await native.getByLabel('Minimum Métal', { exact: true }).fill('6000');
        await native.getByLabel('Minimum Nano', { exact: true }).fill('300');
        await native.getByLabel('Disponible pour de nouveaux lancements').check();
        await native.getByRole('button', { name: 'Enregistrer le modèle', exact: true }).click();
        await expect(native.locator('.flash.error')).toContainText('dépasse le coût total');
        await expect(native.getByLabel('Description', { exact: true })).toHaveValue('Description conservée après refus.');
        await expect(native.getByLabel('Minimum Métal', { exact: true })).toHaveValue('6000');
        await expect(native.getByLabel('Disponible pour de nouveaux lancements')).toBeChecked();
        expect(queryIsolatedSql(project, 'SELECT COUNT(*) FROM guild_project_definition')).toBe('0');
        await native.screenshot({ path: testInfo.outputPath('projets-erreur-390.png'), fullPage: true });
        await native.getByLabel('Minimum Métal', { exact: true }).fill('1000');
        await native.getByRole('button', { name: 'Enregistrer le modèle', exact: true }).click();
        await expect(native.locator('.flash.success')).toContainText('Modèle enregistré');
        await native.getByRole('link', { name: 'Modifier Réseau natif A', exact: true }).click();
        const edit = native.url();
        await expect(native.getByLabel('Univers', { exact: true })).toBeDisabled();
        const stale = await context.newPage();
        await stale.goto(edit);
        await stale.getByLabel('Description', { exact: true }).fill('Ancien formulaire conservé.');
        await native.getByLabel('Description', { exact: true }).fill('Version enregistrée.');
        await native.getByLabel('Disponible pour de nouveaux lancements').uncheck();
        await native.getByRole('button', { name: 'Enregistrer le modèle', exact: true }).click();
        await expect(native.locator('.admin-project-list')).toContainText('Désactivé pour les nouveaux lancements');
        await stale.getByRole('button', { name: 'Enregistrer le modèle', exact: true }).click();
        await expect(stale.locator('.flash.error')).toContainText('Le modèle a changé');
        await expect(stale.getByLabel('Description', { exact: true })).toHaveValue('Ancien formulaire conservé.');
        expect(queryIsolatedSql(project, 'SELECT description FROM guild_project_definition')).toBe('Version enregistrée.');
        await native.goto(edit);
        await native.reload();
        await expect(native.getByLabel('Description', { exact: true })).toHaveValue('Version enregistrée.');
        await native.getByRole('link', { name: 'Annuler la modification', exact: true }).click();
        await native.goBack();
        await expect(native).toHaveURL(edit);
        await native.goto('/admin/projets-guilde/nouveau');
        await native.getByLabel('Nom du projet', { exact: true }).fill('Brouillon de dix paliers');
        for (let index = 2; index <= 10; index++) {
            await native.getByRole('button', { name: 'Ajouter un palier', exact: true }).click();
            await expect(native.getByLabel(`Nom du palier ${index}`, { exact: true })).toBeFocused();
        }
        await expect(native.getByRole('button', { name: 'Ajouter un palier', exact: true })).toBeDisabled();
        await expect(native.locator('.admin-project-stage')).toHaveCount(10);
        await expect(native.getByLabel('Nom du projet', { exact: true })).toHaveValue('Brouillon de dix paliers');
        expect(queryIsolatedSql(project, 'SELECT COUNT(*) FROM guild_project_definition')).toBe('1');
        await native.getByRole('button', { name: 'Retirer le dernier palier', exact: true }).click();
        await expect(native.locator('.admin-project-stage')).toHaveCount(9);
        await expect(native.getByLabel('Nom du palier 9', { exact: true })).toBeFocused();
        // Les volumes de pagination sont des données synthétiques de la base jetable.
        const rows = Array.from({ length: 52 }, (_, index) => `(${user.universe},'Modèle ${index}','',${quote(JSON.stringify(stages))},'{"metal":1000,"nano":300}',0,1,UTC_TIMESTAMP(),UTC_TIMESTAMP())`).join(',');
        runIsolatedSql(project, `INSERT INTO guild_project_definition (universe_id,name,description,stages,minimums,enabled,version,created_at,updated_at) VALUES ${rows}`);
        await native.goto('/admin/projets-guilde');
        await expect(native.locator('.admin-project-list article')).toHaveCount(50);
        await native.getByRole('link', { name: 'Page suivante', exact: true }).click();
        await expect(native.locator('.admin-project-list article')).toHaveCount(3);
        await native.reload();
        await expect(native).toHaveURL(/page=2/);
        await native.getByRole('link', { name: /Modifier / }).first().click();
        await expect(native.getByLabel('Nom du projet', { exact: true })).toBeVisible();
        await native.goBack();
        await expect(native.locator('.admin-project-list article')).toHaveCount(3);
        expect(queryIsolatedSql(project, 'SELECT COUNT(*) FROM guild_project_definition')).toBe('53');
    } finally {
        await context.close();
    }
});
