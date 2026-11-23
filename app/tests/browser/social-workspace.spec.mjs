import { test, expect } from './support/isolated-test.mjs';
import { queryIsolatedSql, runIsolatedSql } from './support/game-fixtures.mjs';
import AxeBuilder from '@axe-core/playwright';

async function register(page, prefix, universe = 'Turbo') {
    const suffix = crypto.randomUUID().slice(0, 8);
    const name = `${prefix}-${suffix}`;
    const email = `${name.toLowerCase()}@example.test`;
    const password = crypto.randomUUID() + 'aA1!';
    await page.goto('/inscription');
    await page.getByLabel('Adresse email').fill(email);
    await page.getByLabel('Nom de commandant').fill(name);
    await page.getByLabel('Mot de passe (12 caractères minimum)', { exact: true }).fill(password);
    await page.getByLabel('Confirmer le mot de passe').fill(password);
    await page.getByRole('button', { name: 'Créer mon compte' }).click();
    await page.getByLabel('Adresse email').fill(email);
    await page.getByLabel('Mot de passe', { exact: true }).fill(password);
    await page.getByRole('button', { name: /Connexion/ }).click();
    await page.locator('.universe-card').filter({ has: page.getByRole('heading', { name: new RegExp(universe) }) }).getByRole('button').click();
    await expect(page).toHaveURL(/planetes\/\d+$/);
    const member = Number(queryIsolatedSql(process.env.BROWSER_ROUTER,
        `SELECT pu.id FROM player_universe pu JOIN player p ON p.id=pu.player_id WHERE p.email='${email}'`));
    return { name, member };
}

async function setTheme(page, name, theme) {
    runIsolatedSql(process.env.BROWSER_ROUTER,
        `UPDATE player SET preferences=JSON_SET(CASE WHEN JSON_TYPE(preferences)='OBJECT' THEN preferences ELSE JSON_OBJECT() END,'$.theme','${theme}') WHERE username='${name}'`);
}

