import { test, expect } from './support/isolated-test.mjs';
import { prepareOwnedColony, runIsolatedSql, queryIsolatedSql, planetCoordinates } from './support/game-fixtures.mjs';

async function enter(page) {
    const id = crypto.randomUUID().slice(0, 8), email = 'max-' + id + '@example.test', password = 'Browser-test-2026!';
    await page.goto('/inscription');
    await page.getByLabel('Adresse email').fill(email);
    await page.getByLabel('Nom de commandant').fill('Maximum-' + id);
    await page.getByLabel('Mot de passe (12 caractères minimum)', { exact: true }).fill(password);
    await page.getByLabel('Confirmer le mot de passe').fill(password);
    await page.getByRole('button', { name: 'Créer mon compte' }).click();
    await expect(page).toHaveURL(/connexion/);
    await page.getByLabel('Adresse email').fill(email);
    await page.getByLabel('Mot de passe', { exact: true }).fill(password);
    await page.getByRole('button', { name: /Connexion/ }).click();
    await page.locator('article.universe-card').first().getByRole('button').click();
    await expect(page).toHaveURL(/planetes\/\d+$/);
    const planet = Number(new URL(page.url()).pathname.split('/').pop());
    prepareOwnedColony(process.env.BROWSER_ROUTER, email, { stock: 150000 });
    return { email, planet, project: process.env.BROWSER_ROUTER };
}

test('Construction : Max dépasse 100, ajuste la saisie et refuse un achat lorsque le maximum devient nul', async ({ page }, info) => {
    test.setTimeout(120000);
    const { email, planet, project } = await enter(page);
    runIsolatedSql(project, `INSERT INTO planet_building (planet_id,slug,tier,level) VALUES (${planet},'shipyard','basic',1)`);
    const foreign = queryIsolatedSql(project, `SELECT id FROM planet WHERE owner_id IS NULL LIMIT 1`);
    expect(await page.evaluate(async url => (await fetch(url)).status, `/planetes/${foreign}/construction-maximum?group=ships&slug=probe&tier=basic`)).toBe(404);
    expect(await page.evaluate(async url => (await fetch(url)).status, `/planetes/${planet}/construction-maximum?group=buildings&slug=mine_metal&tier=basic`)).toBe(422);
    await page.goto(`/planetes/${planet}?section=ships`);
    const panel = page.locator('#item-probe-basic'), input = panel.locator('[name="quantity"]');
    const maximum = panel.getByRole('button', { name: /^Quantité maximale/ });
    await maximum.focus(); await page.keyboard.press('Enter');
    await expect.poll(async () => Number(await input.inputValue())).toBeGreaterThan(100);
    const initial = Number(await input.inputValue());
    await input.fill(String(initial + 1000)); await input.press('Tab');
    await expect(input).toHaveValue(String(initial));
    await expect(panel.getByRole('status')).toContainText('Quantité ajustée');
    for (const width of [320, 390, 768, 1440]) {
        await page.setViewportSize({ width, height: 900 });
        expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(width);
    }
    await page.setViewportSize({ width: 390, height: 900 });
    await panel.screenshot({ path: info.outputPath('construction-max-390.png'), animations: 'disabled' });
    runIsolatedSql(project, `UPDATE planet_resource SET current_stock=0,last_updated_at=UTC_TIMESTAMP() WHERE planet_id=${planet}`);
    await maximum.click();
    await expect(panel.getByRole('status')).toContainText('Ressources manquantes : 300 Métal, 100 Nano, 200 Électricité.');
    await expect(panel.getByRole('button', { name: 'Manque 300 Métal · 100 Nano · 200 Électricité', exact:true })).toBeDisabled();
    expect(queryIsolatedSql(project, `SELECT COUNT(*) FROM queue_item WHERE planet_id=${planet}`)).toBe('0');
    prepareOwnedColony(project, email, { stock: 150000 });
    await maximum.click();
    await expect.poll(async () => Number(await input.inputValue())).toBeGreaterThan(100);
    await input.fill('999999');
    await panel.getByRole('button', { name: /Construire/ }).click();
    await expect(page.locator('.flash.success')).toContainText('Quantité ajustée au maximum constructible');
    await expect.poll(() => Number(queryIsolatedSql(project, `SELECT COALESCE(MAX(quantity),0) FROM queue_item WHERE planet_id=${planet} AND item_slug='probe'`))).toBeGreaterThan(100);
    expect(queryIsolatedSql(project, `SELECT COUNT(*) FROM planet_resource WHERE planet_id=${planet} AND current_stock<0`)).toBe('0');
});

