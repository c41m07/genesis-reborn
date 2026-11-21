import { test, expect } from './support/isolated-test.mjs';
import { queryIsolatedSql, runIsolatedSql } from './support/game-fixtures.mjs';

test.use({ keepDailyRewardPrompt: true });

async function login(page, email, password, remember = false) {
    await page.getByLabel('Adresse email').fill(email);
    await page.getByLabel('Mot de passe', { exact: true }).fill(password);
    if (remember) await page.getByRole('checkbox', { name: /Se souvenir/ }).check();
    await page.getByRole('button', { name: /Connexion/ }).click();
}

async function newMember(page, { asAdmin = false, remember = false } = {}) {
    const id = crypto.randomUUID().slice(0, 8);
    const email = 'daily-reward-menu-' + id + '@example.test';
    const password = crypto.randomUUID() + 'Aa1!';
    await page.goto('/inscription');
    await page.getByLabel('Adresse email').fill(email);
    await page.getByLabel('Nom de commandant').fill('Orion-' + id);
    await page.getByLabel('Mot de passe (12 caractères minimum)', { exact: true }).fill(password);
    await page.getByLabel('Confirmer le mot de passe').fill(password);
    await page.getByRole('button', { name: 'Créer mon compte' }).click();
    if (asAdmin) {
        await expect(page).toHaveURL(/connexion/);
        runIsolatedSql(process.env.BROWSER_ROUTER, "UPDATE player SET roles=JSON_ARRAY('ROLE_ADMIN') WHERE email='" + email + "'");
    }
    await login(page, email, password, remember);
    await expect(page).toHaveURL(/univers/);
    await expect(page.getByRole('dialog', { name: 'Récompense quotidienne' })).toBeHidden();
    await page.locator('.universe-card').filter({ has: page.getByRole('heading', { name: /Omega/ }) }).getByRole('button').click();
    await expect(page).toHaveURL(/planetes\/\d+$/);

    return { email, password, home: page.url() };
}

const rewardPanel = response => new URL(response.url()).pathname === '/recompense-quotidienne/panneau';

test('Quotidiennes : une ouverture à la connexion, sept jours, fermeture et retrait explicite', async ({ page, browser, baseURL }) => {
    const pendingPanel = page.waitForResponse(rewardPanel);
    const member = await newMember(page);
    const panelResponse = await pendingPanel;
    expect(panelResponse.headers()['cache-control']).toContain('no-store');
    const dialog = page.getByRole('dialog', { name: 'Récompense quotidienne' });
    const gift = page.locator('.topbar').getByRole('link', { name: 'Quotidiennes', exact: true });
    const project = process.env.BROWSER_ROUTER;
    const playerId = Number(queryIsolatedSql(project, "SELECT id FROM player WHERE email='" + member.email + "'"));
    const receipts = () => Number(queryIsolatedSql(project, 'SELECT COUNT(*) FROM daily_reward_claim WHERE player_id=' + playerId));
    await expect(dialog).toBeVisible();
    await expect(dialog.getByRole('listitem')).toHaveCount(7);
    await expect(dialog.locator('[aria-current="step"]')).toContainText('Jour 1');
    await page.keyboard.press('Escape');
    await expect(dialog).toBeHidden();
    await expect(gift).toBeFocused();
    expect(receipts()).toBe(0);

    await page.reload();
    await expect(page.locator('[data-daily-reward-ui-auto-open-value]')).toHaveAttribute('data-daily-reward-ui-auto-open-value', 'false');
    await expect(dialog).toBeHidden();
    const other = await page.context().newPage();
    try {
        await other.goto(member.home);
        await expect(other.locator('[data-daily-reward-ui-auto-open-value]')).toHaveAttribute('data-daily-reward-ui-auto-open-value', 'false');
        await expect(other.getByRole('dialog')).toBeHidden();
    } finally {
        await other.close();
    }

    const nativeContext = await browser.newContext({ baseURL, javaScriptEnabled: false, storageState: await page.context().storageState() });
    try {
        const native = await nativeContext.newPage();
        await native.goto(member.home);
        await native.locator('.topbar').getByRole('link', { name: 'Quotidiennes', exact: true }).click();
        await expect(native.getByRole('button', { name: 'Récupérer', exact: true })).toBeVisible();
        expect(receipts()).toBe(0);
    } finally {
        await nativeContext.close();
    }

    await page.setViewportSize({ width: 390, height: 844 });
    const fresh = page.waitForResponse(rewardPanel);
    await gift.click();
    expect((await fresh).ok()).toBe(true);
    await expect(dialog).toBeVisible();
    expect(await dialog.evaluate(node => node.scrollWidth <= node.clientWidth + 1)).toBe(true);
    await dialog.getByRole('button', { name: 'Plus tard' }).click();
    await expect(dialog).toBeHidden();
    expect(receipts()).toBe(0);
    const planetId = Number(new URL(member.home).pathname.split('/').pop());
    const setMetal = stock => runIsolatedSql(project, 'UPDATE planet_resource SET current_stock=' + stock +
        " WHERE planet_id=" + planetId + " AND resource_type='metal'");
    setMetal(10000);
    await gift.press('Enter');
    await dialog.getByRole('button', { name: 'Récupérer le lot' }).click();
    await expect(page.getByRole('alert').filter({ hasText: 'Stockage insuffisant' })).toBeVisible();
    await expect(gift).toBeVisible();
    expect(receipts()).toBe(0);

    setMetal(5000);
    await gift.click();
    await dialog.getByRole('button', { name: 'Récupérer le lot' }).click();
    await expect(page.getByRole('status').filter({ hasText: 'Jour 1 récupéré' })).toBeVisible();
    await expect(gift).toHaveCount(0);
    await expect(page.locator('#daily-reward-heading')).toBeFocused();
    expect(receipts()).toBe(1);
});

