import { test, expect } from './support/isolated-test.mjs';
import { prepareOwnedColony, queryIsolatedSql, runIsolatedSql } from './support/game-fixtures.mjs';
import { readdirSync } from 'node:fs';
import AxeBuilder from '@axe-core/playwright';

async function enter(page) {
    const suffix = crypto.randomUUID().slice(0, 8);
    const email = `catalog-workspace-${suffix}@example.test`;
    const password = crypto.randomUUID() + 'aA1!';
    await page.goto('/inscription');
    await page.getByLabel('Adresse email').fill(email);
    await page.getByLabel('Nom de commandant').fill('Catalogue-' + suffix);
    await page.getByLabel('Mot de passe (12 caractères minimum)', { exact: true }).fill(password);
    await page.getByLabel('Confirmer le mot de passe').fill(password);
    await page.getByRole('button', { name: 'Créer mon compte' }).click();
    await page.getByLabel('Adresse email').fill(email);
    await page.getByLabel('Mot de passe', { exact: true }).fill(password);
    await page.getByRole('button', { name: /Connexion/ }).click();
    await page.locator('.universe-card').filter({ has: page.getByRole('heading', { name: /Omega/ }) }).getByRole('button').click();
    await expect(page).toHaveURL(/planetes\/\d+$/);
    const path = new URL(page.url()).pathname;
    const planet = Number(path.split('/').pop());
    const project = process.env.BROWSER_ROUTER;
    const member = Number(queryIsolatedSql(project, `SELECT owner_id FROM planet WHERE id=${planet}`));
    prepareOwnedColony(project, email, { stock: 100000000, showLockedItems: true });
    // Ces niveaux synthétiques ne sont écrits que dans la base jetable du parcours.
    for (const [group, table, owner] of [['buildings', 'planet_building', `planet_id`], ['technologies', 'player_technology', `player_universe_id`]]) {
        const names = readdirSync(new URL(`../../config/game/${group}/`, import.meta.url)).map(name => name.replace('.yaml', ''));
        const values = names.flatMap(slug => ['basic', 'intermediate', 'superior'].map(tier => `(${group === 'buildings' ? planet : member},'${slug}','${tier}',25)`));
        runIsolatedSql(project, `INSERT INTO ${table}(${owner},slug,tier,level) VALUES ${values.join(',')} ON DUPLICATE KEY UPDATE level=VALUES(level)`);
    }
    runIsolatedSql(project, `UPDATE planet_building SET level=5 WHERE planet_id=${planet} AND slug='mine_metal' AND tier='intermediate'`);
    runIsolatedSql(project, `UPDATE player_technology SET level=5 WHERE player_universe_id=${member} AND slug='engineering_foundations' AND tier='superior'`);
    return { email, planet, member, project, path };
}

async function capture(page, info, name, width) {
    expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(width);
    await page.locator('.catalog-card-art img:visible').evaluateAll(images => Promise.all(images.map(image => {
        image.loading = 'eager';
        return image.decode().catch(() => {});
    })));
    await page.screenshot({ path: info.outputPath(`jeu-L7B-${name}-${width}.png`), fullPage: true, animations: 'disabled' });
}

