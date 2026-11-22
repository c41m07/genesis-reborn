import { test, expect } from '@playwright/test';
import { readFileSync } from 'node:fs';
import { createRequire } from 'node:module';

const require = createRequire(import.meta.url);
const controller = readFileSync(new URL('../../assets/controllers/admin_list_controller.js', import.meta.url), 'utf8');
const stimulus = require.resolve('@hotwired/stimulus/dist/stimulus.umd.js');
const fragment = `<div data-admin-list-target="results"><p role="status">1 résultat actualisé</p><a href="#updated">Joueur actualisé</a></div>`;

async function prepare(page) {
    let finish;
    const response = new Promise(resolve => { finish = resolve; });
    await page.route('https://admin-ui.test/**', async route => {
        if (new URL(route.request().url()).search) {
            const status = await response;
            await route.fulfill({ status: status ?? 200, contentType: 'text/html', body: fragment });
        } else {
            await route.fulfill({ contentType: 'text/html', body: `<!doctype html><html lang="fr"><body>
                <section data-controller="admin-list">
                <form data-admin-list-target="form" data-action="submit->admin-list#search">
                <label for="query">Rechercher</label><input id="query" name="q"><button>Rechercher</button>
                </form><p data-admin-list-target="error" role="alert" hidden>Erreur</p>
                <div data-admin-list-target="results"><p role="status">1 résultat</p><a href="#old">Ancien joueur</a></div>
                </section><button id="outside">Autre action</button></body></html>` });
        }
    });
    await page.goto('https://admin-ui.test/admin-test');
    await page.addScriptTag({ path: stimulus });
    // Adaptation d'import seulement : exécuter le vrai contrôleur sans compiler ni contacter le jeu.
    await page.addScriptTag({ content: controller.replace("import { Controller } from '@hotwired/stimulus';", 'const { Controller } = window.Stimulus;').replace('export default class', 'window.AdminListController = class') });
    await page.evaluate(() => {
        window.testApplication = window.Stimulus.Application.start();
        window.testApplication.register('admin-list', window.AdminListController);
    });
    await expect.poll(() => page.evaluate(() => Boolean(window.testApplication.getControllerForElementAndIdentifier(document.querySelector('section'), 'admin-list')))).toBe(true);
    return finish;
}

for (const width of [1366, 390]) {
    test.describe(`Clavier à ${width}px`, () => {
        test.use({ viewport: { width, height: 844 } });
        test('Recherche admin différée : le focus dans un ancien résultat rejoint le bilan actualisé', async ({ page }) => {
            const finish = await prepare(page);
            await page.getByLabel('Rechercher').fill('nouveau');
            await page.keyboard.press('Enter');
            await expect(page.locator('[data-admin-list-target="results"]')).toHaveAttribute('aria-busy', 'true');
            await page.keyboard.press('Tab');
            await page.keyboard.press('Tab');
            await expect(page.getByRole('link', { name: 'Ancien joueur' })).toBeFocused();
            finish();
            await expect(page.getByRole('status')).toHaveText('1 résultat actualisé');
            await expect(page.getByRole('status')).toBeFocused();
            await page.keyboard.press('Tab');
            await expect(page.getByRole('link', { name: 'Joueur actualisé' })).toBeFocused();
        });

        test('Recherche admin différée : ne vole pas le focus resté dans la saisie ou sorti du tableau', async ({ page }) => {
            for (const outside of [false, true]) {
                const finish = await prepare(page);
                await page.getByLabel('Rechercher').fill('nouveau');
                await page.keyboard.press('Enter');
                if (outside) await page.locator('#outside').focus();
                finish();
                await expect(page.getByRole('status')).toHaveText('1 résultat actualisé');
                await expect(outside ? page.locator('#outside') : page.getByLabel('Rechercher')).toBeFocused();
                await page.unrouteAll({ behavior: 'wait' });
            }
        });

        test('Une erreur de recherche garde le focus et les résultats précédents', async ({ page }) => {
            const finish = await prepare(page);
            await page.getByLabel('Rechercher').fill('nouveau');
            await page.keyboard.press('Enter');
            await page.getByRole('link', { name: 'Ancien joueur' }).focus();
            finish(503);
            await expect(page.getByRole('alert')).toBeVisible();
            await expect(page.getByRole('link', { name: 'Ancien joueur' })).toBeFocused();
            await expect(page.locator('[data-admin-list-target="results"]')).not.toHaveAttribute('aria-busy', 'true');
        });
    });
}
