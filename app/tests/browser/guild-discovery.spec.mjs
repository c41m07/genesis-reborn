import { test, expect } from './support/isolated-test.mjs';
import { queryIsolatedSql, runIsolatedSql } from './support/game-fixtures.mjs';
import AxeBuilder from '@axe-core/playwright';

async function enterUniverse(page, name, email, universe = /Omega/) {
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
  await page.locator('.universe-card').filter({ has: page.getByRole('heading', { name: universe }) })
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

test('L1B A : recherche, création conservée, présentation et adhésion natives', async ({ page, browser, baseURL }, testInfo) => {
  test.setTimeout(240000);
  const suffix = crypto.randomUUID().slice(0, 8);
  const project = process.env.BROWSER_ROUTER;
  const ownerContext = await browser.newContext({ baseURL });
  const otherContext = await browser.newContext({ baseURL });
  const email = 'discovery-visitor-' + suffix + '@example.test';
  const guildName = 'Les Vigies de l’Aube ' + suffix;
  const tag = 'V' + suffix.slice(0, 4);
  try {
    const owner = await ownerContext.newPage();
    await enterUniverse(owner, 'Orion-' + suffix, 'discovery-owner-' + suffix + '@example.test');
    await createGuild(owner, guildName, tag);
    const guildId = Number(queryIsolatedSql(project, `SELECT id FROM guild WHERE name='${guildName.replaceAll("'", "''")}'`));
    await owner.goto('/guildes/' + guildId);
    await expect(owner.locator('.guild-profile-emblem')).toBeVisible();
    await expect(owner.locator('.guild-public-description')).toContainText('pas encore ajouté de description');
    await owner.getByRole('link', { name: 'Personnaliser la guilde', exact: true }).click();
    await owner.getByLabel('Description', { exact: true }).fill('Nous explorons les confins ensemble.\nEntraide, échanges et projets collectifs : chaque commandant trouve sa place.\n\nNotre route se dessine à plusieurs, au fil des découvertes et des rencontres.');
    // Une image originale aux dimensions autorisées exerce le vrai parcours de stockage.
    const banner = await owner.evaluate(() => {
      const canvas = document.createElement('canvas');
      canvas.width = 1200;
      canvas.height = 360;
      const context = canvas.getContext('2d');
      const gradient = context.createLinearGradient(0, 0, 1200, 360);
      gradient.addColorStop(0, '#122336');
      gradient.addColorStop(1, '#26394f');
      context.fillStyle = gradient;
      context.fillRect(0, 0, 1200, 360);
      context.fillStyle = '#b8d7e9';
      for (let index = 0; index < 80; index++) {
        context.fillRect(index * 137 % 1200, index * 61 % 360, 2, 2);
      }
      return canvas.toDataURL('image/png').split(',')[1];
    });
    await owner.getByLabel('Bannière', { exact: true }).setInputFiles({
      name: 'banniere-test.png', mimeType: 'image/png', buffer: Buffer.from(banner, 'base64'),
    });
    await owner.getByRole('button', { name: 'Enregistrer la présentation' }).click();
    await expect(owner.locator('.guild-public-banner')).toBeVisible();

    const other = await otherContext.newPage();
    await enterUniverse(other, 'Cassiopée-des-Confins-' + suffix, 'discovery-other-' + suffix + '@example.test');
    await createGuild(other, 'Vigiles des confins et des systèmes oubliés ' + suffix, 'C' + suffix.slice(0, 4));
    await enterUniverse(page, 'Éclaireur-' + suffix, email);
    await page.goto('/guildes/creer');
    await page.getByLabel('Nom de votre nouvelle guilde').fill('Les Éclaireurs d’Orion ' + suffix);
    await page.getByLabel('Tag de la guilde', { exact: true }).fill(tag);
    await page.getByRole('button', { name: 'Fonder une guilde' }).click();
    await expect(page.getByRole('alert')).toContainText('Ce tag est déjà utilisé');
    await expect(page.getByLabel('Nom de votre nouvelle guilde')).toHaveValue('Les Éclaireurs d’Orion ' + suffix);
    await page.reload();
    await expect(page.getByLabel('Tag de la guilde', { exact: true })).toHaveValue(tag);

    for (const theme of ['dark', 'light']) {
      runIsolatedSql(project, `UPDATE player SET preferences=JSON_SET(CASE WHEN JSON_TYPE(preferences)='OBJECT' THEN preferences ELSE JSON_OBJECT() END,'$.theme','${theme}') WHERE email='${email}'`);
      for (const width of [1440, 768, 390, 320]) {
        await page.setViewportSize({ width, height: 900 });
        for (const [url, key] of [['/guildes?q=Vig', 'recherche'], ['/guildes/creer', 'creation'], ['/guildes/' + guildId, 'presentation']]) {
          expect((await page.goto(url)).status()).toBe(200);
          await expect(page.locator('body')).toHaveAttribute('data-theme', theme);
          await expect(page.getByRole('heading', { level: 1 })).toHaveCount(1);
          await expect(page.locator('.space-navigation a[aria-current="page"]')).toHaveCount(1);
          await expect.poll(() => page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(width);
          if (key === 'recherche') {
            const field = await page.getByRole('searchbox', { name: 'Rechercher une guilde', exact: true }).boundingBox();
            const search = await page.getByRole('button', { name: 'Rechercher', exact: true }).boundingBox();
            if (width > 600) {
              expect(Math.abs(field.y - search.y)).toBeLessThan(2);
              expect(Math.abs(field.height - search.height)).toBeLessThan(2);
            }
            await expect(page.locator('.guild-card')).toHaveCount(2);
            await expect(page.locator('.guild-presence')).toHaveCount(0);
            const cards = await page.locator('.guild-card').all();
            const first = await cards[0].boundingBox();
            const second = await cards[1].boundingBox();
            expect(second.y).toBeGreaterThanOrEqual(first.y + first.height);
            const tops = await cards[0].locator('.guild-result-actions > *').evaluateAll(elements => elements.map(element => element.getBoundingClientRect().top));
            if (width === 1440) {
              expect(Math.max(...tops) - Math.min(...tops)).toBeLessThan(2);
            } else if (width <= 390) {
              expect(tops[2]).toBeGreaterThan(tops[1]);
              expect(tops[1]).toBeGreaterThan(tops[0]);
            }
          } else if (key === 'creation') {
            await expect(page.getByLabel('Nom de votre nouvelle guilde')).toHaveValue('Les Éclaireurs d’Orion ' + suffix);
            const intro = await page.locator('.guild-foundation-intro').boundingBox();
            const form = await page.locator('.guild-create').boundingBox();
            if (width === 1440) expect(form.x).toBeGreaterThan(intro.x);
            else expect(intro.y).toBeGreaterThanOrEqual(form.y + form.height);
          } else {
            await expect(page.getByRole('heading', { level: 1 })).toContainText(guildName);
            await expect(page.getByRole('link', { name: 'Personnaliser la guilde', exact: true })).toHaveCount(0);
            await expect(page.locator('.guild-public-banner')).toBeVisible();
            const description = await page.locator('section[aria-labelledby="guild-public-description"]').boundingBox();
            const leader = await page.locator('section[aria-labelledby="guild-public-leader"]').boundingBox();
            expect(leader.y).toBeGreaterThanOrEqual(description.y + description.height);
          }
          if (width === 390) {
            expect((await new AxeBuilder({ page }).include('.guild-discovery')
              .withTags(['wcag2a', 'wcag2aa', 'wcag21aa']).analyze()).violations).toEqual([]);
            await page.screenshot({ path: testInfo.outputPath(`jeu-L1B-A-${key}-${theme}-${width}-ecran.png`), animations: 'disabled' });
          }
          if (width !== 320) {
            await page.screenshot({ path: testInfo.outputPath(`jeu-L1B-A-${key}-${theme}-${width}.png`), fullPage: true, animations: 'disabled' });
          }
        }
      }
    }

    await page.goto('/guildes?q=Vig');
    const presentation = page.locator('.guild-card').first().getByRole('link', { name: 'Consulter la présentation', exact: true });
    await presentation.focus();
    await page.keyboard.press('Enter');
    await expect(page).toHaveURL(new RegExp('/guildes/' + guildId + '$'));
    await page.reload();
    await page.goBack();
    await expect(page).toHaveURL(/guildes\?q=Vig$/);
    await page.getByRole('searchbox', { name: 'Rechercher une guilde', exact: true }).fill('Introuvable' + suffix);
    await page.getByRole('button', { name: 'Rechercher', exact: true }).click();
    await expect(page.getByRole('status')).toHaveText('Aucune guilde trouvée dans cet univers.');
    await page.goto('/guildes?q=A');
    await expect(page.getByRole('status')).toHaveText('Saisissez au moins deux caractères.');
    await page.goto('/guildes');
    const directory = page.getByRole('region', { name: 'Guildes qui recrutent', exact: true });
    await expect(directory.getByRole('link', { name: 'Voir la guilde ' + guildName, exact: true })).toHaveAttribute('href', '/guildes/' + guildId);
    await expect(directory.getByRole('link')).toHaveCount(2);
    await expect(directory.locator('form')).toHaveCount(0);
    await expect(page.getByRole('searchbox', { name: 'Rechercher une guilde', exact: true })).toBeVisible();

    const nativeContext = await browser.newContext({ baseURL, javaScriptEnabled: false,
      viewport: { width: 390, height: 844 }, storageState: await page.context().storageState() });
    try {
      const native = await nativeContext.newPage();
      await native.goto('/guildes');
      await expect(native.getByRole('region', { name: 'Guildes qui recrutent', exact: true }).getByRole('link', { name: 'Voir la guilde ' + guildName, exact: true })).toBeVisible();
      await native.goto('/guildes?q=Vig');
      await expect(native.getByRole('region', { name: 'Guildes qui recrutent', exact: true })).toHaveCount(0);
      const first = native.locator('.guild-card').first();
      await first.locator('summary').click();
      await expect(first.locator('details li')).toContainText('Orion-' + suffix);
      await first.getByRole('link', { name: 'Consulter la présentation', exact: true }).click();
      await expect(native.getByRole('heading', { level: 1 })).toContainText(guildName);
      await native.getByRole('navigation', { name: 'Navigation de guilde' })
        .getByRole('link', { name: 'Créer une guilde', exact: true }).click();
      await native.getByRole('navigation', { name: 'Navigation de guilde' })
        .getByRole('link', { name: 'Créer une guilde', exact: true }).focus();
      await native.keyboard.press('Tab');
      await expect(native.getByLabel('Nom de votre nouvelle guilde')).toBeFocused();
      await expect(native.getByLabel('Tag de la guilde', { exact: true })).toHaveValue(tag);
      await native.goto('/guildes?q=Vig');
      await native.locator('.guild-card').first().getByRole('button', { name: 'Rejoindre · recrutement ouvert', exact: true }).click();
      await expect(native.getByRole('heading', { level: 1 })).toHaveText('Ma guilde');
      await native.goto('/guildes/creer');
      await expect(native).toHaveURL(/\/guildes$/);
    } finally {
      await nativeContext.close();
    }
  } finally {
    await ownerContext.close();
    await otherContext.close();
  }
});

test('Annuaire initial : seuls les profils actifs du même univers sont proposés', async ({ page, browser, baseURL }) => {
  const suffix = crypto.randomUUID().slice(0, 8);
  const project = process.env.BROWSER_ROUTER;
  const ownerContext = await browser.newContext({ baseURL });
  const foreignContext = await browser.newContext({ baseURL });
  try {
    const owner = await ownerContext.newPage();
    const foreign = await foreignContext.newPage();
    const guildName = 'Guilde active ' + suffix;
    const archivedName = 'Guilde archivée ' + suffix;
    const foreignName = 'Guilde hors univers ' + suffix;
    await enterUniverse(owner, 'Annuaire-' + suffix, 'annuaire-owner-' + suffix + '@example.test');
    await createGuild(owner, guildName, 'A' + suffix.slice(0, 4));
    const guildId = Number(queryIsolatedSql(project, "SELECT id FROM guild WHERE name='"+guildName+"'"));
    expect(guildId).toBeGreaterThan(0);
    runIsolatedSql(project, "INSERT INTO guild(universe_id,leader_id,name,archived_at) SELECT universe_id,leader_id,'"+archivedName+"',UTC_TIMESTAMP() FROM guild WHERE id="+guildId);
    const foreignEmail = 'annuaire-foreign-' + suffix + '@example.test';
    await enterUniverse(foreign, 'Étranger-' + suffix, foreignEmail, /Turbo/);
    runIsolatedSql(project, "INSERT INTO guild(universe_id,leader_id,name) SELECT membership.universe_id,membership.id,'"+foreignName+"' FROM player_universe membership JOIN player owner ON owner.id=membership.player_id WHERE owner.email='"+foreignEmail+"'");
    expect(Number(queryIsolatedSql(project, "SELECT id FROM guild WHERE name='"+foreignName+"'"))).toBeGreaterThan(0);

    await enterUniverse(page, 'Visiteur-' + suffix, 'annuaire-visitor-' + suffix + '@example.test');
    await page.goto('/guildes');
    const directory = page.getByRole('region', { name: 'Guildes qui recrutent', exact: true });
    await expect(directory.getByRole('link', { name: 'Voir la guilde ' + guildName, exact: true })).toHaveAttribute('href', '/guildes/' + guildId);
    await expect(directory).not.toContainText(archivedName);
    await expect(directory).not.toContainText(foreignName);
    await expect(directory).not.toContainText('Annuaire-' + suffix);
    expect(await directory.getByRole('link').count()).toBeLessThanOrEqual(6);

    const nativeContext = await browser.newContext({ baseURL, javaScriptEnabled: false,
      viewport: { width: 390, height: 844 }, storageState: await page.context().storageState() });
    try {
      const native = await nativeContext.newPage();
      await native.goto('/guildes');
      const suggestions = native.getByRole('region', { name: 'Guildes qui recrutent', exact: true });
      await expect(suggestions.getByRole('link', { name: 'Voir la guilde ' + guildName, exact: true })).toBeVisible();
      const search = native.getByRole('searchbox', { name: 'Rechercher une guilde', exact: true });
      await search.fill(guildName);
      await native.getByRole('button', { name: 'Rechercher', exact: true }).click();
      await expect(suggestions).toHaveCount(0);
      await expect(native.getByRole('region', { name: 'Résultats de recherche de guildes' }).getByRole('link', { name: guildName, exact: true })).toBeVisible();
    } finally {
      await nativeContext.close();
    }
  } finally {
    await ownerContext.close();
    await foreignContext.close();
  }
});
