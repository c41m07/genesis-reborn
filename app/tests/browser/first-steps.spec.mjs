import AxeBuilder from '@axe-core/playwright';
import { test, expect } from './support/isolated-test.mjs';
import { accelerateQueuedItem, queryIsolatedSql, revealVirginPlanet, runIsolatedSql } from './support/game-fixtures.mjs';

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
        await expect(popup.locator('.first-steps-preview')).toBeHidden();
        await dialog.getByRole('button', { name: 'Plus tard', exact: true }).click();
        await expect(dialog).toBeHidden();
        await expect(popup.locator('.first-steps-preview')).toBeVisible();
    }

    return email;
}

async function openIllustrations(popup) {
    // La classe signale la connexion de Stimulus et l'initialisation synchrone de details.open.
    await expect(popup).toHaveClass(/first-steps-popup-enhanced/);
    const preview = popup.locator('.first-steps-preview');
    if (await popup.evaluate(element => element.open)) {
        await preview.getByRole('button', { name: 'Voir l’étape illustrée', exact: true }).press('Enter');
    } else {
        await popup.locator('summary').press('Enter');
    }
    await expect(popup.locator('[data-first-steps-guide-fragment]')).toBeVisible();
}

test('Premiers pas : conseil facultatif et progression après la fin réelle de la mine', async ({ page, browser, baseURL }, info) => {
    test.setTimeout(120000);
    const email = await register(page);
    const overview = page.url();
    await expect(page.locator('.topbar .universe-context + .header-technology-goal')).toBeVisible();
    await expect(page.locator('#overview-first-steps-title')).toHaveCount(0);
    const popup = page.locator('.first-steps-popup');
    const summary = popup.locator('summary');
    const guideLink = popup.getByRole('link', { name: 'Guide complet et remboursements', exact: true });
    await expect(popup.locator('.first-steps-preview')).toBeVisible();
    await openIllustrations(popup);
    await expect(popup).toHaveAttribute('open', '');
    await expect(popup).toContainText('0/12');
    await expect(popup).toContainText('Mine à ciel ouvert');
    // Faire la même requête que le contrôleur live depuis le navigateur et sa session.
    const refresh = await page.evaluate(async () => {
        const response = await fetch(location.href, { headers: { 'X-Genesis-Live': '1', Accept: 'application/json' } });
        return { ok: response.ok, type: response.headers.get('Content-Type'), text: await response.text() };
    });
    expect(refresh.ok).toBe(true);
    expect(refresh.type).toContain('application/json');
    expect(refresh.text).not.toContain('first-steps-overview');
    await page.setViewportSize({ width: 1440, height: 900 });
    await expect(summary).toHaveAttribute('aria-controls', 'first-steps-popup-panel');
    expect((await summary.boundingBox())?.width).toBeGreaterThanOrEqual(44);
    await page.setViewportSize({ width: 390, height: 844 });
    expect((await summary.boundingBox())?.height).toBeGreaterThanOrEqual(44);
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
    const nextAction = guide.getByRole('region', { name: 'Prochaine étape conseillée' });
    await expect(nextAction.getByRole('link')).toHaveAttribute('href', /section=buildings.*#item-mine_metal-basic$/);
    await expect(guide.locator('.first-steps-next .primary')).toHaveCount(1);
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
    await expect(popup.locator('.first-steps-preview')).toBeVisible();
    await openIllustrations(popup);
    await guideLink.click();
    await guide.getByRole('region', { name: 'Prochaine étape conseillée', exact: true })
        .getByRole('link', { name: 'Ouvrir la Mine à ciel ouvert', exact: true }).click();
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

test('Premiers pas : la prochaine action précède deux remboursements natifs disponibles', async ({ page, browser, baseURL }) => {
    test.setTimeout(120000);
    const email = await register(page);
    expect(email).toMatch(/^first-steps-[a-f0-9]{8}@example[.]test$/);
    const project = process.env.BROWSER_ROUTER;
    const [player, member, planet] = queryIsolatedSql(project,
        "SELECT p.id, p.current_player_universe_id, planet.id FROM player p JOIN planet ON planet.owner_id = p.current_player_universe_id WHERE p.email = '" + email + "'"
    ).split(/\s+/).map(Number);
    expect([player, member, planet].every(id => Number.isSafeInteger(id) && id > 0)).toBe(true);
    // Des preuves synthétiques coexistantes ; le scénario de construction réelle couvre leur émission.
    runIsolatedSql(project, "INSERT INTO first_step_completion (player_universe_id, step_key, completed_at) VALUES (" + member + ", 'metal_mine', UTC_TIMESTAMP()), (" + member + ", 'research', UTC_TIMESTAMP())");
    runIsolatedSql(project, "INSERT INTO first_step_reward (player_id, source_membership_id, step_key, metal, nano) VALUES (" + player + ", " + member + ", 'metal_mine', 96, 52), (" + player + ", " + member + ", 'research', 240, 130)");

    const native = await browser.newContext({ baseURL, storageState: await page.context().storageState(), javaScriptEnabled: false });
    try {
        const fallback = await native.newPage();
        await fallback.setViewportSize({ width: 390, height: 844 });
        await fallback.goto('/premiers-pas');
        const next = fallback.getByRole('region', { name: 'Prochaine étape conseillée', exact: true });
        const refunds = fallback.getByRole('region', { name: 'Remboursements disponibles', exact: true });
        await expect(next.getByRole('link', { name: 'Ouvrir la Centrale nucléaire', exact: true }))
            .toHaveAttribute('href', /section=buildings.*#item-power_plant-basic$/);
        await expect(next.locator('.primary')).toHaveCount(1);
        expect(await next.evaluate(section => Boolean(
            section.compareDocumentPosition(document.querySelector('#training-reward-title')) & Node.DOCUMENT_POSITION_FOLLOWING
        ))).toBe(true);
        expect((await next.boundingBox())?.y).toBeLessThan((await refunds.boundingBox())?.y);
        const colony = await fallback.getByRole('link', { name: 'Retour à la vue générale', exact: true }).getAttribute('href');
        expect(colony).toBe('/planetes/' + planet);
        const colonyName = queryIsolatedSql(project, 'SELECT name FROM planet WHERE id = ' + planet);
        expect(colonyName).not.toBe('');
        await expect(refunds.locator('#training-refund-destination strong')).toHaveText(colonyName);
        await expect(refunds.locator('#first-step-refund-metal_mine')).toContainText('96 métal et 52 nanos');
        await expect(refunds.locator('#first-step-refund-research')).toContainText('240 métal et 130 nanos');
        const metal = refunds.locator('#first-step-refund-metal_mine').getByRole('button', { name: /^Récupérer :/ });
        const research = refunds.locator('#first-step-refund-research').getByRole('button', { name: /^Récupérer :/ });
        for (const button of [metal, research]) {
            await expect(button).toBeEnabled();
            await expect(button).toHaveAccessibleDescription(new RegExp('^Destination :'));
            await button.click();
            await expect(fallback.getByRole('status').filter({ hasText: 'Remboursement ajouté aux stocks' })).toBeVisible();
        }
        await expect(refunds).toHaveCount(0);
        await expect(next.getByRole('link', { name: 'Ouvrir la Centrale nucléaire', exact: true })).toBeEnabled();
    } finally {
        await native.close();
    }
});

test('Exercice pirate : privé, formulaire natif, rejouable sans récompense', async ({ page, browser, baseURL }, info) => {
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
        const summary = report.getByRole('table', { name: 'Survivants et pertes par camp' });
        await expect(summary.getByRole('columnheader', { name: 'Survivants' })).toBeVisible();
        await expect(summary.getByRole('columnheader', { name: 'Pertes simulées' })).toBeVisible();
        for (const side of ['Escorte', 'Pirates']) {
            const row = summary.getByRole('row').filter({ has: view.getByRole('rowheader', { name: side, exact: true }) });
            await expect(row).toHaveCount(1);
            const values = await row.getByRole('cell').allTextContents();
            expect(values).toHaveLength(2);
            expect(values[0]).toMatch(/^[0-2] sur 2$/);
            expect(values[1]).toMatch(/^[0-2]$/);
            expect(Number(values[0][0]) + Number(values[1])).toBe(2);
        }
        const captureReport = async (tab, theme) => {
            await expect(tab.locator('body')).toHaveAttribute('data-theme', theme);
            const resultCard = tab.getByRole('region', { name: 'Rapport d’exercice', exact: true });
            await expect(resultCard.getByRole('heading', { name: 'Simulation pédagogique', exact: true })).toBeVisible();
            await expect(tab.getByRole('button', { name: 'Rejouer la simulation', exact: true })).toHaveCount(1);
            await expect(resultCard.getByRole('button', { name: 'Rejouer la simulation', exact: true })).toHaveAttribute('aria-describedby', 'training-rules');
            await expect(resultCard.locator('.first-steps-exercise-metric > dd').filter({ hasText: /^[0-2] \/ 2$/ })).toHaveCount(2);
            for (const viewport of [{ width: 390, height: 844 }, { width: 1920, height: 1080 }]) {
                await tab.setViewportSize(viewport);
                await expect.poll(() => tab.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(viewport.width);
                await tab.evaluate(() => window.scrollTo({ top: 0, behavior: 'instant' }));
                await tab.screenshot({ path: info.outputPath('P21-B-resultat-' + theme + '-' + viewport.width + '.png'), animations: 'disabled', scale: 'css' });
                if (viewport.width === 390) {
                    await resultCard.getByRole('button', { name: 'Rejouer la simulation', exact: true }).scrollIntoViewIfNeeded();
                    await tab.screenshot({ path: info.outputPath('P21-B-actions-' + theme + '-390.png'), animations: 'disabled', scale: 'css' });
                    expect((await new AxeBuilder({ page: tab }).include('.first-steps-exercise-notice').include('.first-steps-exercise-metrics').analyze()).violations).toEqual([]);
                }
            }
            await tab.setViewportSize({ width: 390, height: 844 });
        };
        await captureReport(view, 'dark');
        await report.getByText('Comprendre les tirs manche par manche', { exact: true }).click();
        await expect(report.getByText(/Manche 1 :/)).toBeVisible();
        await expect.poll(() => view.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(390);

        const first = completed();
        expect(first).toMatch(/^\d{4}-\d{2}-\d{2} /);
        await view.getByRole('button', { name: 'Rejouer la simulation', exact: true }).click();
        await expect(report).toBeVisible();
        expect(completed()).toBe(first);
        expect(assets()).toBe(before);
        await view.goto('/profil/preferences');
        await view.getByLabel('Thème de l’interface').selectOption('light');
        await view.getByRole('button', { name: 'Enregistrer les paramètres' }).click();
        await expect(view.locator('body')).toHaveAttribute('data-theme', 'light');
        await view.goto(route);
        await view.getByRole('button', { name: 'Lancer la simulation', exact: true }).press('Enter');
        await captureReport(view, 'light');

        // Avec Stimulus actif le guide s’ouvre, mais le rejeu reste cliquable dans les deux tailles.
        await page.goto(route);
        await page.getByRole('button', { name: 'Lancer la simulation', exact: true }).click();
        const guide = page.locator('.first-steps-popup-enhanced');
        const toggle = guide.locator(':scope > summary');
        await expect(toggle).toBeVisible();
        if (await guide.getAttribute('open') === null) await toggle.press('Enter');
        await expect(guide).toHaveAttribute('open', '');
        const replay = page.getByRole('button', { name: 'Rejouer la simulation', exact: true });
        for (const viewport of [{ width: 390, height: 844 }, { width: 1920, height: 1080 }]) {
            await page.setViewportSize(viewport);
            await replay.evaluate(element => element.scrollIntoView({ block: 'center', behavior: 'instant' }));
            await expect.poll(() => replay.evaluate(element => {
                const button = element.getBoundingClientRect();
                const hit = document.elementFromPoint(button.x + button.width / 2, button.y + button.height / 2);
                const shell = document.querySelector('.first-steps-exercise-layout.has-report').getBoundingClientRect();
                const guide = document.querySelector('.first-steps-popup-enhanced .first-steps-popup-mount').getBoundingClientRect();
                return (element === hit || element.contains(hit)) && guide.top >= shell.bottom;
            })).toBe(true);
        }
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


test('Premiers pas : panneau non modal, images à la demande et reprise par Préférences', async ({ page, browser, baseURL }, info) => {
    await page.setViewportSize({ width: 1440, height: 900 });
    await register(page);
    const overview = page.url();
    const popup = page.locator('.first-steps-popup');
    const summary = popup.locator('summary');
    await openIllustrations(popup);
    await expect(popup.getByText('Validation :')).toBeVisible();
    for (const [width, height] of [[1440, 900], [850, 900], [390, 844]]) {
        await page.setViewportSize({ width, height });
        const panel = popup.locator('[data-first-steps-guide-fragment]');
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
    await expect(first).toHaveAttribute('src', /mine-fiche[.]webp$/);
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
    await expect(second).toHaveAttribute('src', /mine-resultat[.]webp$/);
    await expect(popup.locator('output')).toHaveText('2 sur 2');
    await page.setViewportSize({ width: 1920, height: 1080 });
    await page.screenshot({ path: info.outputPath('P09-illustration-1920x1080.png'), scale: 'css', animations: 'disabled' });
    await page.setViewportSize({ width: 1440, height: 900 });
    await page.keyboard.press('Escape');
    await expect(popup).not.toHaveAttribute('open', '');
    await expect(summary).toBeFocused();
    await expect(summary).toHaveAttribute('aria-label', 'Ouvrir Premiers pas');
    await expect(summary).toHaveAttribute('aria-expanded', 'false');
    const tabBox = await summary.boundingBox();
    expect(tabBox.x + tabBox.width).toBeCloseTo(1440, 0);
    if (captures) await page.screenshot({ path: captures + '/GENESIS_PREMIERS_PAS_LANGUETTE_PC.png', animations: 'disabled' });
    await page.reload();
    await expect(popup).not.toHaveAttribute('open', '');
    await summary.press('Enter');
    await expect(summary).toHaveAttribute('aria-expanded', 'true');
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
        await expect(popup.locator('.first-steps-preview')).toBeVisible();
        await openIllustrations(popup);
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
    await expect(page.locator('#overview-first-steps-title')).toHaveCount(0);
    await page.locator('.topbar').getByRole('link', { name: 'Paramètres', exact: true }).click();
    await page.getByRole('link', { name: 'Voir le guide complet et les remboursements', exact: true }).click();
    await expect(page.getByRole('heading', { name: 'Premiers pas', exact: true })).toBeVisible();
    await page.goBack();
    await expect(setting).not.toBeChecked();
    await setting.check();
    await page.getByRole('button', { name: 'Enregistrer le réglage du guide' }).click();
    await expect(setting).toBeChecked();
    await page.goto(overview);
    await expect(popup.locator('.first-steps-preview')).toBeVisible();
    await openIllustrations(popup);
    await expect(popup.locator('output')).toHaveText('2 sur 2');
    await popup.getByRole('link', { name: 'Guide complet et remboursements' }).click();

    const grid = page.locator('.first-steps-grid');
    for (const [width, columns] of [[390, 1], [1440, 4], [850, 2], [390, 1]]) {
        await page.setViewportSize({ width, height: 900 });
        // Le viewport peut changer avant que Chromium ait appliqué la nouvelle media query.
        await expect.poll(() => grid.evaluate(node => getComputedStyle(node).gridTemplateColumns.trim().split(/\s+/).length)).toBe(columns);
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
        await expect(fallback.getByRole('link', { name: 'Guide complet et remboursements' })).toHaveAttribute('href', '/premiers-pas');
    } finally {
        await native.close();
    }
});


for (const exit of ['refus temporaire', 'lot déjà retiré', 'aucun dialogue natif']) {
    test('Premiers pas affiche son aperçu même si Quotidiennes ne s’ouvre pas : ' + exit, async ({ page }) => {
        if (exit === 'aucun dialogue natif') {
            await page.addInitScript(() => { HTMLDialogElement.prototype.showModal = undefined; });
        } else {
            await page.route('**/recompense-quotidienne/panneau?*', route => route.fulfill(exit === 'refus temporaire'
                ? { status: 503, body: 'Indisponible pour la recette' }
                : { status: 200, contentType: 'text/html', body:
                    '<dialog id="daily-reward-dialog" data-daily-reward-ui-target="dialog">Lot déjà récupéré</dialog>' }));
        }
        await register(page, { closeDaily: false });
        const popup = page.locator('.first-steps-popup');
        await expect(popup.locator('.first-steps-preview')).toBeVisible();
        await expect(popup.locator('img[src]')).toHaveCount(0);
        await expect(page.locator('dialog[open]')).toHaveCount(0);
        const gift = page.locator('.topbar').getByRole('link', { name: 'Quotidiennes', exact: true });
        if (exit === 'lot déjà retiré') {
            await expect(gift).toBeHidden();
        } else {
            await expect(gift).toBeVisible();
        }
        await openIllustrations(popup);
        await expect(popup.getByRole('heading', { name: 'Mine à ciel ouvert' })).toBeVisible();
    });
}

test('Un clic natif avant Stimulus reste une ouverture volontaire du guide', async ({ page }) => {
    await register(page);
    const overview = new URL(page.url()).pathname;
    let resume;
    const javascriptReady = new Promise(resolve => { resume = resolve; });
    const chunks = /\/build\/.*\.js(?:\?.*)?$/;
    const hold = async route => { await javascriptReady; await route.continue(); };
    await page.route(chunks, hold);
    try {
        await page.goto(overview, { waitUntil: 'commit' });
        const popup = page.locator('.first-steps-popup');
        const arrow = popup.locator('summary');
        await expect(arrow).toBeVisible();
        await expect(popup).not.toHaveClass(/first-steps-popup-enhanced/);
        await arrow.press('Enter');
        await expect(popup).toHaveAttribute('open', '');
        await expect(popup.getByRole('link', { name: 'Guide complet et remboursements' })).toBeVisible();
        resume();
        await expect(popup.locator('[data-first-steps-guide-fragment]')).toBeVisible();
        await expect(popup.locator('.first-steps-preview')).toBeHidden();
        await expect(arrow).toBeFocused();
    } finally {
        resume();
        await page.unroute(chunks, hold);
    }
});

test('Sur Galaxie, l’aperçu et ses commandes restent hors de la carte', async ({ page }) => {
    await register(page);
    await page.goto('/carte-v2');
    const popup = page.locator('.first-steps-popup');
    const preview = popup.locator('.first-steps-preview');
    const canvas = page.locator('.gv2-canvas--galaxy');
    for (const width of [1440, 390]) {
        await page.setViewportSize({ width, height: 844 });
        await expect(preview).toBeVisible();
        await expect(preview.getByRole('button', { name: 'Voir l’étape illustrée' })).toBeEnabled();
        await expect.poll(async () => {
            const [panel, map] = await Promise.all([popup.boundingBox(), canvas.boundingBox()]);
            return panel !== null && map !== null && panel.y + panel.height <= map.y;
        }).toBe(true);
    }
    await openIllustrations(popup);
    await popup.getByRole('button', { name: 'Fermer', exact: true }).click();
    await expect(popup).not.toHaveAttribute('open', '');
    await expect(popup.locator('summary')).toBeFocused();
});

test('L’aperçu ne charge ni fragment ni image et reste fermé dans le même onglet après fermeture', async ({ page }) => {
    const requests = [];
    page.on('request', request => {
        const path = new URL(request.url()).pathname;
        if (path === '/premiers-pas/panneau' || path.startsWith('/art/first-steps/')) requests.push(path);
    });
    await register(page);
    expect(requests).toHaveLength(0);
    const overview = new URL(page.url());
    const pages = [
        overview.pathname,
        overview.pathname + '?section=buildings',
        overview.pathname + '?section=technologies',
        '/flottes',
        '/planetes',
        '/rapports',
        '/carte-v2',
    ];
    const popup = page.locator('.first-steps-popup');
    const arrow = popup.locator('summary');
    const preview = popup.locator('.first-steps-preview');
    for (const width of [1440, 390]) {
        await page.setViewportSize({ width, height: 844 });
        await expect(preview).toBeVisible();
        await expect(arrow).not.toBeFocused();
        await expect(popup.locator('img[src]')).toHaveCount(0);
        await expect.poll(() => preview.getByRole('heading', { name: 'Mine à ciel ouvert' }).evaluate(node => {
            const box = node.getBoundingClientRect();
            const underneath = document.elementFromPoint(box.x + box.width / 2, box.y + box.height / 2);
            return underneath !== null && !node.closest('.first-steps-popup').contains(underneath);
        })).toBe(true);
        expect((await new AxeBuilder({ page }).include('.first-steps-popup').analyze()).violations).toEqual([]);
    }
    expect(requests).toHaveLength(0);
    await arrow.press('Enter');
    await expect(popup).not.toHaveAttribute('open', '');
    await page.reload();
    await expect(popup).not.toHaveAttribute('open', '');
    const other = await page.context().newPage();
    try {
        await other.goto('/flottes');
        await expect(other.locator('.first-steps-preview')).toBeVisible();
        await expect(other.locator('.first-steps-popup img[src]')).toHaveCount(0);
    } finally {
        await other.close();
    }

    for (const [index, location] of pages.entries()) {
        await page.setViewportSize(index % 2 ? { width: 390, height: 844 } : { width: 1440, height: 900 });
        const before = requests.length;
        await page.goto(location);
        await expect(arrow).toBeVisible();
        await expect(popup).not.toHaveAttribute('open', '');
        await expect(popup.locator('figure')).toHaveCount(0);

        expect(requests).toHaveLength(before);
        await arrow.press('Enter');
        await expect(popup.getByRole('heading', { name: 'Mine à ciel ouvert' })).toBeVisible();
        // Attendre aussi la demande d’image de cette ouverture avant de compter les suivantes.
        await expect.poll(() => popup.locator('figure:visible img').evaluate(img => img.complete && img.naturalWidth > 0)).toBe(true);
        expect(requests.slice(before).filter(path => path === '/premiers-pas/panneau')).toHaveLength(1);
        await page.keyboard.press('Escape');
        await expect(popup).not.toHaveAttribute('open', '');
        await expect(arrow).toBeFocused();
    }

    // Un réglage de compte, le sélecteur et une page publique ne sont pas des écrans de jeu.
    const before = requests.length;
    for (const location of ['/profil/preferences', '/univers', '/mentions-legales']) {
        await page.goto(location);
        await expect(popup).toHaveCount(0);
    }
    expect(requests).toHaveLength(before);
});

test('Le dernier vrai jalon masque la languette dans cet empire, sans retirer le remboursement', async ({ page }) => {
    test.setTimeout(120000);
    const email = await register(page);
    const origin = new URL(page.url()).pathname;
    const project = process.env.BROWSER_ROUTER;
    const colony = Number(origin.split('/').pop());
    const member = Number(queryIsolatedSql(project, "SELECT owner_id FROM planet WHERE id=" + colony));
    expect(member).toBeGreaterThan(0);

    // Acquérir la mine et son remboursement par le même parcours que le joueur.
    await page.goto(origin + '?section=buildings&focusItem=1&tutorial=mine_metal#item-mine_metal-basic');
    const mine = page.locator('#item-mine_metal-basic');
    await mine.getByRole('button', { name: 'Améliorer au niveau 1', exact: true }).click();
    accelerateQueuedItem(project, email, 'building', 'mine_metal');
    await page.goto(origin + '?section=buildings');
    await expect(page.locator('#building-tab-mine_metal-basic .building-rank-level')).toHaveText('1');

    // Les dix autres jalons du passé sont préparés en DB jetable. L’exercice manquant est lancé dans le vrai formulaire.
    const past = ['power_plant', 'nano_workshop', 'metal_storage', 'laboratory', 'research', 'assembly_yard',
        'ship', 'exploration', 'espionage', 'colonization'];
    runIsolatedSql(project, 'INSERT INTO first_step_completion (player_universe_id,step_key,completed_at) VALUES '
        + past.map(step => '(' + member + ",'" + step + "',UTC_TIMESTAMP())").join(','));
    const second = revealVirginPlanet(project, email);
    runIsolatedSql(project, "UPDATE planet SET owner_id=" + member + ", acquired_at=UTC_TIMESTAMP(), name='Colonie exemple' WHERE id=" + second.id);

    await page.goto('/premiers-pas/exercice-pirate');
    const popup = page.locator('.first-steps-popup');
    await expect(popup.locator('summary')).toBeVisible();
    const panelUrl = await popup.getAttribute('data-first-steps-guide-url-value');
    await page.getByRole('button', { name: 'Lancer la simulation' }).click();
    await expect(page.getByRole('heading', { name: 'Rapport d’exercice' })).toBeVisible();
    await expect(popup).toHaveCount(0);
    expect(await page.evaluate(async url => (await fetch(url)).status, panelUrl)).toBe(204);

    for (const href of ['/flottes', origin, '/planetes/' + second.id, '/rapports']) {
        await page.goto(href);
        await expect(popup).toHaveCount(0);
    }
    await page.goto(origin);
    await page.goto('/profil/preferences');
    await page.getByRole('link', { name: 'Voir le guide complet et les remboursements' }).click();
    await expect(page.getByRole('progressbar', { name: 'Progression des premiers pas' })).toHaveAttribute('value', '12');
    const claim = page.getByRole('button', { name: 'Récupérer : Mine à ciel ouvert' });
    await expect(claim).toBeVisible();
    await claim.click();
    await expect(page.getByRole('status').filter({ hasText: 'Remboursement ajouté aux stocks' })).toBeVisible();

    await page.goto('/univers');
    await page.locator('.universe-card').filter({ has: page.getByRole('heading', { name: /Turbo/ }) }).getByRole('button').click();
    await expect(popup.locator('summary')).toBeVisible();
    // Le fragment d’un onglet de l’ancien univers ne réutilise pas sa colonie dans le nouveau.
    expect(await page.evaluate(async url => (await fetch(url, { redirect: 'manual' })).status, panelUrl)).toBe(404);
});

test('Un guide fermé relit le jalon acquis dans un autre onglet, puis masque une désactivation', async ({ page }) => {
    test.setTimeout(120000);
    const email = await register(page);
    const overview = new URL(page.url()).pathname;
    const popup = page.locator('.first-steps-popup');
    const arrow = popup.locator('summary');
    await openIllustrations(popup);
    await expect(popup.getByRole('heading', { name: 'Mine à ciel ouvert' })).toBeVisible();
    await popup.getByRole('button', { name: 'Fermer', exact: true }).click();
    const reads = [];
    page.on('request', request => {
        if (new URL(request.url()).pathname === '/premiers-pas/panneau') reads.push(request.url());
    });
    const other = await page.context().newPage();
    try {
        await other.goto(overview + '?section=buildings&focusItem=1&tutorial=mine_metal#item-mine_metal-basic');
        await other.locator('#item-mine_metal-basic').getByRole('button', { name: 'Améliorer au niveau 1', exact: true }).click();
        accelerateQueuedItem(process.env.BROWSER_ROUTER, email, 'building', 'mine_metal');
        await other.goto(overview + '?section=buildings');
        await expect(other.locator('#building-tab-mine_metal-basic .building-rank-level')).toHaveText('1');
        expect(reads).toHaveLength(0);

        await arrow.press('Enter');
        await expect(popup.getByRole('heading', { name: 'Centrale nucléaire et bilan énergie' })).toBeVisible();
        expect(reads).toHaveLength(1);
        await popup.getByRole('button', { name: 'Fermer', exact: true }).click();
        await other.goto('/profil/preferences');
        await other.getByRole('checkbox', { name: 'Afficher le guide Premiers pas' }).uncheck();
        await other.getByRole('button', { name: 'Enregistrer le réglage du guide' }).click();
        await expect(other.getByRole('status').filter({ hasText: 'Guide désactivé' })).toBeVisible();
        expect(reads).toHaveLength(1);

        await arrow.press('Enter');
        await expect(popup).toBeHidden();
        await expect(page.locator('#main')).toBeFocused();
        await expect(arrow).not.toBeVisible();
        expect(reads).toHaveLength(2);
    } finally {
        await other.close();
    }
});

test('Le guide qui échoue reste réessayable et un 204 tardif ne déplace pas un focus extérieur', async ({ page }) => {
    await register(page);
    const overview = new URL(page.url()).pathname;
    await page.goto(overview + '?section=buildings');
    const popup = page.locator('.first-steps-popup');
    const arrow = popup.locator('summary');
    let requests = 0;
    let release;
    const hold = new Promise(resolve => { release = resolve; });
    await page.route('**/premiers-pas/panneau?*', async route => {
        requests++;
        if (requests === 1) {
            await route.fulfill({ status: 503 });
        } else {
            await hold;
            await route.fulfill({ status: 204 });
        }
    });
    try {
        await popup.getByRole('button', { name: 'Voir l’étape illustrée', exact: true }).press('Enter');
        await expect(popup.getByRole('status')).toContainText('Impossible de charger');
        await expect(popup.getByRole('link', { name: 'Guide complet et remboursements' })).toBeVisible();
        await page.keyboard.press('Escape');
        await expect(arrow).toBeFocused();
        await expect(popup).not.toHaveAttribute('open', '');
        await arrow.press('Enter');
        await expect(popup.getByRole('status')).toContainText('Chargement');
        const outside = page.locator('.topbar').getByRole('link', { name: 'Paramètres' });
        await outside.focus();
        release();
        await expect(popup).toBeHidden();
        await expect(outside).toBeFocused();
        expect(requests).toBe(2);
    } finally {
        release();
    }
});

test('Les douze gestes ont leur carte annotée ; l’agrandissement reste dans le panneau', async ({ page }) => {
    test.setTimeout(120000);
    await page.setViewportSize({ width: 390, height: 844 });
    await register(page);
    const popup = page.locator('.first-steps-popup');
    const arrow = popup.locator('summary');
    await openIllustrations(popup);
    const project = process.env.BROWSER_ROUTER;
    const colony = Number(new URL(page.url()).pathname.split('/').pop());
    const member = Number(queryIsolatedSql(project, 'SELECT owner_id FROM planet WHERE id=' + colony));
    expect(member).toBeGreaterThan(0);
    const examples = [
        ['metal_mine', 'mine-fiche', 'Améliorer au niveau 1'],
        ['power_plant', 'centrale-fiche', 'Améliorer au niveau 1'],
        ['nano_workshop', 'nano-fiche', 'Améliorer au niveau 1'],
        ['metal_storage', 'depot-fiche', 'Mine à ciel ouvert'],
        ['laboratory', 'laboratoire-fiche', 'Améliorer au niveau 1'],
        ['research', 'recherche-fiche', 'Rechercher le niveau 1'],
        ['assembly_yard', 'chantier-fiche', 'Améliorer au niveau 1'],
        ['ship', 'sonde-fiche', 'Construire'],
        ['exploration', 'exploration-fiche', 'Vérifier la mission'],
        ['espionage', 'espionnage-fiche', 'Vérifier la mission'],
        ['pirate_exercise', 'exercice-fiche', 'Lancer la simulation'],
        ['colonization', 'colonisation-fiche', 'Vérifier la mission'],
    ];

    for (const [step, image, exactControl] of examples) {
        const active = popup.locator('figure:visible');
        await expect(active.locator('figcaption')).toContainText(exactControl);
        const picture = active.locator('img');
        await expect(picture).toHaveAttribute('src', new RegExp('/' + image + '[.]webp$'));
        await expect(picture).toHaveAttribute('alt', /.+/);
        await expect.poll(() => picture.evaluate(img => img.complete && img.naturalWidth > 0)).toBe(true);
        await expect(active.locator('.first-steps-gesture-arrow')).toHaveAttribute('aria-hidden', 'true');
        // Le repère reste visible dans l’aperçu, avant tout défilement ou agrandissement.
        await expect(active.locator('.first-steps-gesture-arrow')).toBeInViewport({ ratio: 0.8 });
        const zoom = active.getByRole('button', { name: 'Voir en grand', exact: true });
        await expect(zoom).toHaveAttribute('aria-expanded', 'false');

        if (step === 'metal_mine') {
            const normalWidth = (await picture.boundingBox()).width;
            const ownImageUrl = await picture.getAttribute('src');
            await zoom.press('Enter');
            const collapse = active.getByRole('button', { name: 'Réduire l’image', exact: true });
            await expect(collapse).toBeFocused();
            await expect(collapse).toHaveAttribute('aria-expanded', 'true');
            await expect.poll(async () => (await picture.boundingBox()).width).toBeGreaterThan(normalWidth + 20);
            await expect(picture).toHaveAttribute('src', ownImageUrl);
            await expect(page.locator('dialog[open]')).toHaveCount(0);
            if (process.env.FIRST_STEPS_CAPTURES) {
                await active.locator('.first-steps-gesture-arrow').scrollIntoViewIfNeeded();
                await expect(active.locator('.first-steps-gesture-arrow')).toBeInViewport();
                await page.screenshot({ path: process.env.FIRST_STEPS_CAPTURES + '/GENESIS_PREMIERS_PAS_MINE_ZOOM_MOBILE.png', animations: 'disabled' });
            }
            await page.keyboard.press('Escape');
            await expect(zoom).toBeFocused();
            await expect(popup).toHaveAttribute('open', '');
            await expect(zoom).toHaveAttribute('aria-expanded', 'false');
        }
        if (step === 'pirate_exercise') {
            for (const width of [390, 1440]) {
                await page.setViewportSize({ width, height: 900 });
                const initial = (await picture.boundingBox()).width;
                await zoom.press('Enter');
                const collapse = active.getByRole('button', { name: 'Réduire l’image', exact: true });
                await expect(collapse).toBeFocused();
                await expect.poll(async () => (await picture.boundingBox()).width).toBeGreaterThan(initial + 20);
                await page.keyboard.press('Escape');
                await expect(zoom).toBeFocused();
                await expect.poll(async () => (await picture.boundingBox()).width).toBeCloseTo(initial, 0);
            }
            await page.setViewportSize({ width: 390, height: 844 });
        }

        await expect(popup.getByRole('button', { name: 'Suivant', exact: true })).toBeInViewport();
        if (step !== 'colonization') {
            await popup.getByRole('button', { name: 'Fermer', exact: true }).click();
            // Fixtures de présentation seulement : la validation métier réelle est couverte séparément.
            runIsolatedSql(project, "INSERT IGNORE INTO first_step_completion (player_universe_id,step_key,completed_at) VALUES ("
                + member + ",'" + step + "',UTC_TIMESTAMP())");
            await arrow.press('Enter');
        }
    }
});

test('La deuxième vue reste accessible tant que la première image attend', async ({ page }) => {
    let release;
    const hold = new Promise(resolve => { release = resolve; });
    let firstRequested = 0;
    await page.route('**/art/first-steps/mine-fiche.webp', async route => {
        firstRequested++;
        // Une réponse qui n’arrive jamais ne doit arrêter ni le texte ni la navigation.
        await hold;
        await route.abort().catch(() => {});
    });
    try {
        await register(page);
        const popup = page.locator('.first-steps-popup');
        await openIllustrations(popup);
        const first = popup.locator('figure').nth(0);
        const second = popup.locator('figure').nth(1);
        await expect(first.getByText('Améliorer au niveau 1', { exact: false }).first()).toBeVisible();
        await expect.poll(() => firstRequested).toBe(1);
        expect(await first.locator('img').evaluate(img => img.complete)).toBe(false);
        await expect(second.locator('img')).not.toHaveAttribute('src', /.+/);

        await popup.getByRole('button', { name: 'Suivant', exact: true }).click();
        await expect(popup.locator('output')).toHaveText('2 sur 2');
        await expect(first).toBeHidden();
        await expect(first.locator('img')).not.toHaveAttribute('src', /.+/);
        await expect(second.getByText('Niveau actuel 1', { exact: false })).toBeVisible();
        await expect.poll(() => second.locator('img').evaluate(img => img.complete && img.naturalWidth > 0)).toBe(true);
    } finally {
        release();
    }
});

test('Événements passe devant le guide volontairement ouvert et restaure son propre focus', async ({ page }) => {
    await register(page);
    runIsolatedSql(process.env.BROWSER_ROUTER, "INSERT INTO game_event (name,description,type,status,starts_at,ends_at,parameters,visible_to_players) VALUES " +
        [1, 2, 3, 4].map(number => "('Priorité Événements " + number + "','Annonce de recette privée','announcement','active',UTC_TIMESTAMP()-INTERVAL 1 HOUR,UTC_TIMESTAMP()+INTERVAL 1 HOUR,'{}',1)").join(','));
    await page.setViewportSize({ width: 390, height: 620 });
    await page.reload();
    const popup = page.locator('.first-steps-popup');
    await expect(popup.locator('.first-steps-preview')).toBeVisible();
    await openIllustrations(popup);
    await expect(popup.getByRole('heading', { name: 'Mine à ciel ouvert' })).toBeVisible();

    const events = page.locator('.topbar .event-drawer');
    const toggle = events.locator('summary');
    await toggle.click();
    const eventsLink = events.getByRole('link', { name: 'Voir les événements', exact: true });
    await expect(eventsLink).toBeVisible();
    await eventsLink.scrollIntoViewIfNeeded();
    const eventPoint = await eventsLink.boundingBox();
    const guideBox = await popup.locator('[data-first-steps-guide-fragment]').boundingBox();
    // Le lien est bien dans la zone de superposition ; le tiroir reçoit le clic à cet endroit.
    const overlapX = eventPoint.x + eventPoint.width / 2;
    const overlapY = eventPoint.y + eventPoint.height / 2;
    expect(overlapX).toBeGreaterThan(guideBox.x);
    expect(overlapX).toBeLessThan(guideBox.x + guideBox.width);
    expect(overlapY).toBeGreaterThan(guideBox.y);
    expect(overlapY).toBeLessThan(guideBox.y + guideBox.height);
    expect(await eventsLink.evaluate(node => {
        const rect = node.getBoundingClientRect();
        return node.contains(document.elementFromPoint(rect.x + rect.width / 2, rect.y + rect.height / 2));
    })).toBe(true);

    await eventsLink.focus();
    await page.keyboard.press('Escape');
    await expect(toggle).toBeFocused();
    await expect(events).not.toHaveAttribute('open', '');
    await expect(popup).toHaveAttribute('open', '');
    await toggle.click();
    await eventsLink.click();
    await expect(page).toHaveURL(/\/evenements$/);
    await expect(popup.locator('.first-steps-preview')).toBeVisible();
    await expect(popup.locator('img[src]')).toHaveCount(0);
});
