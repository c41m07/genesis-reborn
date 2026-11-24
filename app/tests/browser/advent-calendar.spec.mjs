import { test, expect } from './support/isolated-test.mjs';
import { queryIsolatedSql, runIsolatedSql } from './support/game-fixtures.mjs';

// Un changement de thème par fixture SQL se voit au prochain document HTTP.
// Réouvrir une même ancre peut rester dans le document précédent.
async function openAdventDocument(page, url) {
    const response = await page.goto(url) ?? await page.reload();
    expect(response?.ok()).toBe(true);
}

function activateInOmega(project, edition) {
    runIsolatedSql(project, "INSERT INTO advent_window (universe_id, edition_id, name, visible_from, visible_until) SELECT u.id, e.id, e.name, e.starts_at, e.closes_at FROM universe u JOIN advent_edition e ON e.id=" + edition + " WHERE u.slug='omega-1'");
}

test('Avent : retrouver sans JavaScript un cadeau réservé après la clôture', async ({ page, browser, baseURL }) => {
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
    activateInOmega(project, edition);
    expect(edition).toBeGreaterThan(0);
    runIsolatedSql(project, "UPDATE planet_resource SET last_updated_at = UTC_TIMESTAMP(), current_stock = IF(resource_type='metal',10000,current_stock) WHERE planet_id=" + colony);

    const native = await browser.newContext({
        baseURL, javaScriptEnabled: false, viewport: { width: 390, height: 844 },
        storageState: await page.context().storageState(),
    });
    try {
        const mobile = await native.newPage();
        await mobile.goto('/evenements');
        await mobile.getByRole('link', { name: 'Calendrier de l’Avent' }).click();
        await expect(mobile.getByRole('heading', { level: 1, name: 'Avent de recette' })).toBeVisible();
        await expect(mobile.locator('.advent-door')).toHaveCount(24);
        const first = mobile.locator('#advent-door-1');
        await mobile.getByRole('link', { name: /^Porte 1,/ }).press('Enter');
        await expect(first).toBeVisible();
        await first.getByText('Choisir mon cadeau', { exact: false }).press('Enter');
        await first.getByLabel('Cadeau de la porte 1').selectOption('0');
        await first.getByLabel('Colonie destinataire').selectOption(String(colony));
        await first.getByRole('button', { name: 'Confirmer le cadeau et la destination' }).click();
        await expect(first.getByText('Réservé', { exact: true })).toBeVisible();
        await expect(mobile.getByText('Aucun versement partiel.', { exact: false })).toBeVisible();
        expect(Number(queryIsolatedSql(project, 'SELECT COUNT(*) FROM advent_claim WHERE delivered_at IS NULL'))).toBe(1);

        // L’onglet déjà ouvert doit aussi quitter la page à la fermeture.
        runIsolatedSql(project, 'UPDATE advent_window SET visible_until = UTC_TIMESTAMP() + INTERVAL 2 MINUTE WHERE edition_id=' + edition);
        runIsolatedSql(project, "INSERT INTO game_event (name, description, type, status, starts_at, ends_at, parameters, visible_to_players) VALUES ('Annonce de décembre', '', 'announcement', 'active', UTC_TIMESTAMP(), UTC_TIMESTAMP() + INTERVAL 1 DAY, '{}', 1)");
        await page.clock.install();
        await page.goto('/evenements/avent/' + edition);
        const shortcut = page.locator('header.topbar .advent-shortcut');
        for (const width of [360, 390, 768, 1440, 1920]) {
            await page.setViewportSize({ width, height: 900 });
            await expect(shortcut).toBeInViewport();
            const events = page.locator('header.topbar .event-drawer');
            await expect(events).toBeInViewport();
            const eventBox = await events.boundingBox();
            const adventBox = await shortcut.boundingBox();
            expect(adventBox.height).toBeCloseTo(eventBox.height, 0);
            expect(adventBox.y).toBeCloseTo(eventBox.y, 0);
            const headerGap = await page.locator('header.topbar').evaluate(header => parseFloat(getComputedStyle(header).columnGap));
            expect(adventBox.x - eventBox.x - eventBox.width).toBeCloseTo(headerGap, 0);
            await expect(page.locator('main a[href="/evenements/avent"]')).toHaveCount(0);
            expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 2)).toBe(true);
        }
        runIsolatedSql(project, 'UPDATE advent_window SET visible_until = UTC_TIMESTAMP() - INTERVAL 1 SECOND WHERE edition_id=' + edition);
        await page.clock.fastForward('02:01');
        await expect(page).toHaveURL(/\/evenements$/);
        runIsolatedSql(project, "UPDATE planet_resource SET last_updated_at = UTC_TIMESTAMP(), current_stock = IF(resource_type='metal',500,current_stock) WHERE planet_id=" + colony);
        await mobile.goto('/evenements');
        await expect(mobile.getByRole('link', { name: 'Calendrier de l’Avent' })).toHaveCount(0);
        expect((await mobile.goto('/evenements/avent/' + edition)).status()).toBe(404);
        await mobile.goto('/inventaire/recompenses');
        await mobile.getByLabel('Colonie destinataire').selectOption(String(colony));
        await mobile.getByRole('button', { name: 'Retenter le versement' }).click();
        await expect(mobile.getByRole('button', { name: 'Retenter le versement' })).toHaveCount(0);
        expect(Number(queryIsolatedSql(project, 'SELECT COUNT(*) FROM advent_claim WHERE delivered_at IS NOT NULL'))).toBe(1);
    } finally {
        await native.close();
    }
});

