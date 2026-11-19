import { test, expect } from './support/isolated-test.mjs';
import { execFileSync } from 'node:child_process';
import AxeBuilder from '@axe-core/playwright';
import { queryIsolatedSql, runIsolatedSql } from './support/game-fixtures.mjs';

async function enter(page, admin = false, join = false) {
    const suffix = crypto.randomUUID().slice(0, 8);
    const email = `player-ui-${suffix}@example.test`;
    const password = crypto.randomUUID() + 'aA1!';
    await page.goto('/inscription');
    await page.getByLabel('Adresse email').fill(email);
    await page.getByLabel('Nom de commandant').fill(`PlayerUI-${suffix}`);
    await page.getByLabel('Mot de passe (12 caractères minimum)', { exact: true }).fill(password);
    await page.getByLabel('Confirmer le mot de passe').fill(password);
    await page.getByRole('button', { name: 'Créer mon compte' }).click();
    await expect(page).toHaveURL(/connexion/);
    if (admin) {
        execFileSync('docker', ['compose', '-p', process.env.BROWSER_ROUTER, '--env-file', '.env.local',
            'exec', '-T', 'app', 'php', 'bin/console', 'app:admin:grant', email], { stdio: ['ignore', 'pipe', 'pipe'] });
    }
    await page.getByLabel('Adresse email').fill(email);
    await page.getByLabel('Mot de passe', { exact: true }).fill(password);
    await page.getByRole('button', { name: /Connexion/ }).click();
    await expect(page).toHaveURL(/univers$/);
    if (join) {
        await page.locator('article.universe-card').first().getByRole('button').click();
        await expect(page).toHaveURL(/planetes\/\d+$/);
    }
    return Number(queryIsolatedSql(process.env.BROWSER_ROUTER, `SELECT id FROM player WHERE email='${email}'`));
}

