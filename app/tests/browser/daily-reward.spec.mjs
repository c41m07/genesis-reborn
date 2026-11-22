import { test, expect } from './support/isolated-test.mjs';
import { queryIsolatedSql, runIsolatedSql } from './support/game-fixtures.mjs';

test('Récompense quotidienne : kit T1, manque de place, retrait clavier et un seul reçu', async ({ page }) => {
    const id = crypto.randomUUID().slice(0, 8);
    const email = 'daily-reward-' + id + '@example.test';
    const password = crypto.randomUUID() + 'Aa1!';
    // L’inscription réelle doit marquer l’éligibilité, la confirmation est la fixture commune.
    await page.goto('/inscription');
    await page.getByLabel('Adresse email').fill(email);
    await page.getByLabel('Nom de commandant').fill('Gift-' + id);
    await page.getByLabel('Mot de passe (12 caractères minimum)', { exact: true }).fill(password);
    await page.getByLabel('Confirmer le mot de passe').fill(password);
    await page.getByRole('button', { name: 'Créer mon compte' }).click();
    await page.getByLabel('Adresse email').fill(email);
    await page.getByLabel('Mot de passe', { exact: true }).fill(password);
    await page.getByRole('button', { name: /Connexion/ }).click();
    await page.locator('.universe-card').filter({ has: page.getByRole('heading', { name: /Omega/ }) }).getByRole('button').click();
    await expect(page).toHaveURL(/planetes\/\d+$/);
    const project = process.env.BROWSER_ROUTER;
    const planetId = Number(new URL(page.url()).pathname.split('/').pop());
    const playerId = Number(queryIsolatedSql(project, "SELECT id FROM player WHERE email='" + email + "'"));
    expect(planetId).toBeGreaterThan(0);
    expect(playerId).toBeGreaterThan(0);
    const stock = resource => Number(queryIsolatedSql(project, "SELECT current_stock FROM planet_resource WHERE planet_id=" + planetId + " AND resource_type='" + resource + "'"));
    const receipts = () => Number(queryIsolatedSql(project, 'SELECT COUNT(*) FROM daily_reward_claim WHERE player_id=' + playerId));
    for (const resource of ['metal', 'nano', 'hydrogen', 'antimatter']) expect(stock(resource)).toBe(5000);
    expect(stock('electricity')).toBe(0);

    await page.setViewportSize({ width: 390, height: 844 });
    await expect(page.locator('main').getByRole('link', { name: 'Récompense quotidienne', exact: true })).toHaveCount(0);
    const gift = page.locator('.topbar').getByRole('link', { name: 'Quotidiennes', exact: true });
    await expect(gift).toHaveAttribute('href', '/recompense-quotidienne?colony=' + planetId);
    // Exercer aussi la page dédiée utilisée sans JavaScript, sans réintroduire le bouton doublon.
    await page.goto(await gift.getAttribute('href'));
    await expect(page.getByRole('heading', { name: 'Jour 1 sur 7' })).toBeVisible();
    await expect(page.getByText('Univers actif :', { exact: false })).toContainText('Omega');
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1)).toBe(true);
    const button = page.getByRole('button', { name: 'Récupérer' });
    runIsolatedSql(project, "UPDATE planet_resource SET current_stock=10000 WHERE planet_id=" + planetId + " AND resource_type='metal'");
    await button.focus();
    await expect(button).toBeFocused();
    await page.keyboard.press('Enter');
    await expect(page.getByRole('alert').filter({ hasText: 'Stockage insuffisant' })).toContainText('métal');
    await expect(button).toBeVisible();
    expect(receipts()).toBe(0);
    expect(stock('nano')).toBeLessThan(5225);

    runIsolatedSql(project, "UPDATE planet_resource SET current_stock=5000 WHERE planet_id=" + planetId + " AND resource_type='metal'");
    await button.click();
    await expect(page.getByRole('status').filter({ hasText: 'Jour 1 récupéré' })).toBeVisible();
    await expect(button).toHaveCount(0);
    expect(receipts()).toBe(1);
    expect(stock('metal')).toBeGreaterThanOrEqual(5500);
    expect(stock('nano')).toBeGreaterThanOrEqual(5250);
    await page.getByRole('link', { name: 'Actualiser la récompense' }).click();
    await expect(page.getByText('Votre compte a déjà récupéré son lot du jour.', { exact: false })).toBeVisible();
    expect(receipts()).toBe(1);
});
