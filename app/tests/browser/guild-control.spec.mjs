import { test, expect } from './support/isolated-test.mjs';
import { queryIsolatedSql, runIsolatedSql } from './support/game-fixtures.mjs';
import AxeBuilder from '@axe-core/playwright';

async function enterUniverse(page, name, email) {
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
  await page.locator('.universe-card').filter({ has: page.getByRole('heading', { name: /Omega/ }) })
    .getByRole('button').click();
  await expect(page).toHaveURL(/planetes\/\d+$/);
}

async function createGuild(page, name, tag) {
  await page.goto('/guildes/creer');
  await page.getByLabel('Nom de votre nouvelle guilde').fill(name);
  await page.getByLabel('Tag de la guilde', { exact: true }).fill(tag);
  await page.getByRole('button', { name: 'Fonder une guilde' }).click();
  await expect(page.getByRole('heading', { level: 1 })).toHaveText('Ma guilde');
}

test('L1C A : rôles, formulaires, traités et confirmations natives de gestion', async ({ page, browser, baseURL }, testInfo) => {
  test.setTimeout(240000);
  const suffix = crypto.randomUUID().slice(0, 8);
  const project = process.env.BROWSER_ROUTER;
  const ownerEmail = 'control-owner-' + suffix + '@example.test';
  const memberName = 'Cassiopée-des-Confins-' + suffix;
  const guildName = 'Les Vigies de l’Aube ' + suffix;
  const peerGuild = 'Les Veilleurs du Nexus ' + suffix;
  const rankName = 'Diplomate des frontières et des systèmes lointains';
  const memberContext = await browser.newContext({ baseURL });
  const peerContext = await browser.newContext({ baseURL });
  try {
    await enterUniverse(page, 'Orion-' + suffix, ownerEmail);
    await createGuild(page, guildName, 'V' + suffix.slice(0, 4));
    const guildId = Number(queryIsolatedSql(project, `SELECT id FROM guild WHERE name='${guildName.replaceAll("'", "''")}'`));
    const originalLeader = Number(queryIsolatedSql(project, `SELECT leader_id FROM guild WHERE id=${guildId}`));
    await page.goto('/guildes/gestion');
    await expect(page.getByText('Aucun autre membre ne peut recevoir le commandement.')).toBeVisible();
    await expect(page.getByLabel('Nouveau commandant')).toBeDisabled();
    await expect(page.getByRole('button', { name: 'Transférer le commandement' })).not.toBeVisible();
    await page.getByText('Vérifier le transfert', { exact: true }).click();
    await expect(page.getByRole('button', { name: 'Transférer le commandement' })).toBeDisabled();

    const member = await memberContext.newPage();
    await enterUniverse(member, memberName, 'control-member-' + suffix + '@example.test');
    await member.goto('/guildes?q=' + encodeURIComponent(guildName));
    await member.getByRole('button', { name: 'Rejoindre · recrutement ouvert' }).click();
    await page.goto('/guildes/grades?rank=0');
    await page.getByLabel('Nom du grade', { exact: true }).fill(rankName);
    await page.getByLabel('Priorité du grade').fill('10');
    await page.getByLabel('Proposer et accepter les alliances de guilde').check();
    await page.getByRole('button', { name: 'Créer le grade', exact: true }).click();
    await page.goto('/guildes/membres');
    await expect(page.getByRole('row').filter({ hasText: memberName }).getByLabel('Grade de '+memberName, { exact: true })).toBeVisible();
    await page.getByLabel('Grade de ' + memberName, { exact: true }).selectOption({ label: rankName });
    await page.getByRole('button', { name: 'Attribuer le grade à ' + memberName, exact: true }).click();
    await member.goto('/guildes/grades');
    await expect(member.locator('.guild-rank-editor')).toContainText('Proposer et accepter les alliances de guilde');
    await expect(member.getByRole('button', { name: 'Enregistrer le grade', exact: true })).toHaveCount(0);
    await expect(member.getByRole('link', { name: 'Créer un grade', exact: true })).toHaveCount(0);

    const description = 'Nous explorons les confins ensemble.\nEntraide et projets collectifs : chaque commandant trouve sa place.';
    await page.goto('/guildes/' + guildId + '/personnaliser');
    await page.getByLabel('Description', { exact: true }).fill(description);
    await page.getByLabel('Logo', { exact: true }).setInputFiles({
      name: 'image-invalide.svg', mimeType: 'image/svg+xml', buffer: Buffer.from('<svg/>'),
    });
    await page.getByRole('button', { name: 'Enregistrer la présentation' }).click();
    await expect(page.getByRole('alert')).toContainText('Votre texte est conservé');
    await expect(page.getByLabel('Description', { exact: true })).toHaveValue(description);
    await expect(page.getByLabel('Logo', { exact: true })).toHaveValue('');
    await page.getByRole('button', { name: 'Enregistrer la présentation' }).click();
    await expect(page).toHaveURL(new RegExp('/guildes/' + guildId + '$'));

    const peer = await peerContext.newPage();
    await enterUniverse(peer, 'Antarès-' + suffix, 'control-peer-' + suffix + '@example.test');
    await createGuild(peer, peerGuild, 'N' + suffix.slice(0, 4));
    await page.goto('/diplomatie?q=' + encodeURIComponent(peerGuild));
    await page.locator('.relation-results .guild-card').getByLabel('Traité à proposer').selectOption('allies');
    await page.getByRole('button', { name: 'Proposer le traité' }).click();
    await peer.goto('/diplomatie?q=' + encodeURIComponent(guildName));
    await peer.locator('.relation-results .guild-card').getByRole('button', { name: 'Accepter le traité' }).click();
    await peer.locator('.relation-results .guild-card').getByLabel('Traité à proposer').selectOption('defense');
    await peer.locator('.relation-results .guild-card').getByRole('button', { name: 'Proposer le traité' }).click();
    await member.goto('/diplomatie');
    await expect(member.locator('.guild-relations-list .guild-card > .badge')).toHaveText('Relation actuelle : Alliés');
    await expect(member.locator('.guild-relations-list').getByRole('button', { name: 'Accepter le traité' })).toHaveCount(0);
    await expect(member.getByText('Déclarer ennemis', { exact: true })).toHaveCount(0);

    for (const theme of ['dark', 'light']) {
      runIsolatedSql(project, `UPDATE player SET preferences=JSON_SET(CASE WHEN JSON_TYPE(preferences)='OBJECT' THEN preferences ELSE JSON_OBJECT() END,'$.theme','${theme}') WHERE email='${ownerEmail}'`);
      for (const width of [1440, 768, 390, 320]) {
        await page.setViewportSize({ width, height: 900 });
        for (const [url, key, title] of [
          ['/guildes/grades', 'grades', 'Grades et permissions'],
          ['/guildes/gestion', 'gestion', 'Gestion de ma guilde'],
          ['/guildes/' + guildId + '/personnaliser', 'personnalisation', 'Personnaliser la guilde'],
          ['/diplomatie', 'traites', 'Relations entre guildes'],
        ]) {
          expect((await page.goto(url)).status()).toBe(200);
          await expect(page.getByRole('heading', { level: 1 })).toHaveText(title);
          await expect(page.locator('body')).toHaveAttribute('data-theme', theme);
          await expect(page.locator('.space-navigation a[aria-current="page"]')).toHaveCount(1);
          await expect.poll(() => page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(width);
          if (key === 'grades') {
            await expect(page.getByLabel('Nom du grade', { exact: true })).toHaveValue(rankName);
            await expect(page.locator('.guild-rank-editor input[type="checkbox"]')).toHaveCount(7);
            const list = await page.locator('.guild-rank-navigation').boundingBox();
            const editor = await page.locator('.guild-rank-editor').boundingBox();
            if (width === 1440) expect(editor.x).toBeGreaterThan(list.x);
            else expect(editor.y).toBeGreaterThanOrEqual(list.y + list.height);
          } else if (key === 'gestion') {
            await expect(page.getByRole('button', { name: 'Enregistrer le tag', exact: true })).toBeVisible();
            await expect(page.getByRole('button', { name: 'Transférer le commandement' })).not.toBeVisible();
            await expect(page.getByRole('button', { name: 'Dissoudre la guilde' })).not.toBeVisible();
          } else if (key === 'personnalisation') {
            await expect(page.getByLabel('Description', { exact: true })).toHaveValue(description);
          } else {
            await expect(page.locator('.guild-relations-list .badge')).toHaveText('Relation actuelle : Alliés');
            await expect(page.locator('.guild-relations-list').getByText(/^Proposition reçue : Pacte de défense/)).toBeVisible();
            await expect(page.locator('.guild-relations-list')).toContainText('La relation actuelle reste applicable');
          }
          if (width === 390) {
            expect((await new AxeBuilder({ page }).include('.guild-control')
              .withTags(['wcag2a', 'wcag2aa', 'wcag21aa']).analyze()).violations).toEqual([]);
            await page.screenshot({ path: testInfo.outputPath(`jeu-L1C-A-${key}-${theme}-${width}-ecran.png`), animations: 'disabled' });
          }
          if (width !== 320) {
            await page.screenshot({ path: testInfo.outputPath(`jeu-L1C-A-${key}-${theme}-${width}.png`), fullPage: true, animations: 'disabled' });
          }
        }
      }
    }

    await page.goto('/guildes/grades');
    await page.getByLabel('Nom du grade', { exact: true }).focus();
    await page.keyboard.press('Tab');
    await expect(page.getByLabel('Priorité du grade')).toBeFocused();
    await page.setViewportSize({ width: 1440, height: 900 });
    await expect(page.getByLabel('Priorité du grade')).toBeFocused();
    await page.setViewportSize({ width: 390, height: 900 });
    await expect(page.getByLabel('Priorité du grade')).toBeFocused();
    await page.locator('.space-navigation-menu summary').click();
    await page.getByRole('navigation', { name: 'Navigation de guilde' }).getByRole('link', { name: 'Gestion', exact: true }).click();
    await page.reload();
    await page.goBack();
    await expect(page).toHaveURL(/\/guildes\/grades$/);

    const nativeContext = await browser.newContext({ baseURL, javaScriptEnabled: false,
      viewport: { width: 390, height: 844 }, storageState: await page.context().storageState() });
    try {
      const native = await nativeContext.newPage();
      await native.goto('/guildes/gestion');
      await native.getByLabel('Nouveau commandant').selectOption({ label: memberName });
      await native.getByLabel('Nouveau commandant').focus();
      await native.keyboard.press('Enter');
      expect(Number(queryIsolatedSql(project, `SELECT leader_id FROM guild WHERE id=${guildId}`))).toBe(originalLeader);
      await expect(native.getByRole('button', { name: 'Transférer le commandement' })).not.toBeVisible();
      await native.getByText('Vérifier le transfert', { exact: true }).click();
      await expect(native.locator('.guild-action-review[open]')).toContainText('Vous perdrez les droits');
      await native.getByRole('button', { name: 'Transférer le commandement' }).click();
      await expect(native.getByRole('heading', { name: 'Départ de la guilde', exact: true })).toBeVisible();
      expect(Number(queryIsolatedSql(project, `SELECT leader_id FROM guild WHERE id=${guildId}`))).not.toBe(originalLeader);
      expect((await native.goto('/guildes/' + guildId + '/personnaliser')).status()).toBe(403);
      await native.goto('/guildes/gestion');
      await native.getByText('Examiner le départ', { exact: true }).click();
      await expect(native.locator('.guild-management-danger')).toContainText('Les dons de projet restent définitifs');
      await native.getByRole('button', { name: 'Quitter la guilde' }).click();
      await expect(native.getByRole('heading', { level: 1 })).toHaveText('Trouver une guilde');
    } finally {
      await nativeContext.close();
    }

    const successorContext = await browser.newContext({ baseURL, javaScriptEnabled: false,
      storageState: await memberContext.storageState() });
    try {
      const successor = await successorContext.newPage();
      await successor.goto('/guildes/gestion');
      await expect(successor.getByRole('button', { name: 'Dissoudre la guilde' })).not.toBeVisible();
      await successor.getByText('Examiner la dissolution', { exact: true }).click();
      await expect(successor.locator('.guild-management-danger')).toContainText('sans rembourser les dons');
      await successor.getByRole('button', { name: 'Dissoudre la guilde' }).click();
      await expect(successor.getByRole('heading', { level: 1 })).toHaveText('Trouver une guilde');
      expect(Number(queryIsolatedSql(project, `SELECT COUNT(*) FROM guild WHERE id=${guildId}`))).toBe(0);
      expect(Number(queryIsolatedSql(project, 'SELECT COUNT(*) FROM guild'))).toBe(1);
    } finally {
      await successorContext.close();
    }
  } finally {
    await memberContext.close();
    await peerContext.close();
  }
});
