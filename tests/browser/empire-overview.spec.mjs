import { test, expect } from './support/isolated-test.mjs';

test('Le bandeau récapitule les points et les accès de l’empire sans débordement', async ({ page }) => {
    const id = Date.now();
    const email = `empire-${id}@example.test`;
    const password = 'EmpireTest!2048';
    await page.goto('/inscription');
    await page.getByLabel('Adresse email').fill(email);
    await page.getByLabel('Nom de commandant').fill(`Empire-${id}`);
    await page.getByLabel('Mot de passe (12 caractères minimum)', { exact: true }).fill(password);
    await page.getByLabel('Confirmer le mot de passe').fill(password);
    await page.getByRole('button', { name: 'Créer mon compte' }).click();
    await page.getByLabel('Adresse email').fill(email);
    await page.getByLabel('Mot de passe', { exact: true }).fill(password);
    await page.getByRole('button', { name: /Connexion/ }).click();
    await page.locator('article.universe-card').first().getByRole('button').click();
    await expect(page).toHaveURL(/planetes\/\d+$/);
    const colonyUrl = page.url();
    await expect(page.locator('[data-resource-strip-target="protectionLabel"]')).toHaveText('Protégé du pillage');
    await expect(page.locator('[data-resource-strip-target="protectionTime"]')).toContainText(/j.*h.*m.*s/);
    await page.goto('/classement');
    await expect(page.locator('tr[aria-current="true"] .player-protection-shield')).toBeVisible();
    await page.goto(colonyUrl);
    const banner = page.locator('.empire-banner');
    await expect(banner.locator('.empire-points dt')).toHaveText(['Planètes', 'Recherche', 'Flotte', 'Total']);
    await expect(banner.locator('.metric-card')).toHaveCount(4);
    for (const width of [1440, 768, 390]) {
        await page.setViewportSize({ width, height: 1000 });
        expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(width);
        const bounds = await banner.boundingBox();
        for (const card of await banner.locator('.metric-card').all()) {
            const box = await card.boundingBox();
            expect(box.y + box.height).toBeLessThanOrEqual(bounds.y + bounds.height);
            expect(box.x + box.width).toBeLessThanOrEqual(bounds.x + bounds.width);
        }
        await page.screenshot({ path: `var/empire-overview-${width}.png` });
    }
});
