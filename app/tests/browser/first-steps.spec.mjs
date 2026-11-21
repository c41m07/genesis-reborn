import AxeBuilder from '@axe-core/playwright';
import { test, expect } from './support/isolated-test.mjs';
import { accelerateQueuedItem, queryIsolatedSql } from './support/game-fixtures.mjs';

test.use({ keepDailyRewardPrompt: true });

async function register(page, { closeDaily = true } = {}) {
    const id = crypto.randomUUID().slice(0, 8);
    const email = 'first-steps-' + id + '@example.test';
    const password = crypto.randomUUID() + 'Aa1!';
    await page.goto('/inscription');
    await page.getByLabel('Adresse email').fill(email);
    await page.getByLabel('Nom de commandant').fill('Cadet-' + id);
    await page.getByLabel('Mot de passe (12 caractères minimum)', { exact: true }).fill(password);
    await page.getByLabel('Confirmer le mot de passe').fill(password);
    await page.getByRole('button', { name: 'Créer mon compte' }).click();
    await page.getByLabel('Adresse email').fill(email);
    await page.getByLabel('Mot de passe', { exact: true }).fill(password);
    await page.getByRole('button', { name: /Connexion/ }).click();
    await page.locator('.universe-card').filter({ has: page.getByRole('heading', { name: /Omega/ }) }).getByRole('button').click();
    await expect(page).toHaveURL(/planetes\/\d+$/);
    // Les comptes neufs de cette fixture ont tous un lot quotidien disponible.
    if (closeDaily) {
        const dialog = page.getByRole('dialog', { name: 'Récompense quotidienne' });
        const popup = page.locator('.first-steps-popup');
        await expect(dialog).toBeVisible();
        await expect(popup).not.toHaveAttribute('open', '');
        await dialog.getByRole('button', { name: 'Plus tard', exact: true }).click();
        await expect(dialog).toBeHidden();
    }

    return email;
}

