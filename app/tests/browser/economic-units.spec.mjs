import { test, expect } from './support/isolated-test.mjs';
import AxeBuilder from '@axe-core/playwright';
import { prepareOwnedColony, revealVirginPlanet, runIsolatedSql, queryIsolatedSql, accelerateTravelingMission } from './support/game-fixtures.mjs';

async function prepare(page) {
    const suffix = crypto.randomUUID().slice(0, 8), email = `civil-${suffix}@example.test`, password = crypto.randomUUID() + 'aA1!';
    await page.goto('/inscription');
    await page.getByLabel('Adresse email').fill(email);
    await page.getByLabel('Nom de commandant').fill('Civil-' + suffix);
    await page.getByLabel('Mot de passe (12 caractères minimum)', { exact: true }).fill(password);
    await page.getByLabel('Confirmer le mot de passe').fill(password);
    await page.getByRole('button', { name: 'Créer mon compte' }).click();
    await expect(page).toHaveURL(/connexion/);
    await page.getByLabel('Adresse email').fill(email);
    await page.getByLabel('Mot de passe', { exact: true }).fill(password);
    await page.getByRole('button', { name: /Connexion/ }).click();
    await page.locator('article.universe-card').first().getByRole('button').click();
    await expect(page).toHaveURL(/planetes\/\d+$/);
    const project = process.env.BROWSER_ROUTER, planet = Number(new URL(page.url()).pathname.split('/').pop());
    prepareOwnedColony(project, email, { showLockedItems: true });
    const member = Number(queryIsolatedSql(project, `SELECT owner_id FROM planet WHERE id=${planet}`));
    for (const [slug, level] of [['shipyard', 2], ['mine_hydrogen', 5], ['power_plant', 5], ['lab', 2]]) {
        runIsolatedSql(project, `INSERT INTO planet_building(planet_id,slug,tier,level) VALUES(${planet},'${slug}','basic',${level}) ON DUPLICATE KEY UPDATE level=VALUES(level)`);
    }
    for (const [slug, level] of [['engineering_foundations', 2], ['vector_propulsion', 1]]) {
        runIsolatedSql(project, `INSERT INTO player_technology(player_universe_id,slug,tier,level) VALUES(${member},'${slug}','basic',${level}) ON DUPLICATE KEY UPDATE level=VALUES(level)`);
    }
    runIsolatedSql(project, `INSERT INTO fleet(player_universe_id,home_planet_id,status,mission,speed_ratio,cargo,reserved_fuel,orbit_fuel,orbit_duration_seconds,travel_seconds,dispatch_token)
        SELECT ${member},${planet},'idle','idle',1,'{}',0,0,0,0,'' WHERE NOT EXISTS(SELECT id FROM fleet WHERE home_planet_id=${planet} AND player_universe_id=${member} AND status='idle')`);
    const fleet = Number(queryIsolatedSql(project, `SELECT MIN(id) FROM fleet WHERE home_planet_id=${planet} AND player_universe_id=${member} AND status='idle'`));
    runIsolatedSql(project, `INSERT INTO fleet_ship(fleet_id,slug,tier,quantity) VALUES(${fleet},'solar_satellite','basic',1),(${fleet},'hydrogen_collector','basic',7)`);
    return { project, planet, member, email, fleet };
}

