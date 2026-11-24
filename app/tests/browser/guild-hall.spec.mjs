import { test, expect } from './support/isolated-test.mjs';
import { queryIsolatedSql, runIsolatedSql } from './support/game-fixtures.mjs';

async function register(page, key) {
    const email = 'fixture-hall-' + key + '@example.test';
    const password = crypto.randomUUID() + 'Aa!';
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
}

test('Pilote Hall : don natif, confirmation du commandant et présentation depuis Galaxie', async ({ page, browser, baseURL }, testInfo) => {
    test.skip(process.env.GUILD_HALL_BROWSER_FIXTURE !== '1', 'Seuil positif réservé au pilote isolé.');
    const key = crypto.randomUUID().slice(0, 8);
    await page.addInitScript(() => {
        const arc = CanvasRenderingContext2D.prototype.arc;
        const fillText = CanvasRenderingContext2D.prototype.fillText;
        const locations = new WeakMap();
        window.hallPoints = {};
        CanvasRenderingContext2D.prototype.arc = function (x, y, ...rest) {
            locations.set(this, { x, y });
            return arc.call(this, x, y, ...rest);
        };
        CanvasRenderingContext2D.prototype.fillText = function (text, ...rest) {
            if (this.canvas.classList.contains('gv2-canvas--galaxy') && /^[^:]+:\d+$/.test(text)) {
                window.hallPoints[text] = locations.get(this);
            }
            return fillText.call(this, text, ...rest);
        };
    });
    await register(page, key);
    const colony = Number(new URL(page.url()).pathname.split('/').pop());
    const system = Number(queryIsolatedSql(process.env.BROWSER_ROUTER, 'SELECT solar_system_id FROM planet WHERE id=' + colony));
    const galaxy = Number(queryIsolatedSql(process.env.BROWSER_ROUTER, 'SELECT galaxy_id FROM solar_system WHERE id=' + system));
    await page.goto('/guildes/creer');
    await page.getByLabel('Nom de votre nouvelle guilde').fill('Guilde hall ' + key);
    await page.getByLabel('Tag de la guilde', { exact: true }).fill('HAL');
    await page.getByRole('button', { name: 'Fonder une guilde' }).click();
    await expect(page.getByRole('heading', { name: 'Guilde hall ' + key })).toBeVisible();
    const project = process.env.BROWSER_ROUTER;
    const guild = Number(queryIsolatedSql(project, 'SELECT g.id FROM guild g JOIN planet p ON p.owner_id=g.leader_id WHERE p.id=' + colony));
    expect(guild).toBeGreaterThan(0);

    const native = await browser.newContext({ baseURL, javaScriptEnabled: false, viewport: { width: 390, height: 844 }, storageState: await page.context().storageState() });
    try {
        const screen = await native.newPage();
        await screen.goto('/guildes/hall');
        const progress = screen.getByRole('progressbar', { name: 'Progression de la construction' });
        const quorum = screen.getByRole('progressbar', { name: 'Donateurs qualifiés' });
        await expect(progress).toHaveAttribute('value', '0');
        await expect(quorum).toHaveAttribute('value', '0');
        const fundingNotice = screen.locator('.guild-hall-funding-notice');
        await expect(fundingNotice).toContainText('Le placement n’est pas encore disponible');
        await expect(fundingNotice).toContainText('Le nombre minimal de donateurs qualifiés n’est pas encore atteint.');
        await expect(screen.locator('[aria-labelledby="hall-donation-title"]')).toContainText('Les dons sont prélevés sur la colonie choisie.');
        await expect(screen.getByRole('link', { name: 'Hall de guilde' })).toHaveAttribute('aria-current', 'page');
        const proofUrl = screen.url();
        async function capturePlacement(state) {
            for (const theme of ['dark', 'light']) {
                runIsolatedSql(project, "UPDATE player SET preferences=JSON_SET(IF(JSON_TYPE(preferences)='OBJECT',preferences,JSON_OBJECT()),'$.theme','" + theme + "','$.firstStepsGuide',false) WHERE email='fixture-hall-" + key + "@example.test'");
                for (const [width, height] of [[1920, 1080], [390, 844]]) {
                    await page.setViewportSize({ width, height });
                    await page.goto(proofUrl);
                    await expect(page.locator('body')).toHaveAttribute('data-theme', theme);
                    const target = page.getByRole('heading', { name: state === 'pret' ? 'Placer le Hall' : 'Hall installé', exact: true });
                    await expect(target).toBeVisible();
                    await expect.poll(() => page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(width);
                    if (width === 390) await target.scrollIntoViewIfNeeded();
                    await page.screenshot({ path: testInfo.outputPath('S19-' + state + '-' + theme + '-' + width + '.png'), animations: 'disabled' });
                    if (state === 'pret') {
                        const label = page.locator('.guild-hall-confirm');
                        const checkbox = label.getByRole('checkbox', { name: 'Je confirme l’emplacement de mon Hall.' });
                        const caption = label.getByText('Je confirme l’emplacement de mon Hall.', { exact: true });
                        const [hitArea, box, text] = await Promise.all([label.boundingBox(), checkbox.boundingBox(), caption.boundingBox()]);
                        expect(hitArea.height).toBeGreaterThanOrEqual(44);
                        expect(box.width).toBeGreaterThanOrEqual(16);
                        expect(box.width).toBeLessThanOrEqual(24);
                        expect(box.height).toBeLessThanOrEqual(24);
                        expect(text.x - (box.x + box.width)).toBeGreaterThan(0);
                        expect(text.x - (box.x + box.width)).toBeLessThanOrEqual(20);
                        expect(Math.abs((box.y + box.height / 2) - (text.y + text.height / 2))).toBeLessThanOrEqual(2);
                        await expect(checkbox).not.toBeChecked();
                        await checkbox.press('Space');
                        await expect(checkbox).toBeFocused();
                        await expect(checkbox).toBeChecked();
                        await expect(checkbox).toHaveCSS('outline-style', 'solid');
                        await caption.click();
                        await expect(checkbox).not.toBeChecked();
                    }
                }
            }
        }
        for (const theme of ['dark','light']) {
            runIsolatedSql(project, "UPDATE player SET preferences=JSON_SET(IF(JSON_TYPE(preferences)='OBJECT',preferences,JSON_OBJECT()),'$.theme','" + theme + "','$.firstStepsGuide',false) WHERE email='fixture-hall-" + key + "@example.test'");
            for (const [width,height] of [[1920,1080],[390,844]]) {
                await page.setViewportSize({ width, height });
                await page.goto(proofUrl);
                await expect(page.locator('body')).toHaveAttribute('data-theme', theme);
                await expect(page.locator('.guild-hall-funding-notice')).toBeVisible();
                const panels = page.locator('.guild-project-detail > section');
                const main = await panels.first().boundingBox();
                const donation = await panels.last().boundingBox();
                if (width === 1920) expect(donation.x).toBeGreaterThanOrEqual(main.x + main.width);
                else expect(donation.y).toBeGreaterThanOrEqual(main.y + main.height);
                await expect.poll(() => page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(width);
                if (width === 390) await page.locator('.guild-hall-funding-notice').scrollIntoViewIfNeeded();
                await page.screenshot({ path: testInfo.outputPath('S11-' + theme + '-' + width + '.png'), animations: 'disabled' });
            }
        }
        const hydrogen = screen.getByLabel('Hydrogène', { exact: true });
        await expect(hydrogen).toHaveAttribute('max', '16');
        await hydrogen.fill('16');
        await screen.getByRole('button', { name: 'Donner ces ressources' }).click();
        await expect(progress).toHaveAttribute('value', '16');
        await expect(quorum).toHaveAttribute('value', '1');
        await expect(fundingNotice).toContainText('16 unités de force à financer');
        await expect(screen.getByRole('button', { name: 'Donner ces ressources' })).toHaveCount(0);
        await expect(screen.getByText('La force restante est réservée aux autres donateurs.', { exact: false })).toBeVisible();
        await expect(screen.getByText('Votre minimum personnel est atteint.', { exact: false })).toBeVisible();
        await expect(screen.getByRole('button', { name: 'Installer le Hall' })).toHaveCount(0);

        // Les deux inscriptions sont isolées ; la fixture ne préfinance aucun don.
        for (const peerKey of [key + 'a', key + 'b']) {
            const peerContext = await browser.newContext({ baseURL });
            try {
                const peer = await peerContext.newPage();
                await register(peer, peerKey);
                const planet = Number(new URL(peer.url()).pathname.split('/').pop());
                runIsolatedSql(project, 'INSERT INTO guild_member (guild_id,player_universe_id) SELECT ' + guild + ', owner_id FROM planet WHERE id=' + planet);
                await peer.goto('/guildes/hall');
                await expect(peer.getByText(/Il vous manque 8 points/)).toBeVisible();
                await expect(peer.getByText(/utiliser une part réservée : de 8 à 8 points/)).toBeVisible();
                await peer.getByLabel('Hydrogène', { exact: true }).fill('8');
                await peer.getByRole('button', { name: 'Donner ces ressources' }).click();
                await expect(peer.getByText(/Votre minimum personnel est atteint/)).toBeVisible();
                await expect(peer.getByRole('button', { name: 'Installer le Hall' })).toHaveCount(0);
            } finally {
                await peerContext.close();
            }
        }
        await screen.reload();
        await expect(progress).toHaveAttribute('value', '32');
        await expect(quorum).toHaveAttribute('value', '3');
        await expect(fundingNotice).toHaveCount(0);
        await expect(screen.getByRole('button', { name: 'Donner ces ressources' })).toHaveCount(0);
        const placement = screen.getByRole('region', { name: 'Placer le Hall' });
        await expect(screen.locator('.guild-project-detail > section').first()).toHaveAttribute('aria-labelledby', 'hall-placement-title');
        await expect(screen.getByRole('heading', { name: 'Hall installé', exact: true })).toHaveCount(0);
        await expect(placement.getByLabel('Je confirme l’emplacement de mon Hall.')).toHaveAttribute('required', '');
        await capturePlacement('pret');
        await screen.getByLabel('Système à revendiquer').selectOption(String(system));
        await screen.getByLabel('Je confirme l’emplacement de mon Hall.').check();
        await screen.getByRole('button', { name: 'Installer le Hall' }).click();
        await expect(screen.getByText(/Installé dans le système/)).toBeVisible();
        const installed = screen.getByRole('region', { name: 'Hall installé' });
        await expect(installed.getByRole('heading', { name: 'Dons clos' })).toBeVisible();
        await expect(screen.getByRole('region', { name: 'Placer le Hall' })).toHaveCount(0);
        await expect(screen.getByRole('button', { name: 'Installer le Hall' })).toHaveCount(0);
        await expect(screen.getByRole('button', { name: 'Donner ces ressources' })).toHaveCount(0);
        await capturePlacement('installe');
        await installed.getByRole('link', { name: 'Voir le système dans Galaxie' }).press('Enter');
        await expect(screen).toHaveURL(/\/carte-v2\?/);
        await screen.goto('/carte?galaxy=' + galaxy + '&system=' + system);
        await expect(screen.locator('[data-system-guild-hall]')).toContainText('Hall de guilde · [HAL]');
    } finally {
        await native.close();
    }

    await page.setViewportSize({ width: 1440, height: 900 });
    await page.emulateMedia({ reducedMotion: 'reduce' });
    await page.goto('/carte-v2?galaxy=' + galaxy + '&system=' + system);
    const shell = page.locator('.galaxy-v2');
    const map = JSON.parse(await shell.getAttribute('data-galaxy-v2-map-value'));
    const site = map.systems.find(row => row.id === system);
    expect(site.hall).toEqual({ position: 0, tag: 'HAL', profileUrl: '/guildes/' + guild });
    const search = page.getByRole('searchbox', { name: 'Rechercher un système ou une planète connue' });
    await search.fill(site.name);
    await page.locator('.gv2-search-results button').filter({ hasText: site.name }).first().focus();
    const code = '[HAL] ' + site.galaxy + ':' + site.number;
    await expect.poll(() => page.evaluate(key => Boolean(window.hallPoints[key]), code)).toBe(true);
    const point = await page.evaluate(key => window.hallPoints[key], code);
    await search.fill('');
    const canvas = page.locator('.gv2-canvas--galaxy');
    await canvas.scrollIntoViewIfNeeded();
    const bounds = await canvas.boundingBox();
    await page.mouse.move(bounds.x + point.x, bounds.y + point.y);
    await expect(page.locator('.gv2-tooltip-coordinates')).toHaveText(code);
    await expect(page.getByRole('complementary', { name: 'Informations sur le système sélectionné' }).locator('[data-system-guild-hall]')).toContainText('[HAL]');
    await page.getByRole('button', { name: 'Entrer dans le système' }).click();
    await expect(shell).toHaveAttribute('data-view', 'system');
    const station = page.getByRole('link', { name: 'Hall de guilde [HAL]', exact: true });
    await expect(station).toBeVisible();
    await expect.poll(() => station.locator('img').evaluate(image => image.naturalWidth)).toBe(192);
    await expect(page.getByRole('link', { name: /Station pirate/ })).toHaveCount(0);
    await station.press('Enter');
    await expect(page).toHaveURL(new RegExp('/guildes/' + guild + '$'));
});
