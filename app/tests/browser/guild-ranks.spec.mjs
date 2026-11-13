import { test, expect } from './support/isolated-test.mjs';
import AxeBuilder from '@axe-core/playwright';
import {runIsolatedSql,queryIsolatedSql} from './support/game-fixtures.mjs';

async function register(page, name, email) {
  await page.goto('/inscription');
  await page.getByLabel('Adresse email').fill(email);
  await page.getByLabel('Nom de commandant').fill(name);
  await page.getByLabel('Mot de passe (12 caractères minimum)', { exact: true }).fill('Guild-ranks-2026!');
  await page.getByLabel('Confirmer le mot de passe').fill('Guild-ranks-2026!');
  await page.getByRole('button', { name: 'Créer mon compte' }).click();
  await expect(page).toHaveURL(/connexion/);
  await page.getByLabel('Adresse email').fill(email);
  await page.getByLabel('Mot de passe', { exact: true }).fill('Guild-ranks-2026!');
  await page.getByRole('button', { name: /Connexion/ }).click();
  await page.locator('article.universe-card').filter({ has: page.getByRole('heading', { name: /Omega/ }) }).getByRole('button').click();
}

test('Le dirigeant crée un grade, le délègue et les droits restent contrôlés', async ({ page, browser, baseURL }, testInfo) => {
  test.setTimeout(90000);
  const id = crypto.randomUUID().slice(0, 8);
  const memberName = 'Member-' + id;
  const guildName = 'Grades-' + id;
  const memberContext = await browser.newContext({ baseURL });
  const memberPage = await memberContext.newPage();
  try {
    await page.setViewportSize({ width: 1440, height: 900 });
    await register(page, 'Leader-' + id, 'leader-' + id + '@example.test');
    await page.goto('/diplomatie');
    await page.getByLabel('Nom de votre nouvelle guilde').fill(guildName);await page.getByLabel('Tag de la guilde', { exact:true }).fill(crypto.randomUUID().replaceAll('-', '').slice(0,5).toUpperCase());
    await page.getByRole('button', { name: 'Fonder une guilde' }).click();
    await page.getByRole('navigation',{name:'Sections de diplomatie'}).getByRole('link',{name:'Grades et permissions',exact:true}).click();
    await page.getByText('Créer un grade', { exact: true }).click();
    const create = page.locator('form').filter({ has: page.getByLabel('Nom du grade', { exact: true }) }).last();
    await create.getByLabel('Nom du grade', { exact: true }).fill('Diplomate');
    await create.getByLabel('Nom du grade', { exact: true }).focus();
    await page.keyboard.press('Tab');
    await expect(create.getByLabel('Priorité du grade')).toBeFocused();
    const focusVisible = await create.getByLabel('Priorité du grade').evaluate(element => {
      const style = getComputedStyle(element);
      return (style.outlineStyle !== 'none' && Number.parseFloat(style.outlineWidth) > 0) || style.boxShadow !== 'none';
    });
    expect(focusVisible).toBe(true);
    await create.getByLabel('Priorité du grade').fill('10');
    await create.getByLabel('Proposer et accepter les alliances de guilde').check();
    const accessibility = await new AxeBuilder({ page })
      .include('[aria-labelledby="guild-ranks-title"]')
      .withTags(['wcag2a', 'wcag2aa', 'wcag21aa'])
      .analyze();
    expect(accessibility.violations).toEqual([]);
    await create.getByRole('button', { name: 'Créer le grade', exact: true }).click();
    await expect(page.getByText('Diplomate · priorité 10', { exact: true })).toBeVisible();

    await register(memberPage, memberName, 'member-' + id + '@example.test');
    await memberPage.goto('/diplomatie?tab=diplomacy&q=' + encodeURIComponent(guildName));
    await memberPage.getByRole('button', { name: 'Rejoindre · recrutement ouvert' }).click();
    await page.goto('/diplomatie?tab=members');
    await page.getByText('Attribuer un grade',{exact:true}).click();
    await page.getByLabel('Grade de ' + memberName, { exact: true }).selectOption({ label: 'Diplomate' });
    await page.getByRole('button', { name: 'Attribuer le grade à ' + memberName, exact: true }).click();
    await memberPage.goto('/diplomatie');
    await expect(memberPage.locator('.guild-hub-stats')).toContainText('Diplomate');
    await expect(memberPage.getByText('Créer un grade', { exact: true })).toHaveCount(0);
    const token = await memberPage.locator('input[name="_token"]').first().inputValue();
    const response = await memberPage.evaluate(async token => {
      const result = await fetch('/diplomatie', {
        method: 'POST',
        body: new URLSearchParams({ _token: token, action: 'rank_save', target: '0', name: 'Interdit', position: '1', version: '0' }),
      });
      return result.text();
    }, token);
    expect(response).toContain('Seul le dirigeant');
    await memberPage.goto('/diplomatie?tab=ranks');
    await expect(memberPage.getByText('Interdit · priorité 1', { exact: true })).toHaveCount(0);
    for (const width of [768, 390]) {
      await memberPage.setViewportSize({ width, height: 844 });
      await expect.poll(() => memberPage.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(width);
      const result = await new AxeBuilder({ page: memberPage })
        .include('[aria-labelledby="guild-ranks-title"]')
        .withTags(['wcag2a', 'wcag2aa', 'wcag21aa'])
        .analyze();
      expect(result.violations).toEqual([]);
    }
    await expect(memberPage.getByRole('navigation', { name: 'Sections de diplomatie' }).getByRole('link', { name: 'Relations commandants', exact: true })).toBeVisible();
    await memberContext.route('**/activite/presence',route=>route.fulfill({status:204}));
    const project=process.env.BROWSER_ROUTER;
    runIsolatedSql(project,`UPDATE player_activity a JOIN player_universe m ON m.id=a.membership_id JOIN player p ON p.id=m.player_id SET a.last_seen_at=UTC_TIMESTAMP()-INTERVAL 6 MINUTE WHERE p.username='${memberName}'`);
    expect(Number(queryIsolatedSql(project,`SELECT COUNT(*) FROM player_activity a JOIN player_universe m ON m.id=a.membership_id JOIN player p ON p.id=m.player_id WHERE p.username='${memberName}' AND a.last_seen_at<UTC_TIMESTAMP()-INTERVAL 5 MINUTE`))).toBe(1);
    for(const theme of ['dark','light']) {
      runIsolatedSql(project,`UPDATE player SET preferences=JSON_SET(CASE WHEN JSON_TYPE(preferences)='OBJECT' THEN preferences ELSE JSON_OBJECT() END,'$.theme','${theme}') WHERE username='Leader-${id}'`);
      await page.goto('/diplomatie?tab=members');
      const target=page.getByRole('row').filter({hasText:memberName});
      await expect(target.locator('.guild-presence--offline')).toHaveText('Hors ligne');
      const online=page.locator('.guild-members-table .guild-presence--online');
      await expect(online).toHaveText('En ligne');
      expect(await target.locator('.guild-presence--offline').evaluate(el=>getComputedStyle(el).color)).toBe(theme==='dark'?'rgb(255, 176, 176)':'rgb(154, 41, 41)');
      expect(await online.evaluate(el=>getComputedStyle(el).color)).toBe(theme==='dark'?'rgb(135, 216, 180)':'rgb(37, 99, 71)');
      await page.setViewportSize({width:390,height:844});
      expect((await new AxeBuilder({page}).include('.guild-members-table').withTags(['wcag2a','wcag2aa','wcag21aa']).analyze()).violations).toEqual([]);
      await page.screenshot({path:testInfo.outputPath('guild-presence-'+theme+'-390.png'),fullPage:true,animations:'disabled'});
      await page.locator('section[aria-labelledby="guild-members-title"]').screenshot({path:testInfo.outputPath('guild-presence-panel-'+theme+'.png'),animations:'disabled'});
    }

  } finally {
    await memberContext.close();
  }
});
