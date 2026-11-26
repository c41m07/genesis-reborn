import { test, expect } from './support/isolated-test.mjs';
import { queryIsolatedSql, runIsolatedSql } from './support/game-fixtures.mjs';

async function enterOmega(page, project) {
    const suffix = crypto.randomUUID().slice(0, 8);
    const email = 'guild-map-' + suffix + '@example.test';
    const name = 'Cartographe-' + suffix;
    const password = crypto.randomUUID() + 'aA1!';
    await page.goto('/inscription');
    await page.getByLabel('Adresse email').fill(email);
    await page.getByLabel('Nom de commandant').fill(name);
    await page.getByLabel('Mot de passe (12 caractères minimum)', { exact: true }).fill(password);
    await page.getByLabel('Confirmer le mot de passe').fill(password);
    await page.getByRole('button', { name: 'Créer mon compte' }).click();
    await expect(page).toHaveURL(/connexion/);
    await page.getByLabel('Adresse email').fill(email);
    await page.getByLabel('Mot de passe', { exact: true }).fill(password);
    await page.getByRole('button', { name: /Connexion/ }).click();
    await page.locator('article.universe-card').filter({ has: page.getByRole('heading', { name: /Omega/ }) }).getByRole('button').click();
    await expect(page).toHaveURL(/planetes\/\d+$/);
    const planet = Number(new URL(page.url()).pathname.split('/').pop());
    const member = Number(queryIsolatedSql(project, 'SELECT owner_id FROM planet WHERE id='+planet));
    return { planet, member, name };
}

test('Galaxie : filtre clavier des flottes de guilde et révocation sans rechargement', async ({ page, browser, baseURL }) => {
    test.setTimeout(120000);
    const project = process.env.BROWSER_ROUTER;
    const viewer = await enterOmega(page, project);
    const context = await browser.newContext({ baseURL });
    try {
        const ownerPage = await context.newPage();
        const owner = await enterOmega(ownerPage, project);
        await ownerPage.goto('/guildes/creer');
        await ownerPage.getByLabel('Nom de votre nouvelle guilde').fill('Veilleurs ' + owner.name);
        await ownerPage.getByLabel('Tag de la guilde', { exact: true }).fill(owner.name.slice(-5).toUpperCase());
        await ownerPage.getByRole('button', { name: 'Fonder une guilde' }).click();
        const guild = Number(queryIsolatedSql(project, 'SELECT id FROM guild WHERE leader_id='+owner.member));
        expect(guild).toBeGreaterThan(0);

        // Une sonde en route vers le lecteur ne crée aucune alerte entrante.
        runIsolatedSql(project, `INSERT INTO guild_member (guild_id,player_universe_id) VALUES (${guild},${viewer.member})`);
        runIsolatedSql(project, `INSERT IGNORE INTO player_discovery (player_universe_id,solar_system_id)
            SELECT ${viewer.member},solar_system_id FROM planet WHERE id=${owner.planet}`);
        runIsolatedSql(project, `INSERT INTO fleet (player_universe_id,home_planet_id,destination_id,destination_system_id,status,mission,speed_ratio,cargo,reserved_fuel,orbit_fuel,orbit_duration_seconds,travel_seconds,departure_at,arrival_at,dispatch_token)
            SELECT ${owner.member},${owner.planet},id,solar_system_id,'traveling','spy',1,'{"hydrogen":987654321}',0,0,0,3600,UTC_TIMESTAMP(),DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 HOUR),UUID() FROM planet WHERE id=${viewer.planet}`);

        await page.emulateMedia({ reducedMotion: 'reduce' });
        await page.addInitScript(() => {
            const clear = CanvasRenderingContext2D.prototype.clearRect;
            const fill = CanvasRenderingContext2D.prototype.fill;
            window.guildScoutDrawn = false;
            CanvasRenderingContext2D.prototype.clearRect = function (...args) {
                if (this.canvas.classList.contains('gv2-canvas--galaxy')) window.guildScoutDrawn = false;
                return clear.apply(this, args);
            };
            CanvasRenderingContext2D.prototype.fill = function (...args) {
                if (this.canvas.classList.contains('gv2-canvas--galaxy') && this.fillStyle === '#a78bfa') {
                    window.guildScoutDrawn = true;
                }
                return fill.apply(this, args);
            };
        });
        await page.setViewportSize({ width: 1920, height: 1080 });
        const errors = [];
        page.on('pageerror', error => errors.push(error.message));
        await page.goto('/carte-v2');
        const openFilters = page.getByRole('button', { name: /Filtres/ });
        const checkbox = page.getByRole('checkbox', { name: 'Afficher les flottes de guilde' });
        const row = page.locator('.gv2-flight-log [data-guild-flight]');
        await expect(checkbox).toBeChecked();
        await expect(row).toBeVisible();
        await expect.poll(() => page.evaluate(() => window.guildScoutDrawn)).toBe(true);
        await expect(row).toContainText('Espionnage');
        await expect(row).toContainText(owner.name);
        await expect(row.locator('time')).toHaveAttribute('datetime', /T/);
        expect(await page.content()).not.toContain('987654321');
        const logged = JSON.parse(await page.locator('[data-galaxy-v2-map-value]').getAttribute('data-galaxy-v2-map-value')).flights.find(flight => flight.guildFlight);
        expect(logged).not.toHaveProperty('shipCount');
        expect(logged).not.toHaveProperty('ships');

        await checkbox.focus();
        await page.keyboard.press('Space');
        await expect(row).toBeHidden();
        await expect.poll(() => page.evaluate(() => window.guildScoutDrawn)).toBe(false);
        await expect(checkbox).toBeFocused();

        // Le rafraîchissement remplace le journal sans réinitialiser le filtre.
        const nextPoll = () => page.waitForResponse(response => /\/flottes\/surveillance\?.*galaxy=/.test(response.url()) && response.ok());
        await nextPoll();
        await expect(row).toBeHidden();
        await expect(checkbox).not.toBeChecked();
        await expect(checkbox).toBeFocused();

        for (const width of [390, 320]) {
            await page.setViewportSize({ width, height: 844 });
            if (!await checkbox.isVisible()) await openFilters.click({ timeout: 7000 });
            await expect(checkbox).toBeVisible();
            expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(width);
        }
        await checkbox.check({ timeout: 7000 });
        await expect(row).toBeVisible();

        await checkbox.focus();
        runIsolatedSql(project, `DELETE FROM guild_member WHERE player_universe_id=${viewer.member}`);
        await nextPoll();
        await expect(row).toHaveCount(0);
        await expect(checkbox).toBeHidden();
        await expect.poll(() => page.evaluate(() => window.guildScoutDrawn)).toBe(false);
        await expect(openFilters).toBeFocused();
        expect(errors).toEqual([]);
    } finally {
        await context.close();
    }
});
