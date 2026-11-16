import { test, expect } from './support/isolated-test.mjs';
import { prepareOwnedColony, runIsolatedSql, queryIsolatedSql } from './support/game-fixtures.mjs';

test('Les cadres verts isolent un niveau avec trois améliorations en file', async ({ page }, testInfo) => {
    test.setTimeout(180000);
    const suffix = crypto.randomUUID().slice(0, 8), email = `queued-${suffix}@example.test`, password = crypto.randomUUID() + 'aA1!';
    await page.goto('/inscription');
    await page.getByLabel('Adresse email').fill(email);
    await page.getByLabel('Nom de commandant').fill(`Queued-${suffix}`);
    await page.getByLabel('Mot de passe (12 caractères minimum)', { exact: true }).fill(password);
    await page.getByLabel('Confirmer le mot de passe').fill(password);
    await page.getByRole('button', { name: 'Créer mon compte' }).click();
    await expect(page).toHaveURL(/connexion/);
    await page.getByLabel('Adresse email').fill(email);
    await page.getByLabel('Mot de passe', { exact: true }).fill(password);
    await page.getByRole('button', { name: /Connexion/ }).click();
    await page.locator('article.universe-card').first().getByRole('button').click();
    await expect(page).toHaveURL(/planetes\/\d+$/);
    const project = process.env.BROWSER_ROUTER, path = new URL(page.url()).pathname, planet = Number(path.split('/').pop());
    const sql = query => runIsolatedSql(project, query);
    prepareOwnedColony(project, email, { stock: 100000000, showLockedItems: true });
    const member = Number(queryIsolatedSql(project, `SELECT owner_id FROM planet WHERE id=${planet}`));
    const position = Number(queryIsolatedSql(project, `SELECT position FROM planet WHERE id=${planet}`));
    sql(`UPDATE universe SET game_settings=JSON_SET(CASE WHEN JSON_TYPE(game_settings)='OBJECT' THEN game_settings ELSE JSON_OBJECT() END,'$.multipliers',JSON_OBJECT('resource_production',1)) WHERE id=(SELECT universe_id FROM player_universe WHERE id=${member})`);
    for (const [slug, level] of [['lab', 1], ['shipyard', 1], ['fortress', 1], ['storage_nano', 1], ['mine_metal', 2], ['mine_nano', 5]]) {
        sql(`INSERT INTO planet_building(planet_id,slug,tier,level) VALUES(${planet},'${slug}','basic',${level}) ON DUPLICATE KEY UPDATE level=VALUES(level)`);
    }
    const readGain = async (panel, label) => {
        const benefits = panel.locator('.card-benefits');
        if (!await benefits.evaluate(element => element.open)) await benefits.locator('summary').click();
        const value = benefits.getByText(label, { exact: true }).locator('..').locator('strong');
        const exact = value.locator('.quantity-number');
        const text = await exact.count() ? await exact.getAttribute('title') : await value.innerText();
        return Number.parseFloat(text.replace(/\s/g, '').replace(',', '.'));
    };
    const bonusGain = (cap, maxLevel) => cap * (Math.log(1 + 2 * 5 / (maxLevel * 3.8125)) - Math.log(1 + 2 * 4 / (maxLevel * 3.8125))) / Math.log(3);
    const examples = [
        ['lab', 'Réduction du temps de recherche', bonusGain(70, 25)],
        ['shipyard', 'Réduction du temps de construction des vaisseaux', bonusGain(70, 25)],
        ['fortress', 'Structure supplémentaire des défenses', bonusGain(25, 10)],
        ['storage_nano', 'Stockage de nano', 100000],
        ['mine_nano', 'Production de nano', 180 * (1.2 - 0.4 * (position - 1) / 8)],
    ];
    for (const [slug, label, expected] of examples) {
        sql(`DELETE FROM queue_item WHERE planet_id=${planet} AND queue_type='building'`);
        sql(`UPDATE planet_building SET level=1 WHERE planet_id=${planet} AND slug='${slug}' AND tier='basic'`);
        for (const level of [2, 3, 4]) {
            sql(`INSERT INTO queue_item(planet_id,player_universe_id,queue_type,item_slug,tier,item_level,quantity,duration_seconds,paid_costs,queued_at,started_at,dispatch_token)
                VALUES(${planet},${member},'building','${slug}','basic',${level},1,86400,'{}',UTC_TIMESTAMP(),${level === 2 ? 'UTC_TIMESTAMP()' : 'NULL'},'queued-ui-${slug}-${level}')`);
        }
        sql(`UPDATE planet SET building_queue_started_at=UTC_TIMESTAMP() WHERE id=${planet}`);
        await page.goto(`${path}?section=buildings`);
        const panel = page.locator(`#item-${slug}-basic`);
        expect(await readGain(panel, label), slug).toBeCloseTo(expected, 2);
        await expect(panel.getByRole('button', { name: 'Améliorer au niveau 5', exact: true })).toBeVisible();
        expect(queryIsolatedSql(project, `SELECT level FROM planet_building WHERE planet_id=${planet} AND slug='${slug}' AND tier='basic'`)).toBe('1');
        if (slug === 'lab') {
            for (const width of [1440, 390]) {
                await page.setViewportSize({ width, height: 1000 });
                await panel.screenshot({ path: testInfo.outputPath(`gain-laboratoire-${width}.png`), animations: 'disabled' });
            }
        }
    }
    sql(`INSERT INTO player_technology(player_universe_id,slug,tier,level) VALUES(${member},'engineering_foundations','basic',1) ON DUPLICATE KEY UPDATE level=VALUES(level)`);
    for (const level of [2, 3, 4]) {
        sql(`INSERT INTO queue_item(planet_id,player_universe_id,queue_type,item_slug,tier,item_level,quantity,duration_seconds,paid_costs,queued_at,started_at,dispatch_token)
            VALUES(${planet},${member},'research','engineering_foundations','basic',${level},1,86400,'{}',UTC_TIMESTAMP(),${level === 2 ? 'UTC_TIMESTAMP()' : 'NULL'},'queued-research-${level}')`);
    }
    await page.goto(`${path}?section=technologies`);
    const research = page.locator('#item-engineering_foundations-basic');
    expect(await readGain(research, 'Réduction du temps de construction des bâtiments')).toBeCloseTo(bonusGain(70, 25), 2);
    await expect(research.getByRole('button', { name: 'Rechercher le niveau 5', exact: true })).toBeVisible();
});