test('L7B : quatre cartes, vraies lignes et cinq coûts lisibles sur quatre formats', async ({ page }, info) => {
    test.setTimeout(240000);
    const user = await enter(page);
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    for (const width of [1440, 768, 390, 320]) {
        await page.setViewportSize({ width, height: 1000 });
        for (const group of ['buildings', 'technologies', 'ships', 'defenses']) {
            await page.goto(`${user.path}?section=${group}`);
            await expect(page.locator('.building-catalog-ready')).toBeVisible();
            const compact = page.getByRole('switch', { name: 'Vue condensée' });
            if (await compact.getAttribute('aria-checked') === 'true') await compact.click();
            const cards = page.locator('.building-card:visible');
            if (width === 1440) {
                const boxes = await cards.evaluateAll(nodes => nodes.slice(0, 4).map(n => n.getBoundingClientRect().top));
                expect(new Set(boxes).size).toBe(1);
            }
            if (width !== 320) await capture(page, info, group + '-cartes', width);
            await compact.click();
            const first = cards.first(), second = cards.nth(1);
            expect((await second.boundingBox()).y).toBeGreaterThanOrEqual((await first.boundingBox()).y + (await first.boundingBox()).height);
            await expect(first.locator('.building-rank:not([hidden]) .catalog-card-art')).toBeHidden();
            const priceErrors = await page.locator('.building-rank:not([hidden]) .building-costs').evaluateAll(strips => strips.flatMap(strip => {
                const cells = [...strip.children].filter(cell => !cell.hidden);
                const ys = cells.map(cell => Math.round(cell.getBoundingClientRect().y));
                return cells.length > 5 || new Set(ys).size > 1 || cells.some(cell => cell.scrollWidth > cell.clientWidth + 1) ? [strip.closest('.building-rank').id] : [];
            }));
            expect(priceErrors).toEqual([]);
            if (width !== 320) await capture(page, info, group + '-lignes', width);
            if (width === 390 && group === 'buildings') {
                expect((await new AxeBuilder({ page }).include('.catalog-workspace').analyze()).violations).toEqual([]);
            }
        }
    }
    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto(`${user.path}?section=technologies`);
    await page.locator('#building-tab-engineering_foundations-superior').click();
    await page.reload();
    await expect(page.locator('#item-engineering_foundations-superior')).toBeVisible();
    await expect(page.getByRole('switch', { name: 'Vue condensée' })).toHaveAttribute('aria-checked', 'true');
    runIsolatedSql(user.project, `UPDATE player SET preferences=JSON_SET(preferences,'$.theme','light') WHERE email='${user.email}'`);
    await page.reload();
    await capture(page, info, 'recherche-superieure-clair', 390);
    expect((await new AxeBuilder({ page }).include('.catalog-workspace').analyze()).violations).toEqual([]);
    expect(errors).toEqual([]);
});

