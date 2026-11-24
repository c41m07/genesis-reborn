import { test, expect } from './support/isolated-test.mjs';

test('Planches joueur : rails de décision sur grand écran et flux mobile sans chevauchement', async ({ page }, info) => {
    test.setTimeout(110000);
    const suffix = crypto.randomUUID().slice(0, 8);
    const email = 'joueur-maquettes-' + suffix + '@example.test';
    const password = crypto.randomUUID() + 'Aa1!';
    await page.goto('/inscription');
    await page.getByLabel('Adresse email').fill(email);
    await page.getByLabel('Nom de commandant').fill('Maquettes-' + suffix);
    await page.getByLabel('Mot de passe (12 caractères minimum)', { exact: true }).fill(password);
    await page.getByLabel('Confirmer le mot de passe').fill(password);
    await page.getByRole('button', { name: 'Créer mon compte' }).click();
    await page.getByLabel('Adresse email').fill(email);
    await page.getByLabel('Mot de passe', { exact: true }).fill(password);
    await page.getByRole('button', { name: /Connexion/ }).click();
    await page.locator('.universe-card').filter({ has: page.getByRole('heading', { name: /Omega/ }) }).getByRole('button').click();
    await expect(page).toHaveURL(/planetes\/\d+$/);

    const ownedPlanetPath = new URL(page.url()).pathname;

    const screens = [
        ['/evenements', 'P04', '.player-event-period--current', '.player-event-period--upcoming'],
        ['/profil', 'P06', '.account-profile-form', '.account-board-rail'],
        ['/profil/preferences', 'P07', '.account-board-settings', '.account-board-rail'],
        ['/premiers-pas', 'P08', '.first-steps-board-layout > .first-steps', '.first-steps-board-rail'],
        ['/boosts', 'P11', '.inventory-board-stock', '.inventory-boost-use'],
        ['/wiki', 'P15', '.wiki-chapters', '.wiki-navigation'],

    ];

    // Les mesures sont prises en pixels CSS à l’échelle 1, pas sur des captures recadrées.
    for (const [path, model, main, aside] of screens) {
        await page.setViewportSize({ width: 1920, height: 1080 });
        const response = await page.goto(path);
        expect(response?.status(), path).toBe(200);
        const first = page.locator(main);
        const second = page.locator(aside);
        await expect(first).toBeVisible();
        await expect(second).toBeVisible();
        const [a, b] = await Promise.all([first.boundingBox(), second.boundingBox()]);
        expect(model === 'P15' ? b.x + b.width : a.x + a.width, model).toBeLessThan(model === 'P15' ? a.x : b.x);
        expect(a.width, model).toBeGreaterThan(b.width);
        expect(await page.evaluate(() => [window.innerWidth, window.innerHeight]), model).toEqual([1920, 1080]);
        await page.screenshot({ path: info.outputPath(model + '-1920x1080.png'), scale: 'css', animations: 'disabled' });

        await page.setViewportSize({ width: 390, height: 844 });
        const [m, n] = await Promise.all([first.boundingBox(), second.boundingBox()]);
        expect(model === 'P15' ? n.y + n.height : m.y + m.height, model).toBeLessThan(model === 'P15' ? m.y : n.y);
        expect(await page.evaluate(() => document.documentElement.scrollWidth), model).toBeLessThanOrEqual(390);
        await page.screenshot({ path: info.outputPath(model + '-390x844.png'), scale: 'css', animations: 'disabled' });
    }

    const additional = [
        ['/classement', 'P14', '.ranking-board-main'],
        ['/evenements/campagne-pirate', 'P05', '.player-campaign-board'],
        ['/planetes', 'P10', '.planet-board-hero'],
        ['/technologies', 'P12', '.player-technology-board'],
        ['/simulateur', 'P13', '#simulation-ships'],
        ['/profil/supprimer', 'P17', '.account-delete-form'],
        ['/recompense-quotidienne', 'P18', '.daily-board-cycle'],
        ['/planetes?view=table', 'P10-comparaison', '.colony-comparison-panel'],
        [ownedPlanetPath + '/abandonner', 'P19-abandon-bloque', '.colony-abandon-confirmation'],
        ['/patchnotes', 'P20', '.player-patchnotes-board'],
        ['/premiers-pas/exercice-pirate', 'P21', '.first-steps-exercise-board'],
    ];
    for (const [path, model, landmark] of additional) {
        for (const [width, height] of [[1920, 1080], [390, 844]]) {
            await test.step(model + ' · ' + width, async () => {
                await page.setViewportSize({ width, height });
                expect.soft((await page.goto(path))?.status(), path).toBe(200);
                await expect.soft(page.locator(landmark).first()).toBeVisible({ timeout: 3000 });
                expect.soft(await page.evaluate(() => document.documentElement.scrollWidth), model).toBeLessThanOrEqual(width);
                if (model === 'P13' && width === 1920) {
                    const next = page.getByRole('link', { name: 'Continuer : défenses', exact: true });
                    await expect(next).toBeVisible();
                    const grids = page.locator('#simulation-ships .simulation-wizard-camp');
                    await expect(grids).toHaveCount(2);
                    for (const grid of await grids.all()) {
                        const lastRow = grid.getByRole('row').last();
                        await lastRow.scrollIntoViewIfNeeded();
                        await expect(lastRow).toBeInViewport({ ratio: 1 });
                    }
                    await page.evaluate(() => window.scrollTo({top: 0, behavior: 'instant'}));
                }
                await page.screenshot({ path: info.outputPath(model + '-' + width + 'x' + height + '.png'), scale: 'css', animations: 'disabled' });
                if (model === 'P12') {
                    const details = page.locator('#technology-detail');
                    const item = page.locator('.technology-overview .tech-node:visible > summary').first();
                    await expect(details).toBeHidden();
                    await item.click();
                    await expect(details).toBeVisible();
                    await expect(details.getByRole('heading', { level: 2 })).toBeVisible();
                    if (width === 1920) {
                        await page.evaluate(() => window.scrollTo({ top: 0, behavior: 'instant' }));
                    } else {
                        await details.scrollIntoViewIfNeeded();
                    }
                    await page.screenshot({ path: info.outputPath('P12-selection-' + width + 'x' + height + '.png'), scale: 'css', animations: 'disabled' });
                    await details.getByRole('button', { name: 'Fermer les détails' }).click();
                    await expect(details).toBeHidden();
                    await expect(item).toBeFocused();
                }
            });
        }
    }
});
