import { test, expect } from './support/isolated-test.mjs';
import { prepareOwnedColony, runIsolatedSql, queryIsolatedSql } from './support/game-fixtures.mjs';

test('Les rangs bâtiments partagent une carte accessible et conservent les filtres au rafraîchissement', async ({ page, browser }) => {
    test.setTimeout(180000);
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    const id = crypto.randomUUID().slice(0, 8), email = `building-${id}@example.test`, password = 'Browser-test-2026!';
    await page.goto('/inscription');
    await page.getByLabel('Adresse email').fill(email);
    await page.getByLabel('Nom de commandant').fill(`Building-${id}`);
    await page.getByLabel('Mot de passe (12 caractères minimum)', { exact: true }).fill(password);
    await page.getByLabel('Confirmer le mot de passe').fill(password);
    await page.getByRole('button', { name: 'Créer mon compte' }).click();
    await expect(page).toHaveURL(/connexion/);
    await page.getByLabel('Adresse email').fill(email);
    await page.getByLabel('Mot de passe', { exact: true }).fill(password);
    await page.getByRole('button', { name: /Connexion/ }).click();
    await page.locator('article.universe-card').first().getByRole('button').click();
    await expect(page).toHaveURL(/planetes\/\d+$/);
    const path = new URL(page.url()).pathname, planet = Number(path.split('/').pop());
    prepareOwnedColony(process.env.BROWSER_ROUTER, email, { stock: 999999999, showLockedItems: true });
    const sql = query => runIsolatedSql(process.env.BROWSER_ROUTER, query);
    // Le niveau 200 conserve un prix en millions malgré la baisse du coût de la centrale.
    sql(`INSERT INTO planet_building (planet_id,slug,tier,level) VALUES (${planet},'power_plant','basic',200),(${planet},'lab','basic',5)`);
    sql(`INSERT INTO player_technology (player_universe_id,slug,tier,level) SELECT owner_id,'engineering_foundations','basic',5 FROM planet WHERE id=${planet}`);
    await page.setViewportSize({ width: 1440, height: 1000 });
    await page.goto(`${path}?section=buildings`);
    const family = page.locator('.building-card[data-slug="power_plant"]');
    const tab = tier => page.locator(`#building-tab-power_plant-${tier}`);
    const panel = tier => page.locator(`#item-power_plant-${tier}`);
    const toggle = page.getByRole('switch', { name: 'Afficher les éléments verrouillés' });
    await expect(page.locator('.building-card')).toHaveCount(13);
    await expect(family.getByRole('tab')).toHaveCount(3);
    await expect(tab('basic')).toHaveAttribute('aria-selected', 'true');
    await expect(tab('basic').locator('.building-rank-level')).toHaveText('200');
    await expect(tab('basic').locator('.building-rank-up')).toHaveCount(0);
    await expect(tab('basic')).toHaveAccessibleDescription(/Ressources insuffisantes/);
    await expect(family.locator('.card-stock-badge, .tier-label')).toHaveCount(0);
    await expect(panel('basic').locator('.costs')).toContainText('1,12 M');
    await expect(panel('basic').locator('.costs > span').first()).toHaveAttribute('aria-label', /Métal : [\d ]+/);
    await expect(panel('basic').locator('form [name="tier"]')).toHaveValue('basic');
    await tab('intermediate').click();
    await expect(tab('intermediate').locator('.building-rank-up')).toBeVisible();
    await expect(panel('intermediate')).toBeVisible();
    await expect(panel('intermediate').locator('img')).toHaveAttribute('src', /power_plant__intermediate.webp$/);
    await expect(panel('intermediate').locator('.catalog-card-heading > h3')).toHaveText('Complexe énergétique à antimatière');
    await expect(panel('intermediate').locator('form [name="tier"]')).toHaveValue('intermediate');
    await expect(panel('intermediate').locator('.costs')).toContainText('1 350');
    await page.keyboard.press('ArrowRight');
    await expect(tab('superior')).toBeFocused();
    await expect(panel('superior')).toBeVisible();
    await expect(tab('superior').locator('.building-rank-cross')).toBeVisible();
    await expect(panel('superior').locator('img')).toHaveAttribute('src', /power_plant__superior.webp$/);
    await expect(panel('superior').locator('.requirement')).toContainText('Nécessite');
    await expect(panel('superior').locator('form[action*="file"]')).toHaveCount(0);
    await toggle.click();
    await expect(toggle).toHaveAttribute('aria-checked', 'false');
    await expect(tab('superior')).toBeHidden();
    await expect(panel('basic')).toBeVisible();
    await page.getByRole('button', { name: 'Production', exact: true }).click();
    await toggle.click();
    await expect(page.locator('.building-card[data-category="storage"]').first()).toBeHidden();
    await tab('intermediate').click();
    await page.locator('h1').click();
    const previousFamily = await family.elementHandle();
    const refreshed = page.waitForResponse(response => response.url().includes('?section=buildings') && response.request().method() === 'GET');
    await page.evaluate(() => window.dispatchEvent(new CustomEvent('genesis:refresh')));
    await refreshed;
    await expect.poll(() => previousFamily.evaluate(element => element.isConnected)).toBe(false);
    await expect(tab('intermediate')).toHaveAttribute('aria-selected', 'true');
    await expect(page.getByRole('button', { name: 'Production', exact: true })).toHaveAttribute('aria-pressed', 'true');
    await expect(toggle).toHaveAttribute('aria-checked', 'true');
    await page.getByRole('button', { name: 'Tous', exact: true }).click();
    for (const [width, columns] of [[1920, 4], [1600, 3], [1440, 2], [768, 1], [390, 1], [320, 1]]) {
        await page.setViewportSize({ width, height: 1000 });
        await tab('superior').click();
        const firstRow = await page.locator('.building-card:visible').evaluateAll(cards => {
            const top = Math.round(cards[0].getBoundingClientRect().top);
            return cards.filter(card => Math.round(card.getBoundingClientRect().top) === top).length;
        });
        expect(firstRow, `Colonnes à ${width} px`).toBe(columns);
        await expect(panel('superior').locator('.catalog-card-art')).toHaveCSS('filter', 'none');
        await expect(panel('superior').locator('.catalog-card-art img')).toHaveCSS('object-fit', 'cover');
        const positions = await family.getByRole('tab').evaluateAll(tabs => tabs.map(tab => Math.round(tab.getBoundingClientRect().top)));
        expect(new Set(positions).size).toBe(1);
        await expect(tab('intermediate')).toHaveAccessibleName(/Intermédiaire/);
        const overflow = await family.getByRole('tab').evaluateAll(tabs => tabs.filter(tab => tab.scrollWidth > tab.clientWidth).map(tab => ({ label: tab.innerText, available: tab.clientWidth, content: tab.scrollWidth })));
        expect(overflow, `Onglets à ${width} px`).toEqual([]);
        const costs = await panel('superior').locator('.costs > span').evaluateAll(spans => spans.map(span => Math.round(span.getBoundingClientRect().top)));
        expect(costs).toHaveLength(4);
        expect(new Set(costs).size).toBe(1);
        expect(await family.evaluate(card => card.scrollWidth <= card.clientWidth)).toBe(true);
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
        const misalignedRows = await page.locator('.building-card:visible').evaluateAll(cards => {
            const rows = new Map();
            cards.forEach(card => {
                const button = card.querySelector('.building-rank:not([hidden]) .card-body > form > button');
                if (!button) return;
                const row = Math.round(card.getBoundingClientRect().top);
                const positions = rows.get(row) ?? [];
                positions.push({ slug: card.dataset.slug, bottom: button.getBoundingClientRect().bottom, cardBottom: card.getBoundingClientRect().bottom, panelBottom: button.closest('.building-rank').getBoundingClientRect().bottom, bodyBottom: button.closest('.card-body').getBoundingClientRect().bottom, height: button.getBoundingClientRect().height });
                rows.set(row, positions);
            });
            return [...rows.values()].filter(positions => Math.max(...positions.map(position => position.bottom)) - Math.min(...positions.map(position => position.bottom)) > 1);
        });
        expect(misalignedRows, `Boutons alignés à ${width} px`).toEqual([]);
    }
    await page.screenshot({ path: 'output/playwright/building-tabs-mobile.png', fullPage: true });
    await page.setViewportSize({ width: 1920, height: 1000 });
    await page.locator('#item-mine_metal-basic .card-benefits summary').click();
    const rowButtons = page.locator('.building-card').filter({ has: page.locator('#item-mine_metal-basic, #item-mine_nano-basic, #item-mine_hydrogen-basic') });
    const buttonBottoms = await rowButtons.evaluateAll(cards => cards.map(card => card.querySelector('.building-rank:not([hidden]) .card-body > form > button').getBoundingClientRect().bottom));
    expect(Math.max(...buttonBottoms) - Math.min(...buttonBottoms)).toBeLessThanOrEqual(1);
    await page.screenshot({ path: 'output/playwright/building-tabs-desktop.png', fullPage: true });
    await page.goto(`${path}?section=buildings&showLockedItems=0#item-power_plant-superior`);
    await expect(panel('superior')).toBeVisible();
    await expect(toggle).toHaveAttribute('aria-checked', 'true');
    const noScript = await browser.newContext({ javaScriptEnabled: false, storageState: await page.context().storageState() });
    const fallback = await noScript.newPage();
    await fallback.goto(`${path}?section=buildings&showLockedItems=0`);
    await expect(fallback.locator('#item-power_plant-superior')).toBeHidden();
    await fallback.getByRole('switch', { name: 'Afficher les éléments verrouillés' }).click();
    await expect(fallback.locator('#item-power_plant-superior')).toBeVisible();
    await noScript.close();
    expect(errors).toEqual([]);
});

