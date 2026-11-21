import { test, expect } from './support/isolated-test.mjs';
import { revealVirginPlanet, runIsolatedSql } from './support/game-fixtures.mjs';

async function register(page, selectUniverse = true) {
    const id = crypto.randomUUID().slice(0, 8);
    const email = `header-context-${id}@example.test`;
    const password = crypto.randomUUID() + 'Aa1!';
    await page.goto('/inscription');
    await page.getByLabel('Adresse email').fill(email);
    await page.getByLabel('Nom de commandant').fill('Rail-' + id);
    await page.getByLabel('Mot de passe (12 caractères minimum)', { exact: true }).fill(password);
    await page.getByLabel('Confirmer le mot de passe').fill(password);
    await page.getByRole('button', { name: 'Créer mon compte' }).click();
    await page.getByLabel('Adresse email').fill(email);
    await page.getByLabel('Mot de passe', { exact: true }).fill(password);
    await page.getByRole('button', { name: /Connexion/ }).click();
    if (selectUniverse) {
        await page.locator('.universe-card').filter({ has: page.getByRole('heading', { name: /Omega/ }) }).getByRole('button').click();
        await expect(page).toHaveURL(/planetes\/\d+$/);
    }
    return email;
}

function createEvent(project) {
    const name = 'Annonce de recette ' + crypto.randomUUID().slice(0, 8);
    runIsolatedSql(project, `INSERT INTO game_event(name,description,type,status,starts_at,ends_at,parameters,visible_to_players) VALUES('${name}','','announcement','active',UTC_TIMESTAMP()-INTERVAL 1 HOUR,UTC_TIMESTAMP()+INTERVAL 1 HOUR,'{}',1)`);
    return name;
}

