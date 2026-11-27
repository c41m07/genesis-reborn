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

test('Les quantités du Chantier et des Défenses restent lisibles au clavier', async ({ page }, info) => {
    const { planet, project } = await enter(page);
    runIsolatedSql(project, `INSERT INTO planet_building (planet_id,slug,tier,level) VALUES (${planet},'shipyard','basic',1),(${planet},'fortress','basic',1)`);
    for (const [section, slug] of [['ships', 'probe'], ['defenses', 'ground_turret_anti_light']]) {
        await page.goto(`/planetes/${planet}?section=${section}`);
        const panel = page.locator(`#item-${slug}-basic`);
        const input = panel.getByRole('spinbutton', { name: 'Quantité' });
        // Reproduire aussi le devis recalculé par Max avant de vérifier les dimensions des tuiles.
        const preview = page.waitForResponse(response => response.url().includes('/construction-maximum'));
        await panel.getByRole('button', { name: /^Max : quantité maximale/ }).click();
        await preview;
        await expect.poll(async () => Number(await input.inputValue())).toBeGreaterThan(1);
        for (const width of [1920, 1440, 390, 320]) {
            await page.setViewportSize({ width, height: 900 });
            for (const value of ['100', '291', '1234567890']) {
                await input.fill(value);
                await expect(input).toBeFocused();
                // La valeur DOM seule reste correcte même si le navigateur masque les derniers chiffres.
                const fits = await input.evaluate(field => {
                    const style = getComputedStyle(field);
                    const context = document.createElement('canvas').getContext('2d');
                    context.font = style.fontSize + ' ' + style.fontFamily;
                    const room = field.clientWidth - parseFloat(style.paddingLeft) - parseFloat(style.paddingRight);
                    return style.appearance === 'textfield' && context.measureText(field.value).width <= room + 1;
                });
                expect(fits, section + ' à ' + width + ' px, quantité ' + value).toBe(true);
                const overflowing = await panel.locator('[data-cost-amount]:visible').evaluateAll(amounts =>
                    amounts.filter(amount => {
                        // Mesurer les lignes de texte elles-mêmes : un parent peut masquer le débordement.
                        const cell = amount.closest('[data-resource]').getBoundingClientRect();
                        const range = document.createRange();
                        range.selectNodeContents(amount);
                        return [...range.getClientRects()].some(line => line.right > cell.right + 1 || line.left < cell.left - 1);
                    }).map(amount => amount.textContent));
                expect(overflowing, section + ' à ' + width + ' px').toEqual([]);
            }
            expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(width);
        }
        await input.fill('1');
        await input.press('ArrowUp');
        await expect(input).toHaveValue('2');
        await input.fill('291');
        await page.setViewportSize({ width: 1920, height: 900 });
        await input.evaluate(field => field.scrollIntoView({block: 'center', behavior: 'instant'}));
        await panel.locator('.quantity-input').screenshot({ path: info.outputPath(section + '-quantite.png') });
    }
});