test('L7B : manques multiples, devis de lot et suivi dépliable conservés après actualisation', async ({ page }, info) => {
    test.setTimeout(180000);
    const user = await enter(page);
    await page.setViewportSize({ width: 1440, height: 1000 });
    await page.goto(`${user.path}?section=ships`);
    const ship = page.locator('#item-fighter-basic');
    await expect(ship.locator('[data-catalogue-main-gain]')).toContainText('Attaque');
    await expect(ship.locator('.core-unit-price')).toBeHidden();
    const unitMetalPrice = Number((await ship.locator('.building-costs>[data-resource=metal]').getAttribute('title')).split(' : ')[1].replace(/\s/g, ''));
    await ship.getByLabel('Quantité', { exact: true }).fill('5');
    const previewResponse = page.waitForResponse(response => response.url().includes('/construction-maximum'));
    await ship.getByLabel('Quantité', { exact: true }).press('Tab');
    const preview = await (await previewResponse).json();
    await expect(ship.locator('[data-construction-quantity-target=costHeading]')).toHaveText('Coût du lot × 5');
    await ship.locator('.core-unit-details summary').click();
    const unitMetalDetail = ship.locator('.core-unit-price li').filter({ hasText: 'Métal' });
    await expect(unitMetalDetail).toBeVisible();
    const detailAmount = await unitMetalDetail.locator('strong').innerText();
    expect(Number(detailAmount.replace(/\s/g, ''))).toBe(unitMetalPrice);
    await ship.locator('.core-unit-details summary').click();
    for (const [resource, amount] of Object.entries(preview.costs).filter(([,amount]) => amount > 0)) {
        const title = await ship.locator(`.building-costs>[data-resource=${resource}]`).getAttribute('title');
        expect(Number(title.split(' : ')[1].replace(/\s/g, ''))).toBe(amount);
    }
    await page.evaluate(() => window.dispatchEvent(new Event('genesis:refresh')));
    await expect(ship.getByLabel('Quantité', { exact: true })).toHaveValue('5');
    await capture(page, info, 'lot-cinq-unites', 1440);
    await ship.getByRole('button', { name: 'Construire 5', exact: true }).click();
    const queue = page.locator('[data-catalog-disclosure=queue-shipyard]');
    await queue.locator(':scope>summary').click();
    await expect(queue.locator('.queue-detail-list')).toContainText('× 5');
    await expect(queue.getByRole('link', { name: /Préparer l’annulation/ })).toBeVisible();
    await page.evaluate(() => document.activeElement?.blur());
    const liveResponse = page.waitForResponse(response => response.request().headers()['x-genesis-live'] === '1');
    await page.evaluate(() => window.dispatchEvent(new Event('genesis:refresh')));
    await liveResponse;
    await expect(queue).toHaveAttribute('open', '');
    await capture(page, info, 'file-complete', 1440);
    await queue.getByRole('link', { name: /Préparer l’annulation/ }).click();
    await expect(page.getByRole('heading', { name: 'Vérifier l’annulation' })).toBeVisible();
    await expect(page.locator('.sensitive-actions')).toContainText('25 %');
    await page.getByRole('link', { name: 'Conserver les commandes' }).click();
    await page.locator('[data-catalog-disclosure=flights]>summary').click();
    await expect(page.locator('[data-catalog-disclosure=flights]')).toHaveAttribute('open', '');
    await page.keyboard.press('Escape');
    await expect(page.locator('[data-catalog-disclosure=flights]')).not.toHaveAttribute('open');
    await expect(page.locator('[data-catalog-disclosure=flights]>summary')).toBeFocused();

    runIsolatedSql(user.project, `UPDATE planet_resource SET current_stock=0,last_updated_at=DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 HOUR) WHERE planet_id=${user.planet}`);
    await page.goto(`${user.path}?section=technologies#item-engineering_foundations-superior`);
    const research = page.locator('#item-engineering_foundations-superior');
    const action = research.locator('.catalog-build-action');
    await expect(action).toBeDisabled();
    await expect(action).toContainText('Il manque :');
    await expect(action.locator('.catalog-missing-resource')).toHaveCount(5);
    await expect(action).toContainText('Antimatière :');
    await expect(action).toContainText('Hydrogène :');
    for (const width of [1440, 390, 320]) {
        await page.setViewportSize({ width, height: 844 });
        expect(await action.evaluate(el => el.scrollHeight <= el.clientHeight + 1 && el.scrollWidth <= el.clientWidth + 1)).toBe(true);
        await capture(page, info, 'cinq-ressources-manquantes', width);
    }
    await page.evaluate(() => window.scrollTo(0, document.body.scrollHeight));
    const rail = await page.locator('.catalog-operations-rail').boundingBox();
    expect(rail.y).toBeGreaterThanOrEqual(0);
    expect(rail.y + rail.height).toBeLessThan(700);
    await page.locator('[data-catalog-disclosure=incoming]>summary').click();
    await expect(page.locator('.incoming-empty-message')).toBeVisible();
    await page.goto(`${user.path}?section=ships`);
    const unavailable = page.locator('#item-fighter-basic');
    await expect(unavailable.getByRole('button', { name: /Max :/ })).toBeDisabled();
    const unavailableResponse = page.waitForResponse(response => response.url().includes('/construction-maximum'));
    const unavailableQuantity = unavailable.getByLabel('Quantité', { exact: true });
    await unavailableQuantity.fill('2');
    await unavailableQuantity.press('Tab');
    const quote = await (await unavailableResponse).json();
    expect(quote.maximum).toBe(0);
    expect(Object.keys(quote.missingResources).length).toBeGreaterThan(1);
    for (const resource of Object.values(quote.missingResources)) {
        await expect(unavailable.locator('.catalog-build-action')).toContainText(new RegExp(resource.label + ' :'));
        expect(resource.amount).toBeGreaterThan(0);
    }
    await expect(unavailable.locator('.catalog-build-action')).toBeDisabled();
    await expect(unavailable.locator('[data-construction-quantity-target=costHeading]')).toHaveText('Coût d’une unité');
});