test('L7E A : recherche, actualisation des non-lus et rédaction restent indépendantes', async ({ page, browser, baseURL }, info) => {
    test.setTimeout(180000);
    const owner = await register(page, 'Social');
    const context = await browser.newContext({ baseURL });
    try {
        const peer = await register(await context.newPage(), 'Social-Peer');
        runIsolatedSql(process.env.BROWSER_ROUTER,
            `INSERT INTO social_message(sender_id,recipient_id,content,created_at) VALUES(${peer.member},${owner.member},'Transport reçu. Merci pour les ressources.',UTC_TIMESTAMP())`);
        const conversationUrl = `/messages?with=${peer.member}`;
        await page.goto('/messages');
        const contact = page.getByRole('link', { name: new RegExp(peer.name) }).first();
        await expect(contact.locator('.inbox-contact-meta time')).toHaveAttribute('datetime', /T/);
        await expect(contact.locator('.inbox-unread-count')).toHaveText('1 message non lu');
        await expect(contact).not.toContainText('Transport reçu. Merci pour les ressources.');
        await page.goto(conversationUrl);
        await expect(page.locator('.message-day time')).toHaveCount(1);
        await page.setViewportSize({ width: 1920, height: 1080 });
        await page.screenshot({ path: info.outputPath('social-S12-messagerie-1920x1080.png'), animations: 'disabled' });
        const textarea = page.getByLabel('Votre message', { exact: true });
        await textarea.fill('Brouillon conservé pendant les actualisations.');
        await textarea.focus();
        const before = await page.locator('.message-thread').evaluate(node => node.scrollTop);
        const refreshed = page.waitForResponse(response => response.url().includes('/messages/non-lus'));
        await page.evaluate(() => window.dispatchEvent(new Event('genesis:refresh')));
        const update = await (await refreshed).json();
        expect(update.conversations).not.toContain('Transport reçu. Merci pour les ressources.');
        await expect(textarea).toBeFocused();
        await expect(textarea).toHaveValue('Brouillon conservé pendant les actualisations.');
        expect(await page.locator('.message-thread').evaluate(node => node.scrollTop)).toBe(before);
        await expect(page.locator('nav[aria-label="Conversations"] .active')).toContainText(peer.name);
        await page.getByRole('link', { name: 'Chercher dans cette conversation', exact: true }).click();
        await expect(page.getByRole('link', { name: 'Messagerie', exact: true })).toHaveAttribute('href', `/messages?with=${peer.member}`);
        await expect(page.locator('.sidebar nav a.active')).toContainText('Messages');
        await page.getByRole('link', { name: 'Messagerie', exact: true }).click();
        await expect(page).toHaveURL(new RegExp(`with=${peer.member}$`));

        // La recherche est une lecture : le nouveau message reste non lu jusqu’à l’ouverture du fil.
        runIsolatedSql(process.env.BROWSER_ROUTER,
            `INSERT INTO social_message(sender_id,recipient_id,content,created_at) VALUES(${peer.member},${owner.member},'Transport en attente de lecture.',UTC_TIMESTAMP())`);
        await page.goto(`/messages/recherche?with=${peer.member}&q=Transport`);
        await expect(page.locator('.message-search-result')).toHaveCount(2);
        expect(Number(queryIsolatedSql(process.env.BROWSER_ROUTER,
            `SELECT COUNT(*) FROM social_message WHERE recipient_id=${owner.member} AND read_at IS NULL`))).toBe(1);
        for (const theme of ['dark', 'light']) {
            await setTheme(page, owner.name, theme);
            await page.reload();
            await expect(page.locator('body')).toHaveAttribute('data-theme', theme);
            for (const width of [1440, 768, 390, 320]) {
                await page.setViewportSize({ width, height: 1000 });
                expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(width);
                if (width === 1440 || width === 390) {
                    expect((await new AxeBuilder({ page }).include('.social-workspace').analyze()).violations).toEqual([]);
                    await page.screenshot({ path: info.outputPath(`recherche-${theme}-${width}.png`), fullPage: true, animations: 'disabled' });
                }
            }
        }
        await page.locator('.message-search-result').first().getByRole('link', { name: 'Ouvrir la conversation' }).click();
        await expect(page.getByRole('heading', { name: 'Conversation avec ' + peer.name })).toBeVisible();
        expect(Number(queryIsolatedSql(process.env.BROWSER_ROUTER,
            `SELECT COUNT(*) FROM social_message WHERE recipient_id=${owner.member} AND read_at IS NULL`))).toBe(0);
    } finally {
        await context.close();
    }
});

