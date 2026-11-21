import { test, expect } from './support/isolated-test.mjs';
import { queryIsolatedSql } from './support/game-fixtures.mjs';

async function connect(page) {
    const id = crypto.randomUUID().slice(0, 8);
    const email = 'galaxy-click-' + id + '@example.test';
    const password = crypto.randomUUID() + 'aA1!';
    await page.goto('/inscription');
    await page.getByLabel('Adresse email').fill(email);
    await page.getByLabel('Nom de commandant').fill('Clic-' + id);
    await page.getByLabel('Mot de passe (12 caractères minimum)', { exact: true }).fill(password);
    await page.getByLabel('Confirmer le mot de passe').fill(password);
    await page.getByRole('button', { name: 'Créer mon compte' }).click();
    await expect(page).toHaveURL(/connexion/);
    await page.getByLabel('Adresse email').fill(email);
    await page.getByLabel('Mot de passe', { exact: true }).fill(password);
    await page.getByRole('button', { name: /Connexion/ }).click();
    await page.locator('.universe-card').filter({ has: page.getByRole('heading', { name: /Omega/ }) }).getByRole('button').click();
    await expect(page).toHaveURL(/planetes[/][0-9]+$/);
    const planet = Number(new URL(page.url()).pathname.split('/').pop());
    const galaxy = Number(queryIsolatedSql(process.env.BROWSER_ROUTER,
        'SELECT s.galaxy_id FROM planet p JOIN solar_system s ON s.id=p.solar_system_id WHERE p.id=' + planet));
    await page.goto('/carte-v2?galaxy=' + galaxy);
}

for (const [width, mobile] of [[1440, false], [390, true], [360, true]]) {
    test('Galaxie : cliquer sur deux repères de la carte importée à ' + width + ' px', async ({ browser, baseURL }, info) => {
        const context = await browser.newContext({ baseURL, hasTouch: mobile, viewport: { width, height: 900 }, reducedMotion: 'reduce' });
        try {
            const page = await context.newPage();
            // Lire les centres réellement dessinés, pas appeler le gestionnaire de sélection.
            await page.addInitScript(() => {
                const proto = CanvasRenderingContext2D.prototype;
                const [arc, clear, stroke, fill] = [proto.arc, proto.clearRect, proto.stroke, proto.fill];
                const lastArc = new WeakMap();
                window.galaxyCenters = { home: null, unknown: [] };
                const isGalaxy = ctx => ctx.canvas.classList.contains('gv2-canvas--galaxy');
                proto.arc = function (x, y, radius, ...rest) {
                    if (isGalaxy(this)) lastArc.set(this, { x, y, radius });
                    return arc.call(this, x, y, radius, ...rest);
                };
                proto.clearRect = function (...rest) {
                    if (isGalaxy(this)) window.galaxyCenters = { home: null, unknown: [] };
                    return clear.apply(this, rest);
                };
                proto.stroke = function (...rest) {
                    if (isGalaxy(this) && this.strokeStyle === '#00e676') window.galaxyCenters.home = lastArc.get(this);
                    return stroke.apply(this, rest);
                };
                proto.fill = function (...rest) {
                    const point = lastArc.get(this);
                    if (isGalaxy(this) && point?.radius === 11 && this.fillStyle === '#000000') window.galaxyCenters.unknown.push(point);
                    return fill.apply(this, rest);
                };
            });
            const errors = [];
            page.on('pageerror', error => errors.push(error.message));
            await connect(page);
            const workspace = page.locator('.gv2-workspace');
            const canvas = workspace.locator('.gv2-canvas--galaxy');
            const panel = workspace.getByRole('complementary', { name: 'Informations sur le système sélectionné' });
            const point = (kind, prefer = false) => canvas.evaluate((element, { kind, prefer }) => {
                const rect = element.getBoundingClientRect();
                const picked = kind === 'home' ? [window.galaxyCenters.home] : window.galaxyCenters.unknown;
                const visible = picked.filter(p => p && p.x > 16 && p.x < rect.width - 16 && p.y > 16 && p.y < rect.height - 16);
                const pane = document.querySelector('.gv2-system-panel')?.getBoundingClientRect();
                return visible.map(p => ({ x: rect.x + p.x, y: rect.y + p.y })).map(p => ({
                    ...p,
                    target: document.elementFromPoint(p.x, p.y)?.className,
                    inPreviewArea: pane && p.x > pane.x && p.x < pane.right && p.y > pane.y && p.y < pane.bottom
                })).sort((a, b) => prefer ? Number(b.inPreviewArea) - Number(a.inPreviewArea) : 0)[0];
            }, { kind, prefer });
            const act = p => mobile ? page.touchscreen.tap(p.x, p.y) : page.mouse.click(p.x, p.y);
            await workspace.locator('.gv2-map-stage').scrollIntoViewIfNeeded();
            await expect.poll(() => point('home')).toBeTruthy();
            const home = await point('home');
            expect(home.target).toContain('gv2-canvas--galaxy');
            const canvasBefore = await canvas.boundingBox();
            const scrollBefore = await page.evaluate(() => window.scrollY);
            await act(home);
            await expect(panel).toBeVisible();
            await expect(panel.getByRole('button', { name: 'Entrer dans le système', exact: true })).toBeVisible();
            await expect(workspace).toHaveAttribute('data-view','galaxy');
            if (!mobile) await expect(panel.getByRole('heading')).toBeFocused();
            const canvasAfter = await canvas.boundingBox();
            expect(canvasAfter).toEqual(canvasBefore);
            const panelBounds = await panel.boundingBox();
            if (mobile) {
                expect(panelBounds.y + panelBounds.height).toBeLessThanOrEqual(canvasAfter.y);
                expect(panelBounds.height).toBeLessThanOrEqual(104);
                const action = await panel.getByRole('button', { name: 'Entrer dans le système', exact: true }).boundingBox();
                expect(action.y).toBeGreaterThanOrEqual(Math.max(panelBounds.y, 0));
                expect(action.y + action.height).toBeLessThanOrEqual(panelBounds.y + panelBounds.height);
                expect(panelBounds.x + panelBounds.width).toBeLessThanOrEqual(width);
                expect(await page.evaluate(() => window.scrollY)).toBe(scrollBefore);
            } else {
                expect(panelBounds.x).toBeGreaterThanOrEqual(canvasAfter.x + canvasAfter.width);
            }
            await workspace.screenshot({ path: info.outputPath('galaxy-click-'+width+'.png'), animations: 'disabled' });
            await canvas.scrollIntoViewIfNeeded();
            const unknown = await point('unknown', true);
            expect(unknown?.target).toContain('gv2-canvas--galaxy');
            await act(unknown);
            await expect(panel).toContainText('Signal inconnu');
            await expect(workspace).toHaveAttribute('data-view','galaxy');
            await expect(workspace.getByRole('button', { name: 'Entrer dans le système', exact: true })).toHaveCount(0);
            await expect(page.getByRole('link', { name: 'Liste des systèmes' })).toHaveAttribute('href', /[?&]system=[0-9]+/);
            await panel.getByRole('heading').focus();
            await page.keyboard.press('Escape');
            await expect(panel).toBeHidden();
            await canvas.scrollIntoViewIfNeeded();
            await act(await point('home'));
            const enter = panel.getByRole('button', { name: 'Entrer dans le système', exact: true });
            await enter.focus();
            await page.keyboard.press('Enter');
            await expect(workspace).toHaveAttribute('data-view', 'system');
            expect(errors).toEqual([]);
        } finally {
            await context.close();
        }
    });
}