test('Fidélité catalogues : même composition en Full HD et actions lisibles sur téléphone', async ({ page }, info) => {
    test.setTimeout(150000);
    const user = await enter(page);
    const sections = { buildings: 'building', technologies: 'research', ships: 'shipyard', defenses: 'shipyard' };
    for (const theme of ['dark', 'light']) {
        runIsolatedSql(user.project, "UPDATE player SET preferences=JSON_SET(CASE WHEN JSON_TYPE(preferences)='OBJECT' THEN preferences ELSE JSON_OBJECT() END,'$.theme','" + theme + "') WHERE email='" + user.email + "'");
        for (const width of [1920, 390]) {
            await page.setViewportSize({ width, height: width === 1920 ? 1080 : 844 });
            for (const [section, firstQueue] of Object.entries(sections)) {
                await page.goto(user.path + '?section=' + section);
                const catalog = page.locator('.catalog-workspace');
                await expect(page.locator('body')).toHaveAttribute('data-theme', theme);
                await expect(catalog.locator('.building-catalog-ready')).toBeVisible();
                const guide = page.locator('.first-steps-popup[open] > summary');
                if (await guide.isVisible()) await guide.click();
                await expect(page.locator('.core-catalogue-header .development-navigation')).toHaveCount(0);
                const rail = catalog.locator('.catalog-operations-rail');
                const queues = rail.locator('.catalog-queue-ribbon');
                await expect(queues.locator('[data-queue-type]')).toHaveCount(3);
                await expect(queues.locator('[data-queue-type]').first()).toHaveAttribute('data-queue-type', firstQueue);
                const widthDelta = (await rail.boundingBox()).width - (await queues.boundingBox()).width;
                expect(Math.abs(widthDelta)).toBeLessThanOrEqual(1);
                await expect(rail.locator('[data-catalog-disclosure="incoming"] > summary')).toBeVisible();
                const cards = catalog.locator('.building-card:visible');
                const panel = cards.first().getByRole('tabpanel');
                await expect(panel.locator('.catalogue-level-badge')).toBeVisible();
                const art = await panel.locator('.catalogue-card-visual').boundingBox();
                const tabs = await cards.first().locator('.building-rank-tabs').boundingBox();
                expect(art.y + art.height).toBeLessThanOrEqual(tabs.y + 1);
                expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(width);
                if (width === 1920) {
                    const firstRow = await cards.evaluateAll(nodes => nodes.slice(0, 4).map(node => node.getBoundingClientRect().top));
                    expect(firstRow).toHaveLength(4);
                    expect(new Set(firstRow).size).toBe(1);
                    expect(firstRow[0], 'Les quatre premières illustrations commencent dans la première moitié de l’écran').toBeLessThanOrEqual(560);
                    if (section === 'buildings') {
                        const action = panel.locator('button.catalog-build-action');
                        await expect(action).toBeVisible();
                        const box = await action.boundingBox();
                        expect(box.y + box.height, 'La première action de construction tient dans le viewport').toBeLessThanOrEqual(1080);
                    }
                }
                // Une capture correspond à la taille CSS affichée, pas à la page déroulée.
                await cards.locator('.building-rank:not([hidden]) .catalog-card-art img').evaluateAll(images =>
                    Promise.all(images.slice(0, 4).map(image => image.decode())),
                );
                await page.screenshot({ path: info.outputPath('CATALOGUE_' + section + '_' + theme + '_' + width + '.png'), animations: 'disabled', fullPage: false, scale: 'css' });
                if (width === 390 && theme === 'dark') {
                    await panel.locator('.catalogue-card-identity').scrollIntoViewIfNeeded();
                    await page.screenshot({ path: info.outputPath('CATALOGUE_' + section + '_dark_390_CARD.png'), animations: 'disabled', fullPage: false, scale: 'css' });
                }
            }
        }
    }
    await page.goto(user.path + '?section=technologies#item-colonial_expansion-basic');
    const maximum = page.locator('#item-colonial_expansion-basic');
    await expect(maximum.locator('.catalogue-family-mark')).toContainText('Au maximum');
    await expect(maximum.locator('[data-catalogue-main-gain]')).toContainText('Effet acquis');
    await expect(maximum.locator('.catalogue-no-quote dd')).toHaveText(['—', '—']);
    await expect(maximum.locator('.catalog-blocked-action')).toBeDisabled();
});
