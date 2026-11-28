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
        const project = process.env.BROWSER_ROUTER;
        runIsolatedSql(project,
            `UPDATE player p JOIN player_universe pu ON pu.player_id=p.id SET p.preferences=JSON_SET(CASE WHEN JSON_TYPE(p.preferences)='OBJECT' THEN p.preferences ELSE JSON_OBJECT() END,'$.timezone','America/St_Johns') WHERE pu.id=${owner.member}`);
        runIsolatedSql(project,
            `INSERT INTO social_message(sender_id,recipient_id,content,created_at) VALUES(${peer.member},${owner.member},'Transport reçu. Merci pour les ressources.','2026-11-10 14:45:00')`);
        const conversationUrl = `/messages?with=${peer.member}`;
        await page.goto('/messages');
        const contact = page.getByRole('link', { name: new RegExp(peer.name) }).first();
        await expect(contact.locator('.inbox-contact-meta time')).toHaveAttribute('datetime', /T/);
        await expect(contact.locator('.inbox-unread-count')).toHaveText('1 message non lu');
        await expect(contact).not.toContainText('Transport reçu. Merci pour les ressources.');
        await page.goto(conversationUrl);
        await expect(page.locator('.message-day time')).toHaveCount(1);
        const contactTime = contact.locator('.inbox-contact-meta time');
        const messageTime = page.locator('.message-meta time');
        await expect(contactTime).toHaveText('10/11 11:15');
        await expect(messageTime).toHaveText('10/11/2026 11:15');
        expect(Date.parse(await contactTime.getAttribute('datetime'))).toBe(Date.parse(await messageTime.getAttribute('datetime')));
        const fragmentTime = await page.evaluate(async (member) => {
            const response = await fetch('/messages/non-lus?with=' + member, {cache:'no-store'});
            if (!response.ok) throw new Error('Actualisation des conversations : HTTP ' + response.status);
            const {conversations} = await response.json();
            return new DOMParser().parseFromString(conversations, 'text/html').querySelector('.inbox-item[aria-current="page"] time')?.textContent;
        }, peer.member);
        expect(fragmentTime).toBe('10/11 11:15');
        await page.setViewportSize({ width: 1920, height: 1080 });
        await page.screenshot({ path: info.outputPath('social-S12-messagerie-1920x1080.png'), animations: 'disabled' });
        await page.setViewportSize({ width: 390, height: 844 });
        await expect(page.getByRole('link', { name: 'Retour à la messagerie' })).toHaveAttribute('href', '/messages');
        const report = page.locator('.message-report');
        await report.locator('summary').focus();
        await page.keyboard.press('Enter');
        await expect(report).toHaveAttribute('open', '');
        await expect(report.getByRole('button', { name: 'Fermer', exact: true })).toBeVisible();
        await page.screenshot({ path: info.outputPath('social-S16-signalement-390x844.png'), animations: 'disabled' });
        await page.keyboard.press('Escape');
        await expect(report).not.toHaveAttribute('open', '');
        await expect(report.locator('summary')).toBeFocused();
        await page.setViewportSize({ width: 1920, height: 1080 });
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
        const peerPage = await context.newPage();
        const peer = await register(peerPage, 'Ranking-Peer', 'Omega');
        await page.goto('/guildes/creer');
        await expect(page.getByLabel('Nom de votre nouvelle guilde')).toBeVisible();
        await page.getByLabel('Nom de votre nouvelle guilde').fill('La confrérie des explorateurs des mondes lointains');
        await page.getByLabel('Tag de la guilde', { exact: true }).fill('L7E');
        await page.getByRole('button', { name: 'Fonder une guilde' }).click();
        runIsolatedSql(process.env.BROWSER_ROUTER,
            `INSERT INTO profile_cosmetic(player_id,code,awarded_at,equipped)
             SELECT player_id,'amateur_pirate_hunter',UTC_TIMESTAMP(),1 FROM player_universe WHERE id=${owner.member}
             UNION ALL SELECT player_id,'polar_night',UTC_TIMESTAMP(),0 FROM player_universe WHERE id IN (${owner.member},${peer.member})
             UNION ALL SELECT player_id,'guardian_of_stars',UTC_TIMESTAMP(),0 FROM player_universe WHERE id=${owner.member}
             UNION ALL SELECT player_id,'guardian_of_stars',UTC_TIMESTAMP(),0 FROM player_universe WHERE id=${peer.member}`);
        await page.goto('/profil#profile-cosmetics');
        await expect(page.getByRole('checkbox', { name: /Chasseur de Pirate amateur/ })).toBeChecked();
        await page.getByRole('checkbox', { name: /Gardien des étoiles/ }).check();
        await page.getByRole('checkbox', { name: /Nuit polaire/ }).check();
        await page.getByRole('button', { name: 'Enregistrer l’apparence' }).click();
        await expect(page.getByRole('checkbox', { name: /Gardien des étoiles/ })).toBeChecked();
        // Deux calculs avec les mêmes joueurs garantissent une variation visible « (=) ».
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
            await expect(ownRow.locator('td').first().locator('.ranking-change')).toHaveText('(=)');
            await expect(ownRow.locator('.leaderboard-contact,.leaderboard-report')).toHaveCount(0);
            const peerRow = page.getByRole('row').filter({ hasText: peer.name });
            await expect(ownRow.locator('th .ranking-label--title')).toHaveText(['Chasseur de Pirate amateur', 'Gardien des étoiles']);
            await expect(ownRow.getByText('Vous', { exact: true })).toHaveCount(0);
            await expect(peerRow.locator('.ranking-label--title')).toHaveCount(0);
            await expect(peerRow.locator('.ranking-label--protection')).toHaveText('Protégé');
            await expect(ownRow).toHaveCSS('background-image', /genesis-avent-panorama-720/);
            await expect(peerRow).not.toHaveClass(/ranking-banner--polar-night/);
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
                    await expect(peerRow.locator('.ranking-extra dl dt')).toHaveCount(5);
                    await expect(peerRow.locator('.ranking-extra dt').last()).toHaveText('Crédits');
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
                await region.scrollIntoViewIfNeeded();
                await region.focus();
                await expect(region).toBeFocused();
                await region.evaluate(node => { node.scrollTo({ left: 0, behavior: 'instant' }); });
                const maxScroll = await region.evaluate(node => node.scrollWidth - node.clientWidth);
                if (width <= 390) {
                    expect(maxScroll).toBeGreaterThan(0);
                    await page.keyboard.press('ArrowRight');
                    await expect.poll(() => region.evaluate(node => node.scrollLeft)).toBeGreaterThan(0);
                }
                await region.evaluate(node => { node.scrollTo({ left: node.scrollWidth, behavior: 'instant' }); });
                await expect.poll(() => region.evaluate(node => Math.abs(node.scrollWidth - node.clientWidth - node.scrollLeft))).toBeLessThanOrEqual(1);
                const lastCell = region.locator('tbody tr').first().locator('td').last();
                await expect(lastCell).toBeInViewport({ ratio: 0.99 });
                if (width <= 390) {
                    const guide = page.locator('.first-steps-popup-enhanced');
                    await expect(guide).toHaveAttribute('open', '');
                    await expect(guide.getByRole('button', { name: 'Voir l’étape illustrée' })).toBeEnabled();
                    await expect.poll(async () => {
                        const [help, cell] = await Promise.all([guide.boundingBox(), lastCell.boundingBox()]);
                        return help.y + help.height - cell.y;
                    }).toBeLessThanOrEqual(0);
                }
                if (width === 1440 || width === 390) {
                    expect((await new AxeBuilder({ page }).include('.social-workspace').analyze()).violations).toEqual([]);
                    await page.screenshot({ path: info.outputPath(`guildes-${theme}-${width}.png`), fullPage: true, animations: 'disabled' });
                }
            }
        }
        await page.goto('/classement');
        await page.getByRole('link', { name: owner.name, exact: true }).click();
        await expect(page.locator('.commander-profile-identity .ranking-label--title')).toHaveText(['Chasseur de Pirate amateur', 'Gardien des étoiles']);
        await peerPage.goto(page.url());
        const heading = peerPage.locator('header.commander-profile-identity');
        const banner = heading.getByRole('img', { name: 'Bannière Nuit polaire' });
        for (const [theme, width] of [['dark', 1920], ['light', 390]]) {
            await setTheme(peerPage, peer.name, theme);
            await peerPage.setViewportSize({ width, height: 1080 });
            await peerPage.reload();
            await expect(banner).toBeVisible();
            await expect.poll(() => banner.evaluate(node => node.naturalWidth)).toBeGreaterThan(0);
            await expect(heading.getByRole('link', { name: 'Écrire à ' + owner.name })).toBeVisible();
            await expect(heading.locator('.ranking-label--title')).toHaveCount(2);
            expect((await new AxeBuilder({ page: peerPage }).include('.commander-profile-identity').analyze()).violations).toEqual([]);
            await heading.screenshot({ path: info.outputPath(`profil-banniere-${theme}-${width}.png`), animations: 'disabled' });
        }
        const project = process.env.BROWSER_ROUTER;
        const peerBadges = page.getByRole('row').filter({ hasText: peer.name }).locator('.ranking-badges');
        runIsolatedSql(project, `INSERT INTO alliance_contract (player_a_id,player_b_id,proposer_id,status)
            VALUES (${owner.member},${peer.member},${owner.member},'accepted')`);
        await page.goto('/classement');
        await expect(peerBadges.locator('.ranking-label--commander')).toHaveText('Allié commandant');
        runIsolatedSql(project, `UPDATE alliance_contract SET status='enemies' WHERE player_a_id=${owner.member} AND player_b_id=${peer.member}`);
        await page.reload();
        await expect(peerBadges.locator('.ranking-label--enemy')).toHaveText('Ennemi');
        runIsolatedSql(project, `INSERT INTO guild_member (guild_id,player_universe_id)
            SELECT id,${peer.member} FROM guild WHERE leader_id=${owner.member}`);
        await page.reload();
        await expect(peerBadges.locator('.ranking-label--guild')).toHaveText('Même guilde');
        await expect(peerBadges.locator('.ranking-label--enemy')).toHaveCount(0);
        runIsolatedSql(project, `UPDATE profile_cosmetic c JOIN player_universe m ON m.player_id=c.player_id
            SET c.equipped=1 WHERE m.id=${peer.member} AND c.code='guardian_of_stars';
            INSERT INTO profile_cosmetic(player_id,code,awarded_at,equipped)
            SELECT player_id,'amateur_pirate_hunter',UTC_TIMESTAMP(),1 FROM player_universe WHERE id=${peer.member}`);
        await page.reload();
        await expect(peerBadges.locator('.ranking-label')).toHaveText(['Gardien des étoiles', 'Chasseur de Pirate amateur', 'Même guilde', 'Protégé']);
        for (const width of [390, 320]) {
            await page.setViewportSize({ width, height: 844 });
            expect(await peerBadges.evaluate(node => node.scrollWidth <= node.clientWidth)).toBe(true);
        }
        runIsolatedSql(project, `INSERT INTO guild (universe_id,leader_id,name,tag)
            SELECT universe_id,id,'Veilleurs du Nord','VN' FROM player_universe WHERE id=${peer.member};
            UPDATE guild_member SET guild_id=(SELECT id FROM guild WHERE leader_id=${peer.member}) WHERE player_universe_id=${peer.member};
            INSERT INTO guild_relation (guild_a_id,guild_b_id,proposer_id,status,version)
            SELECT a.id,b.id,a.id,'pending',1 FROM guild a JOIN guild b ON a.leader_id=${owner.member} AND b.leader_id=${peer.member};
            INSERT INTO vacation_period (membership_id,started_at,scheduled_end_at) VALUES (${peer.member},UTC_TIMESTAMP(),DATE_ADD(UTC_TIMESTAMP(),INTERVAL 2 DAY))`);
        await page.reload();
        await expect(peerBadges.locator('.ranking-label--allied-guild')).toHaveCount(0);
        expireRanking();
        await expect(peerBadges.locator('summary')).toHaveText('En vacances');
        await peerBadges.locator('summary').click();
        await expect(peerBadges.locator('details p')).toContainText('jusqu’au');
        for (const [status, label, badge] of [
            ['non_aggression', 'Pacte de non-agression', 'non-aggression'],
            ['defense', 'Pacte de défense', 'defense'],
            ['allies', 'Guilde alliée', 'allied-guild'],
        ]) {
            runIsolatedSql(project, `UPDATE guild_relation SET status='${status}' WHERE guild_b_id=(SELECT id FROM guild WHERE leader_id=${peer.member})`);
            await page.reload();
            await expect(peerBadges.locator('.ranking-label--' + badge)).toHaveText(label);
            await expect(peerBadges.locator('.ranking-label--enemy')).toHaveCount(status === 'allies' ? 0 : 1);
            await page.getByRole('navigation', { name: 'Classements', exact: true }).getByRole('link', { name: 'Guildes' }).click();
            const guildRow = page.getByRole('row').filter({ hasText: 'Veilleurs du Nord' });
            await expect(guildRow.locator('.ranking-label--' + badge)).toHaveText(label);
            await page.getByRole('navigation', { name: 'Classements', exact: true }).getByRole('link', { name: 'Joueurs' }).click();
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
