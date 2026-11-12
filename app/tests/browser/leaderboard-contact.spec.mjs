import { test, expect } from './support/isolated-test.mjs';
import { runIsolatedSql, queryIsolatedSql } from './support/game-fixtures.mjs';

async function registerAndJoin(page, prefix, universe = 'Turbo') {
  const id = crypto.randomUUID().slice(0, 8);
  const email = prefix.toLowerCase() + '-' + id + '@example.test';
  const username = prefix + '-' + id;
  const password = 'Browser-test-2026!';
  await page.goto('/inscription');
  await page.getByLabel('Adresse email').fill(email);
  await page.getByLabel('Nom de commandant').fill(username);
  await page.getByLabel('Mot de passe (12 caractères minimum)', { exact: true }).fill(password);
  await page.getByLabel('Confirmer le mot de passe').fill(password);
  await page.getByRole('button', { name: 'Créer mon compte' }).click();
  await expect(page).toHaveURL(/connexion/);
  await page.getByLabel('Adresse email').fill(email);
  await page.getByLabel('Mot de passe', { exact: true }).fill(password);
  await page.getByRole('button', { name: /Connexion/ }).click();
  await page.locator('article.universe-card').filter({ has: page.getByRole('heading', { name: new RegExp(universe) }) }).getByRole('button').click();
  await expect(page).toHaveURL(/planetes\/\d+$/);
  return username;
}