test('Quotidiennes : deux nouvelles pages de jeu partagent le même repère de connexion', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 });
    const member = await newMember(page);
    const dialog = page.getByRole('dialog', { name: 'Récompense quotidienne' });
    await dialog.getByRole('button', { name: 'Plus tard' }).click();
    await page.getByRole('button', { name: 'Déconnexion', exact: true }).click();
    await expect(page).toHaveURL(/connexion/);
    await login(page, member.email, member.password);
    await expect(page).toHaveURL(/univers/);

    const other = await page.context().newPage();
    try {
        const pages = [page, other];
        const responses = await Promise.all(pages.map(tab => tab.goto(member.home)));
        const documents = await Promise.all(responses.map(response => response.text()));
        const shown = documents.map(html => html.includes('data-daily-reward-ui-auto-open-value="true"'));
        expect(shown.filter(Boolean)).toHaveLength(1);
        await expect(pages[shown.indexOf(true)].getByRole('dialog', { name: 'Récompense quotidienne' })).toBeVisible();
        await expect(pages[shown.indexOf(false)].getByRole('dialog')).toBeHidden();
    } finally {
        await other.close();
    }
});


test('Quotidiennes : le cookie de mémorisation ne lance pas la fenêtre', async ({ page }) => {
    const member = await newMember(page, { remember: true });
    const dialog = page.getByRole('dialog', { name: 'Récompense quotidienne' });
    await dialog.getByRole('button', { name: 'Plus tard' }).click();
    const cookies = await page.context().cookies();
    expect(cookies.some(cookie => cookie.name === 'REMEMBERME')).toBe(true);
    expect(cookies.some(cookie => cookie.name === 'PHPSESSID')).toBe(true);
    await page.context().clearCookies({ name: 'PHPSESSID' });
    const selected = await page.goto(member.home);
    const html = await selected.text();
    await expect(page).toHaveURL(/planetes\/\d+$/);
    expect(html).toContain('data-daily-reward-ui-auto-open-value="false"');
    await expect(dialog).toBeHidden();
    await expect(page.locator('.topbar').getByRole('link', { name: 'Quotidiennes', exact: true })).toBeVisible();
});

test('Barre du jeu : alertes, droits, focus et infobulles au-dessus du vrai rail', async ({ page, browser, baseURL }) => {
    const member = await newMember(page, { asAdmin: true });
    const header = page.locator('.topbar');
    const closeAvailable = tab => tab.getByRole('dialog', { name: 'Récompense quotidienne' })
        .getByRole('button', { name: 'Plus tard', exact: true }).click();
    await closeAvailable(page);
    const other = await browser.newContext({ baseURL });
    try {
        const senderPage = await other.newPage();
        const sender = await newMember(senderPage);
        await closeAvailable(senderPage);
        await expect(senderPage.locator('.topbar').getByRole('link', { name: /^Admin/ })).toHaveCount(0);
        const project = process.env.BROWSER_ROUTER;
        const empire = email => Number(queryIsolatedSql(project, "SELECT current_player_universe_id FROM player WHERE email='" + email + "'"));
        runIsolatedSql(project, 'INSERT INTO social_message (sender_id,recipient_id,content,created_at) VALUES (' +
            empire(sender.email) + ',' + empire(member.email) + ",'Recette barre du jeu',UTC_TIMESTAMP())");
    } finally {
        await other.close();
    }
    await page.reload();
    const wiki = header.getByRole('link', { name: 'Wiki', exact: true });
    const mail = header.getByRole('link', { name: 'Messages', exact: true });
    const gift = header.getByRole('link', { name: 'Quotidiennes', exact: true });
    const admin = header.getByRole('link', { name: /^Admin/ });
    await expect(admin).toHaveAttribute('href', /admin/);
    await expect(mail).toHaveAttribute('aria-description', '1 message non lu');
    await expect(page.locator('.resource-event-stack .event-drawer')).toBeVisible();
    const order = header.locator('[data-header-tooltip]:visible');
    expect(await order.evaluateAll(nodes => nodes.map(node => node.dataset.headerTooltip))).toEqual([
        'Quotidiennes', 'Messages', 'Wiki', 'Feedback', 'Admin',
    ]);
    const tooltipOverRail = () => wiki.evaluate(node => {
        const rect = node.getBoundingClientRect();
        const y = rect.bottom + 10;
        const tooltip = getComputedStyle(node, '::after');
        const hit = document.elementFromPoint(rect.left + rect.width / 2, y);
        return tooltip.content === '"Wiki"' && tooltip.display === 'block' && hit === node;
    });
    for (const width of [1440, 390, 360]) {
        await page.setViewportSize({ width, height: 844 });
        await expect(admin).toBeInViewport();
        await mail.focus();
        await page.keyboard.press('Tab');
        await expect(wiki).toBeFocused();
        await expect.poll(tooltipOverRail).toBe(true);
        await page.keyboard.press('Escape');
        await expect(wiki).toBeFocused();
        await expect.poll(tooltipOverRail).toBe(false);
        expect(await header.evaluate(node => node.scrollWidth <= node.clientWidth + 1)).toBe(true);
    }
    await expect(gift).toBeVisible();
    await mail.focus();
    await page.evaluate(() => window.dispatchEvent(new CustomEvent('genesis:message-count', { detail: { count: 0 } })));
    await expect(mail).toBeHidden();
    await expect(wiki).toBeFocused();
});
