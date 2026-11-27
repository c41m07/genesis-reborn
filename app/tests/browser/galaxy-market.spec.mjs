import { test, expect } from './support/isolated-test.mjs';
import { queryIsolatedSql, runIsolatedSql } from './support/game-fixtures.mjs';

test.use({ hasTouch: true, viewport: { width: 1920, height: 1080 } });

test('Station commerciale : le noyau ouvre la Bourse et ne devient pas un système', async ({ page, browser }) => {
    await page.emulateMedia({ reducedMotion: 'reduce' });
    const email = 'station-' + crypto.randomUUID().slice(0, 8) + '@example.test';
    const password = crypto.randomUUID() + '!Aa1';
    const project = process.env.BROWSER_ROUTER;

    await page.goto('/inscription');
    await page.getByLabel('Adresse email').fill(email);
    await page.getByLabel('Nom de commandant').fill(email.split('@')[0]);
    await page.getByLabel('Mot de passe (12 caractères minimum)', { exact: true }).fill(password);
    await page.getByLabel('Confirmer le mot de passe').fill(password);
    await page.getByRole('button', { name: 'Créer mon compte' }).click();
    await expect(page).toHaveURL(/connexion/);
    await page.getByLabel('Adresse email').fill(email);
    await page.getByLabel('Mot de passe', { exact: true }).fill(password);
    await page.getByRole('button', { name: /Connexion/ }).click();
    await page.locator('article.universe-card').filter({ has: page.getByRole('heading', { name: /Omega/ }) }).getByRole('button').click();
    await expect(page).toHaveURL(/planetes\/\d+$/);

    const universe = Number(queryIsolatedSql(project, "SELECT id FROM universe WHERE slug='omega-1'"));
    expect(universe).toBeGreaterThan(0);
    const marker = page.getByRole('link', { name: 'Station commerciale A:0:0 : ouvrir la Bourse' });
    const response = await page.goto('/carte-v2');
    expect(response.status()).toBe(200);
    await expect(marker).toHaveCount(0);
    expect(await page.locator('.gv2-market-legend').count()).toBe(0);

    // L’univers est ouvert uniquement pour cette recette. La seconde galaxie
    // est une vraie ligne de carte mais ne reçoit pas la station commerciale.
    runIsolatedSql(project, "INSERT INTO resource_market (universe_id,enabled,stock,targets,pressure,last_occurrence) VALUES ("
        + universe + ", 1, '{}', '{}', '{}', '') ON DUPLICATE KEY UPDATE enabled=1");
    runIsolatedSql(project, "INSERT IGNORE INTO galaxy (universe_id,name) VALUES (" + universe + ",'B')");
    const otherGalaxy = Number(queryIsolatedSql(project, "SELECT id FROM galaxy WHERE universe_id=" + universe + " AND name='B'"));
    runIsolatedSql(project, "INSERT IGNORE INTO solar_system (galaxy_id,coord_x,coord_y,name) VALUES (" + otherGalaxy + ",0,0,'Système de recette')");
    await page.goto('/carte-v2?galaxy=' + otherGalaxy);
    await expect(marker).toHaveCount(0);

    await page.goto('/carte-v2');
    await expect(marker).toBeVisible();
    const map = page.locator('.galaxy-v2');
    const data = JSON.parse(await map.getAttribute('data-galaxy-v2-map-value'));
    expect(data.systems.every(system => system.number > 0)).toBe(true);
    expect(data.systems.flatMap(system => system.planets).every(planet => planet.coordinates !== 'A:0:0')).toBe(true);
    await expect(marker).toHaveAttribute('href', '/bourse');
    await expect.poll(() => marker.locator('img').evaluate(image => image.complete && image.naturalWidth > 0)).toBe(true);

    // Un zoom à partir du centre de la scène ne déplace le noyau que par
    // rapport à la caméra : le repère change de position avec la projection.
    const label = marker.locator('.gv2-station-label');
    await expect(label).toBeHidden();
    await marker.hover();
    await expect(label).toBeVisible();
    await expect(label).toHaveText('Bourse');
    await page.mouse.move(0, 0);
    await expect(label).toBeHidden();
    const beforeZoom = await marker.getAttribute('style');
    await page.getByRole('button', { name: 'Zoomer', exact: true }).click();
    await expect(marker).not.toHaveAttribute('style', beforeZoom);
    await page.getByRole('button', { name: 'Vue entière' }).click();
    const known = data.systems.find(system => system.known && system.planets.some(planet => planet.ownedByCurrentPlayer));
    await page.goto('/carte-v2?system=' + known.id);
    await page.getByRole('button', { name: 'Entrer dans le système' }).click();
    await expect(marker).toBeHidden();
    await page.locator('[data-galaxy-v2-target="systemBack"]').click();
    await expect(marker).toBeVisible();

    await marker.focus();
    await expect(marker).toBeFocused();
    await page.keyboard.press('Enter');
    await expect(page).toHaveURL(/\/bourse$/);
    await expect(page.getByRole('heading', { name: 'Cours et stock disponible' })).toBeVisible();

    await page.goto('/carte-v2');
    await page.getByRole('button', { name: 'Grille de repérage' }).click();
    await expect(marker).toBeVisible();
    await marker.click();
    await expect(page).toHaveURL(/\/bourse$/);

    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto('/carte-v2');
    await expect(marker).toBeVisible();
    const target = await marker.boundingBox();
    expect(target.width).toBeGreaterThanOrEqual(44);
    expect(target.height).toBeGreaterThanOrEqual(44);
    expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(390);
    await marker.tap();
    await expect(page).toHaveURL(/\/bourse$/);

    const plain = await browser.newContext({ javaScriptEnabled: false, storageState: await page.context().storageState() });
    try {
        const native = await plain.newPage();
        await native.goto('/carte-v2');
        await expect(native.getByRole('link', { name: 'Station commerciale A:0:0 : ouvrir la Bourse' })).toHaveCount(0);
        await native.getByRole('list', { name: 'Repères de la carte' }).getByRole('link', { name: 'Bourse', exact: true }).click();
        await expect(native).toHaveURL(/\/bourse$/);
    } finally {
        await plain.close();
    }

    runIsolatedSql(project, 'UPDATE resource_market SET enabled=0 WHERE universe_id=' + universe);
    await page.goto('/carte-v2');
    await expect(marker).toHaveCount(0);
});