test('Construction : Max dépasse 100, ajuste la saisie et refuse un achat lorsque le maximum devient nul', async ({ page, browser }, info) => {
    test.setTimeout(120000);
    const { email, planet, project } = await enter(page);
    runIsolatedSql(project, `INSERT INTO planet_building (planet_id,slug,tier,level) VALUES (${planet},'shipyard','basic',1)`);
    const foreign = queryIsolatedSql(project, `SELECT id FROM planet WHERE owner_id IS NULL LIMIT 1`);
    expect(await page.evaluate(async url => (await fetch(url)).status, `/planetes/${foreign}/construction-maximum?group=ships&slug=probe&tier=basic`)).toBe(404);
    expect(await page.evaluate(async url => (await fetch(url)).status, `/planetes/${planet}/construction-maximum?group=buildings&slug=mine_metal&tier=basic`)).toBe(422);
    await page.goto(`/planetes/${planet}?section=ships`);
    const panel = page.locator('#item-probe-basic'), input = panel.locator('[name="quantity"]');
    const maximum = panel.getByRole('button', { name: /^Max : quantité maximale de/ });
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
    await expect(panel.getByRole('status')).toContainText('Ressources manquantes : 240 Métal, 130 Nano, 200 Électricité.');
    await expect(panel.getByRole('button', { name: /^Il manque :/ })).toBeDisabled();
    await expect(maximum).toBeDisabled();
    await expect(panel.locator('.catalog-missing-resource')).toHaveText(['Métal : 240', 'Nano : 130', 'Électricité : 200']);
    const wait = panel.locator('.catalog-wait');
    const waitText = panel.locator('.catalog-wait > span');
    await expect(waitText).toContainText(/Délai (estimé|indisponible)/);
    await expect(panel.locator('.catalog-payment')).toContainText('Il manque :');
    await expect(panel.locator('.catalog-payment')).toContainText('aucun gain net');
    await page.reload();
    await expect(waitText).toContainText(/Délai (estimé|indisponible)/);
    await page.setViewportSize({ width: 1440, height: 900 });
    await expect(wait).toContainText('Au rythme actuel, sans garantie de disponibilité.');
    await expect(wait.locator('button, [popover]')).toHaveCount(0);
    const itemInfo = panel.getByRole('button', { name: 'Informations sur Sonde de reconnaissance', exact: true });
    await itemInfo.press('Enter');
    await expect(panel.getByRole('dialog')).toBeVisible();
    await page.keyboard.press('Escape');
    await expect(panel.getByRole('dialog')).toBeHidden();
    await expect(itemInfo).toBeFocused();
    await input.fill('2');
    await expect(wait).toBeHidden();
    await input.press('Tab');
    await expect(wait).toBeVisible();

    const touch = await browser.newContext({ hasTouch: true, isMobile: true, viewport: { width: 390, height: 900 }, storageState: await page.context().storageState() });
    try {
        const touchPage = await touch.newPage();
        await touchPage.goto('/planetes/' + planet + '?section=ships');
        const touchPanel = touchPage.locator('#item-probe-basic');
        await expect(touchPanel.locator('.catalog-wait')).toContainText('Au rythme actuel, sans garantie de disponibilité.');
        await expect(touchPanel.locator('.catalog-wait button')).toHaveCount(0);
        await touchPanel.getByRole('button', { name: 'Informations sur Sonde de reconnaissance', exact: true }).tap();
        await expect(touchPanel.getByRole('dialog')).toBeVisible();
    } finally {
        await touch.close();
    }
    expect(queryIsolatedSql(project, `SELECT COUNT(*) FROM queue_item WHERE planet_id=${planet}`)).toBe('0');
    prepareOwnedColony(project, email, { stock: 150000 });
    await page.evaluate(() => document.activeElement?.blur());
    const stockRefresh = page.waitForResponse(response => response.request().headers()['x-genesis-live'] === '1');
    await page.evaluate(() => window.dispatchEvent(new Event('genesis:refresh')));
    await stockRefresh;
    await expect(maximum).toBeEnabled();
    await maximum.click();
    await expect.poll(async () => Number(await input.inputValue())).toBeGreaterThan(100);
    // Le navigateur bloque une valeur supérieure à max avant l’envoi. Le parcours
    // natif sans JavaScript permet de vérifier aussi le plafonnement côté serveur.
    const nativeContext = await browser.newContext({ javaScriptEnabled: false, storageState: await page.context().storageState(), viewport: { width: 390, height: 900 } });
    const nativePage = await nativeContext.newPage();
    await nativePage.goto(`/planetes/${planet}?section=ships`);
    const nativePanel = nativePage.locator('#item-probe-basic');
    await nativePanel.getByLabel('Quantité', { exact: true }).fill('999999');
    await nativePanel.getByRole('button', { name: /Construire/ }).click();
    await expect(nativePage.locator('.flash.success')).toContainText('Quantité ajustée au maximum constructible');
    await expect.poll(() => Number(queryIsolatedSql(project, `SELECT COALESCE(MAX(quantity),0) FROM queue_item WHERE planet_id=${planet} AND item_slug='probe'`))).toBeGreaterThan(100);
    expect(queryIsolatedSql(project, `SELECT COUNT(*) FROM planet_resource WHERE planet_id=${planet} AND current_stock<0`)).toBe('0');
    await nativeContext.close();
});

