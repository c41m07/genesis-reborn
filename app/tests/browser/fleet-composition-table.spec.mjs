import { test, expect } from './support/isolated-test.mjs';
import { planetCoordinates, queryIsolatedSql, runIsolatedSql } from './support/game-fixtures.mjs';
import AxeBuilder from '@axe-core/playwright';

async function seed(page) {
    const suffix = crypto.randomUUID().slice(0, 8);
    const email = 'ranks-' + suffix + '@example.test';
    const password = crypto.randomUUID() + 'aA1!';
    await page.goto('/inscription');
    await page.getByLabel('Adresse email').fill(email);
    await page.getByLabel('Nom de commandant').fill('Rangs-' + suffix);
    await page.getByLabel('Mot de passe (12 caractères minimum)', { exact: true }).fill(password);
    await page.getByLabel('Confirmer le mot de passe').fill(password);
    await page.getByRole('button', { name: 'Créer mon compte' }).click();
    await page.getByLabel('Adresse email').fill(email);
    await page.getByLabel('Mot de passe', { exact: true }).fill(password);
    await page.getByRole('button', { name: /Connexion/ }).click();
    await page.locator('.universe-card').filter({ has: page.getByRole('heading', { name: /Omega/ }) }).getByRole('button').click();
    await expect(page).toHaveURL(/planetes\/\d+$/);

    const project = process.env.BROWSER_ROUTER;
    const origin = Number(new URL(page.url()).pathname.split('/').at(-1));
    const owner = Number(queryIsolatedSql(project, 'SELECT owner_id FROM planet WHERE id=' + origin));
    const target = Number(queryIsolatedSql(project, 'SELECT id FROM planet WHERE solar_system_id=(SELECT solar_system_id FROM planet WHERE id=' + origin + ') AND owner_id IS NULL AND npc_planet=0 LIMIT 1'));
    expect(target).toBeGreaterThan(0);
    runIsolatedSql(project, "UPDATE universe JOIN player_universe pu ON pu.universe_id=universe.id SET universe.game_settings=JSON_SET(CASE WHEN JSON_TYPE(universe.game_settings)='OBJECT' THEN universe.game_settings ELSE JSON_OBJECT() END,'$.limits',JSON_OBJECT('max_fleet_size',7)) WHERE pu.id=" + owner);
    runIsolatedSql(project, 'UPDATE planet SET owner_id=' + owner + ' WHERE id=' + target);
    runIsolatedSql(project, "INSERT INTO fleet (player_universe_id,home_planet_id,status,mission,speed_ratio,cargo,reserved_fuel,orbit_fuel,orbit_duration_seconds,travel_seconds,dispatch_token) VALUES (" + owner + ',' + origin + ",'idle','idle',1,'{}',0,0,0,0,'')");
    const fleet = Number(queryIsolatedSql(project, "SELECT id FROM fleet WHERE home_planet_id=" + origin + " AND status='idle'"));
    runIsolatedSql(project, "INSERT INTO fleet_ship(fleet_id,slug,tier,quantity) VALUES (" + fleet + ",'fighter','basic',12),(" + fleet + ",'fighter','intermediate',6),(" + fleet + ",'fighter','superior',4),(" + fleet + ",'probe','basic',3)");
    return { coordinates: planetCoordinates(project, target), fleet, owner, project };
}

function ship(table, tier) {
    return table.locator('[name="ships[fighter][' + tier + ']"]');
}

