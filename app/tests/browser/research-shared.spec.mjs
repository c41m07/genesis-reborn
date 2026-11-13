import { test, expect } from './support/isolated-test.mjs';
import { prepareOwnedColony, runIsolatedSql, queryIsolatedSql } from './support/game-fixtures.mjs';

async function colonies(page) {
    const id = crypto.randomUUID().slice(0, 8), email = 'research-shared-' + id + '@example.test', password = 'Browser-test-2026!';
    await page.goto('/inscription');
    await page.getByLabel('Adresse email').fill(email);
    await page.getByLabel('Nom de commandant').fill('Research-' + id);
    await page.getByLabel('Mot de passe (12 caractères minimum)', { exact: true }).fill(password);
    await page.getByLabel('Confirmer le mot de passe').fill(password);
    await page.getByRole('button', { name: 'Créer mon compte' }).click();
    await expect(page).toHaveURL(/connexion/);
    await page.getByLabel('Adresse email').fill(email);
    await page.getByLabel('Mot de passe', { exact: true }).fill(password);
    await page.getByRole('button', { name: /Connexion/ }).click();
    await page.locator('article.universe-card').first().getByRole('button').click();
    await expect(page).toHaveURL(/planetes\/\d+$/);
    const one = Number(new URL(page.url()).pathname.split('/').pop()), project = process.env.BROWSER_ROUTER;
    prepareOwnedColony(project, email, { stock: 150000 });
    const membership = Number(queryIsolatedSql(project, `SELECT owner_id FROM planet WHERE id=${one}`));
    const two = Number(queryIsolatedSql(project, `SELECT id FROM planet WHERE solar_system_id=(SELECT solar_system_id FROM planet WHERE id=${one}) AND owner_id IS NULL AND npc_planet=0 LIMIT 1`));
    runIsolatedSql(project, `UPDATE planet SET name='Aster' WHERE id=${one}; UPDATE planet SET name='Beryl',owner_id=${membership} WHERE id=${two}`);
    runIsolatedSql(project, `INSERT INTO planet_resource (planet_id,resource_type,current_stock,production_rate,last_updated_at) SELECT ${two},resource_type,150000,0,UTC_TIMESTAMP() FROM planet_resource WHERE planet_id=${one} ON DUPLICATE KEY UPDATE current_stock=150000,last_updated_at=UTC_TIMESTAMP()`);
    runIsolatedSql(project, `INSERT INTO planet_building (planet_id,slug,tier,level) VALUES (${one},'lab','basic',1),(${two},'lab','basic',25) ON DUPLICATE KEY UPDATE level=VALUES(level)`);
    runIsolatedSql(project, `UPDATE universe SET game_settings=JSON_SET(CASE WHEN JSON_TYPE(game_settings)='OBJECT' THEN game_settings ELSE JSON_OBJECT() END,'$.multipliers.research_time',100) WHERE id=(SELECT universe_id FROM player_universe WHERE id=${membership})`);
    return { one, two, membership, project };
}

test('Une recherche ajoutée depuis une autre colonie rejoint la même file et le niveau suivant, avec annulation partagée', async ({ page }, info) => {
    test.setTimeout(120000);
    const { one, two, membership, project } = await colonies(page);
    const queue = page.locator('[data-queue-type="research"]');
    await page.goto(`/planetes/${one}?section=technologies`);
    await page.locator('#item-engineering_foundations-basic').getByRole('button', { name: /Rechercher le niveau 1/ }).click();
    await expect(queue).toContainText('Recherche commune');
    await expect(queue).toContainText('depuis Aster');
    await page.goto(`/planetes/${two}?section=technologies`);
    const card = page.locator('#item-engineering_foundations-basic');
    await expect(card.getByRole('button', { name: /Rechercher le niveau 2/ })).toBeVisible();
    await card.getByRole('button', { name: /Rechercher le niveau 2/ }).click();
    expect(queryIsolatedSql(project, `SELECT COUNT(*) FROM queue_item WHERE player_universe_id=${membership} AND queue_type='research'`)).toBe('2');
    expect(queryIsolatedSql(project, `SELECT COUNT(*) FROM queue_item WHERE player_universe_id=${membership} AND queue_type='research' AND started_at IS NOT NULL`)).toBe('1');
    expect(queryIsolatedSql(project, `SELECT item_level FROM queue_item WHERE planet_id=${two} AND queue_type='research'`)).toBe('2');
    await queue.locator('summary').click();
    await expect(queue.locator('.queue-detail-item')).toHaveCount(2);
    await expect(queue.locator('.queue-detail-item').nth(0)).toContainText('En cours · Aster');
    await expect(queue.locator('.queue-detail-item').nth(1)).toContainText('En attente · Beryl');
    await expect(card.getByRole('button', { name: /Rechercher le niveau 3/ })).toBeVisible();
    for (const width of [320, 390, 768, 1440]) {
        await page.setViewportSize({ width, height: 900 });
        expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(width);
    }
    await page.setViewportSize({ width: 390, height: 900 });
    await queue.screenshot({ path: info.outputPath('common-research-390.png'), animations: 'disabled' });
    const cancel = queue.locator('.queue-detail-item').first().getByRole('link', { name: /Préparer l’annulation/ });
    await cancel.focus(); await page.keyboard.press('Enter');
    await expect(page.getByRole('heading',{name:'Commandes concernées'})).toBeVisible();
    await expect(page.locator('main')).toContainText('Dépendance annulée');
    await page.getByRole('button',{name:/Confirmer l’annulation/}).click();
    await expect(queue.locator('.operation-idle')).toContainText('Aucune commande en cours');
    expect(queryIsolatedSql(project, `SELECT COUNT(*) FROM queue_item WHERE player_universe_id=${membership} AND queue_type='research'`)).toBe('0');
    await page.goto(`/planetes/${one}?section=technologies`);
    await expect(queue.locator('.operation-idle')).toContainText('Aucune commande en cours');
    await expect(page.locator('#item-engineering_foundations-basic').getByRole('button', { name: /Rechercher le niveau 1/ })).toBeVisible();
});

