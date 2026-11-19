import { test, expect } from './support/isolated-test.mjs';
import { grantSecondColony, queryIsolatedSql, revealVirginPlanet, runIsolatedSql } from './support/game-fixtures.mjs';
import AxeBuilder from '@axe-core/playwright';

async function capture(page, info, key, theme, width) {
  expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(width);
  for (const panel of await page.locator('.project-redesign .panel:visible').all()) {
    expect(await panel.evaluate(el => el.scrollWidth <= el.clientWidth + 1)).toBe(true);
  }
  if (width === 390) expect((await new AxeBuilder({ page }).include('.project-redesign').analyze()).violations).toEqual([]);
  if (width !== 320) {
    await page.evaluate(() => { document.activeElement?.blur(); window.scrollTo({ top: 0, behavior: 'instant' }); });
    await page.screenshot({ path: info.outputPath(`jeu-L5-A-${key}-${theme}-${width}.png`), fullPage: true, animations: 'disabled' });
    if (width === 390) await page.screenshot({ path: info.outputPath(`jeu-L5-A-${key}-${theme}-390-ecran.png`), animations: 'disabled' });
  }
}

test('L5 A : projets en lignes, don rapproché et états de la guilde dans les deux thèmes', async ({ page }, info) => {
  test.setTimeout(300000);
  const suffix = crypto.randomUUID().slice(0, 8);
  const email = `project-a-${suffix}@example.test`;
  const password = crypto.randomUUID() + 'aA1!';
  const errors = [];
  page.on('pageerror', error => errors.push(error.message));
  await page.goto('/inscription');
  await page.getByLabel('Adresse email').fill(email);
  await page.getByLabel('Nom de commandant').fill('Horizon-' + suffix);
  await page.getByLabel('Mot de passe (12 caractères minimum)', { exact: true }).fill(password);
  await page.getByLabel('Confirmer le mot de passe').fill(password);
  await page.getByRole('button', { name: 'Créer mon compte' }).click();
  await page.getByLabel('Adresse email').fill(email);
  await page.getByLabel('Mot de passe', { exact: true }).fill(password);
  await page.getByRole('button', { name: /Connexion/ }).click();
  await page.locator('.universe-card').filter({ has: page.getByRole('heading', { name: /Omega/ }) }).getByRole('button').click();
  await expect(page).toHaveURL(/planetes\/\d+$/);
  const home = Number(new URL(page.url()).pathname.split('/').at(-1));
  const project = process.env.BROWSER_ROUTER;
  const member = Number(queryIsolatedSql(project, `SELECT owner_id FROM planet WHERE id=${home}`));
  const routes = [['ongoing', '/guildes/projets'], ['available', '/guildes/projets/disponibles'], ['history', '/guildes/projets/historique']];
  await page.setViewportSize({ width: 390, height: 900 });
  for (const [key, url] of routes) {
    await page.goto(url);
    await expect(page.locator('.project-redesign')).toContainText('Rejoignez une guilde');
    await expect(page.locator('.project-redesign form')).toHaveCount(0);
    await capture(page, info, key + '-sans-guilde', 'dark', 390);
  }
  const guildName = 'Chantiers-' + suffix;
  await page.goto('/guildes/creer');
  await page.getByLabel('Nom de votre nouvelle guilde').fill(guildName);
  await page.getByLabel('Tag de la guilde', { exact: true }).fill(suffix.slice(0, 5).toUpperCase());
  await page.getByRole('button', { name: 'Fonder une guilde' }).click();
  const guild = Number(queryIsolatedSql(project, `SELECT guild_id FROM guild_member WHERE player_universe_id=${member}`));
  for (const [key, url] of routes) {
    await page.goto(url);
    await expect(page.locator('.guild-project-grid article')).toHaveCount(0);
    await capture(page, info, key + '-vide', 'dark', 390);
  }
  grantSecondColony(project, email);
  const second = revealVirginPlanet(project, email);
  runIsolatedSql(project, `UPDATE planet SET owner_id=${member},name='Avant-poste des Constellations Oubliées',acquired_at=UTC_TIMESTAMP() WHERE id=${second.id}`);
  runIsolatedSql(project, `INSERT IGNORE INTO planet_resource(planet_id,resource_type,current_stock,production_rate,last_updated_at) SELECT ${second.id},resource_type,1000,0,UTC_TIMESTAMP() FROM planet_resource WHERE planet_id=${home}`);
  const stages = [{ name: 'Balisage', costs: { metal: 10000, nano: 6000, electricity: 500 } }, { name: 'Relevés', costs: { metal: 8000, nano: 4000 } }];
  const minimums = { metal: 2000, nano: 1000 };
  const samples = [['Relais exploration', 'active'], ['Cartographie commune', 'completed'], ['Archives stellaires', 'abandoned'], ['Observatoire partagé', null]];
  const ids = {};
  for (const [name, status] of samples) {
    runIsolatedSql(project, `INSERT INTO guild_project_definition(universe_id,name,description,stages,minimums,enabled,created_at,updated_at) SELECT id,'${name}','Financez les relevés et les balises de votre guilde.','${JSON.stringify(stages)}','${JSON.stringify(minimums)}',1,UTC_TIMESTAMP(),UTC_TIMESTAMP() FROM universe WHERE slug='omega-1'`);
    const definition = Number(queryIsolatedSql(project, `SELECT id FROM guild_project_definition WHERE name='${name}'`));
    if (!status) continue;
    const snapshot = { name, description: 'Financez les relevés et les balises de votre guilde.', stages, minimums };
    const progress = status === 'active' ? { metal: 6000, nano: 2000, electricity: 500 } : {};
    const eligible = status === 'completed' ? [member] : [];
    runIsolatedSql(project, `INSERT INTO guild_project(universe_id,definition_id,guild_id,guild_name,snapshot,progress,stage_index,status,version,started_at,eligible_ids,rewards_delivered_at) SELECT id,${definition},${guild},'${guildName}','${JSON.stringify(snapshot)}','${JSON.stringify(progress)}',${status === 'completed' ? 2 : 0},'${status}',1,DATE_SUB(UTC_TIMESTAMP(),INTERVAL 2 DAY),'${JSON.stringify(eligible)}',${status === 'completed' ? 'UTC_TIMESTAMP()' : 'NULL'} FROM universe WHERE slug='omega-1'`);
    ids[status] = Number(queryIsolatedSql(project, `SELECT id FROM guild_project WHERE definition_id=${definition}`));
    runIsolatedSql(project, `INSERT INTO guild_project_contribution(project_id,player_universe_id,planet_id,resources,updated_at) VALUES (${ids[status]},${member},${home},'${JSON.stringify(status === 'active' ? { metal: 1500, nano: 500 } : { metal: 2500, nano: 1100 })}',UTC_TIMESTAMP())`);
  }
  const pages = [...routes, ['detail', '/guildes/projets/' + ids.active], ['completed', '/guildes/projets/' + ids.completed], ['abandoned', '/guildes/projets/' + ids.abandoned]];
  for (const theme of ['dark', 'light']) {
    runIsolatedSql(project, `UPDATE player SET preferences=JSON_SET(CASE WHEN JSON_TYPE(preferences)='OBJECT' THEN preferences ELSE JSON_OBJECT() END,'$.theme','${theme}') WHERE email='${email}'`);
    for (const width of [1440, 768, 390, 320]) {
      await page.setViewportSize({ width, height: 900 });
      for (const [key, url] of pages) {
        expect((await page.goto(url)).status()).toBe(200);
        await expect(page.locator('body')).toHaveAttribute('data-theme', theme);
        await expect(page.locator('nav[aria-label="Navigation de guilde"] [aria-current]')).toHaveAttribute('href', '/guildes/projets');
        const current = page.locator('nav[aria-label="Navigation des projets collectifs"] [aria-current]');
        await expect(current).toHaveAttribute('aria-current', ['detail', 'completed', 'abandoned'].includes(key) ? 'location' : 'page');
        await expect(page.locator('title')).not.toContainText('</div>');
        if (key === 'ongoing' && width === 1440) {
          const identity = await page.locator('.project-identity').boundingBox();
          const stage = await page.locator('.project-stage').boundingBox();
          const personal = await page.locator('.project-personal').boundingBox();
          expect(stage.x).toBeGreaterThanOrEqual(identity.x + identity.width);
          expect(personal.x).toBeGreaterThanOrEqual(stage.x + stage.width);
        }
        if (key === 'available') {
          await expect(page.locator('.project-model-launch')).toContainText('2000 Métal et 1000 Nano');
          await expect(page.locator('.project-model-description details')).not.toHaveAttribute('open');
          await expect(page.locator('.project-model-launch')).toBeVisible();
        }
        if (key === 'detail') {
          await expect(page.locator('#project-donation input[type="number"]')).toHaveCount(2);
          await expect(page.getByLabel('Don Métal', { exact: true })).toHaveAttribute('max', '4000');
          await page.getByLabel('Colonie qui fournit les ressources').selectOption(String(second.id));
          const overview = await page.locator('.project-overview').boundingBox();
          const form = await page.locator('#project-donation').boundingBox();
          if (width === 1440) expect(form.x).toBeGreaterThanOrEqual(overview.x + overview.width);
          else expect(form.y).toBeGreaterThanOrEqual(overview.y + overview.height);
        }
        if (key === 'completed' || key === 'abandoned') await expect(page.locator('.project-redesign form')).toHaveCount(0);
        await capture(page, info, key, theme, width);
      }
    }
  }
  await page.setViewportSize({ width: 390, height: 900 });
  await page.goto('/guildes/projets/' + ids.active);
  await page.getByText('Contributions des membres', { exact: true }).click();
  await expect(page.locator('#project-contributions')).toContainText('Horizon-' + suffix);
  await capture(page, info, 'contributions', 'light', 390);
  await page.locator('.project-redesign > .space-navigation-menu').nth(1).locator('summary').click();
  const history = page.getByRole('navigation', { name: 'Navigation des projets collectifs', exact: true }).getByRole('link', { name: 'Historique', exact: true });
  await page.keyboard.press('Tab');
  await history.focus();
  expect(await history.evaluate(el => el.matches(':focus-visible') && getComputedStyle(el).outlineStyle !== 'none')).toBe(true);
  await page.keyboard.press('Enter');
  await expect(page).toHaveURL(/projets\/historique$/);
  await page.reload();
  await expect(page.locator('.project-summary')).toHaveCount(2);
  await page.goBack();
  await expect(page).toHaveURL(new RegExp('/projets/' + ids.active + '$'));
  // Les vacances n’affectent que les formulaires ; les contributions restent consultables.
  runIsolatedSql(project, `INSERT INTO vacation_period(membership_id,started_at,scheduled_end_at,ended_at) VALUES (${member},DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 HOUR),DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 DAY),NULL)`);
  for (const [key, url] of [['available', '/guildes/projets/disponibles'], ['detail', '/guildes/projets/' + ids.active]]) {
    await page.goto(url);
    await expect(page.locator('.project-redesign')).toContainText('Pendant les vacances');
    await expect(page.locator('.project-redesign form')).toHaveCount(0);
    await capture(page, info, key + '-vacances', 'light', 390);
  }
  expect(errors).toEqual([]);
});
