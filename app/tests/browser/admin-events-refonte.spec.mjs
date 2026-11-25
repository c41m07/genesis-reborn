import { test, expect } from './support/isolated-test.mjs';
import { execFileSync } from 'node:child_process';
import AxeBuilder from '@axe-core/playwright';
import { queryIsolatedSql, runIsolatedSql } from './support/game-fixtures.mjs';

const quote = value => "'" + String(value).replaceAll("'", "''") + "'";
const future = minutes => new Date(Date.now() + minutes * 60000).toISOString().slice(0, 16);

async function admin(page) {
    page.setDefaultTimeout(15000);
    const suffix = crypto.randomUUID().slice(0, 8);
    const email = `events-ui-${suffix}@example.test`;
    const password = crypto.randomUUID() + 'aA1!';
    await page.goto('/inscription');
    await page.getByLabel('Adresse email').fill(email);
    await page.getByLabel('Nom de commandant').fill(`EventsUI-${suffix}`);
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
    await page.goto('/admin/events');
    await expect(page).toHaveURL(/acces-refuse/);
    execFileSync('docker', ['compose', '-p', process.env.BROWSER_ROUTER, '--env-file', '.env.local', 'exec', '-T', 'app', 'php', 'bin/console', 'app:admin:grant', email], { stdio: ['ignore', 'pipe', 'pipe'] });
    await page.context().clearCookies();
    await login();
    const universe = Number(queryIsolatedSql(process.env.BROWSER_ROUTER, "SELECT id FROM universe WHERE slug='omega-1'"));
    return { email, universe };
}