test('Rail : suit le véritable objectif, garde le contexte et ne déplace pas les ressources', async ({ page, browser, baseURL }, info) => {
    test.setTimeout(120000);
    const email = await register(page);
    const overview = new URL(page.url()).pathname;
    const planetId = Number(overview.split('/').at(-1));
    const project = process.env.BROWSER_ROUTER;
    const eventName = createEvent(project);
    await page.reload();
    const rail = page.getByRole('region', { name: 'Suivi du commandement', exact: true });
    const goal = page.locator('.topbar .universe-context + .header-technology-goal:has(+ .header-tools)');
    const drawer = rail.locator('.event-drawer');
    const summary = drawer.locator('summary');
    const label = goal.locator('strong');
    const progress = goal.locator('small');
    const strip = page.locator('[data-live-region="resources"]');
    const stack = page.locator('.resource-event-stack');
    const main = page.locator('main');
    const lookupUrl = await goal.getAttribute('data-header-technology-goal-url-value');
    const storageKey = await goal.getAttribute('data-header-technology-goal-storage-key-value');
    const target = await revealVirginPlanet(project, email);
    expect(lookupUrl).toBe(`/planetes/${planetId}/objectif-technologique`);
    const foreignStatus = await page.evaluate(async path => (await fetch(path)).status,
        `/planetes/${target.id}/objectif-technologique?goal=technologies%3Avector_propulsion-basic`);
    expect(foreignStatus).toBe(404);

    runIsolatedSql(project, `INSERT INTO planet_building (planet_id,slug,tier,level) VALUES (${planetId},'lab','basic',5) ON DUPLICATE KEY UPDATE level=5`);
    for (const [slug, level] of [['signature_masking', 2], ['nanomaterial_synthesis', 2], ['engineering_foundations', 3]]) {
        runIsolatedSql(project, `INSERT INTO player_technology (player_universe_id,slug,tier,level) SELECT owner_id,'${slug}','basic',${level} FROM planet WHERE id=${planetId} ON DUPLICATE KEY UPDATE level=${level}`);
    }
    let requests = 0;
    page.on('request', request => {
        if (new URL(request.url()).pathname === lookupUrl) requests++;
    });
    await expect(label).toHaveText('Choisir un objectif');
    await goal.click();
    await expect(page).toHaveURL(/\/technologies$/);
    expect(requests).toBe(0);
    await page.getByLabel('Rechercher un élément').fill('signature_reduction');
    const node = page.locator('#tech-signature_reduction-basic');
    await node.locator('summary').click();
    const displayed = 'Métallurgie des nanomatériaux';
    await expect(page.getByText(`${displayed} → niveau 3 (actuel : 2)`, { exact: true })).toBeVisible();
    await page.getByRole('button', { name: 'Suivre cet objectif', exact: true }).click();
    await expect(label).toHaveText(displayed);
    await expect(progress).toHaveText('2/3');
    await page.reload();
    await expect(label).toHaveText(displayed);
    await expect(progress).toHaveText('2/3');
    expect(requests).toBe(0);

    const resolved = page.waitForResponse(response => new URL(response.url()).pathname === lookupUrl);
    await page.goto(overview);
    await expect(label).toHaveText(displayed);
    await expect(progress).toHaveText('2/3');
    const result = await resolved;
    expect(result.headers()['cache-control']).toContain('no-store');
    const { goal: resolvedGoal } = await result.json();
    expect(resolvedGoal.recommendation.requirement).toMatchObject({ current: 2, level: 3, slug: 'nanomaterial_synthesis', tier: 'basic' });
    expect(requests).toBe(1);
    await page.evaluate(() => window.dispatchEvent(new CustomEvent('genesis:refresh')));
    for (const width of [1440, 390, 360, 320]) {
        await page.setViewportSize({ width, height: 900 });
        await expect(summary).toBeVisible();
        await expect(goal).toBeVisible();
        await expect(goal).toHaveAccessibleName(/Guide techno · Métallurgie des nanomatériaux · niveau 2 sur 3/);
        await expect(progress).toBeVisible();
        expect(await goal.evaluate(el => el.getBoundingClientRect().height)).toBeGreaterThanOrEqual(44);
        const measured = async () => Promise.all([stack.boundingBox(), strip.boundingBox(), main.boundingBox()]);
        const railBox = await rail.boundingBox();
        const [stackBefore, stripBefore, mainBefore] = await measured();
        expect(railBox.y + railBox.height).toBeLessThanOrEqual(stripBefore.y + 1);
        await expect.poll(() => stack.evaluate(el => parseFloat(el.closest('.app-frame').style.getPropertyValue('--resource-stack-height')))).toBeCloseTo(stackBefore.height, 0);
        await summary.focus();
        await page.keyboard.press('Enter');
        await expect(drawer.getByText(eventName, { exact: true })).toBeVisible();
        if (width <= 390) {
            // Le chevron des ressources ne doit ni percer ni capter le toucher dans le tiroir.
            const covered = await strip.locator('.resource-toggle').evaluate(toggle => {
                const { x, y, width, height } = toggle.getBoundingClientRect();
                const hit = document.elementFromPoint(x + width / 2, y + height / 2);
                return Boolean(hit?.closest('.event-drawer-list'));
            });
            expect(covered).toBe(true);
        }
        const [stackOpen, stripOpen, mainOpen] = await measured();
        expect(stackOpen.height).toBeCloseTo(stackBefore.height, 0);
        expect(stripOpen.y).toBeCloseTo(stripBefore.y, 0);
        expect(mainOpen.y).toBeCloseTo(mainBefore.y, 0);
        await expect.poll(() => page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(width);
        if (width !== 320) await page.screenshot({ path: info.outputPath(`rail-evenements-${width}.png`), animations: 'disabled' });
        await page.keyboard.press('Escape');
        await expect(summary).toBeFocused();
        await expect(drawer).not.toHaveAttribute('open');
        if (width !== 320) await page.screenshot({ path: info.outputPath(`rail-objectif-${width}.png`), animations: 'disabled' });
    }

    expect(requests).toBe(1);

    // Une réponse retardée de l’ancienne clé ne doit pas réafficher un objectif retiré.
    await page.setViewportSize({ width: 1440, height: 900 });
    const other = await page.context().newPage();
    await other.goto(overview);
    let intercepted;
    const lookupStarted = new Promise(resolve => { intercepted = resolve; });
    let release;
    const gate = new Promise(resolve => { release = resolve; });
    await page.route('**/objectif-technologique?*', async route => {
        intercepted();
        await gate;
        // La lecture est annulée lors du retrait : ne pas tenter de répondre après fermeture.
        await route.continue().catch(() => {});
    });
    try {
        await page.reload();
        await lookupStarted;
        await other.evaluate(key => localStorage.removeItem(key), storageKey);
        await expect(label).toHaveText('Choisir un objectif');
    } finally {
        release();
    }
    await page.unrouteAll({ behavior: 'wait' });
    await expect(label).toHaveText('Choisir un objectif');
    await other.evaluate(key => localStorage.setItem(key, 'technologies:absent_du_catalogue-basic'), storageKey);
    await expect(label).toHaveText('Choisir un objectif');

    // Ajouter une seconde colonie uniquement dans la base jetable.
    runIsolatedSql(project, `UPDATE planet SET owner_id=(SELECT owner_id FROM (SELECT owner_id FROM planet WHERE id=${planetId}) origin), name='Colonie témoin du rail' WHERE id=${target.id}`);
    await page.evaluate(key => localStorage.setItem(key, 'technologies:signature_reduction-basic'), storageKey);
    await page.goto(`/planetes/${target.id}`);
    await expect(goal).not.toHaveAttribute('data-header-technology-goal-storage-key-value', storageKey);
    await expect(label).toHaveText('Choisir un objectif');
    await page.goto(overview);
    await expect(label).toHaveText(displayed);
    await expect(progress).toHaveText('2/3');

    runIsolatedSql(project, `UPDATE player_technology SET level=3 WHERE slug='nanomaterial_synthesis' AND tier='basic' AND player_universe_id=(SELECT owner_id FROM planet WHERE id=${planetId})`);
    await page.reload();
    await expect(label).toHaveText('Laboratoire colonial');
    await expect(progress).toHaveText('5/7');

    const native = await browser.newContext({ baseURL, javaScriptEnabled: false, storageState: await page.context().storageState(), viewport: { width: 390, height: 844 } });
    try {
        const fallback = await native.newPage();
        await fallback.goto(overview);
        await expect(fallback.locator('.header-technology-goal')).toContainText('Choisir un objectif');
        await fallback.locator('.event-drawer summary').click();
        await expect(fallback.getByRole('link', { name: 'Voir les événements', exact: true })).toBeVisible();
        await fallback.locator('.topbar .universe-context').focus();
        await fallback.keyboard.press('Tab');
        await expect(fallback.getByRole('link', { name: 'Guide techno : choisir un objectif' })).toBeFocused();
        await fallback.keyboard.press('Enter');
        await expect(fallback).toHaveURL(/\/technologies$/);
        await fallback.setViewportSize({ width: 1440, height: 900 });
        await fallback.goto(overview + '?section=buildings');
        await expect.poll(() => fallback.locator('.resource-event-stack').evaluate(element =>
            parseFloat(getComputedStyle(element).getPropertyValue('--resource-stack-height')) >= element.getBoundingClientRect().height - 1,
        )).toBe(true);
    } finally {
        await native.close();
        await other.close();
    }
    await goal.click();
    await page.getByRole('button', { name: 'Retirer l’objectif' }).click();
    await expect(label).toHaveText('Choisir un objectif');
});

test('Rail : aucun encart hors univers, événements conservés sans colonie', async ({ page }) => {
    await page.goto('/');
    await expect(page.locator('.header-context-rail')).toHaveCount(0);
    await register(page, false);
    await expect(page).toHaveURL(/\/univers$/);
    await expect(page.locator('.header-context-rail')).toHaveCount(0);
    await page.locator('.universe-card').filter({ has: page.getByRole('heading', { name: /Omega/ }) }).getByRole('button').click();
    await expect(page).toHaveURL(/planetes\/\d+$/);
    const project = process.env.BROWSER_ROUTER;
    const planet = Number(new URL(page.url()).pathname.split('/').at(-1));
    const eventName = createEvent(project);
    runIsolatedSql(project, `UPDATE planet SET owner_id=NULL WHERE id=${planet}`);
    await page.goto('/evenements');
    await expect(page.locator('.event-drawer summary')).toBeVisible();
    await expect(page.locator('.header-technology-goal, [data-live-region="resources"]')).toHaveCount(0);
    await page.locator('.event-drawer summary').click();
    await expect(page.locator('.event-drawer').getByText(eventName, { exact: true })).toBeVisible();
});
