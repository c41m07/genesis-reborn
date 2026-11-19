import { test, expect } from './support/isolated-test.mjs';
import { execFileSync } from 'node:child_process';
import AxeBuilder from '@axe-core/playwright';
import { queryIsolatedSql, runIsolatedSql } from './support/game-fixtures.mjs';

const quote = value => "'" + String(value).replaceAll("'", "''") + "'";

async function enter(page, isAdmin = true) {
    page.setDefaultTimeout(15000);
    const suffix = crypto.randomUUID().slice(0, 8);
    const email = `surveillance-${suffix}@example.test`;
    const password = crypto.randomUUID() + 'aA1!';
    await page.goto('/inscription');
    await page.getByLabel('Adresse email').fill(email);
    await page.getByLabel('Nom de commandant').fill(`Surveillance-${suffix}`);
    await page.getByLabel('Mot de passe (12 caractères minimum)', { exact: true }).fill(password);
    await page.getByLabel('Confirmer le mot de passe').fill(password);
    await page.getByRole('button', { name: 'Créer mon compte' }).click();
    await expect(page).toHaveURL(/connexion/);
    const project = process.env.BROWSER_ROUTER;
    if (isAdmin) {
        execFileSync('docker', ['compose', '-p', project, '--env-file', '.env.local', 'exec', '-T', 'app', 'php', 'bin/console', 'app:admin:grant', email], { stdio: ['ignore', 'pipe', 'pipe'] });
    }
    await page.getByLabel('Adresse email').fill(email);
    await page.getByLabel('Mot de passe', { exact: true }).fill(password);
    await page.getByRole('button', { name: /Connexion/ }).click();
    await page.locator('article.universe-card').filter({ has: page.getByRole('heading', { name: /Omega/ }) }).getByRole('button').click();
    await expect(page).toHaveURL(/planetes\/\d+$/);
    const [player, membership, universe, planet] = queryIsolatedSql(project, `SELECT p.id,pu.id,pu.universe_id,pl.id FROM player p JOIN player_universe pu ON pu.id=p.current_player_universe_id JOIN planet pl ON pl.owner_id=pu.id WHERE p.email=${quote(email)} LIMIT 1`).split('\t').map(Number);
    return { player, membership, universe, planet };
}

function fleet(user, status = 'traveling', late = false) {
    const project = process.env.BROWSER_ROUTER;
    runIsolatedSql(project, `INSERT INTO fleet (player_universe_id,home_planet_id,destination_id,mission,status,speed_ratio,cargo,reserved_fuel,orbit_fuel,orbit_duration_seconds,departure_at,arrival_at,travel_seconds,dispatch_token) VALUES (${user.membership},${user.planet},${user.planet},'transport','${status}',1,'{"metal":1200,"hydrogen":250}',50,0,0,UTC_TIMESTAMP(),${late ? 'UTC_TIMESTAMP()-INTERVAL 8 MINUTE' : 'UTC_TIMESTAMP()+INTERVAL 1 HOUR'},3600,UUID())`);
    return Number(queryIsolatedSql(project, 'SELECT MAX(id) FROM fleet'));
}

async function accessible(page, width) {
    await expect(page.locator('h1')).toHaveCount(1);
    await expect(page.locator('.page-contents')).toHaveCount(0);
    expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(width);
    const result = await new AxeBuilder({ page }).include('#admin-main')
        .withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa']).analyze();
    expect(result.violations).toEqual([]);
}

