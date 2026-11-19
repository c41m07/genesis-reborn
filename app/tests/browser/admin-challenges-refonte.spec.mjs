import { test, expect } from './support/isolated-test.mjs';
import { execFileSync } from 'node:child_process';
import AxeBuilder from '@axe-core/playwright';
import { queryIsolatedSql, runIsolatedSql } from './support/game-fixtures.mjs';

const quote = value => "'" + String(value).replaceAll("'", "''") + "'";

async function admin(page) {
    page.setDefaultTimeout(15000);
    const suffix = crypto.randomUUID().slice(0, 8);
    const email = `challenge-ui-${suffix}@example.test`;
    const password = crypto.randomUUID() + 'aA1!';
    await page.goto('/inscription');
    await page.getByLabel('Adresse email').fill(email);
    await page.getByLabel('Nom de commandant').fill(`ChallengeUI-${suffix}`);
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




function event(project, universe, key, name, { weekly = false, archived = false, status = 'active' } = {}) {
    const configuration = { guided: !weekly, name, description: 'Les nouvelles découvertes sont suivies pendant la période. '.repeat(8), minimum_contribution: 2, reward: { kind: 'resource_box', box: { label: 'Boîte de recette', resources: { metal: 1000, electricity: 200, antimatter: 50, hydrogen: 100, nano: 25 } } } };
    if (weekly) configuration.reward = { kind: 'boost' };
    if (archived) configuration.archived_at = '2026-11-18T00:00:00Z';
    runIsolatedSql(project, `INSERT INTO boost_event (universe_id,event_key,template_key,status,format,reward_mode,contribution_metric,duration_seconds,scheduled_at,started_at,ends_at,global_goal,total_contribution,configuration) VALUES (${universe},${quote(key)},'exploration',${quote(status)},${quote(weekly ? 'competitive' : 'collective')},${quote(weekly ? 'top3' : 'all_contributors')},'new_systems_discovered',604800,UTC_TIMESTAMP()-INTERVAL 1 DAY,UTC_TIMESTAMP()-INTERVAL 1 DAY,UTC_TIMESTAMP()+INTERVAL 6 DAY,10,2,${quote(JSON.stringify(configuration))})`);
    return Number(queryIsolatedSql(project, `SELECT id FROM boost_event WHERE event_key=${quote(key)}`));
}

function participants(project, universe, count) {
    // Des participations sans compte suffisent à tester le nom de repli et la pagination.
    const first = Number(queryIsolatedSql(project, 'SELECT COALESCE(MAX(id),0) FROM player_universe'));
    runIsolatedSql(project, `INSERT INTO player_universe (universe_id,joined_at,catalog_unlocks) VALUES ${Array.from({ length: count }, () => `(${universe},UTC_TIMESTAMP(),'{}')`).join(',')}`);
    return queryIsolatedSql(project, `SELECT GROUP_CONCAT(id ORDER BY id) FROM player_universe WHERE id>${first}`).split(',').map(Number);
}

async function fixtures(page) {
    const user = await admin(page);
    const project = process.env.BROWSER_ROUTER;
    runIsolatedSql(project, "UPDATE universe SET game_settings=JSON_SET(CASE WHEN JSON_TYPE(game_settings)='OBJECT' THEN game_settings ELSE JSON_OBJECT() END,'$.weekly_rotations',JSON_ARRAY())");
    const guided = event(project, user.universe, 'guided-ui', 'Exploration collective de recette');
    const weekly = event(project, user.universe, 'weekly-ui', 'Rotation de recette', { weekly: true });
    event(project, user.universe, 'archived-ui', 'Ancien défi retiré', { archived: true, status: 'closed' });
    const members = participants(project, user.universe, 2);
    for (const id of [guided, weekly]) runIsolatedSql(project, `INSERT INTO boost_event_contribution (event_id,player_universe_id,source_key,value) VALUES ${members.map(member => `(${id},${member},'ui-${id}-${member}',1)`).join(',')}`);
    return { ...user, project, guided, weekly };
}

test('Défis A : liste, suivi et rotation lisibles dans les deux thèmes', async ({ page }, testInfo) => {
    test.setTimeout(180000);
    const own = await fixtures(page);
    runIsolatedSql(own.project, `UPDATE boost_event SET configuration=JSON_SET(configuration,'$.name',${quote('ExplorationCollectiveSansEspaces'.repeat(4).slice(0, 120))}) WHERE id=${own.guided}`);
    const before = queryIsolatedSql(own.project, "SELECT id,status,configuration,total_contribution FROM boost_event WHERE event_key LIKE '%-ui' ORDER BY id");
    for (const theme of ['dark', 'light']) {
        runIsolatedSql(own.project, `UPDATE player SET preferences=JSON_OBJECT('theme','${theme}') WHERE email=${quote(own.email)}`);
        for (const width of [1440, 768, 390]) {
            await page.setViewportSize({ width, height: 900 });
            for (const [index, route] of [`/admin/defis?universe=${own.universe}`, `/admin/defis/${own.guided}`, `/admin/defis/${own.weekly}`].entries()) {
                expect((await page.goto(route)).status()).toBe(200);
                await expect(page.locator('h1')).toHaveCount(1);
                await expect(page.locator('body')).toHaveAttribute('data-theme', theme);
                await expect(page.locator('.page-contents')).toHaveCount(0);
                await expect.poll(() => page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(width);
                if (index > 0) {
                    expect(await page.locator('.admin-contribution-table').isVisible()).toBe(width === 1440);
                    expect(await page.locator('.admin-contribution-cards').isVisible()).toBe(width !== 1440);
                    await expect(page.locator('.admin-contribution-cards article')).toHaveCount(2);
                    await expect(page.locator('.admin-contribution-cards')).toContainText('Empire oublié');
                    if (index === 1) {
                        await expect(page.locator('.report-facts')).toHaveCount(2);
                        await expect(page.locator('.report-facts').first()).toContainText('Boîte de recette');
                        await expect(page.locator('.report-facts').last()).toContainText(/1\s000,00/);
                    }
                    else await expect(page.locator('.event-removal-form')).toHaveCount(0);
                }
                expect((await new AxeBuilder({ page }).include('#admin-main').analyze()).violations).toEqual([]);
                await page.screenshot({ path: testInfo.outputPath(`defis-${index}-${theme}-${width}.png`), fullPage: true, animations: 'disabled' });
            }
        }
    }
    const summary = page.locator('.admin-challenge-cancel > summary');
    await summary.focus();
    await page.keyboard.press('Enter');
    await page.getByLabel('Motif d’annulation de la rotation').fill('Motif conservé dans le dépliable');
    await summary.click();
    await summary.click();
    await expect(page.getByLabel('Motif d’annulation de la rotation')).toHaveValue('Motif conservé dans le dépliable');
    expect(queryIsolatedSql(own.project, "SELECT id,status,configuration,total_contribution FROM boost_event WHERE event_key LIKE '%-ui' ORDER BY id")).toBe(before);
    expect(Number(queryIsolatedSql(own.project, "SELECT COUNT(*) FROM admin_audit_log WHERE action='admin_challenge_view'"))).toBeGreaterThanOrEqual(12);
});

test('Défis A sans JavaScript : filtres, deux paginations, annulation et retrait', async ({ page, browser }, testInfo) => {
    test.setTimeout(180000);
    const own = await fixtures(page);
    const context = await browser.newContext({ storageState: await page.context().storageState(), javaScriptEnabled: false, viewport: { width: 390, height: 900 } });
    try {
        const native = await context.newPage();
        for (let index = 0; index < 52; index++) event(own.project, own.universe, `volume-ui-${index}`, `Défi historique ${index}`, { status: 'closed' });
        await native.goto(`/admin/defis?universe=${own.universe}&status=closed`);
        await expect(native.locator('.admin-challenges-list article')).toHaveCount(50);
        await native.getByRole('link', { name: 'Suivant', exact: true }).click();
        await expect(native.locator('.admin-challenges-list article')).toHaveCount(2);
        await native.reload();
        expect(new URL(native.url()).searchParams.get('status')).toBe('closed');
        expect(new URL(native.url()).searchParams.get('universe')).toBe(String(own.universe));
        await native.getByRole('link', { name: /Défi historique/ }).first().click();
        await native.goBack();
        await expect(native.locator('.admin-challenges-list article')).toHaveCount(2);
        await native.getByLabel('État', { exact: true }).selectOption('cancelled');
        await native.getByRole('button', { name: 'Filtrer les défis', exact: true }).click();
        await expect(native.locator('.admin-challenges-list')).toContainText('Aucun défi ne correspond aux filtres.');
        await native.screenshot({ path: testInfo.outputPath('defis-vide-390.png'), fullPage: true });
        const members = participants(own.project, own.universe, 50);
        runIsolatedSql(own.project, `INSERT INTO boost_event_contribution (event_id,player_universe_id,source_key,value) VALUES ${members.map(member => `(${own.guided},${member},'page-${member}',1)`).join(',')}; UPDATE boost_event SET total_contribution=52 WHERE id=${own.guided}`);
        await native.goto(`/admin/defis/${own.guided}`);
        await expect(native.locator('.admin-contribution-cards article')).toHaveCount(50);
        await native.getByRole('link', { name: 'Suivant', exact: true }).click();
        await expect(native.locator('.admin-contribution-cards article')).toHaveCount(2);
        await native.reload();
        await expect(native).toHaveURL(/page=2/);
        await native.locator('.admin-challenge-cancel > summary').click();
        await native.getByLabel('Motif d’annulation', { exact: true }).fill('Clôturer cette animation de recette');
        await native.getByRole('button', { name: 'Annuler ce défi', exact: true }).click();
        expect(queryIsolatedSql(own.project, `SELECT status FROM boost_event WHERE id=${own.guided}`)).toBe('active');
        await native.getByLabel('Je confirme l’annulation du défi et de son annonce.').check();
        await native.getByRole('button', { name: 'Annuler ce défi', exact: true }).click();
        await expect(native.locator('.flash.success')).toContainText('Défi et annonce annulés');
        expect(queryIsolatedSql(own.project, `SELECT status FROM boost_event WHERE id=${own.guided}`)).toBe('cancelled');
        await native.getByText('Supprimer ce défi', { exact: true }).click();
        await native.getByLabel('Motif de suppression').fill('Retirer le défi de recette terminé');
        await native.getByLabel('Je confirme la suppression ou le retrait de cet élément.').check();
        await native.getByRole('button', { name: 'Supprimer le défi', exact: true }).click();
        await expect(native.locator('.flash.success')).toContainText('Défi retiré');
        expect(queryIsolatedSql(own.project, `SELECT COUNT(*) FROM boost_event_contribution WHERE event_id=${own.guided}`)).toBe('52');
        await native.goto(`/admin/defis?archived=1&universe=${own.universe}&status=cancelled`);
        await expect(native.getByRole('link', { name: 'Exploration collective de recette', exact: true })).toBeVisible();
        await native.getByRole('link', { name: 'Exploration collective de recette', exact: true }).click();
        await expect(native.locator('.event-removal-form')).toHaveCount(0);
        await expect(native.locator('.admin-challenge-cancel')).toHaveCount(0);
        await expect(native.locator('main')).toContainText('Son historique est conservé.');
    } finally {
        await context.close();
    }
});
