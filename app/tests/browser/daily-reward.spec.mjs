import { test, expect } from './support/isolated-test.mjs';
import { queryIsolatedSql, runIsolatedSql } from './support/game-fixtures.mjs';

test('Récompense quotidienne : kit T1, retrait au clavier, stockage plein et activation distincte', async ({ page }, testInfo) => {
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
    await expect(page.getByRole('status').filter({ hasText: 'Jour 1 récupéré' })).toBeVisible();
    await expect(button).toHaveCount(0);
    expect(receipts()).toBe(1);
    expect(stock('metal')).toBe(10000);
    expect(stock('nano')).toBe(5000);
    await page.getByRole('link', { name: 'Actualiser la récompense' }).click();
    await expect(page.getByText('Votre compte a déjà récupéré son lot du jour.', { exact: false })).toBeVisible();
    expect(receipts()).toBe(1);
    const giftCount = () => page.evaluate(async () => {
        // Le navigateur partage la session et résout le sous-domaine local du jeu isolé.
        const response = await fetch('/messages/non-lus');
        if (!response.ok) throw new Error('Le compteur des cadeaux est indisponible.');
        return (await response.json()).inventoryGiftCount;
    });
    expect(await giftCount()).toBe(1);
    await page.goto('/boosts');
    await expect(page.locator('.inventory-gift-notice')).toContainText('1 nouveau cadeau');
    await page.getByRole('button', { name: 'Marquer les cadeaux comme vus' }).click();
    await expect(page.locator('.inventory-gift-notice')).toHaveCount(0);
    expect(await giftCount()).toBe(0);
    const bonus = page.locator('.header-timed-boost');
    await expect(bonus).toHaveCount(0);
    await page.getByRole('button', { name: 'Activer Moteurs économes' }).click();
    await expect(page.getByRole('status').filter({ hasText: 'Bonus actif pendant cinq heures' })).toBeVisible();
    await expect(page.getByText('est actif jusqu’au', { exact: false })).toContainText('Moteurs économes');
    await expect(bonus).toContainText('Carburant civil −20 %');
    await page.goto('/planetes/' + planetId);
    for (const width of [1920, 1280, 1024, 768, 541, 390, 320]) {
        await page.setViewportSize({ width, height: 900 });
        const guide = await page.locator('.header-technology-goal').boundingBox();
        const frame = await bonus.boundingBox();
        expect(frame.x).toBeGreaterThanOrEqual(guide.x + guide.width - 1);
        expect(frame.y).toBeLessThan(guide.y + guide.height);
        expect(frame.y + frame.height).toBeGreaterThan(guide.y);
        expect(await page.locator('.topbar').evaluate(bar => bar.scrollWidth <= bar.clientWidth + 1)).toBe(true);
    }
    // À mi-parcours, l’anneau doit occuper la moitié du cadran dans les deux thèmes.
    runIsolatedSql(project, `UPDATE player_boost SET used_at=UTC_TIMESTAMP() - INTERVAL 150 MINUTE
        WHERE player_universe_id=(SELECT owner_id FROM planet WHERE id=${planetId}) AND kind='timed_boost' AND status='used'`);
    for (const [theme, width] of [['dark', 1920], ['light', 390]]) {
        runIsolatedSql(project, `UPDATE player SET preferences=JSON_SET(CASE WHEN JSON_TYPE(preferences)='OBJECT' THEN preferences ELSE JSON_OBJECT() END,'$.theme','${theme}') WHERE id=${playerId}`);
        await page.setViewportSize({ width, height: 900 });
        await page.reload();
        await expect(page.locator('body')).toHaveAttribute('data-theme', theme);
        const fraction = await bonus.locator('.boost-dial').evaluate(dial =>
            parseFloat(getComputedStyle(dial).getPropertyValue('--remaining')));
        expect(fraction).toBeCloseTo(50, 0);
        const guide = await page.locator('.header-technology-goal').boundingBox();
        const frame = await bonus.boundingBox();
        const top = Math.max(0, Math.min(guide.y, frame.y) - 4);
        await page.screenshot({ path: testInfo.outputPath('bonus-' + theme + '.png'), clip: {
            x: guide.x - 4, y: top, width: frame.x + frame.width - guide.x + 8,
            height: Math.max(guide.y + guide.height, frame.y + frame.height) - top + 4,
        } });
    }
    // Approcher l’échéance de cette seule attribution dans la base éphémère.
    runIsolatedSql(project, `UPDATE player_boost SET used_at=UTC_TIMESTAMP() - INTERVAL 5 HOUR + INTERVAL 8 SECOND
        WHERE player_universe_id=(SELECT owner_id FROM planet WHERE id=${planetId}) AND kind='timed_boost' AND status='used'`);
    await page.reload();
    const timer = bonus.getByRole('timer');
    await expect(timer).toBeVisible();
    const first = await timer.textContent();
    await expect(timer).not.toHaveText(first);
    await expect(bonus).toBeHidden({timeout:12000});
    await page.reload();
    await expect(bonus).toHaveCount(0);
});
