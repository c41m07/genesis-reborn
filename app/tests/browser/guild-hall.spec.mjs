import { test, expect } from './support/isolated-test.mjs';
import { queryIsolatedSql } from './support/game-fixtures.mjs';

test('Pilote Hall : don natif, confirmation du commandant et position 0 dans Galaxie', async ({ page, browser, baseURL }) => {
    test.skip(process.env.GUILD_HALL_BROWSER_FIXTURE !== '1', 'Seuil positif réservé au pilote isolé.');
    const key = crypto.randomUUID().slice(0, 8);
    const email = 'fixture-hall-' + key + '@example.test';
    const password = crypto.randomUUID() + 'Aa!';
    await page.addInitScript(() => {
        const arc = CanvasRenderingContext2D.prototype.arc;
        const fillText = CanvasRenderingContext2D.prototype.fillText;
        const locations = new WeakMap();
        window.hallPoints = {};
        window.hallOrbitalMarker = false;
        CanvasRenderingContext2D.prototype.arc = function (x, y, ...rest) {
            locations.set(this, { x, y });
            return arc.call(this, x, y, ...rest);
        };
        CanvasRenderingContext2D.prototype.fillText = function (text, ...rest) {
            if (this.canvas.classList.contains('gv2-canvas--galaxy') && /^[^:]+:\d+$/.test(text)) {
                window.hallPoints[text] = locations.get(this);
            }
            if (this.canvas.classList.contains('gv2-canvas--system') && text === '◆') window.hallOrbitalMarker = true;
            return fillText.call(this, text, ...rest);
        };
    });
    await page.goto('/inscription');
    await page.getByLabel('Adresse email').fill(email);
    await page.getByLabel('Nom de commandant').fill('Hall-' + key);
    await page.getByLabel('Mot de passe (12 caractères minimum)', { exact: true }).fill(password);
    await page.getByLabel('Confirmer le mot de passe').fill(password);
    await page.getByRole('button', { name: 'Créer mon compte' }).click();
    await expect(page).toHaveURL(/connexion/);
    await page.getByLabel('Adresse email').fill(email);
    await page.getByLabel('Mot de passe', { exact: true }).fill(password);
    await page.getByRole('button', { name: /Connexion/ }).click();
    await page.locator('.universe-card').filter({ has: page.getByRole('heading', { name: /Omega/ }) }).getByRole('button').click();
    await expect(page).toHaveURL(/planetes\/\d+$/);
    const colony = Number(new URL(page.url()).pathname.split('/').pop());
    const system = Number(queryIsolatedSql(process.env.BROWSER_ROUTER, 'SELECT solar_system_id FROM planet WHERE id=' + colony));
    const galaxy = Number(queryIsolatedSql(process.env.BROWSER_ROUTER, 'SELECT galaxy_id FROM solar_system WHERE id=' + system));
    await page.goto('/guildes/creer');
    await page.getByLabel('Nom de votre nouvelle guilde').fill('Guilde hall ' + key);
    await page.getByLabel('Tag de la guilde', { exact: true }).fill('HAL');
    await page.getByRole('button', { name: 'Fonder une guilde' }).click();
    await expect(page.getByRole('heading', { name: 'Guilde hall ' + key })).toBeVisible();

    const native = await browser.newContext({ baseURL, javaScriptEnabled: false, viewport: { width: 390, height: 844 }, storageState: await page.context().storageState() });
    try {
        const screen = await native.newPage();
        await screen.goto('/guildes/hall');
        await expect(screen.getByRole('progressbar', { name: 'Progression de la construction' })).toHaveAttribute('value', '0');
        await expect(screen.getByRole('link', { name: 'Hall de guilde' })).toHaveAttribute('aria-current', 'page');
        await screen.getByLabel('Hydrogène', { exact: true }).fill('32');
        await screen.getByRole('button', { name: 'Donner ces ressources' }).click();
        await expect(screen.getByRole('progressbar')).toHaveAttribute('value', '32');
        await screen.getByLabel('Système à revendiquer').selectOption(String(system));
        await screen.getByLabel('Je confirme l’emplacement de mon Hall.').check();
        await screen.getByRole('button', { name: 'Installer le Hall' }).click();
        await expect(screen.getByText(/Installé à la position 0/)).toBeVisible();
        await screen.goto('/carte?galaxy=' + galaxy + '&system=' + system);
        await expect(screen.locator('[data-system-guild-hall]')).toContainText('Position 0 · Hall de guilde · [HAL]');
    } finally {
        await native.close();
    }

    await page.setViewportSize({ width: 1440, height: 900 });
    await page.emulateMedia({ reducedMotion: 'reduce' });
    await page.goto('/carte-v2?galaxy=' + galaxy + '&system=' + system);
    const shell = page.locator('.galaxy-v2');
    const map = JSON.parse(await shell.getAttribute('data-galaxy-v2-map-value'));
    const site = map.systems.find(row => row.id === system);
    expect(site.hall).toEqual({ position: 0, tag: 'HAL' });
    const search = page.getByRole('searchbox', { name: 'Rechercher un système ou une planète connue' });
    await search.fill(site.name);
    await page.locator('.gv2-search-results button').filter({ hasText: site.name }).first().focus();
    const code = site.galaxy + ':' + site.number;
    await expect.poll(() => page.evaluate(key => Boolean(window.hallPoints[key]), code)).toBe(true);
    const point = await page.evaluate(key => window.hallPoints[key], code);
    await search.fill('');
    const canvas = page.locator('.gv2-canvas--galaxy');
    await canvas.scrollIntoViewIfNeeded();
    const bounds = await canvas.boundingBox();
    await page.mouse.move(bounds.x + point.x, bounds.y + point.y);
    await expect(page.locator('.gv2-tooltip-facts')).toContainText('[HAL] · Position 0');
    await expect(page.getByRole('complementary', { name: 'Informations sur le système sélectionné' }).locator('[data-system-guild-hall]')).toContainText('[HAL]');
    await page.getByRole('button', { name: 'Entrer dans le système' }).click();
    await expect(shell).toHaveAttribute('data-view', 'system');
    await expect.poll(() => page.evaluate(() => window.hallOrbitalMarker)).toBe(true);
});
