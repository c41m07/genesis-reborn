import { test, expect } from './support/isolated-test.mjs';
import { runIsolatedSql } from './support/game-fixtures.mjs';

test('Stock protégé et productivité réelle, pénuries et reprise sans rechargement', async ({ page }) => {
    test.setTimeout(120000);
    const id = crypto.randomUUID().slice(0, 8), email = `resources-${id}@example.test`, password = 'Browser-test-2026!';
    await page.goto('/inscription');
    await page.getByLabel('Adresse email').fill(email);
    await page.getByLabel('Nom de commandant').fill(`Resources-${id}`);
    await page.getByLabel('Mot de passe (12 caractères minimum)', { exact: true }).fill(password);
    await page.getByLabel('Confirmer le mot de passe').fill(password);
    await page.getByRole('button', { name: 'Créer mon compte' }).click();
    await expect(page).toHaveURL(/connexion/);
    await page.getByLabel('Adresse email').fill(email);
    await page.getByLabel('Mot de passe', { exact: true }).fill(password);
    await page.getByRole('button', { name: /Connexion/ }).click();
    await page.locator('article.universe-card').first().getByRole('button').click();
    await expect(page).toHaveURL(/planetes\/\d+$/);
    const planet = Number(new URL(page.url()).pathname.split('/').pop());
    const sql = query => runIsolatedSql(process.env.BROWSER_ROUTER, query);
    sql("INSERT INTO game_event (name,description,type,status,starts_at,ends_at,parameters,visible_to_players) VALUES ('Vérification bandeau','','production_bonus','active',UTC_TIMESTAMP()-INTERVAL 1 HOUR,UTC_TIMESTAMP()+INTERVAL 1 HOUR,'{\"percentage\":0}',1)");

    for (const [slug, level] of [['mine_metal', 100], ['mine_antimatter', 10], ['storage_metal', 1]]) {
        sql(`INSERT INTO planet_building (planet_id, slug, tier, level) VALUES (${planet}, '${slug}', 'basic', ${level}) ON DUPLICATE KEY UPDATE level=${level}`);
    }
    sql(`UPDATE planet_resource SET current_stock=CASE WHEN resource_type='metal' THEN 100000 ELSE 1000 END, last_updated_at=UTC_TIMESTAMP() WHERE planet_id=${planet}`);
    await page.reload();
    const metal = page.locator('.resource--metal'), antimatter = page.locator('.resource--antimatter');
    const strip = page.locator('.resource-strip');
    const events = page.getByRole('region', { name: 'Événements de l’univers' });
    await expect(events).toBeVisible();
    await expect(page.locator('.resource-event-stack .event-drawer-wrap')).toHaveCount(0);
    await page.evaluate(async () => { await document.fonts.ready; window.scrollTo({top:0,behavior:'instant'}); });
    await expect.poll(()=>page.evaluate(()=>window.scrollY)).toBe(0);
    const stackBox = await page.locator('.resource-event-stack').boundingBox();
    const stripBox = await strip.boundingBox();
    expect(stackBox.height).toBeCloseTo(stripBox.height, 0);
    const eventBox = await events.boundingBox();
    expect(eventBox.y).toBeGreaterThanOrEqual(stripBox.y + stripBox.height - 1);
    await expect(events).toHaveCSS('position', 'relative');
    await events.locator('summary').focus();
    await page.keyboard.press('Enter');
    await expect(events.locator('details')).toHaveAttribute('open', '');
    await page.keyboard.press('Escape');
    await expect(events.locator('details')).not.toHaveAttribute('open', '');

    await expect(metal.locator('.resource-productivity')).toHaveText('100,0 %');
    await expect(metal.locator('.storage-meter')).toHaveAttribute('aria-label', /11\s000 protégés du pillage/);
    await expect(page.locator('.storage-meter-protected')).toHaveCount(5);
    await expect.poll(() => metal.locator('.storage-meter').evaluate(el => el.offsetHeight)).toBe(7);
    const ratio = await metal.locator('.storage-meter-protected').evaluate(el => el.getBoundingClientRect().width / el.parentElement.getBoundingClientRect().width);
    expect(ratio).toBeCloseTo(0.1, 2);
    for (const [stock, capacity] of [[0, 200000], [10000, 200000], [20000, 200000], [100000, 200000], [200000, 200000], [100000, 300000]]) {
        await metal.evaluate((element, resource) => element.dispatchEvent(new CustomEvent('resource:update', { detail: resource })), {
            stock, capacity, rate: 0, protectedCapacity: capacity * 0.1, productivity: 100,
        });
        expect(await metal.locator('.storage-meter-stock').evaluate(element => parseFloat(element.style.width))).toBeCloseTo(stock / capacity * 100, 4);
        expect(await metal.locator('.storage-meter-protected').evaluate(element => parseFloat(element.style.width))).toBeCloseTo(Math.min(stock, capacity * 0.1) / capacity * 100, 4);
        expect(await metal.locator('.storage-meter-limit').evaluate(element => parseFloat(element.style.left))).toBeCloseTo(10, 4);
    }
    await metal.evaluate(element => element.dispatchEvent(new CustomEvent('resource:update', { detail: { stock: 25000, capacity: 10000, rate: 0, protectedCapacity: 1000, productivity: null } })));
    await expect(metal.locator('[data-resource-target="stock"]')).toHaveAttribute('title', '25 000');
    expect(await metal.locator('.storage-meter-stock').evaluate(element => parseFloat(element.style.width))).toBe(100);
    await page.reload();
    sql(`UPDATE planet_resource SET current_stock=0, last_updated_at=UTC_TIMESTAMP() WHERE planet_id=${planet} AND resource_type='electricity'`);
    await expect(metal.locator('.resource-productivity')).toHaveClass(/resource-productivity--reduced/, { timeout: 20000 });
    await expect(metal.locator('.resource-productivity')).toContainText('↓');
    await expect(metal.locator('.resource-productivity')).toHaveCSS('color', 'rgb(255, 156, 156)');
    sql(`INSERT INTO planet_building (planet_id, slug, tier, level) VALUES (${planet}, 'power_plant', 'basic', 100) ON DUPLICATE KEY UPDATE level=100`);
    sql(`UPDATE planet_resource SET current_stock=0, last_updated_at=UTC_TIMESTAMP() WHERE planet_id=${planet} AND resource_type='hydrogen'`);
    await expect(metal.locator('.resource-productivity')).toHaveText('100,0 %', { timeout: 20000 });
    await expect(antimatter.locator('.resource-productivity')).toHaveClass(/resource-productivity--reduced/);
    await expect(page.locator('.resource--hydrogen .storage-meter-protected')).toHaveCSS('background-color', 'rgb(66, 189, 130)');
    sql(`UPDATE planet_resource SET current_stock=100000, last_updated_at=UTC_TIMESTAMP() WHERE planet_id=${planet} AND resource_type='hydrogen'`);
    await expect(antimatter.locator('.resource-productivity')).toHaveText('100,0 %', { timeout: 20000 });
    await expect(page.locator('.resource--electricity .resource-productivity')).toBeHidden();
    for (const width of [1440, 768, 390]) {
        await page.setViewportSize({ width, height: 900 });
        expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(width);
        for (const badge of await page.locator('.resource-productivity:visible').all()) {
            const bounds = await badge.boundingBox();
            expect(bounds.x).toBeGreaterThanOrEqual(0);
            expect(bounds.x + bounds.width).toBeLessThanOrEqual(width);
        }
    }
    const toggle = page.getByRole('button', { name: /Ressources de .* afficher ou masquer les détails/ });
    for (const width of [320, 390]) {
        await page.setViewportSize({ width, height: 900 });
        await expect(toggle).toHaveAttribute('aria-expanded', 'false');
        await expect(page.locator('#resource-details')).toBeHidden();
        await expect(page.locator('.resource-summary-item')).toHaveCount(5);
        expect((await page.locator('.resource-strip').boundingBox()).height).toBeLessThanOrEqual(64);
        const stocks = await page.locator('.resource-summary-item strong').evaluateAll(elements => elements.map(element => {
            const { top, left, right } = element.getBoundingClientRect();
            return { top, left, right };
        }));
        expect(new Set(stocks.map(stock => Math.round(stock.top))).size).toBe(1);
        for (let index = 1; index < stocks.length; index++) expect(stocks[index].left).toBeGreaterThanOrEqual(stocks[index - 1].right);
        await toggle.focus();
        await page.keyboard.press('Enter');
        await expect(toggle).toHaveAttribute('aria-expanded', 'true');
        await expect(metal.locator('.storage-meter')).toBeVisible();
        await metal.evaluate(element => element.dispatchEvent(new CustomEvent('resource:update', { detail: {
            stock: 123456, capacity: 300000, rate: 0, protectedCapacity: 30000, productivity: 100,
        } })));
        await expect(page.locator('[data-compact-resource="metal"] strong')).toHaveText('123,46 k');
        await expect(metal.locator('[data-resource-target="stock"]')).toHaveText('123,46 k');
        await expect(page.locator('[data-compact-resource="metal"] strong')).toHaveAttribute('title', '123 456');
        await expect(metal.locator('[data-resource-target="stock"]')).toHaveAttribute('title', '123 456');
        expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(width);
        await toggle.focus();
        await page.keyboard.press('Space');
        await expect(page.locator('#resource-details')).toBeHidden();
        expect(await toggle.evaluate(element => document.activeElement === element)).toBe(true);
    }
    await page.screenshot({ path: 'output/playwright/resource-strip-mobile.png' });
    await toggle.click();
    await page.screenshot({ path: 'output/playwright/resource-strip-mobile-expanded.png' });
    await page.setViewportSize({ width: 1440, height: 900 });
    await page.screenshot({ path: 'var/resource-strip-desktop.png' });
    sql(`UPDATE player SET preferences=JSON_SET(CASE WHEN JSON_TYPE(preferences)='OBJECT' THEN preferences ELSE JSON_OBJECT() END, '$.theme', 'light') WHERE email='${email}'`);
    await page.reload();
    await expect(page.locator('body')).toHaveAttribute('data-theme', 'light');
    await expect(metal.locator('.storage-meter-protected')).toHaveCSS('background-color', 'rgb(39, 132, 84)');
    await page.setViewportSize({ width: 390, height: 900 });
    await expect(page.locator('#resource-details')).toBeHidden();
    expect((await page.locator('.resource-strip').boundingBox()).height).toBeLessThanOrEqual(80);
    await expect(page.locator('.resource-strip')).toHaveCSS('grid-template-columns', '112px 278px');
    await toggle.click();
    await expect(metal.locator('.storage-meter')).toBeVisible();
    await page.screenshot({ path: 'output/playwright/resource-strip-mobile-light.png', animations: 'disabled' });
});