test('Surveillance A : trois vues, départ distinct, formulaires dépliables et thèmes', async ({ page }, testInfo) => {
    test.setTimeout(180000);
    const user = await enter(page);
    const project = process.env.BROWSER_ROUTER;
    const id = fleet(user);
    fleet(user, 'returning', true);
    runIsolatedSql(project, `INSERT INTO fleet_ship (fleet_id,slug,tier,quantity) VALUES (${id},'transport','basic',4)`);
    runIsolatedSql(project, `UPDATE planet SET name=${quote('ColonieSansEspace'.repeat(5).slice(0, 80))} WHERE id=${user.planet}`);
    const trace = { origin: 'Terra au départ', target: 'Nova au départ', ships: [{ label: 'Transporteur', tier: 'basic', quantity: 6 }], freight: { metal: 1500, hydrogen: 200 }, fuel: 80 };
    runIsolatedSql(project, `INSERT INTO fleet_mission_trace (fleet_id,universe_id,sender_id,recipient_id,mission,target_was_player,departed_at,snapshot) VALUES (${id},${user.universe},${user.membership},${user.membership},'transport',1,UTC_TIMESTAMP(),${quote(JSON.stringify(trace))})`);
    const state = () => queryIsolatedSql(project, `SELECT MD5(CONCAT(status,dispatch_token,cargo,reserved_fuel,arrival_at)) FROM fleet WHERE id=${id}`);
    const before = state();
    for (const theme of ['dark', 'light']) {
        runIsolatedSql(project, `UPDATE player SET preferences=JSON_OBJECT('theme','${theme}') WHERE id=${user.player}`);
        for (const width of [1440, 768, 390]) {
            await page.setViewportSize({ width, height: 900 });
            for (const [index, route] of [`/admin/surveillance?universe=${user.universe}`, '/admin/flottes', `/admin/flottes/${id}`].entries()) {
                expect((await page.goto(route)).status()).toBe(200);
                await expect(page.locator('body')).toHaveAttribute('data-theme', theme);
                if (index === 0) {
                    await expect(page.locator('.admin-surveillance-current')).toContainText('Retard > 5 min');
                    await expect(page.locator('.admin-surveillance-thresholds details')).not.toHaveAttribute('open');
                    await page.locator('.admin-surveillance-thresholds summary').click();
                    await accessible(page, width);
                    await page.locator('.admin-surveillance-thresholds summary').click();
                } else if (index === 1) {
                    await expect(page.locator('.admin-missions-table tbody tr')).toHaveCount(2);
                    await expect(page.locator('.admin-missions-table tbody tr').first().locator('td[data-label]')).toHaveCount(7);
                    expect(await page.locator('.admin-missions-table tbody tr').first().evaluate(node => getComputedStyle(node).display)).toBe(width === 1440 ? 'table-row' : 'block');
                    if (width !== 1440) {
                        const row = await page.locator('.admin-missions-table tbody tr').first().boundingBox();
                        const heading = await page.locator('.admin-missions-table tbody th').first().boundingBox();
                        expect(heading.width).toBeGreaterThanOrEqual(row.width - 2);
                    }
                } else {
                    await expect(page.locator('.admin-mission-intervention details')).not.toHaveAttribute('open');
                    await page.getByText('Consulter l’instantané du départ', { exact: true }).click();
                    await expect(page.getByText('Terra au départ', { exact: false })).toBeVisible();
                    await expect(page.getByRole('region', { name: 'Composition actuelle de la flotte' })).toContainText('4');
                    const composition = page.getByRole('region', { name: 'Composition actuelle de la flotte' });
                    const bounds = await composition.boundingBox();
                    const quantity = await composition.getByRole('cell', { name: '4', exact: true }).boundingBox();
                    expect(quantity.x + quantity.width).toBeLessThanOrEqual(bounds.x + bounds.width);
                    await page.getByText('Consulter l’instantané du départ', { exact: true }).click();
                    await page.locator('.admin-mission-intervention summary').focus();
                    await page.keyboard.press('Enter');
                    expect((await page.locator('textarea').boundingBox()).height).toBeGreaterThanOrEqual(200);
                    await accessible(page, width);
                    await page.locator('.admin-mission-intervention summary').click();
                    await expect(page.getByRole('link', { name: 'Dossiers de cette mission', exact: true })).toHaveAttribute('href', `/admin/dossiers?fleet=${id}`);
                }
                await accessible(page, width);
                await page.evaluate(() => window.scrollTo({ top: 0, behavior: 'instant' }));
                await page.screenshot({ path: testInfo.outputPath(`surveillance-${index}-${theme}-${width}.png`), fullPage: true, animations: 'disabled' });
            }
        }
    }
    expect(state()).toBe(before);
    expect(Number(queryIsolatedSql(project, `SELECT COUNT(*) FROM admin_audit_log WHERE action='admin_fleet_view' AND target_id=${id}`))).toBeGreaterThan(0);
    expect(queryIsolatedSql(project, `SELECT COUNT(*) FROM admin_audit_log WHERE action LIKE 'admin_fleet_%' AND action<>'admin_fleet_view'`)).toBe('0');
});