test('Il manque : décompte estimatif sans requête, expiration prudente et nouvelle colonie', async ({ page, browser }) => {
    test.setTimeout(120000);
    const { planet, project } = await enter(page);
    const url = id => `/planetes/${id}?section=buildings`;
    await page.goto(url(planet));
    const card = page.locator('#item-mine_metal-basic');
    const priced = async slug => {
        const title = await card.locator(`.building-costs > [data-resource="${slug}"]`).getAttribute('title');
        return Number(title.split(' · ')[0].replace(/\D/g, ''));
    };
    const metal = await priced('metal');
    const nano = await priced('nano');
    expect(metal).toBeGreaterThan(1);
    expect(nano).toBeGreaterThan(1);
    const second = Number(queryIsolatedSql(project, `SELECT id FROM planet WHERE owner_id IS NULL AND npc_planet=0 AND solar_system_id=(SELECT solar_system_id FROM planet WHERE id=${planet}) LIMIT 1`));
    expect(second).toBeGreaterThan(0);
    runIsolatedSql(project, `
        UPDATE planet target JOIN planet source ON source.id=${planet} SET target.owner_id=source.owner_id WHERE target.id=${second};
        INSERT INTO planet_resource (planet_id,resource_type,current_stock,production_rate,last_updated_at)
            SELECT ${second},resource_type,IF(resource_type='nano',${nano - 2},150000),0,UTC_TIMESTAMP()
            FROM planet_resource WHERE planet_id=${planet}
            ON DUPLICATE KEY UPDATE current_stock=VALUES(current_stock),last_updated_at=VALUES(last_updated_at);
        UPDATE planet_resource SET current_stock=${metal - 1},last_updated_at=UTC_TIMESTAMP()
            WHERE planet_id=${planet} AND resource_type='metal';
    `);
    await page.clock.install({ time: new Date('2031-12-17T13:00:00Z') });
    await page.goto(url(planet));
    const timer = card.getByRole('timer');
    await expect(timer).toContainText(/Délai estimé : .+ \(Métal\)/);
    await expect(timer).toHaveAttribute('aria-live', 'off');
    await expect(card.locator('.catalog-wait')).toContainText('Au rythme actuel, sans garantie de disponibilité.');
    await expect(card.locator('.catalog-wait button')).toHaveCount(0);
    const initial = await timer.textContent();
    const estimateMs = await timer.evaluate(element => (Number(element.dataset.deadline) - Number(element.dataset.observedAt)) * 1000);
    expect(estimateMs).toBeGreaterThan(2000);
    const estimateRequests = [];
    page.on('request', request => {
        if (request.url().includes('/construction-maximum') || request.headers()['x-genesis-live'] === '1') {
            estimateRequests.push(request.url());
        }
    });
    await page.clock.fastForward(2000);
    await expect(timer).not.toHaveText(initial);
    await page.clock.fastForward(estimateMs);
    await expect(timer).toHaveText('Estimation écoulée. Actualisez pour vérifier les ressources.');
    await expect(card.getByRole('button', { name: /^Il manque :/ })).toBeDisabled();
    expect(estimateRequests).toEqual([]);

    await page.goto(url(second));
    await expect(timer).toContainText(/Délai estimé : .+ \(Nano\)/);
    const native = await browser.newContext({ javaScriptEnabled: false, storageState: await page.context().storageState() });
    try {
        const nativePage = await native.newPage();
        await nativePage.goto(url(second));
        const nativeCard = nativePage.locator('#item-mine_metal-basic .catalog-payment');
        await expect(nativeCard.getByRole('timer')).toContainText(/Délai estimé : .+ \(Nano\)/);
        await expect(nativeCard).toContainText('Il manque :');
        await expect(nativeCard).toContainText('Au rythme actuel, sans garantie de disponibilité.');
        await expect(nativeCard.locator('.catalog-wait button')).toHaveCount(0);
        await expect(nativeCard.getByRole('button', { name: /^Il manque :/ })).toBeDisabled();
    } finally {
        await native.close();
    }
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
    await page.goto('/flottes/preparer');
    const form = page.locator('.fleet-form form').first();
    const fighter = form.locator('[name="ships[fighter]"]'), transport = form.locator('[name="ships[transport]"]');
    await form.locator('[data-action="fleet-quantity#maximumShip"][data-input-id$="-fighter-basic"]').click();
    await expect(fighter).toHaveValue('200');
    await fighter.fill('999'); await expect(fighter).toHaveValue('200');
    await transport.fill('1');
    await form.getByRole('button', { name: /Choisir la destination/ }).click();
    await form.locator('[name="mission"]').selectOption('transport');
    await form.getByLabel('Destination personnalisée', { exact: true }).check();await form.locator('[name="coordinates"]').fill(planetCoordinates(project, target));
    const cargo = form.locator('[data-fleet-fields-target="cargo"]');
    if (!(await cargo.evaluate(element => element.open))) await cargo.locator('summary').click();
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