test('Événements A : cinq vues, annulation dépliable et lecture dans les deux thèmes', async ({ page }, testInfo) => {
    test.setTimeout(180000);
    const user = await admin(page);
    const project = process.env.BROWSER_ROUTER;
    const name = 'EvenementInterstellaireSansEspace'.repeat(4).slice(0, 120);
    runIsolatedSql(project, `INSERT INTO game_event (universe_id,name,description,type,status,starts_at,ends_at,parameters,visible_to_players) VALUES (${user.universe},${quote(name)},${quote('Description de la fenêtre de production. '.repeat(12))},'production_bonus','planned',UTC_TIMESTAMP()+INTERVAL 2 DAY,UTC_TIMESTAMP()+INTERVAL 3 DAY,'{"guided_key":"ui-reference","percentage":15}',1),(${user.universe},'Brouillon de calendrier','Description conservée','announcement','draft',UTC_TIMESTAMP()+INTERVAL 2 DAY,UTC_TIMESTAMP()+INTERVAL 3 DAY,'{}',0)`);
    const before = queryIsolatedSql(project, 'SELECT id,name,status,parameters FROM game_event ORDER BY id');
    await page.goto('/admin/events/assistant?model=bonus');
    await page.getByLabel('Nom de l’événement').fill('Aperçu sans publication');
    await page.getByLabel('Description pour les joueurs').fill('Réglages relus avant confirmation.');
    await page.getByLabel('Début (UTC)').fill(future(60));
    await page.getByLabel('Fin (UTC)').fill(future(120));
    await page.getByRole('button', { name: 'Voir l’aperçu' }).click();
    const preview = new URL(page.url()).pathname;
    expect(preview).toMatch(/assistant\/apercu\/[a-f0-9]{32}$/);
    for (const theme of ['dark', 'light']) {
        runIsolatedSql(project, `UPDATE player SET preferences=JSON_OBJECT('theme','${theme}') WHERE email=${quote(user.email)}`);
        for (const width of [1440, 768, 390]) {
            await page.setViewportSize({ width, height: 900 });
            for (const [index, route] of ['/admin/events', '/admin/events/assistant', '/admin/events/assistant?model=bonus', preview, '/admin/events/nouveau'].entries()) {
                expect((await page.goto(route)).status()).toBe(200);
                await expect(page.locator('body')).toHaveAttribute('data-theme', theme);
                await expect(page.locator('h1')).toHaveCount(1);
                await expect(page.locator('.page-contents')).toHaveCount(0);
                await expect.poll(() => page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(width);
                if (index === 1) await expect(page.locator('.admin-event-model-choices article')).toHaveCount(3);
                if (index === 2 || index === 4) {
                    const form = page.locator(index === 2 ? '.admin-event-wizard-form' : '.admin-event-form');
                    expect(await form.evaluate(element => getComputedStyle(element).gridTemplateColumns.split(' ').length)).toBe(width === 1440 ? 2 : 1);
                }
                const accessibility = await new AxeBuilder({ page }).include('#admin-main').analyze();
                expect(accessibility.violations).toEqual([]);
                await page.screenshot({ path: testInfo.outputPath(`evenements-${index}-${theme}-${width}.png`), fullPage: true, animations: 'disabled' });
            }
        }
    }
    expect(queryIsolatedSql(project, 'SELECT id,name,status,parameters FROM game_event ORDER BY id')).toBe(before);
    await page.goto('/admin/events');
    const guided = page.locator('.admin-events-list article').filter({ has: page.getByRole('heading', { name, exact: true }) });
    await expect(guided.getByLabel('Motif d’annulation')).toBeHidden();
    const summary = guided.locator('.admin-event-cancel > summary');
    await summary.scrollIntoViewIfNeeded();
    await summary.focus();
    await page.keyboard.press('Enter');
    await expect(guided.getByLabel('Motif d’annulation')).toBeVisible();
    await guided.getByLabel('Motif d’annulation').fill('Saisie en cours conservée');
    await expect(guided).toContainText('La définition publiée est figée');
    await summary.click();
    await summary.click();
    await expect(guided.getByLabel('Motif d’annulation')).toHaveValue('Saisie en cours conservée');
    expect(queryIsolatedSql(project, 'SELECT id,name,status,parameters FROM game_event ORDER BY id')).toBe(before);
});

test('Événements A sans JavaScript : erreurs, aperçu, création, édition et historique paginé', async ({ page, browser }) => {
    test.setTimeout(180000);
    const user = await admin(page);
    const project = process.env.BROWSER_ROUTER;
    const context = await browser.newContext({ storageState: await page.context().storageState(), javaScriptEnabled: false, viewport: { width: 390, height: 900 } });
    const native = await context.newPage();
    native.setDefaultTimeout(15000);
    await native.goto('/admin/events');
    await expect(native.locator('.admin-events-list')).toContainText('Aucun événement pour le moment.');
    const createDraft = async name => {
        await native.goto('/admin/events/nouveau');
        await native.getByLabel('Nom', { exact: true }).fill(name);
        await native.getByLabel('Description', { exact: true }).fill('Description native conservée.');
        await native.getByLabel('Début', { exact: true }).fill(future(60));
        await native.getByLabel('Fin', { exact: true }).fill(future(120));
        await native.getByRole('button', { name: 'Créer', exact: true }).click();
        await expect(native.locator('.flash.success')).toContainText('Événement créé');
    };
    await native.goto('/admin/events/nouveau');
    await native.getByLabel('Nom', { exact: true }).fill('Annonce native');
    await native.getByLabel('Description', { exact: true }).fill('Texte à conserver après erreur.');
    await native.getByLabel('Début', { exact: true }).fill(future(120));
    await native.getByLabel('Fin', { exact: true }).fill(future(60));
    await native.getByRole('button', { name: 'Créer', exact: true }).click();
    await expect(native.locator('.flash.error')).toContainText('après la date de début');
    await expect(native.getByLabel('Nom', { exact: true })).toHaveValue('Annonce native');
    await expect(native.getByLabel('Description', { exact: true })).toHaveValue('Texte à conserver après erreur.');
    expect(queryIsolatedSql(project, 'SELECT COUNT(*) FROM game_event')).toBe('0');
    await native.getByLabel('Fin', { exact: true }).fill(future(180));
    await native.getByRole('button', { name: 'Créer', exact: true }).click();
    await native.getByRole('link', { name: 'Modifier Annonce native', exact: true }).click();
    await native.getByLabel('Nom', { exact: true }).fill('Annonce native modifiée');
    const status = native.locator('form.admin-event-form select[name="status"]');
    await expect(status).toHaveValue('draft');
    await expect(status.locator('option[value="planned"]')).toHaveText('Planifié');
    await status.selectOption('planned');
    await expect(status).toHaveValue('planned');
    await native.getByRole('button', { name: 'Enregistrer', exact: true }).click();
    await expect(native.locator('.flash.success')).toContainText('Événement modifié');
    await native.reload();
    await expect(native.locator('.admin-events-list')).toContainText('Annonce native modifiée');
    expect(queryIsolatedSql(project, "SELECT status FROM game_event WHERE name='Annonce native modifiée'")).toBe('planned');
    await native.getByRole('button', { name: 'Annuler l’événement', exact: true }).click();
    expect(queryIsolatedSql(project, "SELECT status FROM game_event WHERE name='Annonce native modifiée'")).toBe('cancelled');
    await createDraft('Brouillon à supprimer');
    const draft = native.locator('article').filter({ has: native.getByRole('heading', { name: 'Brouillon à supprimer', exact: true }) });
    await expect(draft.locator('form[action$="/delete"], form[action$="/cancel"]')).toHaveCount(0);
    await expect(draft.locator('form[action$="/retirer"]')).toHaveCount(1);
    const summary = draft.getByText('Retirer le brouillon', { exact: true });
    await summary.focus();
    await native.keyboard.press('Enter');
    const remove = draft.getByRole('button', { name: 'Confirmer le retrait', exact: true });
    await remove.click();
    expect(queryIsolatedSql(project, "SELECT status FROM game_event WHERE name='Brouillon à supprimer'")).toBe('draft');
    await draft.getByLabel('Motif du retrait', { exact: true }).fill('Doublon avant publication');
    await draft.getByLabel('Je confirme le retrait de ce brouillon.', { exact: true }).check();
    await remove.click();
    await expect(native.locator('.flash.success')).toContainText('Événement supprimé');
    expect(queryIsolatedSql(project, "SELECT COUNT(*) FROM game_event WHERE name='Brouillon à supprimer'")).toBe('0');
    expect(queryIsolatedSql(project, "SELECT JSON_UNQUOTE(JSON_EXTRACT(details,'$.reason')) FROM admin_audit_log WHERE action='admin_event_deleted' AND JSON_UNQUOTE(JSON_EXTRACT(details,'$.name'))='Brouillon à supprimer'")).toBe('Doublon avant publication');
    await native.goto('/admin/events/assistant');
    await native.getByRole('link', { name: 'Choisir ce modèle : Bonus temporaire de production', exact: true }).click();
    await native.getByLabel('Nom de l’événement').fill('Bonus natif A');
    await native.getByLabel('Début (UTC)').fill('2020-01-01T00:00');
    await native.getByLabel('Fin (UTC)').fill('2020-01-02T00:00');
    await native.getByRole('button', { name: 'Voir l’aperçu' }).click();
    await expect(native.locator('.flash.error')).toContainText('Le début doit être à venir');
    await expect(native.getByLabel('Nom de l’événement')).toHaveValue('Bonus natif A');
    await native.getByLabel('Début (UTC)').fill(future(60));
    await native.getByLabel('Fin (UTC)').fill(future(120));
    await native.getByLabel('Bonus de production (%)').fill('15');
    await native.getByRole('button', { name: 'Voir l’aperçu' }).click();
    await expect(native.locator('main')).toContainText('Production des ressources +15 %');
    await native.getByRole('link', { name: '← Modifier les réglages', exact: true }).click();
    await expect(native.getByLabel('Bonus de production (%)')).toHaveValue('15');
    await native.getByRole('button', { name: 'Voir l’aperçu' }).click();
    await native.getByRole('button', { name: 'Confirmer et planifier' }).click();
    await expect(native.locator('.flash.success')).toContainText('Événement planifié');
    expect(queryIsolatedSql(project, "SELECT COUNT(*) FROM game_event WHERE name='Bonus natif A'")).toBe('1');
    await native.goto('/admin/events/assistant/apercu/' + '0'.repeat(32));
    await expect(native.locator('.flash.error')).toContainText('Cet aperçu a expiré');
    const archived = Array.from({ length: 52 }, (_, index) => `(${user.universe},'Archive-${index}','Historique conservé','announcement','finished',UTC_TIMESTAMP()-INTERVAL 2 DAY,UTC_TIMESTAMP()-INTERVAL 1 DAY,JSON_OBJECT('archived_at','2026-11-18T12:00:00Z'),1)`).join(',');
    runIsolatedSql(project, `INSERT INTO game_event (universe_id,name,description,type,status,starts_at,ends_at,parameters,visible_to_players) VALUES ${archived}`);
    await native.goto('/admin/events?archived=1');
    await expect(native.locator('.admin-events-list article')).toHaveCount(50);
    await expect(native.locator('.admin-events-list button')).toHaveCount(0);
    const next = native.getByRole('link', { name: 'Page suivante', exact: true });
    expect(new URL(await next.getAttribute('href'), native.url()).searchParams.get('archived')).toBe('1');
    await next.click();
    await expect(native.locator('.admin-events-list article')).toHaveCount(2);
    await native.reload();
    await expect(native).toHaveURL(/archived=1.*page=2/);
    await native.getByRole('link', { name: 'Événements courants', exact: true }).click();
    await native.goBack();
    await expect(native.locator('.admin-events-list article')).toHaveCount(2);
    await context.close();
});