test('Premiers pas : conseil facultatif et progression après la fin réelle de la mine', async ({ page, browser, baseURL }, info) => {
    test.setTimeout(120000);
    const email = await register(page);
    const overview = page.url();
    await expect(page.locator('.topbar .universe-context + .header-technology-goal')).toBeVisible();
    const overviewGuide = page.getByRole('region', { name: 'Premiers pas', exact: true });
    await expect(overviewGuide).toContainText('0 sur 12');
    await expect(overviewGuide).toContainText('Mine à ciel ouvert');
    const guideLink = overviewGuide.getByRole('link', { name: 'Voir le guide Premiers pas et les remboursements', exact: true });
    await page.setViewportSize({ width: 1440, height: 900 });
    expect((await guideLink.boundingBox())?.height).toBeGreaterThanOrEqual(44);
    await overviewGuide.screenshot({ path: info.outputPath('premiers-pas-vue-generale-desktop.png'), animations: 'disabled' });
    await page.setViewportSize({ width: 390, height: 844 });
    expect((await guideLink.boundingBox())?.height).toBeGreaterThanOrEqual(44);
    await expect.poll(() => page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(390);
    await guideLink.focus();
    await expect(guideLink).toBeFocused();
    await guideLink.press('Enter');
    await expect(page.getByRole('heading', { name: 'Premiers pas', exact: true })).toBeVisible();
    const guide = page.getByRole('region', { name: 'Développer sa colonie et explorer', exact: true });
    const progress = guide.getByRole('progressbar', { name: 'Progression des premiers pas', exact: true });
    const next = guide.locator('.first-step--next');
    await expect(progress).toHaveAttribute('value', '0');
    await expect(next.getByRole('heading', { name: 'Mine à ciel ouvert', exact: true })).toBeVisible();
    await expect(guide.getByRole('heading', { name: 'Première recherche', exact: true })).toBeVisible();
    await expect(progress).toHaveAttribute('max', '12');
    await expect(guide.getByRole('link', { name: 'Ouvrir la fiche de la sonde', exact: true })).toHaveAttribute('href', /section=ships.*#item-probe-basic$/);
    const explore = guide.getByRole('listitem').filter({ has: page.getByRole('heading', { name: 'Premier système exploré', exact: true }) });
    await expect(explore).toContainText('consomme une sonde à l’arrivée');
    await expect(explore).toContainText('en construire une autre au chantier pour l’espionnage');
    await expect(explore.getByRole('link', { name: 'Préparer une exploration', exact: true })).toHaveAttribute('href', /\/flottes\/preparer\?mission=explore&origin=\d+$/);
    const espionage = guide.getByRole('listitem').filter({ has: page.getByRole('heading', { name: 'Premier rapport d’espionnage PNJ', exact: true }) });
    await expect(espionage).toContainText('Un rapport « Détecté » ne contient aucun renseignement');
    await expect(espionage).toContainText('La sonde envoyée est consommée à l’arrivée');
    await expect(espionage.getByRole('link', { name: 'Consulter les rapports d’espionnage', exact: true })).toHaveAttribute('href', '/rapports?type=espionage');
    await espionage.getByRole('link', { name: 'Préparer un espionnage PNJ', exact: true }).click();
    await expect(page).toHaveURL(/\/flottes\/preparer\?mission=spy&origin=\d+&tutorial=1$/);
    await expect(page.getByRole('heading', { name: 'Premiers pas : espionnage PNJ', exact: true })).toBeVisible();
    await page.goto('/premiers-pas');
    const colonization = guide.getByRole('listitem').filter({ has: page.getByRole('heading', { name: 'Première colonisation', exact: true }) });
    await expect(colonization).toContainText('sans avoir à terminer le guide');
    await expect(colonization).toContainText('planète découverte et inhabitée, non PNJ');
    await expect(colonization.getByRole('link', { name: 'Ouvrir la fiche du Vaisseau colonisateur', exact: true })).toHaveAttribute('href', /section=ships.*#item-colony_ship-basic$/);
    await colonization.getByRole('link', { name: 'Préparer une colonisation', exact: true }).click();
    await expect(page).toHaveURL(/\/flottes\/preparer\?mission=colonize&origin=\d+$/);
    await page.goto('/premiers-pas');
    await expect(progress).toHaveAttribute('value', '0');
    const storage = guide.getByRole('listitem').filter({ has: page.getByRole('heading', { name: 'Dépôt de minerais', exact: true }) });
    await storage.getByRole('link', { name: 'Ouvrir le Dépôt de minerais', exact: true }).click();
    const storageHelp = page.getByRole('complementary', { name: 'Conseil Premiers pas', exact: true });
    await expect(storageHelp).toContainText(/10\s000 unités de métal de capacité totale, dont 1\s000 protégées/);
    await expect(storageHelp).toContainText(/110\s000 unités au total, dont 11\s000 protégées/);
    await expect(storageHelp).toContainText('Mine à ciel ouvert niveau 5');
    await storageHelp.screenshot({ path: info.outputPath('premiers-pas-depot-mobile.png'), animations: 'disabled' });
    await page.goto('/premiers-pas');
    await expect.poll(() => page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(390);
    await page.screenshot({ path: info.outputPath('premiers-pas-mobile.png'), animations: 'disabled' });

    // Le guide ne bloque pas le reste du jeu avant la première construction.
    await page.getByRole('link', { name: 'Retour à la vue générale', exact: true }).click();
    await expect(page).toHaveURL(overview);
    await page.getByRole('link', { name: 'Voir le guide Premiers pas et les remboursements', exact: true }).click();
    await next.getByRole('link', { name: 'Ouvrir la Mine à ciel ouvert', exact: true }).click();
    await expect(page).toHaveURL(/section=buildings.*#item-mine_metal-basic$/);
    const card = page.locator('#item-mine_metal-basic');
    await expect(card.getByRole('complementary', { name: 'Conseil Premiers pas' })).toContainText('paie le prix normal');
    await card.getByRole('button', { name: /Améliorer .* niveau 1/ }).click();
    await page.goto('/premiers-pas');
    await expect(progress).toHaveAttribute('value', '0');

    accelerateQueuedItem(process.env.BROWSER_ROUTER, email, 'building', 'mine_metal');
    const target = new URL(overview);
    target.searchParams.set('section', 'buildings');
    await page.goto(target.href);
    await expect(page.locator('#building-tab-mine_metal-basic .building-rank-level')).toHaveText('1');
    await page.goto('/premiers-pas');
    await expect(progress).toHaveAttribute('value', '1');
    const refunds = page.getByRole('region', { name: 'Remboursements disponibles', exact: true });
    await expect(refunds).toContainText('96 métal et 52 nanos');
    await refunds.getByRole('button', { name: 'Récupérer : Mine à ciel ouvert', exact: true }).click();
    await expect(refunds).toHaveCount(0);
    await expect(page.getByRole('status').filter({ hasText: 'Remboursement ajouté aux stocks' })).toBeVisible();
    await expect(guide.getByRole('listitem').filter({ has: page.getByRole('heading', { name: 'Mine à ciel ouvert', exact: true }) })).toContainText('Terminé');
    await expect(next.getByRole('heading', { name: 'Centrale nucléaire et bilan énergie', exact: true })).toBeVisible();

    const native = await browser.newContext({ baseURL, storageState: await page.context().storageState(), javaScriptEnabled: false });
    try {
        const fallback = await native.newPage();
        await fallback.goto('/premiers-pas');
        await expect(fallback.getByRole('progressbar', { name: 'Progression des premiers pas', exact: true })).toHaveAttribute('value', '1');
        await fallback.getByRole('link', { name: 'Ouvrir la Centrale nucléaire', exact: true }).click();
        await expect(fallback).toHaveURL(/section=buildings.*#item-power_plant-basic$/);
    } finally {
        await native.close();
    }
});

test('Exercice pirate : privé, formulaire natif, rejouable sans récompense', async ({ page, browser, baseURL }) => {
    test.setTimeout(120000);
    const route = '/premiers-pas/exercice-pirate';
    const guest = await page.goto(route);
    expect(guest?.ok()).toBeTruthy();
    await expect(page).toHaveURL(/connexion/);
    const email = await register(page);
    const project = process.env.BROWSER_ROUTER;
    expect(email).toMatch(/^first-steps-[a-f0-9]{8}@example[.]test$/);
    const member = Number(queryIsolatedSql(project, "SELECT p.current_player_universe_id FROM player p WHERE p.email = '" + email + "'"));
    expect(member).toBeGreaterThan(0);
    const assets = () => queryIsolatedSql(project, [
        'SELECT planet.id, planet.owner_id, pr.resource_type, pr.current_stock, pr.last_updated_at FROM planet JOIN planet_resource pr ON planet.id = pr.planet_id WHERE planet.owner_id = ' + member + ' ORDER BY planet.id, pr.resource_type',
        'SELECT id, status, reserved_fuel, cargo FROM fleet WHERE player_universe_id = ' + member + ' ORDER BY id',
        'SELECT f.id, s.slug, s.tier, s.quantity FROM fleet f JOIN fleet_ship s ON f.id = s.fleet_id WHERE f.player_universe_id = ' + member + ' ORDER BY s.id',
    ].join('; '));
    const completed = () => queryIsolatedSql(project, "SELECT completed_at FROM first_step_completion WHERE step_key = 'pirate_exercise' AND player_universe_id = " + member);
    const before = assets();
    const native = await browser.newContext({ baseURL, storageState: await page.context().storageState(), javaScriptEnabled: false });
    try {
        const view = await native.newPage();
        await view.setViewportSize({ width: 390, height: 844 });
        await view.goto('/premiers-pas');
        await view.getByRole('link', { name: 'Ouvrir le briefing de l’exercice', exact: true }).click();
        await expect(view).toHaveURL(new RegExp(route + '$'));
        await expect(view.getByRole('heading', { name: 'Briefing : alerte civile', exact: true })).toBeVisible();
        await expect(view.getByText('Simulation sans perte réelle, sans carburant et sans butin.', { exact: true })).toBeVisible();
        await expect(view.getByRole('heading', { name: 'Rapport d’exercice', exact: true })).toHaveCount(0);
        expect(completed()).toBe('');
        const submitWithToken = async (token) => {
            const form = view.getByRole('region', { name: 'Briefing : alerte civile' }).locator('form');
            await form.locator('input[name="_token"]').evaluate((field, value) => { field.value = value; }, token);
            const response = view.waitForResponse(result => new URL(result.url()).pathname === route && result.request().method() === 'POST');
            await form.getByRole('button', { name: 'Lancer la simulation', exact: true }).click();
            return response;
        };
        const formToken = await view.getByRole('region', { name: 'Briefing : alerte civile' }).locator('input[name="_token"]').getAttribute('value');
        expect(formToken).toBeTruthy();
        const rejected = await submitWithToken('');
        expect(rejected.status()).toBe(403);
        expect(completed()).toBe('');
        await view.goto(route);

        const post = view.waitForResponse(response => new URL(response.url()).pathname === route && response.request().method() === 'POST');
        await view.getByRole('button', { name: 'Lancer la simulation', exact: true }).click();
        const result = await post;
        expect(result.status()).toBe(200);
        expect(result.headers()['cache-control']).toContain('no-store');
        const report = view.getByRole('region', { name: 'Rapport d’exercice', exact: true });
        await expect(report).toBeVisible();
        await expect(report).toContainText('Le jalon d’apprentissage est validé quel que soit le résultat');
        await expect(report).toContainText(/Escorte : [0-2] sur 2 en service, [0-2] perdues dans la simulation/);
        await report.getByText('Comprendre les tirs manche par manche', { exact: true }).click();
        await expect(report.getByText(/Manche 1 :/)).toBeVisible();
        await expect.poll(() => view.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(390);

        const first = completed();
        expect(first).toMatch(/^\d{4}-\d{2}-\d{2} /);
        await view.getByRole('button', { name: 'Rejouer la simulation', exact: true }).click();
        await expect(report).toBeVisible();
        expect(completed()).toBe(first);
        expect(assets()).toBe(before);
        await view.getByRole('link', { name: 'Retour aux Premiers pas', exact: true }).click();
        const exercise = view.getByRole('listitem').filter({ has: view.getByRole('heading', { name: 'Exercice pirate', exact: true }) });
        await expect(exercise).toContainText('Terminé');
        await expect(view.getByRole('link', { name: 'Préparer une colonisation', exact: true })).toBeVisible();
        await view.getByRole('link', { name: 'Rouvrir le briefing de l’exercice', exact: true }).click();
        await expect(view.getByRole('heading', { name: 'Rapport d’exercice', exact: true })).toHaveCount(0);

        // Un autre univers ne reçoit ni le rapport ni le jalon ; l’ancien formulaire ne peut pas y écrire.
        await view.goto('/univers');
        await view.locator('.universe-card').filter({ has: view.getByRole('heading', { name: /Turbo/ }) }).getByRole('button').click();
        await expect(view).toHaveURL(/planetes\/\d+$/);
        await view.goto(route);
        const oldForm = await submitWithToken(formToken);
        expect(oldForm.status()).toBe(403);
        await view.goto('/premiers-pas');
        await expect(view.getByRole('progressbar', { name: 'Progression des premiers pas' })).toHaveAttribute('value', '0');
        await view.goto(route);
        await expect(view.getByRole('heading', { name: 'Rapport d’exercice', exact: true })).toHaveCount(0);
    } finally {
        await native.close();
    }
});


test('Premiers pas : panneau non modal, images à la demande et reprise par Préférences', async ({ page, browser, baseURL }) => {
    await page.setViewportSize({ width: 1440, height: 900 });
    await register(page);
    const overview = page.url();
    const popup = page.locator('.first-steps-popup');
    const summary = popup.locator('summary');
    await expect(popup).toHaveAttribute('open', '');
    await expect(popup.getByText('Validation :')).toBeVisible();
    for (const [width, height] of [[1440, 900], [850, 900], [390, 844]]) {
        await page.setViewportSize({ width, height });
        const panel = popup.locator('.first-steps-popup-panel');
        const content = panel.locator('.first-steps-popup-content');
        const box = await panel.boundingBox();
        expect(box.x).toBeGreaterThanOrEqual(0);
        expect(box.x + box.width).toBeLessThanOrEqual(width);
        await expect.poll(() => content.evaluate(element => element.scrollWidth - element.clientWidth)).toBeLessThanOrEqual(1);
        expect((await new AxeBuilder({ page }).include('.first-steps-popup').analyze()).violations).toEqual([]);
    }
    // Une petite fenêtre force le défilement : le clavier doit atteindre la zone textuelle.
    await page.setViewportSize({ width: 390, height: 620 });
    const scrollRegion = popup.getByRole('region', { name: 'Instructions et illustrations', exact: true });
    await scrollRegion.focus();
    await expect(scrollRegion).toBeFocused();
    await page.keyboard.press('PageDown');
    await expect.poll(() => scrollRegion.evaluate(element => element.scrollTop)).toBeGreaterThan(0);
    await scrollRegion.evaluate(element => { element.scrollTop = 0; });
    await page.setViewportSize({ width: 1440, height: 900 });
    const first = popup.locator('figure').nth(0).locator('img');
    const second = popup.locator('figure').nth(1).locator('img');
    await expect(first).toHaveAttribute('src', /mine_metal/);
    await expect.poll(() => first.evaluate(img => img.complete && img.naturalWidth > 0)).toBe(true);
    await expect(popup.getByRole('button', { name: 'Désactiver le guide', exact: true })).toBeInViewport({ ratio: 1 });
    const captures = process.env.FIRST_STEPS_CAPTURES;
    if (captures) {
        await page.screenshot({ path: captures + '/GENESIS_PREMIERS_PAS_FENETRE_PC.png', animations: 'disabled' });
        await page.setViewportSize({ width: 390, height: 844 });
        await expect(popup.getByRole('button', { name: 'Désactiver le guide', exact: true })).toBeInViewport({ ratio: 1 });
        await expect(popup.getByRole('button', { name: 'Suivant', exact: true })).toBeInViewport({ ratio: 1 });
        await page.screenshot({ path: captures + '/GENESIS_PREMIERS_PAS_FENETRE_MOBILE.png', animations: 'disabled' });
        await page.setViewportSize({ width: 1440, height: 900 });
    }

    await expect(second).not.toHaveAttribute('src', /.+/);
    await popup.getByRole('button', { name: 'Suivant', exact: true }).click();
    await expect(second).toHaveAttribute('src', /ui-preview-colony/);
    await expect(popup.locator('output')).toHaveText('2 sur 2');
    await page.keyboard.press('Escape');
    await expect(popup).not.toHaveAttribute('open', '');
    await expect(summary).toBeFocused();
    await page.reload();
    await expect(popup).not.toHaveAttribute('open', '');
    await summary.click();
    await expect(popup.locator('output')).toHaveText('2 sur 2');
    await expect(page.locator('dialog[open]')).toHaveCount(0);

    // Une vraie navigation reste possible alors que le panneau est ouvert.
    await page.locator('#command-navigation').getByRole('link', { name: 'Bâtiments', exact: true }).click();
    await expect(page).toHaveURL(/section=buildings/);
    const staleSettings = await page.context().newPage();
    try {
        await staleSettings.goto('/profil/preferences');
        await expect(staleSettings.getByRole('checkbox', { name: 'Afficher le guide Premiers pas' })).toBeChecked();
        await page.goto(overview);
        await summary.click();
        await popup.getByRole('button', { name: 'Désactiver le guide', exact: true }).click();
        await expect(page).toHaveURL(/profil\/preferences/);
        // Enregistrer un autre réglage depuis la page devenue ancienne ne réactive pas l’aide.
        await staleSettings.getByRole('checkbox', { name: 'Réduire les animations de l’interface' }).check();
        await staleSettings.getByRole('button', { name: 'Enregistrer les paramètres' }).click();
        await expect(staleSettings.getByRole('status').filter({ hasText: 'Préférences enregistrées' })).toBeVisible();
        await expect(staleSettings.getByRole('checkbox', { name: 'Afficher le guide Premiers pas' })).not.toBeChecked();
        await expect(staleSettings.getByRole('checkbox', { name: 'Réduire les animations de l’interface' })).toBeChecked();
    } finally {
        await staleSettings.close();
    }
    const setting = page.getByRole('checkbox', { name: 'Afficher le guide Premiers pas' });
    await expect(setting).not.toBeChecked();
    await page.goto(overview);
    await expect(popup).toHaveCount(0);
    await expect(page.getByText('Guide désactivé.')).toBeVisible();
    await page.getByRole('link', { name: 'Réactiver dans Préférences' }).click();
    await setting.check();
    await page.getByRole('button', { name: 'Enregistrer le réglage du guide' }).click();
    await expect(setting).toBeChecked();
    await page.goto(overview);
    await expect(popup).not.toHaveAttribute('open', '');
    await summary.click();
    await expect(popup.locator('output')).toHaveText('2 sur 2');
    await popup.getByRole('link', { name: 'Guide complet et remboursements' }).click();

    const grid = page.locator('.first-steps-grid');
    for (const [width, columns] of [[1440, 4], [850, 2], [390, 1]]) {
        await page.setViewportSize({ width, height: 900 });
        expect(await grid.evaluate(node => getComputedStyle(node).gridTemplateColumns.split(' ').length)).toBe(columns);
        if (captures && width === 1440) {
            const visibleImages = grid.locator('img').and(page.locator('.first-steps-grid > li:nth-child(-n+4) img'));
            await visibleImages.evaluateAll(async images => {
                await Promise.all(images.map(image => image.decode()));
                await new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve)));
            });
            expect(await visibleImages.evaluateAll(images => images.every(image => {
                const rect = image.getBoundingClientRect();
                return getComputedStyle(image).visibility === 'visible' && rect.width > 0
                    && document.elementFromPoint(rect.left + rect.width / 2, rect.top + rect.height / 2) === image;
            }))).toBe(true);
            await page.screenshot({ path: captures + '/GENESIS_PREMIERS_PAS_GRILLE_PC.png', animations: 'disabled' });
        }
        await expect.poll(() => page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(width);
    }
    await expect(grid.locator('li').first().getByRole('heading')).toHaveText('Mine à ciel ouvert');

    const native = await browser.newContext({ baseURL, storageState: await page.context().storageState(), javaScriptEnabled: false });
    try {
        const fallback = await native.newPage();
        await fallback.goto(overview);
        await fallback.locator('.first-steps-popup summary').click();
        await expect(fallback.getByRole('link', { name: 'Ouvrir le visuel de cette étape' })).toHaveAttribute('href', /mine_metal/);
    } finally {
        await native.close();
    }
});


for (const exit of ['refus temporaire', 'lot déjà retiré', 'aucun dialogue natif']) {
    test('Premiers pas reprend quand Quotidiennes ne s’ouvre pas : ' + exit, async ({ page }) => {
        if (exit === 'aucun dialogue natif') {
            await page.addInitScript(() => { HTMLDialogElement.prototype.showModal = undefined; });
        } else {
            await page.route('**/recompense-quotidienne/panneau?*', route => route.fulfill(exit === 'refus temporaire'
                ? { status: 503, body: 'Indisponible pour la recette' }
                : { status: 200, contentType: 'text/html', body:
                    '<dialog id="daily-reward-dialog" data-daily-reward-ui-target="dialog">Lot déjà récupéré</dialog>' }));
        }
        await register(page, { closeDaily: false });
        await expect(page.locator('.first-steps-popup')).toHaveAttribute('open', '');
        await expect(page.locator('dialog[open]')).toHaveCount(0);
        const gift = page.locator('.topbar').getByRole('link', { name: 'Quotidiennes', exact: true });
        if (exit === 'lot déjà retiré') {
            await expect(gift).toBeHidden();
        } else {
            await expect(gift).toBeVisible();
        }
    });
}
