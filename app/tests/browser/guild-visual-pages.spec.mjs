import { test, expect } from './support/isolated-test.mjs';
import AxeBuilder from '@axe-core/playwright';

test('Guilde : trois pages lisibles, thèmes, clavier et repli sans image ni JavaScript', async ({ page, browser }, testInfo) => {
    test.setTimeout(120000);
    const identifier = crypto.randomUUID().slice(0, 8);
    const email = `guild-visual-${identifier}@example.test`;
    const password = crypto.randomUUID() + '!aA1';
    await page.goto('/inscription');
    await page.getByLabel('Adresse email').fill(email);
    await page.getByLabel('Nom de commandant').fill('Veilleur-' + identifier);
    await page.getByLabel('Mot de passe (12 caractères minimum)', { exact: true }).fill(password);
    await page.getByLabel('Confirmer le mot de passe').fill(password);
    await page.getByRole('button', { name: 'Créer mon compte' }).click();
    await page.getByLabel('Adresse email').fill(email);
    await page.getByLabel('Mot de passe', { exact: true }).fill(password);
    await page.getByRole('button', { name: /Connexion/ }).click();
    await page.locator('.universe-card').filter({ has: page.getByRole('heading', { name: /Omega/ }) }).getByRole('button').click();
    await page.goto('/guildes/creer');
    await page.getByLabel('Nom de votre nouvelle guilde').fill('Les Veilleurs d’Orion');
    await page.getByLabel('Tag de la guilde', { exact: true }).fill('ORION');
    await page.getByRole('button', { name: 'Fonder une guilde' }).click();
    await expect(page.locator('.guild-identity h2')).toHaveText('Les Veilleurs d’Orion');
    await expect(page.locator('.guild-hub-stats')).toContainText('Commandant');

    // Les images restent facultatives : l’identité et les actions sont toujours du texte natif.
    await expect(page.locator('.guild-public-banner')).toHaveCount(0);
    await page.getByRole('navigation', { name: 'Navigation de guilde' }).getByRole('link', { name: 'Personnalisation', exact: true }).click();
    await page.getByLabel('Description', { exact: true }).fill('Explorons les frontières connues. Ensemble, bâtissons notre place parmi les étoiles.');
    // La fixture réutilise l’illustration locale, aux dimensions acceptées par le formulaire.
    const banner = await page.evaluate(async () => {
        const image = new Image();
        image.src = '/art/colony-overview.png';
        await image.decode();
        const canvas = document.createElement('canvas');
        canvas.width = 1440;
        canvas.height = 480;
        canvas.getContext('2d').drawImage(image, 0, 0, canvas.width, canvas.height);
        return canvas.toDataURL('image/png').split(',')[1];
    });
    await page.getByLabel('Bannière', { exact: true }).setInputFiles({ name: 'banniere-test.png', mimeType: 'image/png', buffer: Buffer.from(banner, 'base64') });
    await page.getByRole('button', { name: 'Enregistrer la présentation' }).click();
    await expect(page.locator('.guild-public-description')).toContainText('Explorons les frontières connues.');
    await page.goto('/guildes');
    await expect.poll(() => page.locator('.guild-public-banner').evaluate(image => image.naturalWidth)).toBe(1440);

    const destinations = [
        { path: '/guildes', label: 'Vue générale', capture: 'guilde' },
        { path: '/guildes/membres', label: 'Membres', capture: 'membres' },
        { path: '/guildes/grades', label: 'Grades et permissions', capture: 'grades' },
    ];
    for (const theme of ['dark', 'light']) {
        await page.goto('/profil/preferences');
        await page.getByLabel('Thème de l’interface').selectOption(theme);
        await page.getByRole('button', { name: 'Enregistrer les paramètres' }).click();
        for (const destination of destinations) {
            await page.goto(destination.path);
            await expect(page.locator('body')).toHaveAttribute('data-theme', theme);
            await expect(page.getByRole('navigation', { name: 'Navigation de guilde' }).getByRole('link', { name: destination.label, exact: true })).toHaveAttribute('aria-current', 'page');
            await expect(page.locator('.page-contents')).toHaveCount(0);
            for (const width of [320, 390, 768, 1440]) {
                await page.setViewportSize({ width, height: 900 });
                await expect.poll(() => page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(width);
                if (destination.path === '/guildes') {
                    // Une absence de débordement ne suffit pas : le grade doit rester lisible en entier.
                    await expect(page.locator('.guild-hub-stats dd').nth(1)).toHaveText('Commandant');
                    expect(await page.locator('.guild-hub-stats dd').nth(1).evaluate(element => element.clientHeight)).toBeLessThan(40);
                }
                if (width === 390 || width === 1440) {
                    await page.screenshot({ path: testInfo.outputPath(`${destination.capture}-${theme}-${width}.png`), fullPage: true, animations: 'disabled' });
                }
            }
            expect((await new AxeBuilder({ page }).include('main').analyze()).violations).toEqual([]);
        }
    }

    await page.goto('/guildes');
    const membersLink = page.getByRole('navigation', { name: 'Navigation de guilde' }).getByRole('link', { name: 'Membres', exact: true });
    await membersLink.focus();
    expect(await membersLink.evaluate(element => element.matches(':focus-visible'))).toBe(true);
    await page.keyboard.press('Enter');
    await expect(page).toHaveURL(/\/guildes\/membres$/);
    await page.reload();
    await expect(page.getByRole('heading', { level: 1 })).toHaveText('Membres de ma guilde');
    await page.goBack();
    await expect(page).toHaveURL(/\/guildes$/);

    await page.route('**/image/banner', route => route.abort());
    await page.reload();
    await expect(page.locator('.guild-identity h2')).toBeVisible();
    await expect(page.getByRole('link', { name: /Ouvrir le canal de guilde/ })).toBeVisible();

    const plainContext = await browser.newContext({ storageState: await page.context().storageState(), javaScriptEnabled: false });
    try {
        const plain = await plainContext.newPage();
        for (const destination of destinations) {
            await plain.goto(new URL(destination.path, page.url()).href);
            await expect(plain.getByRole('navigation', { name: 'Navigation de guilde' }).getByRole('link', { name: destination.label, exact: true })).toHaveAttribute('aria-current', 'page');
            await expect(plain.getByRole('heading', { level: 1 })).toBeVisible();
        }
        await expect(plain.getByLabel('Nom du grade', { exact: true })).toBeVisible();
    } finally {
        await plainContext.close();
    }
});
