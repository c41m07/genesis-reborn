import { test, expect } from './support/isolated-test.mjs';
import { queryIsolatedSql, runIsolatedSql } from './support/game-fixtures.mjs';
import AxeBuilder from '@axe-core/playwright';

async function register(page) {
    const key = crypto.randomUUID().slice(0, 8);
    const password = crypto.randomUUID() + 'aA1!';
    const email = 'markdown-' + key + '@example.test', name = 'Markdown-' + key;
    await page.goto('/inscription');
    await page.getByLabel('Adresse email').fill(email);await page.getByLabel('Nom de commandant').fill(name);
    await page.getByLabel('Mot de passe (12 caractères minimum)', { exact: true }).fill(password);
    await page.getByLabel('Confirmer le mot de passe').fill(password);
    await page.getByRole('button', { name: 'Créer mon compte' }).click();
    await page.getByLabel('Adresse email').fill(email);await page.getByLabel('Mot de passe', { exact: true }).fill(password);
    await page.getByRole('button', { name: /Connexion/ }).click();
    await page.locator('.universe-card').filter({ has: page.getByRole('heading', { name: /Omega/ }) }).getByRole('button').click();
    await expect(page).toHaveURL(/planetes\/\d+$/);
    const planet = Number(new URL(page.url()).pathname.split('/').pop());
    return { name, member: Number(queryIsolatedSql(process.env.BROWSER_ROUTER, `SELECT owner_id FROM planet WHERE id=${planet}`)) };
}