test('Avent consolidé : tranche avant choix, élection et versement clavier en format mobile', async ({ page, browser, baseURL }) => {
    const project = process.env.BROWSER_ROUTER;
    const now = Date.now();
    const doors = Object.fromEntries(Array.from({ length: 24 }, (_, index) => [index + 1, {
        opens_at: new Date(now + (index - 1) * 86400000).toISOString(),
        options: Object.fromEntries([['beginner', 1], ['intermediate', 6], ['veteran', 24]].map(([bracket, multiplier]) => [
            bracket, [{ kind: 'resources', resources: { metal: 600 * multiplier, nano: 300 * multiplier } }],
        ])),
    }]));
    // Seules les données de cette édition fictive sortent dans le récapitulatif.
    doors[6].opens_at = new Date(now - 60_000).toISOString();
    doors[6].options.beginner = [{
        kind: 'ship_voucher', slug: 'transport', tier: 'basic', quantity: 1, budget_metal: 6000,
    }, {
        kind: 'ship_pack', units: [{ slug: 'probe', tier: 'basic', quantity: 12 }], budget_metal: 6000,
    }];
    doors[12].opens_at = new Date(now - 50_000).toISOString();
    doors[12].options.beginner = [{ kind: 'research_discount', percent: 50, budget_metal: 7500 }];
    doors[18].opens_at = new Date(now - 40_000).toISOString();
    doors[18].options.beginner = [{
        kind: 'ship_pack', units: [{ slug: 'fighter', tier: 'basic', quantity: 1 }], budget_metal: 8000,
    }];
    doors[24].opens_at = new Date(now - 30_000).toISOString();
    doors[24].cosmetics = ['guardian_of_stars', 'polar_night'];
    doors[24].options.beginner = [{
        kind: 'ship_voucher', slug: 'cruiser', tier: 'basic', quantity: 1, budget_metal: 16_000,
    }];
    doors[24].options.intermediate = [{
        kind: 'ship_voucher', slug: 'capital', tier: 'basic', quantity: 1, budget_metal: 96_000,
    }];
    // L’édition précède la vraie inscription : la photo d’arrivée est faite
    // par le service natif au choix de l’univers, pas inventée par la fixture.
    runIsolatedSql(project, "INSERT INTO advent_edition (season, name, starts_at, closes_at, doors, published_at, plan) VALUES (2100, 'Avent des étoiles', UTC_TIMESTAMP() - INTERVAL 1 DAY, UTC_TIMESTAMP() + INTERVAL 30 DAY, '" + JSON.stringify(doors) + "', UTC_TIMESTAMP(), 'consolidated')");
    const edition = Number(queryIsolatedSql(project, 'SELECT id FROM advent_edition WHERE season=2100'));
    activateInOmega(project, edition);
    const marker = crypto.randomUUID().slice(0, 8);
    const email = 'advent-return-' + marker + '@example.test';
    const password = crypto.randomUUID() + 'Aa!';
    await page.goto('/inscription');
    await page.getByLabel('Adresse email').fill(email);
    await page.getByLabel('Nom de commandant').fill('AventChoix-' + marker);
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
    runIsolatedSql(project, "UPDATE planet_resource SET last_updated_at = UTC_TIMESTAMP(), current_stock = IF(resource_type IN ('metal','nano'),500,current_stock) WHERE planet_id=" + colony);

    const native = await browser.newContext({
        baseURL, javaScriptEnabled: false, viewport: { width: 360, height: 844 },
        storageState: await page.context().storageState(),
    });
    try {
        const mobile = await native.newPage();
        await mobile.goto('/evenements');
        await mobile.getByRole('link', { name: 'Calendrier de l’Avent' }).click();
        await expect(mobile.getByRole('heading', { name: 'Avent des étoiles' })).toBeVisible();
        await expect(mobile.locator('.advent-universe')).toContainText('Progression à l’arrivée');
        await expect(mobile.locator('.advent-universe')).toContainText('Débutant ×1');
        const door = mobile.locator('#advent-door-1');
        await expect(door.locator('form')).toHaveCount(0);
        await mobile.getByRole('button', { name: /Confirmer .* pour cette édition/ }).press('Enter');
        await expect(mobile).toHaveURL(new URL('/evenements/avent/' + edition, baseURL).href);

        // La confirmation revient à la même URL. Le formulaire du cadeau
        // apparaît dans le nouveau document, une fois l’univers enregistré.
        await expect(door.locator('form')).toHaveCount(1);
        await mobile.waitForLoadState('load');
        await mobile.getByRole('link', { name: /^Porte 1,/ }).press('Enter');
        await expect(mobile).toHaveURL(/#advent-door-1$/);
        await expect(door).toBeFocused();
        const choice = door.getByText('Choisir mon cadeau', { exact: false });
        await mobile.keyboard.press('Tab');
        await expect(choice).toBeFocused();
        await mobile.keyboard.press('Enter');
        await expect(door.locator('details')).toHaveAttribute('open', '');
        await expect(choice).toBeFocused();
        await door.getByRole('radio').check();
        await door.getByLabel('Colonie destinataire').selectOption(String(colony));
        await door.getByRole('button', { name: 'Confirmer le cadeau et la destination' }).press('Enter');
        await expect(door.getByText('Versé', { exact: true })).toBeVisible();
        expect(Number(queryIsolatedSql(project, 'SELECT COUNT(*) FROM advent_binding WHERE advent_edition_id=' + edition))).toBe(1);
        expect(Number(queryIsolatedSql(project, 'SELECT COUNT(*) FROM advent_claim WHERE delivered_at IS NOT NULL AND advent_edition_id=' + edition))).toBe(1);

        // L’aide est ouverte par défaut pour un nouveau joueur, sans recouvrir le cadeau.
        for (const [width, height] of [[1920, 1080], [390, 844]]) {
            await page.setViewportSize({ width, height });
            await openAdventDocument(page, '/evenements/avent/' + edition + '#advent-door-6');
            const help = page.locator('main.shell--advent .first-steps-popup[open] .first-steps-preview');
            const action = page.locator('#advent-door-6').getByRole('button', { name: 'Confirmer le cadeau et la destination' });
            await expect(help).toBeVisible();
            await action.click({ trial: true });
            await expect(page.getByRole('link', { name: /^Porte 6,/ })).not.toHaveCSS('border-left-color', await page.getByRole('link', { name: /^Porte 7,/ }).evaluate((tile) => getComputedStyle(tile).borderLeftColor));
            if (width === 1920) {
                await page.evaluate(() => { window.scrollTo(0, 0); });
                await expect.poll(() => page.evaluate(() => window.scrollY)).toBe(0);
                const calendarTop = await page.locator('.advent-calendar').evaluate((calendar) => calendar.getBoundingClientRect().top);
                expect(calendarTop).toBeLessThan(635);
            }
            const [helpBounds, actionBounds] = await Promise.all([help.boundingBox(), action.boundingBox()]);
            expect(helpBounds.y + helpBounds.height).toBeLessThanOrEqual(actionBounds.y);
        }

        // La fermeture locale masque aussi l’adresse directe pour un joueur qui a un reçu.
        runIsolatedSql(project, 'UPDATE advent_window SET visible_until = UTC_TIMESTAMP() WHERE edition_id=' + edition);
        expect((await page.goto('/evenements/avent/' + edition)).status()).toBe(404);
        runIsolatedSql(project, 'UPDATE advent_window w JOIN advent_edition e ON e.id=w.edition_id SET w.visible_until=e.closes_at WHERE e.id=' + edition);
        const proof = process.env.GR_AVENT_PROOF_DIRECTORY;
        if (proof) {
            // Les planches de comparaison montrent un compte dont l’aide est fermée.
            runIsolatedSql(project, `UPDATE player SET preferences=JSON_SET(preferences,'$.firstStepsGuide',false) WHERE email='${email}'`);
            await page.setViewportSize({ width: 1920, height: 1080 });
            for (const theme of ['dark', 'light']) {
                runIsolatedSql(project, "UPDATE player SET preferences=JSON_SET(IF(JSON_TYPE(preferences)='OBJECT', preferences, JSON_OBJECT()), '$.theme', '" + theme + "') WHERE email='" + email + "'");
                await openAdventDocument(page, '/evenements/avent/' + edition + '#advent-door-6');
                await expect(page.locator('body')).toHaveAttribute('data-theme', theme);
                await expect(page.locator('#advent-door-6').getByRole('radio')).toHaveCount(2);
                await page.evaluate(() => window.scrollTo(0, 0));
                await page.screenshot({ path: proof + '/AVENT_CALENDRIER_J6_' + theme + '.png' });
            }
        }

        await mobile.getByRole('link', { name: /^Porte 6,/ }).press('Enter');
        const sixth = mobile.locator('#advent-door-6');
        await expect(sixth).toBeVisible();
        await expect(sixth.getByText('Flotte utilitaire', { exact: true })).toBeVisible();
        await expect(sixth.getByRole('radio')).toHaveCount(2);
        await sixth.getByRole('radio').first().press('Space');
        await sixth.getByLabel('Colonie destinataire').selectOption(String(colony));
        await sixth.getByRole('button', { name: 'Confirmer le cadeau et la destination' }).press('Enter');
        await expect(sixth.getByRole('link', { name: 'Voir le devis de cette remise' })).toBeVisible();
        const reserves = mobile.getByRole('region', { name: 'Cadeaux et réserves' });
        await mobile.getByRole('link', { name: 'Cadeaux et réserves', exact: true }).press('Enter');
        await expect(reserves.getByRole('link', { name: 'Voir le devis de la porte 6' })).toBeVisible();
        await expect(reserves.getByText('Gardien des étoiles', { exact: true })).toBeVisible();
        await expect(reserves.getByText('Nuit polaire', { exact: true })).toBeVisible();
        const milestones = reserves.locator('.advent-moments');
        await milestones.getByRole('link', { name: 'Voir la porte 12' }).press('Enter');
        const researchDoor = mobile.locator('#advent-door-12');
        await researchDoor.getByRole('radio').press('Space');
        await researchDoor.getByLabel('Colonie destinataire').selectOption(String(colony));
        await researchDoor.getByRole('button', { name: 'Confirmer le cadeau et la destination' }).press('Enter');
        const researchCredit = researchDoor.getByRole('link', { name: 'Voir le devis de cette remise' });
        await expect(researchCredit).toBeVisible();
        await researchCredit.focus();
        await expect(researchCredit).toBeFocused();
        const researchCreditUrl = new URL(await researchCredit.getAttribute('href'), baseURL).href;
        await researchCredit.press('Enter');
        await expect(mobile).toHaveURL(researchCreditUrl);
        await expect(mobile.getByRole('heading', { name: 'Remise de recherche' })).toBeVisible();
        await expect(mobile.getByRole('button', { name: 'Calculer le devis' })).toBeVisible();
        await mobile.getByRole('link', { name: 'Retour au calendrier' }).click();
        await expect(mobile).toHaveURL(new URL('/evenements/avent/' + edition, baseURL).href);
        await mobile.getByRole('link', { name: /^Porte 18,/ }).press('Enter');
        const fightDoor = mobile.locator('#advent-door-18');
        await fightDoor.getByRole('radio').press('Space');
        await fightDoor.getByLabel('Colonie destinataire').selectOption(String(colony));
        await fightDoor.getByRole('button', { name: 'Confirmer le cadeau et la destination' }).press('Enter');
        await expect(fightDoor.getByText('Réservé', { exact: true })).toBeVisible();
        await mobile.getByRole('link', { name: 'Cadeaux et réserves', exact: true }).press('Enter');
        await expect(milestones.getByRole('link', { name: 'Voir le devis de la porte 12' })).toBeVisible();
        await expect(milestones.getByText('Lot réservé :', { exact: false })).toBeVisible();
        await reserves.getByRole('link', { name: 'Choisir mon cadeau lourd' }).press('Enter');
        const last = mobile.locator('#advent-door-24');
        await expect(last.getByRole('radio')).toHaveCount(1);
        await expect(last).toContainText('Croiseur de combat');
        await expect(last).toContainText('16,00 k');
        await expect(last).not.toContainText('96,00 k');
        expect(Number(queryIsolatedSql(project, 'SELECT COUNT(*) FROM advent_claim WHERE advent_edition_id=' + edition))).toBe(4);
        expect(Number(queryIsolatedSql(project, 'SELECT COUNT(*) FROM profile_cosmetic WHERE player_id=(SELECT id FROM player WHERE email=\x27' + email + '\x27)'))).toBe(0);
    } finally {
        await native.close();
    }

    for (const theme of ['dark', 'light']) {
        runIsolatedSql(project, "UPDATE player SET preferences=JSON_SET(IF(JSON_TYPE(preferences)='OBJECT', preferences, JSON_OBJECT()), '$.theme', '" + theme + "') WHERE email='" + email + "'");
        for (const width of [360, 390, 768, 1440, 1920]) {
            await page.setViewportSize({ width, height: width <= 390 ? 844 : 1080 });
            await openAdventDocument(page, '/evenements/avent/' + edition);
            await expect(page.locator('body')).toHaveAttribute('data-theme', theme);
            await expect(page.getByRole('heading', { name: 'Avent des étoiles' })).toBeVisible();
            await expect(page.locator('.advent-door')).toHaveCount(24);
            await expect(page.locator('.advent-day-tile')).toHaveCount(24);
            if (width === 1920) {
                const calendar = await page.locator('.advent-calendar').boundingBox();
                const selection = await page.locator('.advent-selection').boundingBox();
                expect(selection.x).toBeGreaterThanOrEqual(calendar.x + calendar.width);
                expect(selection.y).toBeCloseTo(calendar.y, 0);
                const rewards = page.locator('.advent-finale-preview img');
                await rewards.scrollIntoViewIfNeeded();
                await expect.poll(() => rewards.evaluate((image) => image.naturalWidth)).toBeGreaterThan(0);
                const finale = page.locator('.advent-finale-preview');
                // Le clair ne doit pas réappliquer le texte gris des panneaux sur le panorama.
                await expect(finale.getByText('Gardien des étoiles', { exact: true })).toHaveCSS('color', 'rgb(245, 248, 255)');
                await expect(finale.getByText('Titre et bannière de profil', { exact: true })).toHaveCSS('color', 'rgb(195, 215, 235)');
                const proof = process.env.GR_AVENT_PROOF_DIRECTORY;
                if (proof) {
                    await page.getByRole('link', { name: 'Cadeaux et réserves', exact: true }).press('Enter');
                    await expect(page).toHaveURL(/#advent-rewards$/);
                    await page.screenshot({ path: proof + '/AVENT_RESERVES_J24_' + theme + '.png' });
                }
            }

            if (width === 390 && process.env.GR_AVENT_PROOF_DIRECTORY) {
                await page.getByRole('link', { name: 'Cadeaux et réserves', exact: true }).press('Enter');
                await expect(page).toHaveURL(/#advent-rewards$/);
                const rewards = page.locator('.advent-finale-preview img');
                await rewards.scrollIntoViewIfNeeded();
                await expect.poll(() => rewards.evaluate((image) => image.naturalWidth)).toBeGreaterThan(0);
                await page.screenshot({ path: process.env.GR_AVENT_PROOF_DIRECTORY + '/AVENT_RESERVES_J24_MOBILE_' + theme + '.png' });
            }

            const contentWidth = await page.evaluate(() => [document.documentElement.scrollWidth, window.innerWidth]);
            expect(contentWidth[0]).toBeLessThanOrEqual(contentWidth[1] + 2);
        }
    }
    await page.goto('/evenements/avent/' + edition + '#advent-door-24');
    const finaleDoor = page.locator('#advent-door-24');
    await finaleDoor.getByRole('radio').check();
    await finaleDoor.getByLabel('Colonie destinataire').selectOption(String(colony));
    await finaleDoor.getByRole('button', { name: 'Confirmer le cadeau et la destination' }).press('Enter');
    await expect(finaleDoor.getByText('Bon à utiliser', { exact: true })).toBeVisible();
    expect(Number(queryIsolatedSql(project, "SELECT COUNT(*) FROM profile_cosmetic WHERE player_id=(SELECT id FROM player WHERE email='" + email + "') AND equipped=0"))).toBe(2);

    runIsolatedSql(project, 'UPDATE advent_window SET visible_until = UTC_TIMESTAMP() - INTERVAL 1 SECOND WHERE edition_id=' + edition);
    expect((await page.goto('/evenements/avent/' + edition)).status()).toBe(404);
    await page.goto('/inventaire/recompenses');
    await expect(page.getByRole('link', { name: 'Voir le devis de la porte 24' })).toBeVisible();
});

test('Avent : devis J6 et J12 maquetés, calcul et confirmation séparés', async ({ page, browser, baseURL }) => {
    test.setTimeout(90_000);
    const project = process.env.BROWSER_ROUTER;
    const marker = crypto.randomUUID().slice(0, 8);
    const email = 'advent-quotes-' + marker + '@example.test';
    const password = crypto.randomUUID() + 'Aa!';
    const now = Date.now();
    const doors = Object.fromEntries(Array.from({ length: 24 }, (_, index) => [index + 1, {
        opens_at: new Date(now + (index - 1) * 86400000).toISOString(),
        options: { beginner: [{ kind: 'resources', resources: { metal: 7 } }] },
    }]));
    for (const day of [6, 12, 24]) doors[day].opens_at = new Date(now - 60_000).toISOString();
    doors[6].options.beginner = [{ kind: 'ship_voucher', slug: 'transport', tier: 'basic', quantity: 1, budget_metal: 6000 }];
    doors[12].options.beginner = [{ kind: 'research_discount', percent: 50, budget_metal: 7500 }];
    doors[24].options.beginner = [{ kind: 'ship_voucher', slug: 'cruiser', tier: 'basic', quantity: 1, budget_metal: 16000 }];
    runIsolatedSql(project, "INSERT INTO advent_edition (season,name,starts_at,closes_at,doors,published_at,plan) VALUES (2101,'Avent Devis','" + new Date(now - 86400000).toISOString().slice(0, 19).replace('T', ' ') + "',UTC_TIMESTAMP() + INTERVAL 30 DAY,'" + JSON.stringify(doors) + "',UTC_TIMESTAMP(),'consolidated')");
    const edition = Number(queryIsolatedSql(project, 'SELECT id FROM advent_edition WHERE season=2101'));
    activateInOmega(project, edition);
    await page.goto('/inscription');
    await page.getByLabel('Adresse email').fill(email);
    await page.getByLabel('Nom de commandant').fill('AventDevis-' + marker);
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
    const native = await browser.newContext({
        baseURL, javaScriptEnabled: false, viewport: { width: 390, height: 844 },
        storageState: await page.context().storageState(),
    });
    const proof = process.env.GR_AVENT_QUOTE_PROOF_DIRECTORY;
    async function captureActualQuote(view, name, width, height) {
        if (!proof) return;
        // Capturer la navigation standard avec JavaScript, distincte du contrôle natif.
        await page.setViewportSize({ width, height });
        await openAdventDocument(page, view.url());
        await expect(page.locator('.advent-credit-board')).toBeVisible();
        await expect(page.locator('.advent-credit-board')).toHaveAttribute(
            'data-advent-credit-day', await view.locator('.advent-credit-board').getAttribute('data-advent-credit-day'));
        await page.screenshot({ path: proof + '/' + name + '.png', fullPage: width === 390 });
    }
    const quoteUrl = {};
    const heading = { 6: 'Bon de construction', 12: 'Remise de recherche' };
    const rowName = { 6: /Cargo de ligne.*Basique/, 12: /niveau 1/ };
    const countQueue = () => Number(queryIsolatedSql(project, 'SELECT COUNT(*) FROM queue_item'));
    function setStocks(amount) {
        runIsolatedSql(project, "UPDATE planet_resource SET last_updated_at=UTC_TIMESTAMP(), current_stock=" + amount + ' WHERE planet_id=' + colony);
    }
    const submit = (view) => view.getByRole('button', { name: 'Confirmer et utiliser la remise' });
    async function pick(view, day) {
        await view.getByLabel('Colonie destinataire').selectOption(String(colony));
        if (day === 12) await view.getByLabel('Technologie et rang').selectOption('engineering_foundations:basic');
        const calculate = view.getByRole('button', { name: 'Calculer le devis' });
        await calculate.focus();
        await expect(calculate).toBeFocused();
        // Le calcul soumet le formulaire en GET. Attendre le document du devis
        // et ses feuilles de style avant de mesurer les panneaux.
        await Promise.all([
            view.waitForURL(url => url.pathname === new URL(quoteUrl[day]).pathname
                && url.searchParams.get('colony') === String(colony), { waitUntil: 'load' }),
            calculate.press('Enter'),
        ]);
        await expect(view.getByRole('heading', { name: rowName[day] })).toBeVisible();
        await expect(view.getByRole('columnheader', { name: 'Remise' })).toBeVisible();
        await expect(view.getByRole('columnheader', { name: 'À payer' })).toBeVisible();
        await expect(view.getByLabel('Colonie destinataire')).toHaveValue(String(colony));
    }
    try {
        const mobile = await native.newPage();
        await mobile.goto('/evenements/avent/' + edition);
        await mobile.getByRole('button', { name: /Confirmer .* pour cette édition/ }).press('Enter');
        for (const day of [6, 12, 24]) {
            await mobile.getByRole('link', { name: new RegExp('^Porte ' + day + ',') }).press('Enter');
            const door = mobile.locator('#advent-door-' + day);
            await door.getByRole('radio').press('Space');
            await door.getByLabel('Colonie destinataire').selectOption(String(colony));
            await door.getByRole('button', { name: 'Confirmer le cadeau et la destination' }).press('Enter');
            const link = door.getByRole('link', { name: 'Voir le devis de cette remise' });
            const destination = await link.getAttribute('href');
            expect(destination).toContain('/avoirs/');
            await link.click();
            await expect(mobile).toHaveURL(new URL(destination, baseURL).href);
            quoteUrl[day] = mobile.url();
            expect(countQueue()).toBe(0);
            await expect(mobile.getByRole('table')).toHaveCount(0);
            await expect(submit(mobile)).toHaveCount(0);
            if (day === 24) {
                await expect(mobile.locator('[data-advent-credit-day]')).toHaveCount(0);
            } else {
                await expect(mobile.getByRole('heading', { level: 1, name: heading[day] })).toBeVisible();
                await expect(mobile.locator('[data-advent-credit-day="' + day + '"]')).toBeVisible();
                if (day === 6) await captureActualQuote(mobile, 'AVENT_J6_AVANT_CHOIX_390', 390, 844);
            }
            await mobile.getByRole('link', { name: 'Retour au calendrier', exact: true }).click();
            await expect(mobile).toHaveURL(new URL('/evenements/avent/' + edition, baseURL).href);
        }

        // On donne au compte synthétique les déblocages et le laboratoire de la recette PHP.
        runIsolatedSql(project, "INSERT INTO planet_building (planet_id,slug,tier,level) VALUES (" + colony + ",'lab','basic',1) ON DUPLICATE KEY UPDATE level=1");
        runIsolatedSql(project, "UPDATE player_universe pu JOIN player p ON p.id=pu.player_id SET pu.catalog_unlocks=JSON_SET(COALESCE(pu.catalog_unlocks,JSON_OBJECT()), '$.\"v2:planet:" + colony + ":ships:transport:basic\"', true, '$.\"v2:universe:technologies:engineering_foundations:basic\"', true) WHERE p.email='" + email + "'");
        setStocks(0);
        await mobile.goto(quoteUrl[12]);
        await pick(mobile, 12);
        await expect(mobile.getByText('Le stock de la colonie ne suffit pas.', { exact: false })).toBeVisible();
        await expect(submit(mobile)).toHaveCount(0);
        await captureActualQuote(mobile, 'AVENT_J12_STOCK_INSUFFISANT_390', 390, 844);
        setStocks(9900);
        expect(countQueue()).toBe(0);

        for (const theme of ['dark', 'light']) {
            runIsolatedSql(project, "UPDATE player SET preferences=JSON_SET(IF(JSON_TYPE(preferences)='OBJECT',preferences,JSON_OBJECT()),'$.theme','" + theme + "') WHERE email='" + email + "'");
            for (const [width, height] of [[1920, 1080], [390, 844], [360, 800]]) {
                await mobile.setViewportSize({ width, height });
                for (const day of [6, 12]) {
                    await openAdventDocument(mobile, quoteUrl[day]);
                    await expect(mobile.locator('body')).toHaveAttribute('data-theme', theme);
                    await expect(mobile.getByLabel('Technologie et rang')).toHaveCount(day === 12 ? 1 : 0);
                    await pick(mobile, day);
                    await expect(submit(mobile)).toBeVisible();
                    await expect(submit(mobile)).toHaveAttribute('aria-describedby', 'advent-credit-usage');
                    const layout = await mobile.locator('.advent-credit-grid').evaluate((grid) => {
                        const [choice, result] = [...grid.children].map((panel) => panel.getBoundingClientRect());
                        return { stacked: result.top >= choice.bottom, inline: choice.right <= result.left && Math.abs(choice.top - result.top) <= 2 };
                    });
                    expect(layout, `Devis J${day}, thème ${theme}, largeur ${width}px`).toMatchObject(
                        width === 1920 ? { inline: true } : { stacked: true },
                    );
                    expect(await mobile.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth + 2)).toBe(true);
                    if (width !== 360) {
                        await captureActualQuote(mobile, 'AVENT_J' + day + '_DEVIS_' + width + '_' + theme, width, height);
                    }
                }
            }
        }
        expect(countQueue()).toBe(0);
        await submit(mobile).press('Enter');
        await expect(mobile).toHaveURL(new RegExp('/evenements/avent/' + edition + '$'));
        expect(countQueue()).toBe(1);
        expect(Number(queryIsolatedSql(project, 'SELECT COUNT(*) FROM advent_claim WHERE redeemed_queue_id IS NOT NULL AND door=12'))).toBe(1);
    } finally {
        await native.close();
    }
});
