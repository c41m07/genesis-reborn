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

    const screens = [
        ['/evenements', 'P04', '.player-event-period--current', '.player-event-period--upcoming'],
        ['/profil', 'P06', '.account-profile-form', '.account-board-rail'],
        ['/profil/preferences', 'P07', '.account-board-settings', '.account-board-rail'],
        ['/premiers-pas', 'P08', '.first-steps-board-layout > .first-steps', '.first-steps-board-rail'],
        ['/boosts', 'P11', '.inventory-board-stock', '.inventory-boost-use'],
        ['/classement', 'P14', '.ranking-board-main', '.ranking-board-help'],
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
        expect(a.x + a.width, model).toBeLessThan(b.x);
        expect(a.width, model).toBeGreaterThan(b.width);
        expect(await page.evaluate(() => [window.innerWidth, window.innerHeight]), model).toEqual([1920, 1080]);
        await page.screenshot({ path: info.outputPath(model + '-1920x1080.png'), scale: 'css', animations: 'disabled' });

        await page.setViewportSize({ width: 390, height: 844 });
        const [m, n] = await Promise.all([first.boundingBox(), second.boundingBox()]);
        expect(m.y + m.height, model).toBeLessThan(n.y);
        expect(await page.evaluate(() => document.documentElement.scrollWidth), model).toBeLessThanOrEqual(390);
        await page.screenshot({ path: info.outputPath(model + '-390x844.png'), scale: 'css', animations: 'disabled' });
    }
});
