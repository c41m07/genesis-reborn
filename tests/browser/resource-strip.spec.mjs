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
    for (const [slug, level] of [['mine_metal', 100], ['mine_antimatter', 10], ['storage_metal', 1]]) {
        sql(`INSERT INTO planet_building (planet_id, slug, tier, level) VALUES (${planet}, '${slug}', 'basic', ${level}) ON DUPLICATE KEY UPDATE level=${level}`);
    }
    sql(`UPDATE planet_resource SET current_stock=100000, last_updated_at=UTC_TIMESTAMP() WHERE planet_id=${planet}`);
    await page.reload();
    const metal = page.locator('.resource--metal'), antimatter = page.locator('.resource--antimatter');
    await expect(metal.locator('.resource-productivity')).toHaveText('100,0 %');
    await expect(metal.locator('.storage-meter')).toHaveAttribute('aria-label', /30\s000 protégés du pillage/);
    await expect(page.locator('.storage-meter-protected')).toHaveCount(5);
    await expect.poll(() => metal.locator('.storage-meter').evaluate(el => el.offsetHeight)).toBe(7);
    const ratio = await metal.locator('.storage-meter-protected').evaluate(el => el.getBoundingClientRect().width / el.parentElement.getBoundingClientRect().width);
    expect(ratio).toBeCloseTo(0.1, 2);
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
    await page.screenshot({ path: 'var/resource-strip-mobile.png' });
    await page.setViewportSize({ width: 1440, height: 900 });
    await page.screenshot({ path: 'var/resource-strip-desktop.png' });
    sql(`UPDATE player SET preferences=JSON_SET(CASE WHEN JSON_TYPE(preferences)='OBJECT' THEN preferences ELSE JSON_OBJECT() END, '$.theme', 'light') WHERE email='${email}'`);
    await page.reload();
    await expect(page.locator('body')).toHaveAttribute('data-theme', 'light');
    await expect(metal.locator('.storage-meter-protected')).toHaveCSS('background-color', 'rgb(39, 132, 84)');
});