test('L7E A : tableaux complets, protection au clavier et colonnes des guildes sur téléphone', async ({ page, browser, baseURL }, info) => {
    test.setTimeout(180000);
    const owner = await register(page, 'Ranking', 'Omega');
    const context = await browser.newContext({ baseURL });
    try {
        const peer = await register(await context.newPage(), 'Ranking-Peer', 'Omega');
        await page.goto('/guildes/creer');
        await expect(page.getByLabel('Nom de votre nouvelle guilde')).toBeVisible();
        await page.getByLabel('Nom de votre nouvelle guilde').fill('La confrérie des explorateurs des mondes lointains');
        await page.getByLabel('Tag de la guilde', { exact: true }).fill('L7E');
        await page.getByRole('button', { name: 'Fonder une guilde' }).click();
        // Deux calculs avec les mêmes joueurs garantissent une variation visible « (= 0) ».
        // Sans cet état, la première lecture peut ne montrer que « (—) » et masquer un débordement.
        const expireRanking = () => runIsolatedSql(process.env.BROWSER_ROUTER, 'UPDATE leaderboard_snapshot SET updated_at=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 2 DAY)');
        expireRanking();
        await page.goto('/classement');
        expireRanking();
        for (const theme of ['dark', 'light']) {
            await setTheme(page, owner.name, theme);
            await page.goto('/classement');
            const ownRow = page.getByRole('row').filter({ hasText: owner.name });
            await expect(ownRow).toHaveAttribute('aria-current', 'true');
            await expect(ownRow.locator('td').first().locator('.ranking-change')).toHaveText('(= 0)');
            await expect(ownRow.locator('.leaderboard-contact,.leaderboard-report')).toHaveCount(0);
            const peerRow = page.getByRole('row').filter({ hasText: peer.name });
            const protection = peerRow.locator('.ranking-protection summary');
            await protection.focus();
            await page.keyboard.press('Enter');
            await expect(peerRow.locator('.ranking-protection')).toHaveAttribute('open', '');
            await expect(peerRow.locator('.ranking-protection p')).toContainText('jusqu’au');
            await page.keyboard.press('Enter');
            for (const width of [1440, 768, 390, 320]) {
                await page.setViewportSize({ width, height: 1000 });
                expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(width);
                await expect(peerRow.getByRole('link', { name: 'Contacter ' + peer.name })).toBeVisible();
                // Une page sans débordement peut tout de même avoir du texte superposé entre deux cellules.
                const overflowingCells = await page.locator('.ranking-table--players').evaluate(table =>
                    [...table.querySelectorAll('th,td')]
                        .filter(cell => cell.getClientRects().length && cell.scrollWidth > cell.clientWidth + 2)
                        .map(cell => cell.textContent.trim()));
                expect(overflowingCells).toEqual([]);
                if (width <= 390) {
                    await peerRow.locator('.ranking-extra summary').click();
                    await expect(peerRow.locator('.ranking-extra dl dt')).toHaveCount(4);
                    await expect(peerRow.locator('.ranking-extra dl')).toBeVisible();
                    await peerRow.locator('.ranking-extra summary').click();
                }
                if (width === 1440 || width === 390) {
                    expect((await new AxeBuilder({ page }).include('.social-workspace').analyze()).violations).toEqual([]);
                    await page.screenshot({ path: info.outputPath(`joueurs-${theme}-${width}.png`), fullPage: true, animations: 'disabled' });
                }
            }
            await page.getByRole('navigation', { name: 'Classements', exact: true }).getByRole('link', { name: 'Guildes' }).click();
            await expect(page.locator('.ranking-table--guilds thead th')).toHaveCount(8);
            await expect(page.getByRole('columnheader', { name: 'Médiane des points joueurs' })).toHaveCount(1);
            for (const width of [1440, 768, 390, 320]) {
                await page.setViewportSize({ width, height: 1000 });
                expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(width);
                const region = page.getByRole('region', { name: 'Classement des guildes défilant horizontalement' });
                await region.focus();
                await region.evaluate(node => { node.scrollLeft = node.scrollWidth; });
                await expect(page.locator('.ranking-table--guilds tbody tr').first().locator('td').last()).toBeInViewport();
                if (width === 1440 || width === 390) {
                    expect((await new AxeBuilder({ page }).include('.social-workspace').analyze()).violations).toEqual([]);
                    await page.screenshot({ path: info.outputPath(`guildes-${theme}-${width}.png`), fullPage: true, animations: 'disabled' });
                }
            }
        }
        const plainContext = await browser.newContext({ baseURL, javaScriptEnabled: false, storageState: await page.context().storageState(), viewport: { width: 390, height: 1000 } });
        try {
            const plain = await plainContext.newPage();
            await plain.goto('/classement');
            const row = plain.getByRole('row').filter({ hasText: peer.name });
            await row.locator('.ranking-extra summary').click();
            await row.locator('.ranking-extra').getByRole('link', { name: 'Points de recherche', exact: true }).click();
            await expect(plain).toHaveURL(/category=research/);
            await plain.getByRole('link', { name: peer.name, exact: true }).click();
            await expect(plain.locator('.sidebar nav a.active')).toContainText('Classements');
            await expect(plain.locator('.social-profile-scores > div')).toHaveCount(5);
            expect(await plain.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(390);
            await plain.screenshot({ path: info.outputPath('profil-sans-javascript-390.png'), fullPage: true });
        } finally {
            await plainContext.close();
        }
    } finally {
        await context.close();
    }
});
