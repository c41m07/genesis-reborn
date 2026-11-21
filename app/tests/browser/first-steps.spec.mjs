import { test, expect } from './support/isolated-test.mjs';
import { accelerateQueuedItem, queryIsolatedSql } from './support/game-fixtures.mjs';

async function register(page) {
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

    return email;
}

test('Premiers pas : conseil facultatif et progression après la fin réelle de la mine', async ({ page, browser, baseURL }, info) => {
    test.setTimeout(120000);
    const email = await register(page);
    const overview = page.url();
    await page.setViewportSize({ width: 390, height: 844 });
    await page.getByRole('link', { name: 'Premiers pas', exact: true }).click();
    await expect(page.getByRole('heading', { name: 'Premiers pas', exact: true })).toBeVisible();
    const guide = page.getByRole('region', { name: 'Développer sa colonie et explorer', exact: true });
    const progress = guide.getByRole('progressbar', { name: 'Progression des premiers pas', exact: true });
    const next = guide.locator('.first-step--next');
    await expect(progress).toHaveAttribute('value', '0');
    await expect(next.getByRole('heading', { name: 'Mine à ciel ouvert', exact: true })).toBeVisible();
    await expect(guide.getByRole('heading', { name: 'Première recherche', exact: true })).toBeVisible();
    await expect(progress).toHaveAttribute('max', '9');
    await expect(guide.getByRole('link', { name: 'Ouvrir la fiche de la sonde', exact: true })).toHaveAttribute('href', /section=ships.*#item-probe-basic$/);
    const explore = guide.getByRole('listitem').filter({ has: page.getByRole('heading', { name: 'Premier système exploré', exact: true }) });
    await expect(explore).toContainText('consomme une sonde à l’arrivée');
    await expect(explore).toContainText('en construire une autre au chantier pour l’espionnage');
    await expect(explore.getByRole('link', { name: 'Préparer une exploration', exact: true })).toHaveAttribute('href', /\/flottes\/preparer\?mission=explore&origin=\d+$/);
    const espionage = guide.getByRole('listitem').filter({ has: page.getByRole('heading', { name: 'Premier rapport d’espionnage PNJ', exact: true }) });
    await expect(espionage).toContainText('« Détecté » signifie qu’aucun renseignement n’a été obtenu');
    await expect(espionage).toContainText('La sonde envoyée est consommée à l’arrivée');
    await expect(espionage.getByRole('link', { name: 'Consulter les rapports d’espionnage', exact: true })).toHaveAttribute('href', '/rapports?type=espionage');
    await espionage.getByRole('link', { name: 'Préparer un espionnage PNJ', exact: true }).click();
    await expect(page).toHaveURL(/\/flottes\/preparer\?mission=spy&origin=\d+$/);
    await page.goto('/premiers-pas');
    const colonization = guide.getByRole('listitem').filter({ has: page.getByRole('heading', { name: 'Première colonisation', exact: true }) });
    await expect(colonization).toContainText('sans avoir validé les autres étapes du guide');
    await expect(colonization).toContainText('« Inhabitée », ni occupée ni PNJ');
    await expect(colonization.getByRole('link', { name: 'Ouvrir la fiche du Vaisseau colonisateur', exact: true })).toHaveAttribute('href', /section=ships.*#item-colony_ship-basic$/);
    await colonization.getByRole('link', { name: 'Préparer une colonisation', exact: true }).click();
    await expect(page).toHaveURL(/\/flottes\/preparer\?mission=colonize&origin=\d+$/);
    await page.goto('/premiers-pas');
    await expect(progress).toHaveAttribute('value', '0');
    await expect.poll(() => page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(390);
    await page.screenshot({ path: info.outputPath('premiers-pas-mobile.png'), animations: 'disabled' });

    // Le guide ne bloque pas le reste du jeu avant la première construction.
    await page.getByRole('link', { name: 'Retour à la vue générale', exact: true }).click();
    await expect(page).toHaveURL(overview);
    await page.getByRole('link', { name: 'Premiers pas', exact: true }).click();
    await next.getByRole('link', { name: 'Ouvrir la Mine à ciel ouvert', exact: true }).click();
    await expect(page).toHaveURL(/section=buildings.*#item-mine_metal-basic$/);
    const card = page.locator('#item-mine_metal-basic');
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
    await expect(guide.getByRole('listitem').filter({ has: page.getByRole('heading', { name: 'Mine à ciel ouvert', exact: true }) })).toContainText('Terminé');
    await expect(next.getByRole('heading', { name: 'Centrale nucléaire', exact: true })).toBeVisible();

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
