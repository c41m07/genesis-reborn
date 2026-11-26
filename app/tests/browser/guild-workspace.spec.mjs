import { test, expect } from './support/isolated-test.mjs';
import AxeBuilder from '@axe-core/playwright';
import { runIsolatedSql } from './support/game-fixtures.mjs';

async function enterUniverse(page, name, email) {
  const password = crypto.randomUUID() + 'aA1!';
  await page.goto('/inscription');
  await page.getByLabel('Adresse email').fill(email);
  await page.getByLabel('Nom de commandant').fill(name);
  await page.getByLabel('Mot de passe (12 caractères minimum)', { exact: true }).fill(password);
  await page.getByLabel('Confirmer le mot de passe').fill(password);
  await page.getByRole('button', { name: 'Créer mon compte' }).click();
  await expect(page).toHaveURL(/connexion/);
  await page.getByLabel('Adresse email').fill(email);
  await page.getByLabel('Mot de passe', { exact: true }).fill(password);
  await page.getByRole('button', { name: /Connexion/ }).click();
  await page.locator('article.universe-card').filter({
    has: page.getByRole('heading', { name: /Omega/ }),
  }).getByRole('button').click();
  await expect(page).toHaveURL(/planetes\/\d+$/);
}

test('Proposition B : pages de guilde, cartes, menus mobiles et aperçu sans lecture implicite', async ({ page, browser, baseURL }, testInfo) => {
  test.setTimeout(240000);
  const suffix = crypto.randomUUID().slice(0, 8);
  const leaderName = 'Aster-' + suffix;
  const memberName = 'Cassiopée-des-Confins-' + suffix;
  const leaderEmail = 'guild-b-leader-' + suffix + '@example.test';
  const guildName = 'Les Navigateurs de l’Éclipse ' + suffix;
  const memberContext = await browser.newContext({ baseURL });
  const outsiderContext = await browser.newContext({ baseURL });
  try {
    await page.setViewportSize({ width: 1440, height: 900 });
    await enterUniverse(page, leaderName, leaderEmail);
    await page.goto('/guildes/creer');
    await page.getByLabel('Nom de votre nouvelle guilde').fill(guildName);
    await page.getByLabel('Tag de la guilde', { exact: true }).fill('N' + suffix.slice(0, 4));
    await page.getByRole('button', { name: 'Fonder une guilde' }).click();

    await page.getByRole('navigation', { name: 'Navigation de guilde' })
      .getByRole('link', { name: 'Personnalisation', exact: true }).click();
    await page.getByLabel('Description', { exact: true }).fill('Nous explorons les confins ensemble.\nEntraide, échanges et projets collectifs : chaque commandant trouve sa place dans notre guilde.');
    // La fixture reste dans les dimensions autorisées et passe par le vrai stockage d’images.
    const banner = await page.evaluate(() => {
      const canvas = document.createElement('canvas');
      canvas.width = 1200;
      canvas.height = 360;
      const context = canvas.getContext('2d');
      const background = context.createLinearGradient(0, 0, 1200, 360);
      background.addColorStop(0, '#101b2b');
      background.addColorStop(.6, '#193e54');
      background.addColorStop(1, '#25243e');
      context.fillStyle = background;
      context.fillRect(0, 0, 1200, 360);
      context.fillStyle = '#b8d7e9';
      for (let index = 0; index < 80; index++) {
        context.beginPath();
        context.arc(index * 137 % 1200, index * 61 % 360, index % 4 ? 1 : 1.7, 0, Math.PI * 2);
        context.fill();
      }
      return canvas.toDataURL('image/png').split(',')[1];
    });
    await page.getByLabel('Bannière', { exact: true }).setInputFiles({
      name: 'banniere-de-test.png',
      mimeType: 'image/png',
      buffer: Buffer.from(banner, 'base64'),
    });
    await page.getByRole('button', { name: 'Enregistrer la présentation' }).click();
    await expect(page.locator('.guild-public-banner')).toBeVisible();

    const memberPage = await memberContext.newPage();
    await enterUniverse(memberPage, memberName, 'guild-b-member-' + suffix + '@example.test');
    await memberPage.goto('/guildes?q=' + encodeURIComponent(guildName));
    await memberPage.getByRole('button', { name: 'Demander à rejoindre' }).click();
    await expect(memberPage.getByText('Candidature envoyée. Elle doit être acceptée par la guilde.', { exact: true })).toBeVisible();
    await page.goto('/guildes');
    await page.getByRole('button', { name: 'Accepter la candidature', exact: true }).click();
    await expect(page.getByText('Demande acceptée : l’adhésion est enregistrée.', { exact: true })).toBeVisible();
    await memberPage.goto('/guildes');
    await memberPage.getByRole('link', { name: 'Ouvrir le canal de guilde' }).click();
    for (const message of ['Premier échange de test', 'Les projets sont disponibles.', 'La discussion continue dans le canal.', 'Bienvenue aux nouveaux commandants !']) {
      await memberPage.getByRole('textbox', { name: 'Message pour le canal de guilde', exact: true }).fill(message);
      await memberPage.getByRole('button', { name: 'Envoyer', exact: true }).click();
    }

    const outsider = await outsiderContext.newPage();
    await enterUniverse(outsider, 'Orion-' + suffix, 'guild-b-outsider-' + suffix + '@example.test');
    await outsider.goto('/diplomatie/commandants?q=' + encodeURIComponent(leaderName));
    await outsider.locator('.relation-results .commander-card')
      .getByRole('button', { name: 'Proposer une alliance', exact: true }).click();

    for (const theme of ['dark', 'light']) {
      runIsolatedSql(process.env.BROWSER_ROUTER, `UPDATE player SET preferences=JSON_SET(CASE WHEN JSON_TYPE(preferences)='OBJECT' THEN preferences ELSE JSON_OBJECT() END,'$.theme','${theme}') WHERE email='${leaderEmail}'`);
      for (const width of [1920, 1440, 768, 390, 320]) {
        await page.setViewportSize({ width, height: width === 1920 ? 1080 : 900 });
        for (const [url, key, title] of [['/guildes', 'guilde', 'Ma guilde'], ['/guildes/membres', 'membres', 'Membres de ma guilde'], ['/diplomatie/commandants', 'relations', 'Relations entre commandants']]) {
          expect((await page.goto(url)).status()).toBe(200);
          await expect(page.getByRole('heading', { level: 1 })).toHaveText(title);
          await expect(page.locator('#command-navigation a[aria-current="page"]')).toHaveCount(1);
          await expect.poll(() => page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(width);

          if (key === 'guilde') {
            await expect(page.locator('.guild-message-preview')).toHaveCount(3);
            await expect(page.locator('.guild-overview-conversation')).not.toContainText('Premier échange de test');
            await expect(page.locator('.guild-overview-conversation')).toContainText('Bienvenue aux nouveaux commandants !');
            await expect(page.locator('body')).toHaveAttribute('data-menu-notifications-messages-value', /[1-9]/);
            if (width === 1920) {
              const latest = await page.locator('.guild-overview-conversation').boundingBox();
              const status = await page.locator('.guild-overview-diplomacy').boundingBox();
              expect(status.x).toBeGreaterThan(latest.x + latest.width);
              expect(Math.abs(status.y - latest.y)).toBeLessThan(4);
            }
            const order = await page.locator('.guild-overview-conversation, .guild-overview-details').evaluateAll(elements => elements.map(element => element.className));
            expect(order[0]).toContain('guild-overview-conversation');
            await expect(page.locator('.guild-public-banner')).toBeVisible();
            await expect(page.locator('.guild-hub-stats')).toContainText('Votre rôle');
            await expect(page.getByRole('link', { name: /Ouvrir le canal de guilde/ })).toBeVisible();
          }
          if (key === 'membres') {
            await expect(page.getByRole('row').filter({ hasText: memberName })).toContainText('En ligne');
          }
          if (key === 'relations') {
            await expect(page.getByRole('link', { name: '1 proposition personnelle à traiter' })).toBeVisible();
            const cards = await page.locator('#alliance-members .commander-card').all();
            expect(cards.length).toBeGreaterThanOrEqual(2);
            const first = await cards[0].boundingBox(), second = await cards[1].boundingBox();
            if (width === 1440) {
              expect(Math.abs(first.y - second.y)).toBeLessThan(2);
              expect(second.x).toBeGreaterThan(first.x);
            } else if (width <= 390) {
              expect(second.y).toBeGreaterThanOrEqual(first.y + first.height);
            }
          }
          if (width === 390) {
            expect((await new AxeBuilder({ page }).include('.guild-workspace')
              .withTags(['wcag2a', 'wcag2aa', 'wcag21aa']).analyze()).violations).toEqual([]);
          }
          if (width !== 320) {
            await page.screenshot({ path: testInfo.outputPath(`jeu-B-${key}-${theme}-${width}.png`), fullPage: true, animations: 'disabled' });
          }
          if (width === 390 || width === 1920) {
            await page.screenshot({ path: testInfo.outputPath(`jeu-B-${key}-${theme}-${width}-ecran.png`), animations: 'disabled' });
          }
        }
      }
    }

    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto('/guildes');
    const menu = page.locator('.space-navigation-menu');
    const summary = menu.locator('summary');
    await expect(menu).not.toHaveAttribute('open', '');
    await summary.click();
    const memberLink = menu.getByRole('link', { name: 'Membres', exact: true });
    await memberLink.focus();
    await page.keyboard.press('Escape');
    await expect(summary).toBeFocused();
    await expect(menu).not.toHaveAttribute('open', '');
    await summary.click();
    await memberLink.click();
    await expect(page).toHaveURL(/\/guildes\/membres$/);
    await page.reload();
    await expect(menu).not.toHaveAttribute('open', '');
    await page.goBack();
    await expect(page).toHaveURL(/\/guildes$/);

    await page.setViewportSize({ width: 1440, height: 900 });
    await expect(menu.getByRole('link', { name: 'Membres', exact: true })).toBeVisible();
    await menu.getByRole('link', { name: 'Membres', exact: true }).focus();
    await page.setViewportSize({ width: 390, height: 844 });
    await expect(summary).toBeFocused();
    await page.setViewportSize({ width: 1440, height: 900 });
    await expect(menu.getByRole('link', { name: 'Vue générale', exact: true })).toBeFocused();

    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto('/diplomatie/commandants');
    await page.getByRole('searchbox', { name: 'Rechercher un joueur', exact: true }).fill('SansResultat' + suffix);
    await page.getByRole('button', { name: 'Rechercher', exact: true }).click();
    await expect(page.locator('.relation-results [role="status"]')).toContainText('Aucun joueur trouvé dans cet univers.');
    await page.setViewportSize({ width: 1440, height: 900 });
    await expect(page.getByRole('searchbox', { name: 'Rechercher un joueur', exact: true })).toHaveValue('SansResultat' + suffix);

    const nativeContext = await browser.newContext({ baseURL, javaScriptEnabled: false,
      viewport: { width: 390, height: 844 }, storageState: await page.context().storageState() });
    try {
      const native = await nativeContext.newPage();
      await native.goto('/guildes');
      await expect(native.locator('.space-navigation-menu')).toHaveAttribute('open', '');
      await native.getByRole('navigation', { name: 'Navigation de guilde' })
        .getByRole('link', { name: 'Membres', exact: true }).click();
      await expect(native.getByRole('row').filter({ hasText: memberName })).toBeVisible();
      await native.goto('/diplomatie/commandants');
      await expect(native.getByRole('navigation', { name: 'Navigation de diplomatie' })
        .getByRole('link', { name: 'Relations entre commandants', exact: true })).toBeVisible();
    } finally {
      await nativeContext.close();
    }
  } finally {
    await memberContext.close();
    await outsiderContext.close();
  }
});