test('Flottes : les trois rangs restent alignés et arrivent ensemble au devis, avec et sans JavaScript', async ({ page, browser, baseURL }) => {
    test.setTimeout(120000);
    const { coordinates, fleet, owner, project } = await seed(page);
    const prepare = '/flottes/preparer?mission=station';
    await page.goto(prepare);
    const form = page.locator('.fleet-form form');
    const table = form.getByRole('table', { name: 'Composez votre flotte', exact: true });
    await expect(table.getByRole('columnheader')).toHaveText(['Élément', 'Basique', 'Intermédiaire', 'Supérieur']);
    await expect(table.getByRole('rowheader')).toHaveCount(2);
    await expect(table.getByRole('row', { name: /Chasseur de patrouille/ }).locator('[data-fleet-stock-value]')).toHaveText(['(12)', '(6)', '(4)']);
    const absent = table.getByRole('row', { name: /Sonde/ }).locator('td').nth(1);
    await expect(absent.locator('[data-fleet-stock-value]')).toHaveText('(0)');
    await expect(absent.getByRole('spinbutton', { name: /Intermédiaire/ })).toBeDisabled();
    await expect(absent.getByRole('button', { name: /Intermédiaire/ })).toBeDisabled();
    for (const width of [1440, 390, 360]) {
        await page.setViewportSize({ width, height: 900 });
        const sizes = await table.locator('.fleet-composition-control').evaluateAll(controls => controls.map(control => {
            const field = control.querySelector('input').getBoundingClientRect();
            const arrow = control.querySelector('button').getBoundingClientRect();
            return {
                fits: Math.abs(field.top - arrow.top) < 0.5 && Math.abs(field.bottom - arrow.bottom) < 0.5,
                hitArea: arrow.height >= 44 && arrow.width >= 24,
            };
        }));
        expect(sizes).toEqual(Array.from({ length: 6 }, () => ({ fits: true, hitArea: true })));
        expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(width);
    }
    const firstSteps = page.locator('details[data-controller~="first-steps-guide"][open] summary');
    if (await firstSteps.count()) await firstSteps.click();
    const basic = ship(table, 'basic');
    await basic.fill('2');
    await ship(table, 'intermediate').fill('2');
    const maximum = table.getByRole('button', { name: /maximum de Chasseur d’assaut orbital · Supérieur/ });
    await ship(table, 'superior').focus();
    await page.keyboard.press('Tab');
    await expect(maximum).toBeFocused();
    expect(await maximum.evaluate(el => el.matches(':focus-visible') && getComputedStyle(el).outlineStyle !== 'none')).toBe(true);
    await page.keyboard.press('Enter');
    await expect(ship(table, 'superior')).toHaveValue('3');
    await expect(basic).toHaveValue('2');
    await expect(ship(table, 'intermediate')).toHaveValue('2');
    expect((await new AxeBuilder({ page }).include('.fleet-composition-table').analyze()).violations).toEqual([]);

    await form.getByRole('button', { name: /Choisir la destination/ }).click();
    const destination = form.getByRole('heading', { name: 'Choisissez la destination' });
    await expect(destination).toBeFocused();
    await form.getByLabel('Destination personnalisée', { exact: true }).check();
    const quoteFromJs = page.waitForResponse(async response => {
        if (new URL(response.url()).pathname !== '/flottes/estimer' || !response.ok()) return false;
        const quote = await response.json();
        return quote.composition.fighter?.superior === 3;
    });
    await form.locator('[name="coordinates"]').fill(coordinates);
    const quoted = await (await quoteFromJs).json();
    expect(quoted.composition).toEqual({ fighter: { basic: 2, intermediate: 2, superior: 3 } });
    expect(quoted.ship_adjusted).toBe(false);
    await form.getByRole('button', { name: /Vérifier la mission/ }).click();
    await expect(form.getByRole('heading', { name: 'Confirmez votre mission' })).toBeFocused();
    await expect(form.locator('[data-fleet-wizard-target="summary"]')).toContainText('2 × Chasseur de patrouille, 2 × Chasseur multirôle, 3 × Chasseur d’assaut orbital');

    const native = await browser.newContext({ baseURL, javaScriptEnabled: false, storageState: await page.context().storageState() });
    try {
        const plain = await native.newPage();
        async function nativeQuote(superior) {
            await plain.goto(prepare);
            const nativeForm = plain.locator('.fleet-form form');
            const nativeTable = nativeForm.getByRole('table', { name: 'Composez votre flotte', exact: true });
            for (const [tier, quantity] of [['basic', '2'], ['intermediate', '2'], ['superior', superior]]) {
                await ship(nativeTable, tier).fill(quantity);
            }
            await plain.getByLabel('Destination personnalisée', { exact: true }).check();
            await plain.locator('[name="coordinates"]').fill(coordinates);
            await expect(nativeForm).toHaveAttribute('action', '/flottes/envoyer');
            // Garder l'encodage et le bouton HTML natifs, mais soumettre uniquement à l'estimation.
            // L'action mutatrice n'est jamais appelée par cette recette.
            await nativeForm.evaluate(element => element.setAttribute('action', '/flottes/estimer'));
            const [response] = await Promise.all([
                plain.waitForResponse(result => new URL(result.url()).pathname === '/flottes/estimer', { timeout: 12000 }),
                nativeForm.getByRole('button', { name: /Envoyer la flotte/ }).click(),
            ]);
            expect(response.ok()).toBe(true);
            const values = new URLSearchParams(response.request().postData());
            expect([...values.keys()].filter(key => key.startsWith('ships[')).sort()).toEqual([
                'ships[fighter][basic]', 'ships[fighter][intermediate]', 'ships[fighter][superior]', 'ships[probe]',
            ]);
            return response.json();
        }
        const ranks = { fighter: { basic: 2, intermediate: 2, superior: 3 } };
        expect(await nativeQuote('3')).toMatchObject({ composition: ranks, ship_adjusted: false });
        expect(await nativeQuote('4')).toMatchObject({ composition: ranks, ship_adjusted: true });
    } finally {
        await native.close();
    }

    runIsolatedSql(project, "UPDATE fleet_ship SET quantity=0 WHERE fleet_id=" + fleet + " AND slug='fighter' AND tier='superior'");
    runIsolatedSql(project, "INSERT INTO fleet_ship(fleet_id,slug,tier,quantity) VALUES (" + fleet + ",'probe','intermediate',2)");
    const quoteAfterLoss = page.waitForResponse(async response => {
        if (new URL(response.url()).pathname !== '/flottes/estimer' || !response.ok()) return false;
        const quote = await response.json();
        return quote.ship_available.fighter.superior === 0 && quote.ship_available.probe.intermediate === 2;
    });
    await form.getByRole('button', { name: 'Estimer le trajet', exact: true }).click();
    await quoteAfterLoss;
    await form.getByRole('button', { name: 'Modifier la destination', exact: true }).click();
    await form.getByRole('button', { name: 'Retour à la composition', exact: true }).click();
    await expect(ship(table, 'superior')).toBeDisabled();
    await expect(maximum).toBeDisabled();
    await expect(table.getByRole('row', { name: /Chasseur de patrouille/ }).locator('[data-fleet-stock-value]').last()).toHaveText('(0)');
    await expect(basic).toHaveValue('2');
    await expect(ship(table, 'intermediate')).toHaveValue('2');
    await expect(absent.locator('[data-fleet-stock-value]')).toHaveText('(2)');
    await expect(absent.getByRole('spinbutton')).toBeEnabled();
    await expect(absent.getByRole('button')).toBeEnabled();
    await expect(table.locator('[name="ships[probe][basic]"]')).toBeEnabled();
    await absent.getByRole('button').click();
    await expect(absent.getByRole('spinbutton')).toHaveValue('2');
    expect(Number(queryIsolatedSql(project, 'SELECT COUNT(*) FROM fleet WHERE player_universe_id=' + owner + " AND status<>'idle'"))).toBe(0);
});