test('Recherche, chantier et défenses partagent les onglets et préservent leurs quantités et formulaires', async ({ page, browser }) => {
    test.setTimeout(180000);
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    const id = crypto.randomUUID().slice(0, 8), email = `ranks-${id}@example.test`, password = 'Browser-test-2026!';
    await page.goto('/inscription');
    await page.getByLabel('Adresse email').fill(email);
    await page.getByLabel('Nom de commandant').fill(`Ranks-${id}`);
    await page.getByLabel('Mot de passe (12 caractères minimum)', { exact: true }).fill(password);
    await page.getByLabel('Confirmer le mot de passe').fill(password);
    await page.getByRole('button', { name: 'Créer mon compte' }).click();
    await expect(page).toHaveURL(/connexion/);
    await page.getByLabel('Adresse email').fill(email);
    await page.getByLabel('Mot de passe', { exact: true }).fill(password);
    await page.getByRole('button', { name: /Connexion/ }).click();
    await page.locator('article.universe-card').first().getByRole('button').click();
    await expect(page).toHaveURL(/planetes\/\d+$/);
    const path = new URL(page.url()).pathname, planet = Number(path.split('/').pop());
    const sql = query => runIsolatedSql(process.env.BROWSER_ROUTER, query);
    prepareOwnedColony(process.env.BROWSER_ROUTER, email, { stock: 150000, showLockedItems: true });
    sql(`INSERT INTO planet_building (planet_id,slug,tier,level) VALUES (${planet},'lab','basic',5),(${planet},'shipyard','basic',5),(${planet},'fortress','basic',5)`);
    sql(`INSERT INTO player_technology (player_universe_id,slug,tier,level) SELECT owner_id,'engineering_foundations','basic',5 FROM planet WHERE id=${planet} UNION ALL SELECT owner_id,'vector_propulsion','basic',5 FROM planet WHERE id=${planet}`);
    sql(`INSERT INTO fleet (player_universe_id,home_planet_id,status,mission,speed_ratio,cargo,reserved_fuel,orbit_fuel,orbit_duration_seconds,travel_seconds,dispatch_token) SELECT owner_id,id,'idle','idle',1,'{}',0,0,0,0,'' FROM planet WHERE id=${planet} AND NOT EXISTS (SELECT id FROM fleet WHERE home_planet_id=${planet} AND status='idle')`);
    sql(`INSERT INTO fleet_ship (fleet_id,slug,tier,quantity) SELECT id,'fighter','basic',125000 FROM fleet WHERE home_planet_id=${planet} AND status='idle' LIMIT 1`);
    sql(`INSERT INTO fleet_ship (fleet_id,slug,tier,quantity) SELECT id,'fighter','intermediate',1250000 FROM fleet WHERE home_planet_id=${planet} AND status='idle' LIMIT 1`);
    sql(`INSERT INTO planet_defense (planet_id,slug,tier,quantity) VALUES (${planet},'ground_turret_anti_light','basic',1250000)`);
    for (const [group, slug, families] of [['technologies', 'engineering_foundations', 19], ['ships', 'fighter', 14], ['defenses', 'ground_turret_anti_light', 4]]) {
        await page.setViewportSize({ width: 1920, height: 1000 });
        await page.goto(`${path}?section=${group}`);
        const family = page.locator(`.building-card[data-slug="${slug}"]`);
        const tab = tier => page.locator(`#building-tab-${slug}-${tier}`);
        const panel = tier => page.locator(`#item-${slug}-${tier}`);
        const toggle = page.getByRole('switch', { name: 'Afficher les éléments verrouillés' });
        await expect(page.locator('.building-card')).toHaveCount(families);
        await expect(tab('basic')).toHaveAttribute('aria-selected', 'true');
        await expect(panel('basic').locator('form [name="group"]')).toHaveValue(group);
        await expect(panel('basic').locator('form [name="tier"]')).toHaveValue('basic');
        await expect(family.locator('.card-stock-badge, .tier-label')).toHaveCount(0);
        await expect(tab('basic').locator('.building-rank-level')).toHaveText(group === 'technologies' ? '5' : group === 'ships' ? '125,00 k' : '1,25 M');
        if (group !== 'technologies') {
            await expect(tab('basic')).toHaveAccessibleDescription(/unités possédées/);
            await panel('basic').getByLabel('Quantité', { exact: true }).fill('3');
            await tab('intermediate').click();
            if (group === 'ships') {
                await expect(tab('intermediate').locator('.building-rank-level')).toHaveText('1,25 M');
                await expect(panel('intermediate').getByLabel('Quantité', { exact: true })).toHaveValue('1');
            }
            await tab('basic').click();
            await expect(panel('basic').getByLabel('Quantité', { exact: true })).toHaveValue('3');
        }
        await tab('superior').click();
        await expect(panel('superior')).toBeVisible();
        await expect(tab('superior').locator('.building-rank-cross')).toBeVisible();
        await expect(panel('superior').locator('img')).toHaveAttribute('alt', /\S/);
        await toggle.click();
        await expect(tab('superior')).toBeHidden();
        await expect(panel('basic')).toBeVisible();
        await toggle.click();
        for (const width of [1920, 1440, 390, 320]) {
            await page.setViewportSize({ width, height: 1000 });
            expect(await family.getByRole('tab').evaluateAll(tabs => tabs.every(tab => tab.scrollWidth <= tab.clientWidth))).toBe(true);
            expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
        }
        await page.setViewportSize({ width: 1920, height: 1000 });
        await page.screenshot({ path: `output/playwright/catalogue-${group}-tabs.png`, fullPage: true });
        if (group === 'defenses') {
            await panel('basic').getByRole('button', { name: 'Construire', exact: true }).click();
            await expect.poll(() => queryIsolatedSql(process.env.BROWSER_ROUTER, `SELECT quantity FROM queue_item WHERE planet_id=${planet} AND item_slug='ground_turret_anti_light' AND tier='basic'`)).toBe('3');
        }
        await page.goto(`${path}?section=${group}&showLockedItems=0#item-${slug}-superior`);
        await expect(panel('superior')).toBeVisible();
        await expect(toggle).toHaveAttribute('aria-checked', 'true');
    }
    const fallbackContext = await browser.newContext({ javaScriptEnabled: false, storageState: await page.context().storageState() });
    const fallback = await fallbackContext.newPage();
    for (const [group, slug] of [['technologies', 'engineering_foundations'], ['ships', 'fighter'], ['defenses', 'ground_turret_anti_light']]) {
        await fallback.goto(`${path}?section=${group}&showLockedItems=0`);
        await expect(fallback.locator(`#item-${slug}-superior`)).toBeHidden();
        await fallback.getByRole('switch', { name: 'Afficher les éléments verrouillés' }).click();
        await expect(fallback.locator(`#item-${slug}-superior`)).toBeVisible();
    }
    await fallbackContext.close();
    expect(errors).toEqual([]);
});