for (const channel of ['private', 'guild', 'global']) {
    test(`Messagerie ${channel} : Ctrl+Entrée, Markdown sûr, historique et doubles envois`, async ({ page, browser, baseURL }, testInfo) => {
        test.setTimeout(180000);
        const owner = await register(page), context = await browser.newContext({ baseURL });
        try {
            const peer = await register(await context.newPage()), project = process.env.BROWSER_ROUTER;
            if (channel === 'guild') {
                await page.goto('/diplomatie');
                await page.getByLabel('Nom de votre nouvelle guilde').fill('Markdown Guild ' + crypto.randomUUID().slice(0, 8));
                await page.getByLabel('Tag de la guilde', { exact: true }).fill('MDK');
                await page.getByRole('button', { name: 'Fonder une guilde' }).click();
                const guild = Number(queryIsolatedSql(project, `SELECT id FROM guild WHERE leader_id=${owner.member}`));
                runIsolatedSql(project, `INSERT INTO guild_member (guild_id,player_universe_id) VALUES (${guild},${peer.member})`);
            }
            const guildSql = channel === 'guild' ? `(SELECT id FROM guild WHERE leader_id=${owner.member})` : 'NULL';
            const recipientSql = channel === 'private' ? peer.member : 'NULL';
            runIsolatedSql(project, `INSERT INTO social_message (sender_id,recipient_id,guild_id,content,content_format,created_at) VALUES (${owner.member},${recipientSql},${guildSql},'**Ancien**  texte\nsuite','plain',UTC_TIMESTAMP())`);
            const url = channel === 'private' ? '/messages?with=' + peer.member : '/messages?channel=' + channel;
            await page.goto(url);
            const form = page.locator('.message-compose'), textarea = form.locator('textarea'), button = form.getByRole('button', { name: 'Envoyer' });
            await expect(page.locator('.message-plain')).toHaveCSS('white-space', 'pre-wrap');
            expect(await page.locator('.message-plain').textContent()).toContain('**Ancien**  texte\nsuite');
            await expect(page.locator('.message-content strong')).toHaveCount(0);
            let posts = 0;
            await page.route('**/messages**', async route => {
                if (route.request().method() === 'POST') posts++;
                await route.continue();
            });
            await textarea.press('Control+Enter');expect(posts).toBe(0);await expect(button).toBeEnabled();
            await textarea.fill('**Bonjour**');await textarea.press('Enter');
            await textarea.press('Enter');await textarea.type('- liste\n\n> citation\n\n`code`\n\n[site](https://example.test) [interdit](javascript:alert%281%29)\n\n<script>window.messageXss=true</script> ![pixel](https://example.test/pixel.png)');
            expect(await textarea.inputValue()).toContain('**Bonjour**\n\n- liste');
            await textarea.evaluate(element => {
                element.dispatchEvent(new KeyboardEvent('keydown', { key: 'Enter', ctrlKey: true, isComposing: true, bubbles: true }));
                element.dispatchEvent(new KeyboardEvent('keydown', { key: 'Enter', ctrlKey: true, repeat: true, bubbles: true }));
            });
            expect(posts).toBe(0);
            await textarea.press('Control+Enter');
            await expect(page.locator('.message-content strong')).toHaveText('Bonjour');
            expect(posts).toBe(1);
            await page.locator('.message-compose textarea').fill('Envoi unique');
            const duringSubmission = await page.locator('.message-compose').evaluate(element => {
                const textarea = element.querySelector('textarea'), button = element.querySelector('button[type=submit]');
                textarea.dispatchEvent(new KeyboardEvent('keydown', { key: 'Enter', ctrlKey: true, bubbles: true }));
                const state = { disabled: button.disabled, busy: element.getAttribute('aria-busy') };
                textarea.dispatchEvent(new KeyboardEvent('keydown', { key: 'Enter', ctrlKey: true, bubbles: true }));
                button.click();
                element.requestSubmit(button);
                return state;
            });
            expect(duringSubmission).toEqual({ disabled: true, busy: 'true' });
            await expect(page.locator('.message-content').filter({ hasText: 'Envoi unique' })).toHaveCount(1);
            expect(posts).toBe(2);
            expect(Number(queryIsolatedSql(project, 'SELECT COUNT(*) FROM social_message WHERE sender_id=' + owner.member))).toBe(3);
            for (const selector of ['ul', 'blockquote', 'code']) await expect(page.locator('.message-content ' + selector)).toHaveCount(1);
            await expect(page.locator('.message-content a[href]')).toHaveCount(1);
            await expect(page.locator('.message-content a[href]')).toHaveAttribute('href', 'https://example.test');
            await expect(page.locator('.message-content :is(script,img,svg,iframe)')).toHaveCount(0);
            expect(await page.evaluate(() => window.messageXss)).toBeUndefined();
            for (const theme of ['dark', 'light']) {
                await page.goto('/profil/preferences');await page.getByLabel('Thème de l’interface').selectOption(theme);
                await page.getByRole('button', { name: 'Enregistrer les paramètres' }).click();await page.goto(url);
                for (const width of [320, 390, 1440]) {
                    await page.setViewportSize({ width, height: 900 });
                    expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
                    if (width !== 390) {
                        expect((await new AxeBuilder({ page }).include('main').analyze()).violations).toEqual([]);
                        if (channel === 'private') {
                            const help = page.locator('.message-markdown-help');
                            await help.locator('summary').focus();await page.keyboard.press('Enter');
                            await expect(help).toHaveAttribute('open', '');
                            expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
                            expect((await new AxeBuilder({ page }).include('main').analyze()).violations).toEqual([]);
                            await page.keyboard.press('Enter');
                            await expect(help).not.toHaveAttribute('open', '');
                            await page.evaluate(() => { document.activeElement?.blur();window.scrollTo({ top: 0, behavior: 'instant' }); });
                            await expect.poll(() => page.evaluate(() => window.scrollY)).toBe(0);
                            await page.screenshot({ path: testInfo.outputPath(`messages-${theme}-${width}.png`), fullPage: true, animations: 'disabled' });
                        }
                    }
                }
            }
        } finally { await context.close(); }
    });
}

test('Messagerie : bouton fonctionnel sans JavaScript et Markdown côté serveur', async ({ page, browser, baseURL }) => {
    await register(page);
    const context = await browser.newContext({ baseURL, javaScriptEnabled: false, storageState: await page.context().storageState() });
    try {
        const noJs = await context.newPage();await noJs.goto('/messages?channel=global');
        await noJs.locator('.message-compose textarea').fill('Sans JavaScript\n**Envoyé**');
        await noJs.getByRole('button', { name: 'Envoyer' }).click();
        await expect(noJs.locator('.message-content strong')).toHaveText('Envoyé');
        await expect(noJs.locator('.message-content br')).toHaveCount(1);
    } finally { await context.close(); }
});
