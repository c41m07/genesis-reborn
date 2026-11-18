import { test, expect } from './support/isolated-test.mjs';
import { queryIsolatedSql, runIsolatedSql } from './support/game-fixtures.mjs';
import AxeBuilder from '@axe-core/playwright';

async function capture(page, info, key, theme, width) {
  expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(width);
  for (const panel of await page.locator('.event-redesign .panel:visible').all()) {
    expect(await panel.evaluate(el => el.scrollWidth <= el.clientWidth + 1)).toBe(true);
  }
  if (width === 390) expect((await new AxeBuilder({ page }).include('.event-redesign').analyze()).violations).toEqual([]);
  if (width !== 320) {
    await page.evaluate(() => { document.activeElement?.blur(); window.scrollTo({ top: 0, behavior: 'instant' }); });
    await page.screenshot({ path: info.outputPath(`jeu-L6-A-${key}-${theme}-${width}.png`), fullPage: true, animations: 'disabled' });
    if (width === 390) await page.screenshot({ path: info.outputPath(`jeu-L6-A-${key}-${theme}-390-ecran.png`), animations: 'disabled' });
  }
}

test('L6 A : annonces en lignes, objectifs et participation, récompenses et mobile', async ({ page }, info) => {
  test.setTimeout(300000);
  const suffix = crypto.randomUUID().slice(0, 8);
  const email = `event-a-${suffix}@example.test`;
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
  const project = process.env.BROWSER_ROUTER;
  const member = Number(queryIsolatedSql(project, `SELECT current_player_universe_id FROM player WHERE email='${email}'`));
  const universe = Number(queryIsolatedSql(project, `SELECT universe_id FROM player_universe WHERE id=${member}`));
  // L’état vide est préparé uniquement dans cette base jetable ; les occurrences gardent leur clé hebdomadaire.
  runIsolatedSql(project, 'UPDATE game_event SET visible_to_players=0');
  runIsolatedSql(project, `UPDATE boost_event SET configuration=JSON_SET(CASE WHEN JSON_TYPE(configuration)='OBJECT' THEN configuration ELSE JSON_OBJECT() END,'$.archived_at','2026-11-01T00:00:00Z')`);
  await page.setViewportSize({ width: 390, height: 900 });
  for (const [key, url] of [['events', '/evenements'], ['challenges', '/evenements/defis']]) {
    await page.goto(url);
    await expect(page.locator('.event-redesign .event-card')).toHaveCount(0);
    await capture(page, info, key + '-vide', 'dark', 390);
  }
  for (const [name, upcoming, global] of [['Production renforcée', false, false], ['Rendez-vous des commandants', false, true], ['Semaine des bâtisseurs', true, false]]) {
    runIsolatedSql(project, `INSERT INTO game_event(universe_id,name,description,type,status,starts_at,ends_at,parameters,visible_to_players) VALUES (${global ? 'NULL' : universe},'${name}','Une annonce de démonstration.','${global ? 'announcement' : 'production_bonus'}','active',${upcoming ? 'DATE_ADD(UTC_TIMESTAMP(),INTERVAL 2 DAY)' : 'DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 HOUR)'},DATE_ADD(UTC_TIMESTAMP(),INTERVAL 3 DAY),'${JSON.stringify(global ? {} : { percentage: 20 })}',1)`);
  }
  const box = { format: 'small', label: 'Boîte de ressources', resources: { metal: 5000, nano: 3000, hydrogen: 1000, antimatter: 0, electricity: 500 } };
  const samples = [
    ['guided', 'Aux confins', 'active', 'exploration', 'participation', true, 30, 12, 2],
    ['weekly', 'Rotation de combat', 'active', 'combat', 'top3', false, 1000000, 420000, 25000],
    ['closed', 'Expédition collective', 'closed', 'exploration', 'participation', true, 30, 30, 3],
    ['cancelled', 'Relais de transport', 'cancelled', 'transport', 'participation', true, 100000, 32000, 1200],
    ['scheduled', 'Prochain transport', 'scheduled', 'transport', 'threshold', true, 100000, 0, 0],
    ['missed', 'Objectif non atteint', 'closed', 'exploration', 'participation', true, 30, 12, 2],
    ['personal', 'Minimum non atteint', 'closed', 'exploration', 'participation', true, 30, 30, 0],
    ['transport', 'Transport actif', 'active', 'transport', 'threshold', false, null, 5000, 1000],
  ];
  const ids = {};
  for (const [key, name, status, objective, mode, guided, goal, total, score] of samples) {
    const eventKey = (guided ? 'guided-' : 'weekly-') + suffix + '-' + key;
    const configuration = { guided, name, description: 'Objectif collectif de démonstration.', minimum_contribution: objective === 'exploration' ? 1 : 1000, reward: key === 'weekly' || key === 'transport' ? { kind: 'boost' } : { kind: 'resource_box', box } };
    const past = status === 'closed' || status === 'cancelled';
    const start = status === 'scheduled' ? 'DATE_ADD(UTC_TIMESTAMP(),INTERVAL 2 DAY)' : 'DATE_SUB(UTC_TIMESTAMP(),INTERVAL 2 DAY)';
    const end = past ? 'DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 DAY)' : 'DATE_ADD(UTC_TIMESTAMP(),INTERVAL 3 DAY)';
    runIsolatedSql(project, `INSERT INTO boost_event(event_key,template_key,universe_id,status,format,reward_mode,contribution_metric,duration_seconds,scheduled_at,started_at,ends_at,threshold_value,global_goal,total_contribution,configuration) VALUES ('${eventKey}','${objective}',${universe},'${status}','collective','${mode}','${objective === 'exploration' ? 'new_systems_discovered' : objective === 'combat' ? 'damage_dealt' : 'freight_delivered'}',432000,${start},${status === 'scheduled' ? 'NULL' : start},${end},1000,${goal ?? 'NULL'},${total},'${JSON.stringify(configuration)}')`);
    ids[key] = Number(queryIsolatedSql(project, `SELECT id FROM boost_event WHERE event_key='${eventKey}'`));
    if (score > 0) runIsolatedSql(project, `INSERT INTO boost_event_contribution(event_id,player_universe_id,source_key,value) VALUES (${ids[key]},${member},'visual-${key}',${score})`);
    if (key === 'closed') {
      runIsolatedSql(project, `INSERT INTO player_boost(player_universe_id,source_type,source_key,status,earned_at,kind,payload) VALUES (${member},'event','event-${eventKey}-player-${member}','pending',UTC_TIMESTAMP(),'resource_box','${JSON.stringify(box)}')`);
    }
  }
  const pages = [['events', '/evenements'], ['challenges', '/evenements/defis'], ...['guided', 'weekly', 'closed', 'cancelled'].map(key => [key, '/evenements/defis/' + ids[key]])];
  for (const theme of ['dark', 'light']) {
    runIsolatedSql(project, `UPDATE player SET preferences=JSON_SET(CASE WHEN JSON_TYPE(preferences)='OBJECT' THEN preferences ELSE JSON_OBJECT() END,'$.theme','${theme}') WHERE email='${email}'`);
    for (const width of [1440, 768, 390, 320]) {
      await page.setViewportSize({ width, height: 900 });
      for (const [key, url] of pages) {
        expect((await page.goto(url)).status()).toBe(200);
        await expect(page.locator('body')).toHaveAttribute('data-theme', theme);
        await expect(page.locator('.event-redesign nav [aria-current]')).toHaveAttribute('aria-current', ['events', 'challenges'].includes(key) ? 'page' : 'location');
        if (key === 'events') {
          await expect(page.locator('section[aria-labelledby="events-current"] .event-card')).toHaveCount(2);
          await expect(page.locator('section[aria-labelledby="events-upcoming"] .event-card')).toHaveCount(1);
          await expect(page.locator('.event-description-column').filter({ hasText: 'Rendez-vous des commandants' })).toContainText('Tous les univers');
        }
        if (key === 'guided') {
          const zero = page.locator('.collective-challenge h3 + .report-facts > div').filter({ has: page.getByText('Antimatière', { exact: true }) });
          await expect(zero.locator('dd')).toHaveText('0,00');
          await expect(page.locator('.collective-challenge')).toContainText('Début effectif (UTC)');
          await expect(page.locator('.event-participation .primary')).toHaveAttribute('href', '/carte-v2');
        }
        if (key === 'weekly') {
          await expect(page.locator('.collective-challenge')).toContainText('ex æquo inclus');
          await expect(page.locator('.event-participation')).toContainText('Votre classement : #1');
        }
        if (key === 'closed') await expect(page.locator('.event-participation')).toContainText('en attente d’une place');
        if (key === 'cancelled') {
          await expect(page.locator('.event-participation')).toContainText('Aucune nouvelle récompense');
          await expect(page.locator('.event-participation a')).toHaveCount(0);
        }
        if (!['events', 'challenges'].includes(key)) {
          const objective = await page.locator('.collective-challenge').boundingBox();
          const personal = await page.locator('.event-participation').boundingBox();
          if (width === 1440) expect(personal.x).toBeGreaterThanOrEqual(objective.x + objective.width);
          else expect(personal.y).toBeGreaterThanOrEqual(objective.y + objective.height);
        }
        await capture(page, info, key, theme, width);
      }
    }
  }
  await page.setViewportSize({ width: 390, height: 900 });
  for (const [key, text] of [['scheduled', 'Planifié'], ['missed', 'L’objectif collectif n’a pas été atteint'], ['personal', 'Vous n’avez pas rempli les conditions'], ['transport', 'Les dons entre joueurs sont exclus']]) {
    await page.goto('/evenements/defis/' + ids[key]);
    await expect(page.locator('.event-redesign')).toContainText(text);
    if (key === 'scheduled') await expect(page.locator('.event-participation .primary')).toHaveCount(0);
    if (key === 'transport') await expect(page.locator('.event-participation .primary')).toHaveAttribute('href', /flottes\/preparer\?mission=transport$/);
    await capture(page, info, key, 'light', 390);
  }
  const rewardKey = `event-guided-${suffix}-closed-player-${member}`;
  for (const status of ['available', 'used']) {
    runIsolatedSql(project, `UPDATE player_boost SET status='${status}',used_at=${status === 'used' ? 'UTC_TIMESTAMP()' : 'NULL'} WHERE source_key='${rewardKey}'`);
    await page.goto('/evenements/defis/' + ids.closed);
    await expect(page.locator('.event-participation')).toContainText(status === 'available' ? 'disponible dans votre inventaire' : 'déjà utilisée');
    await expect(page.locator('.event-participation .secondary')).toHaveAttribute('href', '/boosts');
    await capture(page, info, 'reward-' + status, 'light', 390);
  }
  runIsolatedSql(project, `UPDATE boost_event SET status='cancelled' WHERE id=${ids.closed}`);
  await page.goto('/evenements/defis/' + ids.closed);
  await expect(page.locator('.event-participation')).toContainText('déjà utilisée');
  await expect(page.locator('.event-participation')).not.toContainText('Aucune nouvelle récompense');
  await capture(page, info, 'cancelled-earned', 'light', 390);
  await page.goto('/evenements');
  const summary = page.locator('.event-redesign .space-navigation-menu > summary');
  await summary.focus();
  await page.keyboard.press('Enter');
  await page.keyboard.press('Tab');
  const link = page.getByRole('navigation', { name: 'Navigation des événements', exact: true }).getByRole('link', { name: 'Défis et rotations', exact: true });
  await link.focus();
  expect(await link.evaluate(el => el.matches(':focus-visible') && getComputedStyle(el).outlineStyle !== 'none')).toBe(true);
  await page.keyboard.press('Enter');
  await expect(page).toHaveURL(/evenements\/defis$/);
  await page.reload();
  await expect(page.locator('h1')).toHaveText('Défis et rotations');
  await page.goBack();
  await expect(page.locator('h1')).toHaveText('Événements et bonus');
  expect(errors).toEqual([]);
});
