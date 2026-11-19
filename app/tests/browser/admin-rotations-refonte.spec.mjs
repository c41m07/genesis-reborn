import { test, expect } from './support/isolated-test.mjs';
import { execFileSync } from 'node:child_process';
import AxeBuilder from '@axe-core/playwright';
import { queryIsolatedSql, runIsolatedSql } from './support/game-fixtures.mjs';

const quote = value => "'" + String(value).replaceAll("'", "''") + "'";

async function admin(page) {
    page.setDefaultTimeout(15000);
    const suffix = crypto.randomUUID().slice(0, 8);
    const email = `rotation-ui-${suffix}@example.test`;
    const password = crypto.randomUUID() + 'aA1!';
    await page.goto('/inscription');
    await page.getByLabel('Adresse email').fill(email);
    await page.getByLabel('Nom de commandant').fill(`RotationUI-${suffix}`);
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
    await page.goto('/admin/rotations');
    await expect(page).toHaveURL(/acces-refuse/);
    execFileSync('docker', ['compose', '-p', process.env.BROWSER_ROUTER, '--env-file', '.env.local', 'exec', '-T', 'app', 'php', 'bin/console', 'app:admin:grant', email], { stdio: ['ignore', 'pipe', 'pipe'] });
    await page.context().clearCookies();
    await login();
    const universe = Number(queryIsolatedSql(process.env.BROWSER_ROUTER, "SELECT id FROM universe WHERE slug='omega-1'"));
    return { email, universe };
}



const definitions = (project, universe) => JSON.parse(queryIsolatedSql(project, `SELECT JSON_EXTRACT(game_settings,'$.weekly_rotations') FROM universe WHERE id=${universe}`));