test('Les anciennes recherches parallèles restent actives et les commandes payées attendent sans changement', async ({ page }, info) => {
    test.setTimeout(120000);
    const { one, two, membership, project } = await colonies(page);
    const token = () => crypto.randomUUID();
    runIsolatedSql(project, `INSERT INTO queue_item (planet_id,player_universe_id,queue_type,item_slug,tier,item_level,quantity,duration_seconds,queued_at,started_at,dispatch_token,paid_costs) VALUES (${one},${membership},'research','engineering_foundations','basic',1,1,600,UTC_TIMESTAMP(),UTC_TIMESTAMP(),'${token()}','{"metal":111}'),(${two},${membership},'research','tactical_intelligence','basic',1,1,1200,UTC_TIMESTAMP(),UTC_TIMESTAMP(),'${token()}','{"metal":222}')`);
    const first = queryIsolatedSql(project, `SELECT MIN(id) FROM queue_item WHERE player_universe_id=${membership}`);
    runIsolatedSql(project, `INSERT INTO queue_item (planet_id,player_universe_id,queue_type,item_slug,tier,item_level,quantity,duration_seconds,queued_at,started_at,dispatch_token,paid_costs,depends_on_id) VALUES (${one},${membership},'research','engineering_foundations','basic',2,1,300,UTC_TIMESTAMP(),NULL,'${token()}','{"metal":333}',${first})`);
    const historical = queryIsolatedSql(project, `SELECT id,COALESCE(started_at,'waiting'),duration_seconds,paid_costs FROM queue_item WHERE player_universe_id=${membership} ORDER BY id`);
    await page.goto(`/planetes/${two}?section=technologies`);
    const queue = page.locator('[data-queue-type="research"]');
    await expect(queue.getByRole('status')).toContainText('Les recherches déjà commencées se terminent');
    await page.locator('#item-engineering_foundations-basic').getByRole('button', { name: /Rechercher le niveau 3/ }).click();
    const ids = historical.split('\n').map(row => row.split('\t')[0]).join(',');
    expect(queryIsolatedSql(project, `SELECT id,COALESCE(started_at,'waiting'),duration_seconds,paid_costs FROM queue_item WHERE id IN (${ids}) ORDER BY id`)).toBe(historical);
    expect(queryIsolatedSql(project, `SELECT COUNT(*) FROM queue_item WHERE player_universe_id=${membership} AND started_at IS NOT NULL`)).toBe('2');
    await queue.locator('summary').click();
    await expect(queue.locator('.queue-detail-item')).toHaveCount(4);
    await expect(queue.locator('.queue-detail-item').filter({ hasText: 'En cours' })).toHaveCount(2);
    await expect(queue.locator('.queue-detail-item').filter({ hasText: 'En attente' })).toHaveCount(2);
    await page.setViewportSize({ width: 390, height: 900 });
    await queue.screenshot({ path: info.outputPath('legacy-research-390.png'), animations: 'disabled' });
    runIsolatedSql(project, `UPDATE queue_item SET started_at=DATE_SUB(UTC_TIMESTAMP(),INTERVAL duration_seconds+2 SECOND) WHERE player_universe_id=${membership} AND started_at IS NOT NULL`);
    await page.goto(`/planetes/${one}?section=technologies`);
    await expect(queue.getByRole('status')).toHaveCount(0);
    expect(queryIsolatedSql(project, `SELECT COUNT(*) FROM queue_item WHERE player_universe_id=${membership}`)).toBe('2');
    expect(queryIsolatedSql(project, `SELECT COUNT(*) FROM queue_item WHERE player_universe_id=${membership} AND started_at IS NOT NULL`)).toBe('1');
    expect(queryIsolatedSql(project, `SELECT item_level FROM queue_item WHERE player_universe_id=${membership} AND started_at IS NOT NULL`)).toBe('2');
});