test('Unités civiles : places actives et réservées, conservation, images et accès clavier', async ({ page }, testInfo) => {
    test.setTimeout(180000);
    const data = await prepare(page), path = `/planetes/${data.planet}?section=ships`;
    const position = Number(queryIsolatedSql(data.project, `SELECT position FROM planet WHERE id=${data.planet}`));
    runIsolatedSql(data.project, `UPDATE universe SET game_settings=JSON_SET(CASE WHEN JSON_TYPE(game_settings)='OBJECT' THEN game_settings ELSE JSON_OBJECT() END,'$.multipliers',JSON_OBJECT('resource_production',2)) WHERE id=(SELECT universe_id FROM player_universe WHERE id=${data.member})`);
    const localFactors = { electricity: 1.2 - 0.4 * (position - 1) / 8, hydrogen: 0.8 + 0.4 * (position - 1) / 8 };
    const numericValue = text => Number.parseFloat(text.replace(/\s/g, '').replace(',', '.'));
    // Acquérir l'accès initial avant de réduire la capacité locale, comme après une perte d'infrastructure.
    await page.goto(path);
    runIsolatedSql(data.project, `UPDATE planet_building SET level=1 WHERE planet_id=${data.planet} AND slug='power_plant' AND tier='basic'`);
    runIsolatedSql(data.project, `INSERT INTO queue_item(planet_id,player_universe_id,queue_type,item_slug,tier,item_level,quantity,duration_seconds,paid_costs,queued_at,started_at,dispatch_token)
        VALUES(${data.planet},${data.member},'shipyard','solar_satellite','basic',NULL,1,600,'{"metal":480,"nano":160,"antimatter":0,"hydrogen":0,"electricity":1000}',UTC_TIMESTAMP(),UTC_TIMESTAMP(),'civil-ui-hold')`);
    runIsolatedSql(data.project, `UPDATE planet SET shipyard_queue_started_at=UTC_TIMESTAMP() WHERE id=${data.planet}`);
    await page.goto(path);
    const satellite = page.locator('#item-solar_satellite-basic'), collector = page.locator('#item-hydrogen_collector-basic');
    await page.locator('#building-tab-solar_satellite-basic').click();
    await expect(satellite.locator('dl[aria-label="Déploiement Basique"]')).toContainText('1 / 2 places');
    await expect(satellite.getByRole('button', { name: 'Aucune place disponible', exact: true })).toBeDisabled();
    const trigger = satellite.locator('.catalog-info-trigger');
    await trigger.focus();await page.keyboard.press('Enter');
    await expect(satellite.getByRole('heading', { name: 'Installation fixe', exact: true })).toBeVisible();
    await expect(satellite.locator('dialog')).toContainText('Places réservées');
    await page.keyboard.press('Escape');await expect(trigger).toBeFocused();
    await page.locator('#building-tab-hydrogen_collector-basic').click();
    await expect(collector.locator('dl[aria-label="Déploiement Basique"]')).toContainText('5 / 5 places');
    await expect(collector).toContainText('Unités conservées, sans production');
    const families = [
        ['hydrogen_collector', ['Récolteur atmosphérique', 'Collecteur orbital', 'Raffineur d’hydrogène']],
        ['solar_satellite', ['Satellite héliocapteur', 'Plateforme solaire', 'Couronne héliosolaire']],
    ];
    for (const [slug, names] of families) {
        for (const [index, tier] of ['basic', 'intermediate', 'superior'].entries()) {
            await page.locator(`#building-tab-${slug}-${tier}`).click();
            const panel = page.locator(`#item-${slug}-${tier}`);
            await expect(panel.locator('.catalog-card-heading > h3')).toHaveText(names[index]);
            const artwork = panel.locator('.catalog-card-art img');
            await artwork.scrollIntoViewIfNeeded();
            await expect(artwork).toHaveAttribute('src', `/images/catalog/ships/${slug}__${tier}.webp`);
            await expect(artwork).toHaveAttribute('alt', names[index]);
            await expect.poll(() => artwork.evaluate(image => image.complete && image.naturalWidth > 0 && image.naturalWidth * 3 === image.naturalHeight * 4)).toBeTruthy();
            const resource = slug === 'solar_satellite' ? 'electricity' : 'hydrogen';
            const reference = (resource === 'electricity' ? [450, 675, 1012.5] : [900, 1350, 2025])[index];
            const expectedProduction = reference * localFactors[resource] * 2;
            const benefits = panel.locator('.card-benefits');
            if (!await benefits.evaluate(element => element.open)) await benefits.locator('summary').click();
            const production = benefits.getByText('Production par unité active · cette colonie', { exact: true }).locator('..').locator('strong');
            expect(numericValue(await production.innerText())).toBeCloseTo(expectedProduction, 2);
            await expect(benefits).not.toContainText('position 5, univers ×1');
            await panel.locator('.catalog-info-trigger').click();
            const detailProduction = panel.locator('dialog').getByText('Production par unité active sur cette colonie', { exact: true }).locator('..').locator('dd');
            expect(numericValue(await detailProduction.innerText())).toBeCloseTo(expectedProduction, 2);
            await page.keyboard.press('Escape');
        }
        await page.locator(`#building-tab-${slug}-basic`).click();
    }
    for (const width of [1440, 390]) {
        await page.setViewportSize({ width, height: 1000 });
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1)).toBeTruthy();
        for (const card of [collector, satellite]) {
            const action = card.locator('button.full');
            await action.scrollIntoViewIfNeeded();
            await expect(action).toBeInViewport({ ratio: 1 });
            if (await action.isEnabled()) await action.click({ trial: true });
        }
        expect((await new AxeBuilder({ page }).include('main').analyze()).violations.filter(v => ['serious', 'critical'].includes(v.impact))).toEqual([]);
        await page.locator('.catalog-card[data-slug="hydrogen_collector"]').screenshot({ path: testInfo.outputPath(`recolteurs-${width}.png`), animations: 'disabled' });
        await page.locator('.catalog-card[data-slug="solar_satellite"]').screenshot({ path: testInfo.outputPath(`satellites-${width}.png`), animations: 'disabled' });
    }
});

