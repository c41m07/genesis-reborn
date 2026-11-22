import { test, expect } from './support/isolated-test.mjs';
import { queryIsolatedSql, runIsolatedSql } from './support/game-fixtures.mjs';

test('Avent : retrouver sans JavaScript un cadeau réservé après la clôture', async ({ page, browser, baseURL }) => {
    test.skip(process.env.ADVENT_BROWSER_FIXTURE !== '1', 'Édition positive uniquement dans la recette isolée.');
    const marker = crypto.randomUUID().slice(0, 8);
    const email = 'fixture-advent-' + marker + '@example.test';
    const password = crypto.randomUUID() + 'Aa!';
    await page.goto('/inscription');
    await page.getByLabel('Adresse email').fill(email);
    await page.getByLabel('Nom de commandant').fill('Avent-' + marker);
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

    const project = process.env.BROWSER_ROUTER;
    // Choix et date uniquement synthétiques : l’administration est vérifiée en HTTP.
    const now = Date.now();
    const doors = Object.fromEntries(Array.from({ length: 24 }, (_, index) => [index + 1, {
        opens_at: new Date(now + (index - 1) * 86400000).toISOString(),
        options: [{ metal: 7 }, { nano: 3 }],
    }]));
    runIsolatedSql(project, "INSERT INTO advent_edition (season, name, starts_at, closes_at, doors, published_at) VALUES (2099, 'Avent de recette', UTC_TIMESTAMP() - INTERVAL 1 DAY, UTC_TIMESTAMP() + INTERVAL 30 DAY, '" + JSON.stringify(doors) + "', UTC_TIMESTAMP())");
    const edition = Number(queryIsolatedSql(project, 'SELECT id FROM advent_edition WHERE season=2099'));
    expect(edition).toBeGreaterThan(0);
    runIsolatedSql(project, "UPDATE planet_resource SET last_updated_at = UTC_TIMESTAMP(), current_stock = IF(resource_type='metal',10000,current_stock) WHERE planet_id=" + colony);

    const native = await browser.newContext({
        baseURL, javaScriptEnabled: false, viewport: { width: 390, height: 844 },
        storageState: await page.context().storageState(),
    });
    try {
        const mobile = await native.newPage();
        await mobile.goto('/evenements');
        await mobile.getByRole('link', { name: 'Ouvrir le calendrier de l’Avent' }).click();
        await expect(mobile.getByRole('heading', { level: 1, name: 'Avent de recette' })).toBeVisible();
        await expect(mobile.locator('.advent-door')).toHaveCount(24);
        const first = mobile.locator('#advent-door-1');
        await first.getByText('Choisir mon cadeau', { exact: false }).press('Enter');
        await first.getByLabel('Cadeau de la porte 1').selectOption('0');
        await first.getByLabel('Colonie destinataire').selectOption(String(colony));
        await first.getByRole('button', { name: 'Confirmer le cadeau et la destination' }).click();
        await expect(first.getByText('Réservé', { exact: true })).toBeVisible();
        await expect(mobile.getByText('Aucun versement partiel.', { exact: false })).toBeVisible();
        expect(Number(queryIsolatedSql(project, 'SELECT COUNT(*) FROM advent_claim WHERE delivered_at IS NULL'))).toBe(1);

        runIsolatedSql(project, 'UPDATE advent_edition SET closes_at = UTC_TIMESTAMP() - INTERVAL 1 SECOND WHERE id=' + edition);
        runIsolatedSql(project, "UPDATE planet_resource SET last_updated_at = UTC_TIMESTAMP(), current_stock = IF(resource_type='metal',500,current_stock) WHERE planet_id=" + colony);
        await mobile.goto('/evenements');
        await mobile.getByRole('link', { name: 'Ouvrir le calendrier de l’Avent' }).click();
        await expect(mobile.locator('#advent-door-24').getByText('Expiré', { exact: true })).toBeVisible();
        await first.getByText('Reprendre le versement', { exact: false }).press('Enter');
        await first.getByLabel('Colonie destinataire').selectOption(String(colony));
        await first.getByRole('button', { name: 'Retenter le versement' }).click();
        await expect(first.getByText('Versé', { exact: true })).toBeVisible();
        expect(Number(queryIsolatedSql(project, 'SELECT COUNT(*) FROM advent_claim WHERE delivered_at IS NOT NULL'))).toBe(1);

        for (const width of [360, 390, 1440]) {
            await page.setViewportSize({ width, height: 900 });
            await page.goto('/evenements/avent/' + edition);
            await expect(page.getByRole('heading', { name: 'Porte 24', exact: true })).toBeAttached();
            const contentWidth = await page.evaluate(() => [document.documentElement.scrollWidth, window.innerWidth]);
            expect(contentWidth[0]).toBeLessThanOrEqual(contentWidth[1] + 2);
        }
    } finally {
        await native.close();
    }
});