test('Flotte et fret : Max conserve les autres champs, réserve le carburant et reste utilisable au clavier sur mobile', async ({ page }, info) => {
    test.setTimeout(120000);
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    page.on('console', message => { if (message.type() === 'error' && message.text().includes('Error invoking')) errors.push(message.text()); });
    const { planet, project } = await enter(page);
    const target = Number(queryIsolatedSql(project, `SELECT id FROM planet WHERE solar_system_id=(SELECT solar_system_id FROM planet WHERE id=${planet}) AND owner_id IS NULL AND npc_planet=0 LIMIT 1`));
    runIsolatedSql(project, `UPDATE planet target JOIN planet origin ON origin.id=${planet} SET target.owner_id=origin.owner_id WHERE target.id=${target}`);
    runIsolatedSql(project, `INSERT INTO fleet (player_universe_id,home_planet_id,status,mission,speed_ratio,cargo,reserved_fuel,orbit_fuel,orbit_duration_seconds,travel_seconds,dispatch_token) SELECT owner_id,id,'idle','idle',1,'{}',0,0,0,0,'' FROM planet WHERE id=${planet}`);
    runIsolatedSql(project, `INSERT INTO fleet_ship (fleet_id,slug,tier,quantity) SELECT id,'fighter','basic',200 FROM fleet WHERE home_planet_id=${planet} AND status='idle'; INSERT INTO fleet_ship (fleet_id,slug,tier,quantity) SELECT id,'transport','basic',1 FROM fleet WHERE home_planet_id=${planet} AND status='idle'`);
    await page.goto('/flottes');
    const form = page.locator('.fleet-form form').first();
    const fighter = form.locator('[name="ships[fighter]"]'), transport = form.locator('[name="ships[transport]"]');
    await form.locator('[data-action="fleet-quantity#maximumShip"][data-input-id$="-fighter-basic"]').click();
    await expect(fighter).toHaveValue('200');
    await fighter.fill('999'); await expect(fighter).toHaveValue('200');
    await transport.fill('1');
    await form.getByRole('button', { name: /Choisir la destination/ }).click();
    await form.locator('[name="mission"]').selectOption('transport');
    await form.getByLabel('Destination personnalisée', { exact: true }).check();await form.locator('[name="coordinates"]').fill(planetCoordinates(project, target));
    await form.locator('[data-fleet-fields-target="cargo"] > summary').click();
    const metal = form.locator('[name="cargo[metal]"]'), hydrogen = form.locator('[name="cargo[hydrogen]"]');
    const hydrogenMax = form.locator('[data-fleet-quantity-target="cargoMax"][data-input-id$="-hydrogen"]');
    await expect(hydrogenMax).toBeEnabled();
    await metal.fill('1000');
    const [response] = await Promise.all([
        page.waitForResponse(async r => new URL(r.url()).pathname === '/flottes/estimer' && r.ok() && (await r.json()).freight.hydrogen > 0),
        hydrogenMax.click(),
    ]);
    expect(errors).toEqual([]);
    const quote = await response.json();
    await expect(metal).toHaveValue('1000');
    expect(Number(await hydrogen.inputValue())).toBeGreaterThan(0);
    expect(Object.values(quote.freight).reduce((a, b) => a + b, 0)).toBeLessThanOrEqual(quote.freight_capacity);
    expect(quote.freight.hydrogen).toBeLessThanOrEqual(quote.cargo_available.hydrogen);
    const metalMax = form.locator('[data-fleet-quantity-target="cargoMax"][data-input-id$="-metal"]');
    await metalMax.focus(); await page.keyboard.press('Enter');
    await expect(metal).toHaveValue('1000');
    for (const width of [320, 390, 768, 1440]) {
        await page.setViewportSize({ width, height: 900 });
        expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(width);
    }
    await page.setViewportSize({ width: 390, height: 900 });
    await page.screenshot({ path: info.outputPath('fret-max-390.png'), fullPage: true, animations: 'disabled' });
    await form.getByRole('button', { name: /Vérifier la mission/ }).click();
    await expect(form.getByRole('button', { name: /Envoyer la flotte/ })).toBeEnabled();
});