test('Déploiement civil : satellite exclu des départs, récolteur transféré et choix du simulateur', async ({ page }) => {
    test.setTimeout(180000);
    const data = await prepare(page), destination = revealVirginPlanet(data.project, data.email);
    runIsolatedSql(data.project, `UPDATE planet SET owner_id=${data.member},npc_planet=0,name='Site civil',acquired_at=UTC_TIMESTAMP() WHERE id=${destination.id}`);
    runIsolatedSql(data.project, `INSERT IGNORE INTO planet_resource(planet_id,resource_type,current_stock,production_rate,last_updated_at)
        SELECT ${destination.id},resource_type,0,0,UTC_TIMESTAMP() FROM planet_resource WHERE planet_id=${data.planet}`);
    runIsolatedSql(data.project, `INSERT INTO planet_building(planet_id,slug,tier,level) VALUES(${destination.id},'mine_hydrogen','basic',1)`);
    await page.goto('/flottes?mission=station');
    await expect(page.locator('input[name="ships[solar_satellite]"]')).toHaveCount(0);
    const form = page.locator('.fleet-form form').first();
    await form.locator('input[name="ships[hydrogen_collector]"]').fill('1');
    await expect(form).toContainText('Stationnement entre vos colonies, sans fret');
    await form.getByRole('button', { name: /Choisir la destination/ }).click();
    await form.locator('select[name="owned_destination"]').selectOption(String(destination.id));
    await form.getByRole('button', { name: /Vérifier la mission/ }).click();
    await expect(form.getByRole('button', { name: /Envoyer la flotte/ })).toBeEnabled();
    await form.getByRole('button', { name: /Envoyer la flotte/ }).click();
    await expect(page.getByText(/Flotte envoyée/)).toBeVisible();
    accelerateTravelingMission(data.project, data.email, 'station');
    await expect.poll(() => queryIsolatedSql(data.project, `SELECT COUNT(*) FROM fleet WHERE player_universe_id=${data.member} AND mission='station' AND status='traveling'`), { timeout: 20000 }).toBe('0');
    await page.goto(`/planetes/${destination.id}?section=ships`);
    await page.locator('#building-tab-hydrogen_collector-basic').click();
    await expect(page.locator('#item-hydrogen_collector-basic dl[aria-label="Déploiement Basique"]')).toContainText('1 / 1 places');
    await page.goto('/simulateur');
    await expect(page.locator('#sim-attacker-ships-solar_satellite-basic')).toHaveCount(0);
    await expect(page.locator('#sim-attacker-ships-hydrogen_collector-basic')).toHaveCount(0);
    await expect(page.locator('#sim-defender-ships-solar_satellite-basic')).toBeVisible();
    await expect(page.locator('#sim-defender-ships-hydrogen_collector-basic')).toBeVisible();
});
