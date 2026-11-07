import { test, expect } from './support/isolated-test.mjs';
import { prepareOwnedColony, runIsolatedSql } from './support/game-fixtures.mjs';

test('Les 150 fiches expliquent leurs effets, bonus, accès et limites sur ordinateur et mobile', async ({ page }) => {
    test.setTimeout(240000);
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    const id = crypto.randomUUID().slice(0, 8), email = `details-${id}@example.test`, password = 'Browser-test-2026!';
    await page.goto('/inscription');
    await page.getByLabel('Adresse email').fill(email);
    await page.getByLabel('Nom de commandant').fill(`Details-${id}`);
    await page.getByLabel('Mot de passe (12 caractères minimum)', { exact: true }).fill(password);
    await page.getByLabel('Confirmer le mot de passe').fill(password);
    await page.getByRole('button', { name: 'Créer mon compte' }).click();
    await expect(page).toHaveURL(/connexion/);
    await page.getByLabel('Adresse email').fill(email);
    await page.getByLabel('Mot de passe', { exact: true }).fill(password);
    await page.getByRole('button', { name: /Connexion/ }).click();
    await page.locator('article.universe-card').first().getByRole('button').click();
    await expect(page).toHaveURL(/planetes\/\d+$/);
    const path = new URL(page.url()).pathname, planet = Number(path.split('/').pop());
    const sql = query => runIsolatedSql(process.env.BROWSER_ROUTER, query);
    prepareOwnedColony(process.env.BROWSER_ROUTER, email, { showLockedItems: true });
    sql(`INSERT INTO planet_building (planet_id,slug,tier,level) VALUES (${planet},'fortress','basic',2)`);
    sql(`INSERT INTO player_technology (player_universe_id,slug,tier,level)
        SELECT owner_id,'weapons_engineering','basic',2 FROM planet WHERE id=${planet}`);
    sql(`INSERT INTO planet_defense (planet_id,slug,tier,quantity) VALUES (${planet},'ground_turret_anti_light','basic',3),(${planet},'ground_turret_anti_light','intermediate',7)`);
    await page.setViewportSize({ width: 1440, height: 1000 });
    let inspected = 0;
    for (const [group, count] of [['buildings', 39], ['technologies', 57], ['ships', 42], ['defenses', 12]]) {
        await page.goto(`${path}?section=${group}`);
        const cards = page.locator('.building-rank');
        await expect(cards).toHaveCount(count);
        for (let index = 0; index < count; index++) {
            const card = cards.nth(index), trigger = card.locator('.catalog-info-trigger'), dialog = card.locator('dialog');
            await page.locator('#' + await card.getAttribute('aria-labelledby')).click();
            await trigger.evaluate(element => element.scrollIntoView({ block: 'center', behavior: 'instant' }));
            await trigger.click();
            await expect(dialog).toBeVisible();
            await expect(dialog.locator('.catalog-info-intro')).not.toBeEmpty();
            await expect(dialog.locator('.catalog-info-scope')).toContainText('Portée');
            await expect(dialog.getByRole('heading', { name: 'Déblocage initial de ce rang', exact: true })).toBeVisible();
            expect(await dialog.locator('.catalog-info-section').count()).toBeGreaterThan(0);
            await expect(dialog.locator('.catalog-info-content')).not.toContainText('[object Object]');
            if (group === 'defenses' && index < 3) {
                await expect(dialog.locator('.catalog-info-stock')).toContainText(String([3, 7, 0][index]));
            }
            await page.keyboard.press('Escape');
            await expect(dialog).not.toBeVisible();
            await expect(trigger).toBeFocused();
            inspected++;
        }
    }
    expect(inspected).toBe(150);

    async function open(group, slug, tier = 'basic') {
        await page.goto(`${path}?section=${group}`);
        const card = page.locator(`#item-${slug}-${tier}`);
        await page.locator(`#building-tab-${slug}-${tier}`).click();
        await card.locator('.catalog-info-trigger').click();
        return card.locator('dialog');
    }
    let dialog = await open('technologies', 'weapons_engineering');
    await expect(dialog).toContainText('armes principales et secondaires');
    await page.keyboard.press('Escape');
    dialog = await open('technologies', 'colonial_expansion');
    await expect(dialog).toContainText('Laboratoire colonial · Basique · niveau 1 requis');
    await expect(dialog).toContainText('Limite après ce niveau');
    await page.keyboard.press('Escape');
    dialog = await open('ships', 'battleship');
    const secondary = dialog.getByRole('row').filter({ has: page.getByRole('rowheader', { name: 'Attaque secondaire par tir', exact: true }) });
    expect(await secondary.locator('td').nth(1).innerText()).not.toBe(await secondary.locator('td').nth(0).innerText());
    await page.keyboard.press('Escape');
    dialog = await open('ships', 'corvette', 'intermediate');
    await expect(dialog.getByRole('heading', { name: 'Contres et faiblesses', exact: true })).toBeVisible();
    const counters = dialog.locator('.catalog-info-section').filter({ has: page.getByRole('heading', { name: 'Contres et faiblesses', exact: true }) });
    await expect(counters).toContainText('Capital');
    await expect(counters.locator('dl dt')).toHaveText(['Chasseur', 'Capital']);
    await expect(counters).toContainText('Chasseur standard, le Chasseur furtif et l’Intercepteur');
    await expect(counters).toContainText('Le Chasseur lourd reste une famille distincte');
    await expect(counters).not.toContainText('Dreadnought');
    await expect(counters).not.toContainText('approche silencieuse');
    await expect(counters).toContainText('dégâts et chance de toucher');
    await page.keyboard.press('Escape');
    dialog = await open('ships', 'probe');
    await expect(dialog).toContainText('Toutes les sondes envoyées en espionnage sont consommées');
    await expect(dialog).toContainText('Aucun');
    await page.keyboard.press('Escape');

    dialog = await open('ships', 'probe', 'superior');
    const access = dialog.getByRole('region', { name: 'Conditions d’accès', exact: true });
    await expect(access).toContainText('Cartographie des opérations · Intermédiaire · niveau 5 requis');
    await expect(access).toContainText('Docks orbitaux modulaires · Intermédiaire · niveau 10 requis');
    await expect(access.locator('.catalog-info-unreachable')).toHaveCount(0);
    await page.keyboard.press('Escape');

    for (const width of [768, 390]) {
        await page.setViewportSize({ width, height: 900 });
        for (const [group, slug] of [['buildings', 'mine_antimatter'], ['technologies', 'signature_masking'], ['ships', 'battleship'], ['defenses', 'ground_turret_anti_light']]) {
            dialog = await open(group, slug);
            const bounds = await dialog.boundingBox();
            expect(bounds.x).toBeGreaterThanOrEqual(0);
            expect(bounds.x + bounds.width).toBeLessThanOrEqual(width);
            expect(await dialog.locator('.catalog-info-content').evaluate(element => element.scrollWidth <= element.clientWidth)).toBe(true);
            await dialog.getByRole('button', { name: 'Fermer la fiche', exact: true }).click();
            await expect(dialog).not.toBeVisible();
        }
    }
    sql(`UPDATE player_technology tech JOIN planet ON planet.owner_id=tech.player_universe_id SET tech.level=25 WHERE planet.id=${planet} AND tech.slug='weapons_engineering' AND tech.tier='basic'`);
    dialog = await open('technologies', 'weapons_engineering');
    await expect(dialog).toContainText('Niveau maximum atteint');
    await expect(dialog).not.toContainText('Durée :');
    await expect(dialog.getByRole('heading', { name: /Gain du niveau/ })).toHaveCount(0);
    await page.keyboard.press('Escape');
    dialog = await open('technologies', 'signature_masking');
    await page.screenshot({ path: 'var/catalogue-details-mobile.png' });
    await page.keyboard.press('Escape');
    sql(`UPDATE player SET preferences=JSON_SET(preferences,'$.theme','light') WHERE email='${email}'`);
    await page.setViewportSize({ width: 1440, height: 1000 });
    dialog = await open('ships', 'battleship');
    await expect(page.locator('body')).toHaveAttribute('data-theme', 'light');
    await expect(dialog).toHaveCSS('background-color', 'rgb(255, 255, 255)');
    await page.screenshot({ path: 'var/catalogue-details-desktop.png' });
    expect(errors).toEqual([]);
});