test('Surveillance A : actualisation suspendue pendant la lecture et erreur sans perte', async ({ page }) => {
    test.setTimeout(90000);
    const user = await enter(page);
    fleet(user);
    await page.clock.install();
    await page.goto('/admin/flottes');
    let requests = 0;
    page.on('request', request => {
        if (new URL(request.url()).pathname === '/admin/flottes' && request.method() === 'GET') requests++;
    });
    const search = page.getByLabel('Joueur ou cible', { exact: true });
    await search.fill('Saisie conservée');
    await page.clock.fastForward(21000);
    expect(requests).toBe(0);
    await expect(search).toHaveValue('Saisie conservée');
    await page.locator('.admin-missions-table tbody a').focus();
    await page.clock.fastForward(21000);
    expect(requests).toBe(0);
    await page.locator('#admin-main').focus();
    await page.evaluate(() => {
        const range = document.createRange();
        range.selectNodeContents(document.querySelector('.admin-missions-table tbody th'));
        window.getSelection().removeAllRanges();
        window.getSelection().addRange(range);
    });
    await page.clock.fastForward(21000);
    expect(requests).toBe(0);
    await page.evaluate(() => window.getSelection().removeAllRanges());
    const results = page.locator('[data-admin-mission-monitor-target="results"]');
    const before = await results.innerHTML();
    await page.route('**/admin/flottes', route => route.fulfill({ status: 503, body: 'Indisponible' }));
    await page.getByRole('button', { name: 'Actualiser le suivi' }).click();
    await expect(page.locator('[data-admin-mission-monitor-target="status"]')).toContainText('Le dernier relevé reste affiché');
    expect(await results.innerHTML()).toBe(before);
    await expect(search).toHaveValue('Saisie conservée');
    await page.unroute('**/admin/flottes');
    await page.getByRole('button', { name: 'Actualiser le suivi' }).click();
    await expect(page.locator('[data-admin-mission-monitor-target="status"]')).toHaveText('Suivi actualisé.');
    await expect(search).toHaveValue('Saisie conservée');
    await expect(results.locator('tbody tr')).toHaveCount(1);
});

test('Surveillance A : filtres paginés et seuils utilisables sans JavaScript', async ({ page, browser }) => {
    test.setTimeout(120000);
    const user = await enter(page);
    const project = process.env.BROWSER_ROUTER;
    // Ces missions fictives terminées n’ont ni message différé ni effet dans le jeu habituel.
    const rows = Array.from({ length: 52 }, () => `(${user.membership},${user.planet},${user.planet},'transport','completed',1,'{}',0,0,0,UTC_TIMESTAMP(),NULL,3600,UUID())`).join(',');
    runIsolatedSql(project, `INSERT INTO fleet (player_universe_id,home_planet_id,destination_id,mission,status,speed_ratio,cargo,reserved_fuel,orbit_fuel,orbit_duration_seconds,departure_at,arrival_at,travel_seconds,dispatch_token) VALUES ${rows}`);
    const native = await browser.newContext({ javaScriptEnabled: false, viewport: { width: 390, height: 900 }, storageState: await page.context().storageState() });
    const fallback = await native.newPage();
    await fallback.goto('/admin/flottes');
    await fallback.getByRole('combobox', { name: 'Univers', exact: true }).selectOption(String(user.universe));
    await fallback.getByRole('combobox', { name: 'Mission', exact: true }).selectOption('transport');
    await fallback.getByRole('combobox', { name: 'Statut', exact: true }).selectOption('completed');
    await fallback.getByRole('button', { name: 'Filtrer les missions' }).click();
    await expect(fallback.locator('tbody tr')).toHaveCount(50);
    await fallback.getByRole('link', { name: 'Page suivante' }).click();
    await expect(fallback.locator('tbody tr')).toHaveCount(2);
    await fallback.reload();
    await expect(fallback.getByRole('combobox', { name: 'Univers', exact: true })).toHaveValue(String(user.universe));
    await expect(fallback.getByRole('combobox', { name: 'Mission', exact: true })).toHaveValue('transport');
    await expect(fallback.getByRole('combobox', { name: 'Statut', exact: true })).toHaveValue('completed');
    await fallback.getByLabel('Joueur ou cible', { exact: true }).fill('Aucune cible de ce nom');
    await fallback.getByRole('button', { name: 'Filtrer les missions' }).click();
    await expect(fallback.getByText('Aucune mission ne correspond aux filtres.', { exact: true })).toBeVisible();
    await fallback.goto(`/admin/surveillance?universe=${user.universe}`);
    await expect(fallback.getByText('Aucun signal ne dépasse les seuils de cet univers.', { exact: true })).toBeVisible();
    await fallback.locator('.admin-surveillance-thresholds summary').click();
    await fallback.getByLabel('Retard de mission supérieur à (minutes)').fill('9');
    await fallback.getByLabel('Transports entre deux joueurs en 24 h').fill('8');
    await fallback.getByLabel('Attaques contre un joueur en 24 h').fill('12');
    await fallback.getByRole('button', { name: 'Enregistrer les seuils' }).click();
    await expect(fallback.locator('.admin-surveillance-current')).toContainText('Retard > 9 min · Transports ≥ 8 · Attaques ≥ 12');
    const settings = JSON.parse(queryIsolatedSql(project, `SELECT game_settings FROM universe WHERE id=${user.universe}`));
    expect(settings.admin_surveillance).toEqual({ late_minutes: 9, transport_count: 8, attack_count: 12 });
    await native.close();
});