test('Fiche A : univers, outils dépliables, champs et thèmes sur trois formats', async ({ page, browser }, testInfo) => {
    test.setTimeout(240000);
    const reader = await browser.newPage();
    const userId = await enter(reader, false, true);
    await reader.goto('/univers');
    await reader.locator('article.universe-card').filter({ has: reader.getByRole('heading', { name: /Turbo/ }) }).getByRole('button').click();
    await expect(reader).toHaveURL(/planetes\/\d+$/);
    const adminId = await enter(page, true);
    const project = process.env.BROWSER_ROUTER;
    runIsolatedSql(project, `UPDATE player SET username='CommandantInterstellaireSansEspace' WHERE id=${userId}`);
    runIsolatedSql(project, `UPDATE planet p JOIN player_universe pu ON pu.id=p.owner_id SET p.name='ColonieInterstellaireAvecUnNomLongSansEspace' WHERE pu.player_id=${userId}`);
    const memberships = page.locator('.admin-player-membership');
    const resources = memberships.first().locator('details[id^="colony-"]').first();
    const ships = memberships.first().locator('details[id^="ships-"]').first();
    for (const theme of ['dark', 'light']) {
        runIsolatedSql(project, `UPDATE player SET preferences=JSON_OBJECT('theme','${theme}') WHERE id=${adminId}`);
        for (const width of [1440, 768, 390]) {
            await page.setViewportSize({ width, height: 900 });
            expect((await page.goto(`/admin/joueurs/${userId}`)).status()).toBe(200);
            await expect(memberships).toHaveCount(2);
            await expect(page.locator('.page-contents')).toHaveCount(0);
            await expect(page.locator('h1')).toHaveText('CommandantInterstellaireSansEspace');
            const columns = await memberships.first().locator('.admin-player-activity > div').evaluateAll(cells =>
                new Set(cells.map(cell => Math.round(cell.getBoundingClientRect().x))).size);
            expect(columns).toBe(width === 1440 ? 3 : width === 768 ? 2 : 1);
            expect(await memberships.first().locator('.admin-player-activity dd').first().evaluate(value => getComputedStyle(value).textAlign)).toBe('left');
            for (const membership of await memberships.all()) {
                const caseLink = membership.getByRole('link', { name: 'Dossiers de cette participation', exact: true });
                const createLink = membership.getByRole('link', { name: 'Ouvrir un dossier pour ce joueur', exact: true });
                expect(new URL(await caseLink.getAttribute('href'), page.url()).searchParams.get('membership')).toBe(
                    new URL(await createLink.getAttribute('href'), page.url()).searchParams.get('membership_id'));
            }
            for (const state of ['closed', 'resources', 'ships']) {
                if (state === 'resources') {
                    await resources.locator('summary').focus();
                    await page.keyboard.press('Enter');
                    await expect(resources).toHaveAttribute('open', '');
                    await expect(resources.locator('.admin-intervention-resources input')).toHaveCount(5);
                    expect((await resources.locator('textarea').boundingBox()).height).toBeGreaterThanOrEqual(150);
                    await resources.locator('[name="amounts[metal]"]').fill('7.25');
                }
                if (state === 'ships') {
                    await ships.locator('summary').click();
                    await ships.getByLabel('Quantité à attribuer').fill('3');
                    expect((await ships.locator('textarea').boundingBox()).height).toBeGreaterThanOrEqual(150);
                    // Ouvrir le deuxième outil ne remplace ni le formulaire ni la saisie du premier.
                    await expect(resources.locator('[name="amounts[metal]"]')).toHaveValue('7.25');
                    await resources.locator('summary').click();
                }
                await expect.poll(() => page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(width);
                const analysis = await new AxeBuilder({ page }).include('#admin-main')
                    .withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa']).analyze();
                expect(analysis.violations).toEqual([]);
                const visibleFields = page.locator('.admin-intervention-field :is(input,select,textarea):visible');
                expect(await visibleFields.evaluateAll(fields => fields.every(field =>
                    field.getBoundingClientRect().width <= field.parentElement.clientWidth && field.getBoundingClientRect().height >= 44))).toBe(true);
                await page.evaluate(() => window.scrollTo({ top: 0, behavior: 'instant' }));
                await page.screenshot({ path: testInfo.outputPath(`fiche-${state}-${theme}-${width}.png`), fullPage: true, animations: 'disabled' });
            }
        }
    }
    expect(queryIsolatedSql(project, "SELECT COUNT(*) FROM admin_audit_log WHERE action IN ('admin_resources_adjust','admin_ships_grant')")).toBe('0');
    await reader.close();
});

test('Fiche A : états vides et correction native sans JavaScript', async ({ page, browser }) => {
    test.setTimeout(120000);
    const reader = await browser.newPage();
    const userId = await enter(reader, false, true);
    await reader.close();
    const emptyPlayer = await browser.newPage();
    const emptyId = await enter(emptyPlayer);
    await enter(page, true);
    await page.goto(`/admin/joueurs/${emptyId}`);
    await expect(page.getByText('Ce joueur ne participe à aucun univers.', { exact: true })).toBeVisible();
    const project = process.env.BROWSER_ROUTER;
    const planetId = Number(queryIsolatedSql(project, `SELECT p.id FROM planet p JOIN player_universe pu ON pu.id=p.owner_id WHERE pu.player_id=${userId} LIMIT 1`));
    runIsolatedSql(project, `DELETE pa FROM player_activity pa JOIN player_universe pu ON pu.id=pa.membership_id WHERE pu.player_id=${userId}`);
    runIsolatedSql(project, `UPDATE planet_resource SET current_stock=5000,last_updated_at=UTC_TIMESTAMP() WHERE planet_id=${planetId} AND resource_type='metal'`);
    const fallback = await browser.newContext({ javaScriptEnabled: false, viewport: { width: 390, height: 900 }, storageState: await page.context().storageState() });
    const nativePage = await fallback.newPage();
    await nativePage.goto(`/admin/joueurs/${userId}`);
    await expect(nativePage.locator('.admin-player-activity')).toContainText('Non mesurée');
    await expect(nativePage.locator('.admin-player-activity')).toContainText('Aucune activité enregistrée');
    const details = nativePage.locator(`#colony-${planetId}`);
    await details.locator('summary').click();
    await details.getByLabel('Métal', { exact: true }).fill('25.50');
    await details.getByLabel('Motif de la correction (obligatoire)').fill('Recette native de la correction');
    await details.getByRole('checkbox').check();
    await details.getByRole('button', { name: 'Appliquer la correction' }).click();
    await expect(nativePage.locator('.flash.success')).toContainText('Correction des ressources enregistrée.');
    await expect(nativePage).toHaveURL(new RegExp(`/admin/joueurs/${userId}#colony-${planetId}$`));
    const audit = JSON.parse(queryIsolatedSql(project, `SELECT details FROM admin_audit_log WHERE action='admin_resources_adjust' AND target_id=${planetId}`));
    expect(audit.after.metal - audit.before.metal).toBeCloseTo(25.5, 4);
    runIsolatedSql(project, `UPDATE planet SET owner_id=NULL WHERE id=${planetId}`);
    await nativePage.reload();
    await expect(nativePage.getByText('Aucune colonie.', { exact: true })).toBeVisible();
    await fallback.close();
    await emptyPlayer.close();
});
