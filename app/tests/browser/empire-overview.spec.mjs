import { test, expect } from './support/isolated-test.mjs';
import { runIsolatedSql } from './support/game-fixtures.mjs';
import AxeBuilder from '@axe-core/playwright';

test('La Vue générale validée regroupe les accès et actualise les alertes sans perdre le focus', async ({ page, browser, baseURL }, info) => {
    test.setTimeout(180000);
    const id = Date.now();
    const email = `empire-${id}@example.test`;
    const password = 'EmpireTest!2048';
    await page.goto('/inscription');
    await page.getByLabel('Adresse email').fill(email);
    await page.getByLabel('Nom de commandant').fill(`Empire-${id}`);
    await page.getByLabel('Mot de passe (12 caractères minimum)', { exact: true }).fill(password);
    await page.getByLabel('Confirmer le mot de passe').fill(password);
    await page.getByRole('button', { name: 'Créer mon compte' }).click();
    await page.getByLabel('Adresse email').fill(email);
    await page.getByLabel('Mot de passe', { exact: true }).fill(password);
    await page.getByRole('button', { name: /Connexion/ }).click();
    await page.locator('article.universe-card').first().getByRole('button').click();
    await expect(page).toHaveURL(/planetes\/\d+$/);
    const colonyUrl = page.url();
    await expect(page.locator('[data-resource-strip-target="protectionLabel"]')).toHaveText('Protégé du pillage');
    await expect(page.locator('[data-resource-strip-target="protectionTime"]')).toContainText(/j.*h.*m.*s/);
    await page.goto('/classement');
    await expect(page.locator('tr[aria-current="true"] .player-protection-shield')).toBeVisible();
    await page.goto(colonyUrl);
    const banner = page.locator('.empire-banner');
    await expect(banner.locator('.empire-points dt')).toHaveText(['Planètes', 'Recherche', 'Flotte', 'Total']);
    const metrics = page.locator('.dashboard-metrics');
    await expect(banner.locator('.metric-card')).toHaveCount(4);
    await expect(metrics.locator('.metric-card')).toHaveCount(4);
    const colony = Number(new URL(colonyUrl).pathname.split('/').at(-1));
    const project = process.env.BROWSER_ROUTER;
    const resourceStocks = stock => runIsolatedSql(project, `UPDATE planet_resource SET current_stock=${stock},last_updated_at=UTC_TIMESTAMP() WHERE planet_id=${colony}`);
    resourceStocks(999999);
    for (const theme of ['dark', 'light']) {
      runIsolatedSql(project, `UPDATE player SET preferences=JSON_SET(CASE WHEN JSON_TYPE(preferences)='OBJECT' THEN preferences ELSE JSON_OBJECT() END,'$.theme','${theme}') WHERE email='${email}'`);
      for (const width of [1920, 1440, 768, 390, 320]) {
        await page.setViewportSize({ width, height: width === 1920 ? 1080 : 1000 });
        await page.goto(colonyUrl);
        await expect(page.locator('body')).toHaveAttribute('data-theme', theme);
        const attention = page.locator('.overview-attention');
        await expect(attention).toContainText('Stockages pleins');
        const attentionBounds = await attention.boundingBox();
        expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(width);
        const heroBounds = await banner.boundingBox();
        const watchBounds = await page.locator('.core-empire-watch').boundingBox();
        expect(attentionBounds.y).toBeGreaterThanOrEqual(watchBounds.y);
        if (width >= 1250) {
            expect(watchBounds.x).toBeGreaterThanOrEqual(heroBounds.x + heroBounds.width);
            expect(Math.abs(watchBounds.y - heroBounds.y)).toBeLessThan(2);
        } else {
            expect(watchBounds.y).toBeGreaterThanOrEqual(heroBounds.y + heroBounds.height);
        }
        const bounds = await metrics.boundingBox();
        expect(bounds.y).toBeGreaterThan(heroBounds.y);
        expect(bounds.y + bounds.height).toBeLessThanOrEqual(heroBounds.y + heroBounds.height);
        for (const card of await metrics.locator('.metric-card').all()) {
            const box = await card.boundingBox();
            expect(box.y + box.height).toBeLessThanOrEqual(bounds.y + bounds.height);
            expect(box.x + box.width).toBeLessThanOrEqual(bounds.x + bounds.width);
        }
        for (const selector of ['.overview-attention', '.empire-banner', '.scene-operations', '.dashboard-secondary', '.dashboard-journal']) {
          expect(await page.locator(selector).evaluate(el => el.scrollWidth <= el.clientWidth + 1), selector).toBe(true);
        }
        const incoming = page.locator('.dashboard-secondary .incoming-attacks');
        const flights = page.locator('.dashboard-secondary [data-live-region="active-flights"]');
        expect((await incoming.boundingBox()).y).toBeLessThan((await flights.boundingBox()).y);
        for (const section of ['buildings', 'technologies', 'ships', 'defenses']) {
          await expect(page.locator(`.overview-queue-links a[href="/planetes/${colony}?section=${section}"]`)).toBeVisible();
        }
        if (width === 390) {
          expect((await new AxeBuilder({ page }).include('.overview-page').analyze()).violations).toEqual([]);
        }
        if (width !== 320) {
          await page.screenshot({ path: info.outputPath(`jeu-vue-generale-${theme}-${width}.png`), fullPage: true, animations: 'disabled' });
          if (width === 390) await page.screenshot({ path: info.outputPath(`jeu-vue-generale-${theme}-390-ecran.png`), animations: 'disabled' });
        }
      }
    }
    await page.setViewportSize({ width: 1440, height: 1000 });
    await page.goto(colonyUrl);
    const focused = page.locator('.overview-attention a').first();
    await focused.focus();
    expect(await focused.evaluate(el => el.matches(':focus-visible'))).toBe(true);
    resourceStocks(0);
    const refreshed = page.waitForResponse(response => response.url() === colonyUrl && response.request().headers()['x-genesis-live'] === '1');
    await page.evaluate(() => window.dispatchEvent(new CustomEvent('genesis:refresh')));
    const response = await refreshed;
    const regions = (await response.json()).regions;
    expect(regions['overview-attention']).not.toContain('Stockages pleins');
    await expect(focused).toBeFocused();
    await expect(page.locator('.overview-attention')).toBeVisible();
    await page.locator('h1').click();
    await expect(page.locator('.overview-attention')).toHaveCount(0);
    resourceStocks(999999);
    await page.evaluate(() => window.dispatchEvent(new CustomEvent('genesis:refresh')));
    await expect(page.locator('.overview-attention')).toContainText('Stockages pleins');

    await page.getByRole('link', { name: 'Ouvrir les bâtiments' }).click();
    await page.locator('#item-mine_metal-basic').getByRole('button', { name: /Améliorer .* niveau 1/ }).click();
    // Allonger uniquement la commande de cette base jetable pour vérifier un état occupé stable.
    runIsolatedSql(project, `UPDATE queue_item SET started_at=UTC_TIMESTAMP(),duration_seconds=86400 WHERE planet_id=${colony} AND queue_type='building'`);
    runIsolatedSql(project, `UPDATE player SET preferences=JSON_SET(preferences,'$.theme','dark') WHERE email='${email}'`);
    await page.goto(colonyUrl);
    const queue = page.locator('[data-queue-type="building"]');
    await expect(queue.locator('.operation-name')).toContainText('Mine à ciel ouvert');
    await queue.locator('.queue-details summary').click();
    await expect(queue.getByRole('link', { name: /Préparer l’annulation/ })).toBeVisible();
    for (const width of [1440, 390]) {
      await page.setViewportSize({ width, height: 1000 });
      expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(width);
      expect(await queue.evaluate(el => el.scrollWidth <= el.clientWidth + 1)).toBe(true);
      await page.evaluate(() => { document.activeElement?.blur(); window.scrollTo({ top: 0, behavior: 'instant' }); });
      await page.screenshot({ path: info.outputPath(`jeu-vue-generale-file-active-${width}.png`), fullPage: true, animations: 'disabled' });
    }
    expect((await new AxeBuilder({ page }).include('.overview-page').analyze()).violations).toEqual([]);

    // Les raccourcis sont des liens natifs : accès direct et retour fonctionnent aussi sans JavaScript.
    const context = await browser.newContext({ baseURL, javaScriptEnabled: false, storageState: await page.context().storageState() });
    try {
      const plain = await context.newPage();
      await plain.goto(colonyUrl);
      await plain.getByRole('link', { name: 'Ouvrir les bâtiments' }).click();
      await expect(plain).toHaveURL(new RegExp(`/planetes/${colony}\\?section=buildings$`));
      await expect(plain.getByRole('heading', { name: 'Bâtiments.', exact: true })).toBeVisible();
      await plain.goBack();
      await expect(plain.locator('.dashboard-journal')).toBeVisible();
      await plain.reload();
      await expect(plain.locator('.overview-queue-links')).toHaveCount(3);
    } finally {
      await context.close();
    }
});