test('Classement : tris, contact direct et échange de messages privés', async ({ page, browser }) => {
  test.setTimeout(90000);
  const recipientPage = await browser.newPage();
  const senderName = await registerAndJoin(page, 'Rank-Sender');
  const recipientName = await registerAndJoin(recipientPage, 'Rank-Recipient');

  await page.goto('/messages');
  await expect(page.getByRole('navigation', { name: 'Conversations' }).getByRole('link', { name: new RegExp(recipientName) })).toHaveCount(0);
  await page.getByLabel('Rechercher un joueur').fill(recipientName);
  await page.getByRole('button', { name: 'Rechercher', exact: true }).click();
  await page.getByRole('region', { name: 'Résultats de recherche' }).getByRole('link', { name: new RegExp(recipientName) }).click();
  await expect(page.getByRole('heading', { name: 'Conversation avec ' + recipientName })).toBeVisible();
  await page.goto('/diplomatie?tab=relations');
  await expect(page.locator('.commander-card')).toHaveCount(0);
  await page.getByLabel('Rechercher un joueur').fill(recipientName);
  await page.getByRole('button', { name: 'Rechercher', exact: true }).click();
  await expect(page.locator('.commander-card')).toHaveCount(1);
  await expect(page.locator('.commander-card')).toContainText(recipientName);
  await page.locator('.commander-card').getByRole('button', { name: 'Proposer une alliance' }).click();
  await expect(page.getByLabel('Rechercher un joueur')).toHaveValue(recipientName);
  await expect(page.locator('#alliance-members .commander-card')).toContainText('Proposition envoyée');
  await page.getByRole('link', { name: 'Fermer la recherche' }).click();
  await expect(page.locator('#alliance-members .commander-card')).toContainText(recipientName);
  // Simuler la nouvelle échéance après la création des deux joueurs.
  runIsolatedSql(process.env.BROWSER_ROUTER, "UPDATE leaderboard_snapshot SET updated_at=DATE_SUB(UTC_TIMESTAMP(), INTERVAL 2 DAY)");
  await page.goto('/classement');
  await expect(page.getByRole('table', { name: /Classement de l’univers Turbo/ })).toBeVisible();
  const recipientRow = page.getByRole('row').filter({ hasText: recipientName });
  await expect(recipientRow.getByRole('link', { name: 'Contacter ' + recipientName })).toBeVisible();
  await expect(page.getByRole('columnheader')).toHaveText(['Rang', 'Commandant', 'Points de bâtiments ↕', 'Points de recherche ↕', 'Points de flotte ↕', 'Nombre de planètes ↕', 'Points totaux ↓', 'Actions']);
  await expect(page.getByRole('columnheader', { name: 'Classer par Points totaux' })).toHaveAttribute('aria-sort', 'descending');

  for (const [category, label] of [['buildings', 'Points de bâtiments'], ['research', 'Points de recherche'], ['fleet', 'Points de flotte'], ['planets', 'Nombre de planètes'], ['points', 'Points totaux']]) {
    const sort = page.getByRole('link', { name: 'Classer par ' + label, exact: true });
    await sort.focus();
    await page.keyboard.press('Enter');
    await expect(sort).toHaveAttribute('aria-current', 'page');
    await expect(page.getByRole('columnheader', { name: 'Classer par ' + label, exact: true })).toHaveAttribute('aria-sort', 'descending');
    await expect(page.getByLabel('Catégorie du classement')).toHaveCount(0);
    const ranks = await page.locator('tbody tr td:first-child').allTextContents();
    expect(ranks.map(text => Number(text.match(/#(\d+)/)[1]))).toEqual(ranks.map(text => Number(text.match(/#(\d+)/)[1])).sort((a, b) => a - b));
  }

  const body = 'Message depuis le classement ' + crypto.randomUUID();
  await page.getByRole('link', { name: 'Contacter ' + recipientName }).click();
  await expect(page).toHaveURL(/messages\?with=\d+/);
  await expect(page.getByRole('heading', { name: 'Conversation avec ' + recipientName, exact: true })).toBeVisible();
  await page.getByLabel('Votre message').fill(body);
  await page.getByRole('button', { name: 'Envoyer', exact: true }).click();
  await expect(page.getByRole('status')).toContainText('Message privé envoyé');
  await expect(page.locator('.message-thread')).toContainText(body);

  const conversationUrl = page.url();
  for (const theme of ['dark', 'light']) {
    runIsolatedSql(process.env.BROWSER_ROUTER, `UPDATE player SET preferences=JSON_SET(CASE WHEN JSON_TYPE(preferences)='OBJECT' THEN preferences ELSE JSON_OBJECT() END, '$.theme', '${theme}') WHERE username='${senderName}'`);
    for (const width of [1440, 834, 390, 320]) {
      await page.setViewportSize({ width, height: 900 });
      await page.goto(conversationUrl);
      await expect(page.locator('body')).toHaveAttribute('data-theme', theme);
      await expect(page.locator('.message-bubble>p').first()).toHaveCSS('font-size', '13px');
      await expect(page.locator('.message-meta').first()).toHaveCSS('font-size', '11px');
      await expect(page.getByLabel('Votre message')).toHaveCSS('font-size', width <= 1050 ? '16px' : '14px');
      expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(width);
      await page.screenshot({ path: `output/playwright/typography-messages-${theme}-${width}.png`, animations: 'disabled' });
    }
  }

  await recipientPage.goto('/messages');
  await expect(recipientPage.getByRole('link', { name: new RegExp(senderName) })).toBeVisible();
  await expect(recipientPage.getByRole('link', { name: new RegExp(senderName) }).getByRole('img', { name: 'Messages non lus' })).toBeVisible();
  await recipientPage.getByRole('link', { name: new RegExp(senderName) }).click();
  await expect(recipientPage.getByRole('link', { name: new RegExp(senderName) }).getByRole('img', { name: 'Messages non lus' })).toHaveCount(0);
  await expect(recipientPage.locator('.message-thread')).toContainText(body);
  const report = recipientPage.locator('.message-report summary').first();
  await expect(report).toHaveAccessibleName('Signaler ce message');
  await report.focus();
  await recipientPage.keyboard.press('Enter');
  await expect(recipientPage.getByLabel('Motif du signalement')).toBeVisible();
  await expect(report).toHaveCSS('min-width', '44px');
  await recipientPage.close();
});


test('Conversations : dernier message, bulles distinctes et témoin mobile', async ({ page, browser }, testInfo) => {
  test.setTimeout(90000);
  const peerPage = await browser.newPage();
  const ownName = await registerAndJoin(page, 'Thread-Own');
  const peerName = await registerAndJoin(peerPage, 'Thread-Peer');
  const project = process.env.BROWSER_ROUTER;
  const membership = name => Number(queryIsolatedSql(project, `SELECT pu.id FROM player_universe pu JOIN player p ON p.id=pu.player_id WHERE p.username='${name}'`));
  const ownId = membership(ownName), peerId = membership(peerName);
  const values = Array.from({ length: 30 }, (_, index) => {
    const sender = index % 2 === 0 ? peerId : ownId;
    const recipient = sender === peerId ? ownId : peerId;
    const content = index === 29 ? 'Dernier message du fil' : `Échange ${index} pour vérifier une conversation suffisamment longue.`;
    return `(${sender},${recipient},'${content}',UTC_TIMESTAMP())`;
  });
  runIsolatedSql(project, `INSERT INTO social_message (sender_id,recipient_id,content,created_at) VALUES ${values.join(',')}`);
  await page.setViewportSize({ width: 768, height: 900 });
  await page.goto('/classement');
  await expect(page.locator('.mobile-menu .menu-unread-dot')).toBeVisible();
  await page.setViewportSize({ width: 390, height: 900 });
  await expect(page.locator('.mobile-bottom-nav .menu-unread-dot')).toBeVisible();
  for (const theme of ['dark', 'light']) {
    runIsolatedSql(project, `UPDATE player SET preferences=JSON_SET(CASE WHEN JSON_TYPE(preferences)='OBJECT' THEN preferences ELSE JSON_OBJECT() END,'$.theme','${theme}') WHERE username='${ownName}'`);
    for (const width of [1440, 768, 390]) {
      await page.setViewportSize({ width, height: 900 });
      await page.goto(`/messages?with=${peerId}`);
      const thread = page.getByLabel('Historique de la conversation', { exact: true });
      await expect.poll(() => thread.evaluate(node => node.scrollHeight - node.scrollTop - node.clientHeight)).toBeLessThan(2);
      await expect(thread.getByText('Dernier message du fil', { exact: true })).toBeVisible();
      await expect(thread.getByText('Dernier message du fil', { exact: true })).toBeInViewport({ ratio: 1 });
      const geometry = await page.locator('.message-pane').evaluate(node => ({ pane: node.getBoundingClientRect().toJSON(), height: getComputedStyle(node).height, form: node.querySelector('form.message-compose').getBoundingClientRect().toJSON(), viewport: innerHeight, scroll: scrollY }));
      await page.screenshot({ path: testInfo.outputPath(`viewport-${theme}-${width}.png`) });
      await expect(page.getByLabel('Votre message', { exact: true }), `${theme}/${width}: ${JSON.stringify(geometry)}`).toBeInViewport({ ratio: 1 });
      await expect(thread.locator('.message-bubble--own .message-direction').first()).toHaveText('Vous');
      await expect(thread.locator('.message-bubble:not(.message-bubble--own) .message-direction').first()).toHaveText('Reçu');
      const colors = await thread.evaluate(node => Array.from(node.querySelectorAll('.message-bubble')).slice(0, 2).map(bubble => getComputedStyle(bubble).backgroundColor));
      expect(colors[0]).not.toBe(colors[1]);
      expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(width);
      if (width !== 768) await page.screenshot({ path: testInfo.outputPath(`thread-${theme}-${width}.png`), fullPage: true });
    }
  }
  await expect(page.locator('.mobile-bottom-nav .menu-unread-dot')).toBeHidden();
  const thread = page.getByLabel('Historique de la conversation', { exact: true });
  await thread.focus();
  await page.keyboard.press('Home');
  await expect.poll(() => thread.evaluate(node => node.scrollTop)).toBe(0);
  await page.keyboard.press('End');
  await expect.poll(() => thread.evaluate(node => node.scrollHeight - node.scrollTop - node.clientHeight)).toBeLessThan(2);
  runIsolatedSql(project, `INSERT INTO notification (recipient_id,message,created_at,journal_kind) VALUES (${ownId},'Construction terminée',UTC_TIMESTAMP(),'queue')`);
  const noticeId = Number(queryIsolatedSql(project, `SELECT MAX(id) FROM notification WHERE recipient_id=${ownId}`));
  await page.evaluate(notificationId => window.dispatchEvent(new CustomEvent('genesis:refresh', { detail: { notificationId, journal: true } })), noticeId);
  await expect(page.locator('.mobile-bottom-nav .menu-unread-dot')).toBeVisible();
  await page.goto('/');
  await expect(page.locator('.mobile-bottom-nav .menu-unread-dot')).toBeVisible();
  await page.getByRole('button', { name: 'Marquer ces événements comme consultés' }).click();
  await expect(page.locator('.mobile-bottom-nav .menu-unread-dot')).toBeHidden();
  await page.goto(`/messages?with=${peerId}`);
  await expect(page.locator('.mobile-bottom-nav .menu-unread-dot')).toBeHidden();
  await peerPage.close();
});

test('Alliances : Guildes par défaut et panneaux séparés', async ({ page }) => {
  await registerAndJoin(page, 'Tabs');
  await page.goto('/diplomatie');
  const tabs = page.getByRole('navigation', { name: 'Sections de diplomatie' });
  await expect(tabs.getByRole('link')).toHaveText(['Créer ou rejoindre une guilde', 'Relations commandants']);
  await expect(tabs.getByRole('link').first()).toHaveAttribute('aria-current', 'page');
  await expect(page.getByRole('region', { name: 'Centre de guilde' })).toBeVisible();
  await expect(page.getByRole('heading', { name: 'Relations entre commandants' })).toHaveCount(0);
  await tabs.getByRole('link', { name: 'Relations commandants', exact: true }).click();
  await expect(page.getByRole('heading', { name: 'Relations entre commandants' })).toBeVisible();
  await expect(page.getByRole('heading', { name: 'Relations entre guildes' })).toHaveCount(0);
});


test('Hub de guilde : création, équipage et discussion commune', async ({ page, browser }) => {
  const memberPage = await browser.newPage();
  const leader = await registerAndJoin(page, 'Hub-Leader', 'Omega');
  const member = await registerAndJoin(memberPage, 'Hub-Member', 'Omega');
  await page.goto('/diplomatie');
  const guildName = 'Hub-' + crypto.randomUUID().slice(0, 8);
  await page.getByLabel('Nom de votre nouvelle guilde').fill(guildName);await page.getByLabel('Tag de la guilde', { exact:true }).fill(crypto.randomUUID().replaceAll('-', '').slice(0,5).toUpperCase());
  await page.getByRole('button', { name: 'Fonder une guilde' }).click();
  await page.getByRole('navigation',{name:'Sections de diplomatie'}).getByRole('link',{name:'Membres',exact:true}).click();
  await expect(page.getByRole('heading', { name: 'Membres de la guilde', exact: true })).toBeVisible();
  await expect(page.locator('.guild-members-table')).toContainText(leader);
  await memberPage.goto('/diplomatie?tab=diplomacy');
  await expect(memberPage.locator('.guild-card')).toHaveCount(0);
  await memberPage.getByLabel('Rechercher une guilde').fill(guildName);
  await memberPage.getByRole('button', { name: 'Rechercher', exact: true }).click();
  await memberPage.locator('.guild-card').filter({ has: memberPage.getByRole('heading', { name: guildName, exact: true }) }).getByRole('button', { name: 'Rejoindre · recrutement ouvert' }).click();
  await memberPage.getByRole('navigation',{name:'Sections de diplomatie'}).getByRole('link',{name:'Membres',exact:true}).click();
  await expect(memberPage.locator('.guild-members-table')).toContainText(leader);
  await expect(memberPage.locator('.guild-members-table')).toContainText(member);
  await expect(memberPage.getByText('Gérer le commandement', { exact: true })).toHaveCount(0);
  await memberPage.goto('/diplomatie');
  await memberPage.getByRole('link', { name: 'Ouvrir le canal de guilde' }).click();
  await memberPage.getByLabel('Message pour la guilde').fill('Préparons notre prochaine expédition.');
  await memberPage.getByRole('button', { name: 'Envoyer', exact: true }).click();
  await page.goto('/diplomatie');
  await expect(page.locator('.guild-message-preview')).toContainText('Préparons notre prochaine expédition.');
  await expect(page.getByRole('link', { name: 'Messages', exact: false }).filter({ has: page.locator('.message-unread-dot') })).toHaveCount(1);
  await page.getByRole('link', { name: 'Ouvrir le canal de guilde' }).click();
  await expect(page.getByRole('navigation', { name: 'Conversations' }).getByRole('link').nth(0)).toContainText('Global');
  await expect(page.getByRole('navigation', { name: 'Conversations' }).getByRole('link').nth(1)).toContainText(guildName);
  runIsolatedSql(process.env.BROWSER_ROUTER, "UPDATE leaderboard_snapshot SET updated_at=DATE_SUB(UTC_TIMESTAMP(), INTERVAL 2 DAY)");
  await page.goto('/classement');
  await expect(page.getByRole('row').filter({ hasText: member })).toHaveClass(/ranking-row--guild/);
  await page.getByRole('navigation', { name: 'Classements' }).getByRole('link', { name: 'Guildes', exact: true }).click();
  await expect(page.getByRole('row').filter({ hasText: guildName })).toContainText('2');
  await expect(page.getByRole('columnheader')).toHaveText(['Rang', 'Guilde', 'Joueurs', 'Points bâtiments', 'Points recherches', 'Points flotte', 'Points totaux ↓', 'Médiane des points joueurs']);
  await expect(page.getByRole('row').filter({ hasText: guildName })).toHaveClass(/ranking-row--guild/);
  await memberPage.goto('/diplomatie');
  await memberPage.getByText('Gestion de la guilde', { exact: true }).click();
  await memberPage.getByRole('button', { name: 'Quitter la guilde' }).click();
  const enemyGuild = 'Enemy-' + crypto.randomUUID().slice(0, 8);
  await memberPage.getByLabel('Nom de votre nouvelle guilde').fill(enemyGuild);await memberPage.getByLabel('Tag de la guilde', { exact:true }).fill(crypto.randomUUID().replaceAll('-', '').slice(0,5).toUpperCase());
  await memberPage.getByRole('button', { name: 'Fonder une guilde' }).click();
  await page.goto('/diplomatie?tab=diplomacy');
  await page.getByLabel('Rechercher une guilde').fill(enemyGuild);
  await page.getByRole('button', { name: 'Rechercher', exact: true }).click();
  const result = page.getByRole('region', { name: 'Résultats de recherche de guildes' });
  await result.getByText('Déclarer ennemis', { exact: true }).click();
  await result.getByRole('button', { name: 'Confirmer l’hostilité' }).click();
  runIsolatedSql(process.env.BROWSER_ROUTER, "UPDATE leaderboard_snapshot SET updated_at=DATE_SUB(UTC_TIMESTAMP(), INTERVAL 2 DAY)");
  await page.goto('/classement?tab=guilds');
  await expect(page.getByRole('row').filter({ hasText: enemyGuild })).toHaveClass(/ranking-row--enemy/);
  await expect(page.getByRole('row').filter({ hasText: enemyGuild })).toContainText('Guilde ennemie');
  await expect(page.locator('.section-heading')).toHaveCount(0);
  await page.goto('/profil');
  await expect(page.getByRole('navigation', { name: 'Sections du profil' })).toHaveCount(0);
  await page.getByRole('link', { name: 'Paramètres', exact: true }).click();
  await expect(page.getByRole('link', { name: 'Paramètres', exact: true })).toHaveAttribute('aria-current', 'page');
  await memberPage.close();
});


test('Classement quotidien : points figés, gains, pertes et signe égal accessibles', async ({ page }, testInfo) => {
  test.setTimeout(120000);
  const name = await registerAndJoin(page, 'Daily');
  const colonyUrl = page.url();
  const project = process.env.BROWSER_ROUTER;
  const membership = Number(queryIsolatedSql(project, `SELECT m.id FROM player_universe m JOIN player p ON p.id=m.player_id WHERE p.username='${name}'`));
  const planet = Number(new URL(colonyUrl).pathname.split('/').pop());
  const universe = Number(queryIsolatedSql(project, `SELECT universe_id FROM player_universe WHERE id=${membership}`));
  const expire = () => runIsolatedSql(project, `UPDATE leaderboard_snapshot SET updated_at=DATE_SUB(UTC_TIMESTAMP(), INTERVAL 2 DAY) WHERE universe_id=${universe}`);
  await page.goto('/classement');
  const row = page.getByRole('row').filter({ hasText: name });
  await expect(row.locator('[data-category="points"] .ranking-change')).toHaveText(/\((—|= 0)\)/);
  const initial = await page.evaluate(() => fetch('/api/classement').then(response => response.json()));
  const before = initial.players.find(player => player.username === name).total_points;
  runIsolatedSql(project, `INSERT INTO planet_building (planet_id,slug,tier,level) VALUES (${planet},'mine_metal','basic',5) ON DUPLICATE KEY UPDATE level=5`);
  await page.reload();
  const frozen = await page.evaluate(() => fetch('/api/classement').then(response => response.json()));
  expect(frozen.players.find(player => player.username === name).total_points).toBe(before);
  expect(frozen.timezone).toBe('Europe/Paris');
  expect(await row.locator('[data-category="points"]').evaluate(node => node.firstChild.textContent.trim())).toBe(String(before));
  expire();
  await page.reload();
  await expect(row.locator('[data-category="points"] .ranking-change')).toHaveText(/\(\+\d/);
  await expect(row.locator('td').first().locator('.ranking-change')).toHaveText('(= 0)');
  await expect(page.getByText('Actualisation chaque jour à 00 h', { exact: false })).toBeVisible();
  const gained = await page.evaluate(() => fetch('/api/classement').then(response => response.json()));
  expect(gained.players.find(player => player.username === name).score_changes.points).toBeGreaterThan(0);
  for (const width of [320, 390, 768, 1440]) {
    await page.setViewportSize({ width, height: 900 });
    expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(width);
    await page.screenshot({ path: testInfo.outputPath(`daily-ranking-${width}.png`), animations: 'disabled' });
  }
  expire();
  await page.reload();
  await expect(row.locator('[data-category="points"] .ranking-change')).toHaveText(/\((—|= 0)\)/);
  await expect(row.locator('td').first().locator('.ranking-change')).toHaveText('(= 0)');
  runIsolatedSql(project, `UPDATE planet_building SET level=1 WHERE planet_id=${planet} AND slug='mine_metal' AND tier='basic'`);
  expire();
  await page.reload();
  await expect(row.locator('[data-category="points"] .ranking-change')).toHaveText(/\(−\d/);
  await page.goto(colonyUrl);
  await expect(page.locator('.empire-points')).toContainText('Points : −');
  await expect(page.locator('.metric-card').filter({ hasText: 'Classement' })).toContainText('Places : =');
});


test('Classement : tri global avant pagination et catégorie conservée', async ({ page }) => {
  test.setTimeout(120000);
  const ownName = await registerAndJoin(page, 'Zpage');
  const project = process.env.BROWSER_ROUTER;
  const universe = Number(queryIsolatedSql(project, `SELECT m.universe_id FROM player_universe m JOIN player p ON p.id=m.player_id WHERE p.username='${ownName}'`));
  const prefix = 'Apage-' + crypto.randomUUID().slice(0, 8);
  const players = Array.from({ length: 51 }, (_, index) => {
    const name = prefix + '-' + String(index).padStart(2, '0');
    return `('${name}@example.test','${name}','!unused-test-password!','[]','{}')`;
  });
  runIsolatedSql(project, `INSERT INTO player (email,username,password,roles,preferences) VALUES ${players.join(',')};
    INSERT INTO player_universe (player_id,universe_id,joined_at)
      SELECT id,${universe},UTC_TIMESTAMP() FROM player WHERE username LIKE '${prefix}%';
    INSERT INTO player_technology (player_universe_id,slug,tier,level)
      SELECT m.id,'weapons_engineering','basic',1 FROM player_universe m JOIN player p ON p.id=m.player_id WHERE p.username LIKE '${prefix}%';
    UPDATE leaderboard_snapshot SET updated_at=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 2 DAY) WHERE universe_id=${universe}`);
  await page.goto('/classement?category=research');
  await expect(page.locator('tbody tr')).toHaveCount(50);
  await expect(page.locator('tbody tr').first().locator('td').first()).toContainText('#1 ');
  await expect(page.locator('tbody tr').last().locator('td').first()).toContainText('#50 ');
  const next = page.getByRole('link', { name: 'Page suivante', exact: true });
  await expect(next).toHaveAttribute('href', /category=research.*page=2/);
  await next.click();
  await expect(page.getByRole('link', { name: 'Classer par Points de recherche', exact: true })).toHaveAttribute('aria-current', 'page');
  await expect(page.locator('tbody tr')).toHaveCount(2);
  await expect(page.locator('tbody tr').first().locator('td').first()).toContainText('#51 ');
  const ownRow = page.getByRole('row').filter({ hasText: ownName });
  await expect(ownRow.locator('td').first()).toContainText('#52 ');
  await page.getByRole('link', { name: 'Page précédente', exact: true }).click();
  await expect(page.getByRole('link', { name: 'Classer par Points de recherche', exact: true })).toHaveAttribute('aria-current', 'page');
  await expect(page.locator('tbody tr')).toHaveCount(50);
});