test('Rotations A : trois pages, thèmes, lecture et activation au clavier', async ({ page }, testInfo) => {
    test.setTimeout(180000);
    const user = await admin(page);
    const project = process.env.BROWSER_ROUTER;
    const rows = [
        { key: 'exploration', name: 'RotationExplorationSansEspace'.repeat(5).slice(0, 120), description: 'Explorer des systèmes lointains. '.repeat(40), objective: 'exploration', format: 'competitive', reward_mode: 'top3', contribution_metric: 'new_systems_discovered', duration_seconds: 604800, global_goal: 10, threshold_value: null, enabled: true },
        { key: 'combat', name: 'Rotation · Dégâts en combat', description: '', objective: 'combat', format: 'individual', reward_mode: 'threshold', contribution_metric: 'damage_dealt', duration_seconds: 604800, global_goal: null, threshold_value: 5000, enabled: false },
        { key: 'transport', name: 'Rotation · Transports entre colonies', description: '', objective: 'transport', format: 'collective', reward_mode: 'all_contributors', contribution_metric: 'freight_delivered', duration_seconds: 604800, global_goal: null, threshold_value: null, enabled: true },
    ];
    // Les univers sans réglage peuvent contenir [] ; les fixtures doivent pouvoir ajouter leur clé JSON.
    runIsolatedSql(project, `UPDATE universe SET game_settings=JSON_SET(CASE WHEN JSON_TYPE(game_settings)='OBJECT' THEN game_settings ELSE JSON_OBJECT() END,'$.weekly_rotations',JSON_EXTRACT(${quote(JSON.stringify(rows))},'$')) WHERE id=${user.universe}`);
    expect(definitions(project, user.universe)).toEqual(rows);
    const before = queryIsolatedSql(project, `SELECT game_settings FROM universe WHERE id=${user.universe}`);
    for (const theme of ['dark', 'light']) {
        runIsolatedSql(project, `UPDATE player SET preferences=JSON_OBJECT('theme','${theme}') WHERE email=${quote(user.email)}`);
        for (const width of [1440, 768, 390]) {
            await page.setViewportSize({ width, height: 900 });
            for (const [index, url] of [`/admin/rotations?universe=${user.universe}`, `/admin/rotations/nouveau?universe=${user.universe}`, `/admin/rotations/${user.universe}/exploration/modifier`].entries()) {
                expect((await page.goto(url)).status()).toBe(200);
                await expect(page.locator('h1')).toHaveCount(1);
                await expect(page.locator('body')).toHaveAttribute('data-theme', theme);
                await expect(page.locator('.admin-primary-link[aria-current]')).toHaveCount(1);
                await expect(page.locator('.page-contents')).toHaveCount(0);
                await expect.poll(() => page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(width);
                if (index === 0) {
                    await expect(page.locator('.admin-rotation-models article')).toHaveCount(3);
                    await expect(page.locator('.admin-rotation-models')).toContainText('Top 3 et ex æquo');
                    await expect(page.locator('.admin-rotation-models')).toContainText(/Seuil individuel : 5\s000/);
                } else {
                    await expect(page.locator('.admin-rotation-form > section')).toHaveCount(3);
                    if (index === 2) await expect(page.getByLabel('Consignes pour les joueurs')).toHaveValue(rows[0].description);
                }
                expect((await new AxeBuilder({ page }).include('#admin-main').analyze()).violations).toEqual([]);
                await page.screenshot({ path: testInfo.outputPath(`rotations-${index}-${theme}-${width}.png`), fullPage: true, animations: 'disabled' });
            }
        }
    }
    const summary = page.locator('.admin-rotation-toggle > summary');
    await expect(page.getByLabel('Motif du changement')).toBeHidden();
    await summary.focus();
    await page.keyboard.press('Enter');
    await page.getByLabel('Motif du changement').fill('Brouillon de changement conservé');
    await summary.click();
    await summary.click();
    await expect(page.getByLabel('Motif du changement')).toHaveValue('Brouillon de changement conservé');
    expect((await new AxeBuilder({ page }).include('#admin-main').analyze()).violations).toEqual([]);
    await page.getByLabel('Motif du changement').focus();
    await page.screenshot({ path: testInfo.outputPath('rotations-activation-light-390.png'), fullPage: true });
    expect(queryIsolatedSql(project, `SELECT game_settings FROM universe WHERE id=${user.universe}`)).toBe(before);
});

test('Rotations A sans JavaScript : erreurs conservées, activation motivée et univers', async ({ page, browser }, testInfo) => {
    test.setTimeout(180000);
    const user = await admin(page);
    const project = process.env.BROWSER_ROUTER;
    const context = await browser.newContext({ storageState: await page.context().storageState(), javaScriptEnabled: false, viewport: { width: 390, height: 900 } });
    try {
        const native = await context.newPage();
        await native.goto(`/admin/rotations/nouveau?universe=${user.universe}`);
        await expect(native.getByLabel('Modèle actif pour les prochaines semaines')).toBeChecked();
        await native.getByLabel('Nom de la rotation').fill('Rotation native A');
        await native.getByLabel('Consignes pour les joueurs').fill('Consignes conservées après refus.');
        await native.getByLabel('Format de la rotation').selectOption('competitive');
        await native.getByLabel('Attribution du boost').selectOption('top3');
        await native.getByLabel('Durée en heures').fill('1.5');
        await native.getByLabel('Modèle actif pour les prochaines semaines').uncheck();
        await native.getByLabel('Motif de l’enregistrement').fill('Réglages de recette.');
        await native.getByRole('button', { name: 'Créer la rotation hebdomadaire', exact: true }).click();
        await expect(native.locator('.flash.error')).toContainText('Le Top 3 nécessite un objectif global');
        await expect(native.getByLabel('Consignes pour les joueurs')).toHaveValue('Consignes conservées après refus.');
        await expect(native.getByLabel('Motif de l’enregistrement')).toHaveValue('Réglages de recette.');
        await expect(native.getByLabel('Modèle actif pour les prochaines semaines')).not.toBeChecked();
        await native.screenshot({ path: testInfo.outputPath('rotations-erreur-390.png'), fullPage: true });
        await native.getByLabel('Objectif global (obligatoire pour le Top 3)').fill('10.5');
        await native.getByRole('button', { name: 'Créer la rotation hebdomadaire', exact: true }).click();
        await expect(native.locator('.flash.error')).toContainText('seuils entiers');
        await native.getByLabel('Objectif global (obligatoire pour le Top 3)').fill('10');
        await native.getByRole('button', { name: 'Créer la rotation hebdomadaire', exact: true }).click();
        await expect(native.locator('.flash.success')).toContainText('Modèle créé');
        const created = definitions(project, user.universe).find(row => row.name === 'Rotation native A');
        expect(created.enabled).toBe(false);
        expect(created.duration_seconds).toBe(5400);
        await native.getByRole('link', { name: 'Modifier Rotation native A', exact: true }).click();
        const edit = native.url();
        await native.locator('.admin-rotation-toggle > summary').click();
        await native.getByLabel('Motif du changement').fill('   ');
        await native.getByRole('button', { name: 'Activer ce modèle', exact: true }).click();
        await expect(native.locator('.flash.error')).toContainText('Motif obligatoire');
        expect(definitions(project, user.universe).find(row => row.key === created.key).enabled).toBe(false);
        await native.goto(edit);
        await native.locator('.admin-rotation-toggle > summary').click();
        await native.getByLabel('Motif du changement').fill('Activer les semaines à venir.');
        await native.getByRole('button', { name: 'Activer ce modèle', exact: true }).click();
        await expect(native.locator('.flash.success')).toContainText('Activation mise à jour');
        expect(definitions(project, user.universe).find(row => row.key === created.key)).toEqual({ ...created, enabled: true });
        await native.goto(edit);
        await native.reload();
        await expect(native.getByLabel('Modèle actif pour les prochaines semaines')).toBeChecked();
        await native.getByRole('link', { name: 'Tous les modèles de cet univers', exact: true }).click();
        await native.goBack();
        await expect(native).toHaveURL(edit);
        const other = Number(queryIsolatedSql(project, "SELECT id FROM universe WHERE slug='turbo-1'"));
        runIsolatedSql(project, `UPDATE universe SET game_settings=JSON_SET(CASE WHEN JSON_TYPE(game_settings)='OBJECT' THEN game_settings ELSE JSON_OBJECT() END,'$.weekly_rotations',JSON_ARRAY()) WHERE id=${other}`);
        expect(definitions(project, other)).toEqual([]);
        await native.goto(`/admin/rotations?universe=${user.universe}`);
        await native.getByLabel('Univers à gérer').selectOption(String(other));
        await native.getByRole('button', { name: 'Afficher cet univers', exact: true }).click();
        await expect(native).toHaveURL(new RegExp(`universe=${other}`));
        await expect(native.locator('.admin-rotation-models')).toContainText('Aucun modèle.');
        expect(new URL(await native.getByRole('link', { name: 'Créer un modèle hebdomadaire' }).getAttribute('href'), native.url()).searchParams.get('universe')).toBe(String(other));
        expect(new URL(await native.getByRole('link', { name: 'Occurrences et historique de cet univers' }).getAttribute('href'), native.url()).searchParams.get('universe')).toBe(String(other));
        await native.screenshot({ path: testInfo.outputPath('rotations-vide-390.png'), fullPage: true });
        // Le plafond est préparé dans la base jetable, sans créer trente modèles par l’interface.
        const full = Array.from({ length: 30 }, (_, index) => ({ ...created, key: `volume-${index}`, name: `Rotation ${index}` }));
        runIsolatedSql(project, `UPDATE universe SET game_settings=JSON_SET(CASE WHEN JSON_TYPE(game_settings)='OBJECT' THEN game_settings ELSE JSON_OBJECT() END,'$.weekly_rotations',JSON_EXTRACT(${quote(JSON.stringify(full))},'$')) WHERE id=${user.universe}`);
        await native.goto(`/admin/rotations?universe=${user.universe}`);
        await expect(native.locator('.admin-rotation-models article')).toHaveCount(30);
        await native.getByRole('link', { name: 'Créer un modèle hebdomadaire', exact: true }).click();
        await native.getByLabel('Nom de la rotation').fill('Au-delà du plafond');
        await native.getByLabel('Motif de l’enregistrement').fill('Vérifier le plafond existant.');
        await native.getByRole('button', { name: 'Créer la rotation hebdomadaire', exact: true }).click();
        await expect(native.locator('.flash.error')).toContainText('Trente modèles au maximum');
        expect(definitions(project, user.universe)).toEqual(full);
    } finally {
        await context.close();
    }
});
