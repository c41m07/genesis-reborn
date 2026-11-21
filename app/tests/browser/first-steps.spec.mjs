import { test, expect } from './support/isolated-test.mjs';
import { accelerateQueuedItem } from './support/game-fixtures.mjs';

async function register(page) {
    const id = crypto.randomUUID().slice(0, 8);
    const email = 'first-steps-' + id + '@example.test';
    const password = crypto.randomUUID() + 'Aa1!';
    await page.goto('/inscription');
    await page.getByLabel('Adresse email').fill(email);
    await page.getByLabel('Nom de commandant').fill('Cadet-' + id);
    await page.getByLabel('Mot de passe (12 caractères minimum)', { exact: true }).fill(password);
    await page.getByLabel('Confirmer le mot de passe').fill(password);
    await page.getByRole('button', { name: 'Créer mon compte' }).click();
    await page.getByLabel('Adresse email').fill(email);
    await page.getByLabel('Mot de passe', { exact: true }).fill(password);
    await page.getByRole('button', { name: /Connexion/ }).click();
    await page.locator('.universe-card').filter({ has: page.getByRole('heading', { name: /Omega/ }) }).getByRole('button').click();
    await expect(page).toHaveURL(/planetes\/\d+$/);

    return email;
}

test('Premiers pas : conseil facultatif et progression après la fin réelle de la mine', async ({ page, browser, baseURL }, info) => {
    test.setTimeout(120000);
    const email = await register(page);
    const overview = page.url();
    await page.setViewportSize({ width: 390, height: 844 });
    await page.getByRole('link', { name: 'Premiers pas', exact: true }).click();
    await expect(page.getByRole('heading', { name: 'Premiers pas', exact: true })).toBeVisible();
    const guide = page.getByRole('region', { name: 'Installer sa colonie', exact: true });
    const progress = guide.getByRole('progressbar', { name: 'Progression des premiers pas', exact: true });
    const next = guide.locator('.first-step--next');
    await expect(progress).toHaveAttribute('value', '0');
    await expect(next.getByRole('heading', { name: 'Mine à ciel ouvert', exact: true })).toBeVisible();
    await expect(guide.getByRole('heading', { name: 'Première recherche', exact: true })).toBeVisible();
    await expect.poll(() => page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(390);
    await page.screenshot({ path: info.outputPath('premiers-pas-mobile.png'), animations: 'disabled' });

    // Le guide ne bloque pas le reste du jeu avant la première construction.
    await page.getByRole('link', { name: 'Retour à la vue générale', exact: true }).click();
    await expect(page).toHaveURL(overview);
    await page.getByRole('link', { name: 'Premiers pas', exact: true }).click();
    await next.getByRole('link', { name: 'Ouvrir la Mine à ciel ouvert', exact: true }).click();
    await expect(page).toHaveURL(/section=buildings.*#item-mine_metal-basic$/);
    const card = page.locator('#item-mine_metal-basic');
    await card.getByRole('button', { name: /Améliorer .* niveau 1/ }).click();
    await page.goto('/premiers-pas');
    await expect(progress).toHaveAttribute('value', '0');

    accelerateQueuedItem(process.env.BROWSER_ROUTER, email, 'building', 'mine_metal');
    const target = new URL(overview);
    target.searchParams.set('section', 'buildings');
    await page.goto(target.href);
    await expect(page.locator('#building-tab-mine_metal-basic .building-rank-level')).toHaveText('1');
    await page.goto('/premiers-pas');
    await expect(progress).toHaveAttribute('value', '1');
    await expect(guide.getByRole('listitem').filter({ has: page.getByRole('heading', { name: 'Mine à ciel ouvert', exact: true }) })).toContainText('Terminé');
    await expect(next.getByRole('heading', { name: 'Centrale nucléaire', exact: true })).toBeVisible();

    const native = await browser.newContext({ baseURL, storageState: await page.context().storageState(), javaScriptEnabled: false });
    try {
        const fallback = await native.newPage();
        await fallback.goto('/premiers-pas');
        await expect(fallback.getByRole('progressbar', { name: 'Progression des premiers pas', exact: true })).toHaveAttribute('value', '1');
        await fallback.getByRole('link', { name: 'Ouvrir la Centrale nucléaire', exact: true }).click();
        await expect(fallback).toHaveURL(/section=buildings.*#item-power_plant-basic$/);
    } finally {
        await native.close();
    }
});
