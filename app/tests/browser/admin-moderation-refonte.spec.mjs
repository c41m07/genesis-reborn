import { test, expect } from './support/isolated-test.mjs';
import { execFileSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import AxeBuilder from '@axe-core/playwright';
import { queryIsolatedSql, runIsolatedSql } from './support/game-fixtures.mjs';

const quote = value => "'" + String(value).replaceAll("'", "''") + "'";
const capturePath = fileURLToPath(new URL('../../public/art/ui-preview-colony.png', import.meta.url));

async function enter(page, admin = false, join = false) {
    page.setDefaultTimeout(15000);
    const suffix = crypto.randomUUID().slice(0, 8);
    const email = `moderation-ui-${suffix}@example.test`;
    const password = crypto.randomUUID() + 'aA1!';
    await page.goto('/inscription');
    await page.getByLabel('Adresse email').fill(email);
    await page.getByLabel('Nom de commandant').fill(`ModerationUI-${suffix}`);
    await page.getByLabel('Mot de passe (12 caractères minimum)', { exact: true }).fill(password);
    await page.getByLabel('Confirmer le mot de passe').fill(password);
    await page.getByRole('button', { name: 'Créer mon compte' }).click();
    await expect(page).toHaveURL(/connexion/);
    if (admin) execFileSync('docker', ['compose', '-p', process.env.BROWSER_ROUTER, '--env-file', '.env.local',
        'exec', '-T', 'app', 'php', 'bin/console', 'app:admin:grant', email], { stdio: ['ignore', 'pipe', 'pipe'] });
    await page.getByLabel('Adresse email').fill(email);
    await page.getByLabel('Mot de passe', { exact: true }).fill(password);
    await page.getByRole('button', { name: /Connexion/ }).click();
    await expect(page).toHaveURL(/univers$/);
    if (join) {
        await page.locator('article.universe-card').first().getByRole('button').click();
        await expect(page).toHaveURL(/planetes\/\d+$/);
    }
    return Number(queryIsolatedSql(process.env.BROWSER_ROUTER, `SELECT id FROM player WHERE email=${quote(email)}`));
}

test('L10C A : contenus, filtres, thèmes et capture privée', async ({ page, browser }, testInfo) => {
    test.setTimeout(240000);
    const owner = await browser.newPage();
    const ownerId = await enter(owner, false, true);
    await owner.goto('/feedback');
    await owner.getByLabel('Type', { exact: true }).selectOption('bug');
    await owner.getByLabel('Priorité ressentie').selectOption('high');
    await owner.getByLabel('Titre', { exact: true }).fill('RetourInterstellaireSansEspace'.repeat(4).slice(0, 120));
    await owner.getByLabel('Description détaillée', { exact: true }).fill('Description complète\n<script>window.feedbackExecuted=true</script>\nFin du retour.');
    await owner.getByLabel('Lien de la page concernée (facultatif)').fill('https://example.test/' + 'colonie'.repeat(35));
    await owner.getByLabel('Joindre une capture').setInputFiles(capturePath);
    await owner.getByRole('button', { name: 'Envoyer mon retour' }).click();
    await expect(owner.locator('.flash.success')).toContainText('Votre feedback a bien été envoyé');
    const project = process.env.BROWSER_ROUTER;
    const feedbackId = Number(queryIsolatedSql(project, 'SELECT MAX(id) FROM user_feedback'));
    const capture = `/feedback/${feedbackId}/capture`;
    expect((await owner.goto(capture)).status()).toBe(200);
    const outsider = await browser.newPage();
    await enter(outsider);
    expect((await outsider.goto(capture)).status()).toBe(403);
    expect((await outsider.goto(`/admin/feedbacks/${feedbackId}`)).status()).toBe(403);
    const adminId = await enter(page, true, true);
    const [sender, recipient, universe] = queryIsolatedSql(project, `SELECT a.current_player_universe_id,p.current_player_universe_id,pu.universe_id FROM player a JOIN player p ON p.id=${ownerId} JOIN player_universe pu ON pu.id=p.current_player_universe_id WHERE a.id=${adminId}`).split('\t').map(Number);
    runIsolatedSql(project, `INSERT INTO social_message (sender_id,recipient_id,content,content_format,created_at) VALUES (${sender},${recipient},'Message signalé de test','plain',UTC_TIMESTAMP())`);
    const messageId = Number(queryIsolatedSql(project, 'SELECT MAX(id) FROM social_message'));
    runIsolatedSql(project, `INSERT INTO message_report (message_id,reporter_id,reason,status,created_at) VALUES (${messageId},${recipient},'Motif à examiner','open',UTC_TIMESTAMP())`);
    runIsolatedSql(project, `INSERT INTO social_message (sender_id,recipient_id,content,content_format,created_at) VALUES (${sender},${recipient},'CONTENU_NON_SIGNALE_CONFIDENTIEL','plain',UTC_TIMESTAMP())`);
    const evidence = { target_username: 'CommandantInterstellaireSansEspace', target_username_seen: 'Ancien nom', reporter_username: 'Témoin',
        target_id: sender, reporter_id: recipient, universe_name: 'Omega', universe_id: universe, category: 'points', rank: 12, leaderboard_at: '2026-11-19T09:00:00Z' };
    runIsolatedSql(project, `INSERT INTO player_report (universe_id,reporter_id,target_id,request_key,reason,comment,context,status,created_at) VALUES (${universe},${recipient},${sender},'${'b'.repeat(32)}','other','Observation conservée',${quote(JSON.stringify(evidence))},'open',UTC_TIMESTAMP())`);
    const response = await page.goto(capture);
    expect(response.status()).toBe(200);
    expect(response.headers()['x-content-type-options']).toBe('nosniff');
    for (const theme of ['dark', 'light']) {
        runIsolatedSql(project, `UPDATE player SET preferences=JSON_OBJECT('theme','${theme}') WHERE id=${adminId}`);
        for (const width of [1440, 768, 390]) {
            await page.setViewportSize({ width, height: 900 });
            for (const [index, route] of ['/admin/moderation', '/admin/feedbacks', `/admin/feedbacks/${feedbackId}`].entries()) {
                expect((await page.goto(route)).status()).toBe(200);
                await expect(page.locator('body')).toHaveAttribute('data-theme', theme);
                await expect(page.locator('.page-contents')).toHaveCount(0);
                await expect(page.locator('h1')).toHaveCount(1);
                await expect.poll(() => page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(width);
                if (index === 0) {
                    await expect(page.locator('#admin-reports')).not.toContainText('CONTENU_NON_SIGNALE_CONFIDENTIEL');
                    const search = page.getByRole('searchbox', { name: 'Rechercher dans les messages' });
                    await search.fill('aucun-message-correspondant');
                    await expect(page.getByText('Aucun signalement ne correspond à cette recherche.', { exact: true })).toBeVisible();
                    await expect(page.locator('#admin-player-reports')).toContainText('Observation conservée');
                    await search.fill('');
                }
                if (index === 2) {
                    await expect(page.locator('.feedback-description')).toContainText('<script>window.feedbackExecuted=true</script>');
                    expect(await page.evaluate(() => window.feedbackExecuted)).toBeUndefined();
                    await expect(page.locator('.feedback-screenshot')).toBeVisible();
                    await expect(page.locator('.feedback-detail-heading > .feedback-status')).toHaveCSS('white-space', 'nowrap');
                    expect(await page.locator('.feedback-detail-heading > .feedback-status').evaluate(badge => badge.scrollWidth <= badge.clientWidth)).toBe(true);
                    expect((await page.locator('#note').boundingBox()).height).toBeGreaterThanOrEqual(200);
                    const grid = await page.locator('.feedback-detail-grid').evaluate(element => getComputedStyle(element).gridTemplateColumns.split(' ').length);
                    expect(grid).toBe(width === 1440 ? 2 : 1);
                }
                const analysis = await new AxeBuilder({ page }).include('#admin-main')
                    .withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa']).analyze();
                expect(analysis.violations).toEqual([]);
                await page.evaluate(() => window.scrollTo({ top: 0, behavior: 'instant' }));
                await page.screenshot({ path: testInfo.outputPath(`moderation-${index}-${theme}-${width}.png`), fullPage: true, animations: 'disabled' });
            }
        }
    }
    // La régression de contraste concerne aussi les autres états du même composant.
    for (const [status, priority] of [['in_progress', 'medium'], ['resolved', 'low'], ['rejected', 'high'], ['archived', 'medium']]) {
        runIsolatedSql(project, `UPDATE user_feedback SET status='${status}',priority='${priority}' WHERE id=${feedbackId}`);
        await page.goto(`/admin/feedbacks/${feedbackId}`);
        const analysis = await new AxeBuilder({ page }).include('#admin-main').withTags(['wcag2a', 'wcag2aa']).analyze();
        expect(analysis.violations).toEqual([]);
        await expect(page.getByLabel('Statut', { exact: true })).toHaveValue(status);
    }
    expect(queryIsolatedSql(project, "SELECT COUNT(*) FROM admin_audit_log WHERE action IN ('feedback_updated','message_report_redact','player_report_actioned')")).toBe('0');
    await owner.close();
    await outsider.close();
});

test('L10C A : filtres natifs, pagination et suivi privé', async ({ page, browser }) => {
    test.setTimeout(120000);
    const adminId = await enter(page, true);
    const project = process.env.BROWSER_ROUTER;
    const universe = Number(queryIsolatedSql(project, "SELECT id FROM universe WHERE slug='omega-1'"));
    const values = Array.from({ length: 53 }, (_, index) => `(${adminId},${universe},'bug','high','Retour-${index}','Description synthétique','open',UTC_TIMESTAMP())`).join(',');
    runIsolatedSql(project, `INSERT INTO user_feedback (player_id,universe_id,type,priority,title,description,status,created_at) VALUES ${values}`);
    const id = Number(queryIsolatedSql(project, 'SELECT MAX(id) FROM user_feedback'));
    runIsolatedSql(project, `INSERT INTO user_feedback (player_id,universe_id,type,priority,title,description,status,created_at) VALUES (${adminId},${universe},'bug','high','Retour archivé','Archive synthétique','archived',UTC_TIMESTAMP())`);
    const native = await browser.newContext({ javaScriptEnabled: false, viewport: { width: 390, height: 900 }, storageState: await page.context().storageState() });
    const fallback = await native.newPage();
    await fallback.goto('/admin/feedbacks');
    const filters = fallback.locator('.feedback-admin-filters');
    await filters.getByLabel('Recherche').fill('Retour');
    await filters.getByLabel('Type').selectOption('bug');
    await filters.getByLabel('Priorité').selectOption('high');
    await filters.getByLabel('Univers').selectOption(String(universe));
    await filters.getByRole('button', { name: 'Appliquer les filtres' }).click();
    await expect(fallback.locator('.feedback-admin-card')).toHaveCount(50);
    const next = fallback.getByRole('link', { name: 'Page suivante', exact: true });
    const nextParams = new URL(await next.getAttribute('href'), fallback.url()).searchParams;
    for (const [key, value] of Object.entries({ q: 'Retour', type: 'bug', priority: 'high', universe: String(universe) })) expect(nextParams.get(key)).toBe(value);
    await next.click();
    await expect(fallback.locator('.feedback-admin-card')).toHaveCount(3);
    await fallback.getByRole('link', { name: 'Voir les archives', exact: true }).click();
    await expect(fallback.locator('.feedback-admin-card')).toHaveCount(1);
    expect(new URL(fallback.url()).searchParams.get('page')).toBe('1');
    await expect(filters.getByLabel('Recherche')).toHaveValue('Retour');
    await fallback.getByRole('link', { name: 'Masquer les archives', exact: true }).click();
    await expect(fallback.locator('.feedback-admin-card')).toHaveCount(50);
    await fallback.goto(`/admin/feedbacks/${id}`);
    await expect(fallback.locator('.feedback-screenshot')).toHaveCount(0);
    await fallback.getByLabel('Statut', { exact: true }).selectOption('in_progress');
    await fallback.getByLabel(/Note interne/).fill('Note confidentielle de recette');
    await fallback.getByRole('button', { name: 'Enregistrer les changements' }).click();
    await expect(fallback.locator('.flash.success')).toContainText('Feedback mis à jour.');
    await expect(fallback.locator('.feedback-internal-note')).toContainText('Note confidentielle de recette');
    expect(queryIsolatedSql(project, `SELECT status FROM user_feedback WHERE id=${id}`)).toBe('in_progress');
    const form = await fallback.locator('.feedback-manage-card form').evaluate(element => Object.fromEntries(new FormData(element)));
    // Les requêtes de contrôle passent par le navigateur, qui résout le domaine isolé de la recette.
    await page.goto(`/admin/feedbacks/${id}`);
    const post = values => page.evaluate(async ({ id, values }) => {
        const response = await fetch(`/admin/feedbacks/${id}`, { method: 'POST', body: new URLSearchParams(values) });
        return { status: response.status, html: await response.text() };
    }, { id, values });
    const invalid = await post({ ...form, status: 'invalid', internalNote: 'Refus attendu' });
    expect(invalid.html).toContain('Statut ou note invalide.');
    expect(queryIsolatedSql(project, `SELECT internal_note FROM user_feedback WHERE id=${id}`)).toBe('Note confidentielle de recette');
    expect((await post({ ...form, _token: 'invalid' })).status).toBe(403);
    expect(queryIsolatedSql(project, `SELECT COUNT(*) FROM admin_audit_log WHERE action='feedback_updated' AND target_id=${id}`)).toBe('1');
    await native.close();
});
