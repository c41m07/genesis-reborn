import { test, expect } from './support/isolated-test.mjs';
import { execFileSync } from 'node:child_process';
import { runIsolatedSql, queryIsolatedSql } from './support/game-fixtures.mjs';

async function enter(page, admin = false) {
  const id = crypto.randomUUID().slice(0, 8);
  const email = `world-ui-${id}@example.test`;
  const password = 'Browser-test-2026!';
  await page.goto('/inscription');
  await page.getByLabel('Adresse email').fill(email);
  await page.getByLabel('Nom de commandant').fill(`WorldUI-${id}`);
  await page.getByLabel('Mot de passe (12 caractères minimum)', { exact: true }).fill(password);
  await page.getByLabel('Confirmer le mot de passe').fill(password);
  await page.getByRole('button', { name: 'Créer mon compte' }).click();
  await expect(page).toHaveURL(/connexion/);
  if (admin) execFileSync('docker', ['compose', '--project-name', process.env.BROWSER_ROUTER, '--env-file', '.env.local', 'exec', '-T', 'app', 'php', 'bin/console', 'app:admin:grant', email], { stdio: ['ignore', 'pipe', 'pipe'] });
  await page.getByLabel('Adresse email').fill(email);
  await page.getByLabel('Mot de passe', { exact: true }).fill(password);
  await page.getByRole('button', { name: /Connexion/ }).click();
  await expect(page).toHaveURL(/univers$/);
  await page.locator('.universe-card').filter({ has: page.getByRole('button') }).first().getByRole('button').click();
  await expect(page).toHaveURL(/planetes\/\d+$/);
  return email;
}

function addColony(email) {
  const project = process.env.BROWSER_ROUTER;
  const membership = Number(queryIsolatedSql(project, `SELECT pu.id FROM player_universe pu JOIN player p ON p.id=pu.player_id WHERE p.email='${email}'`));
  const home = Number(queryIsolatedSql(project, `SELECT id FROM planet WHERE owner_id=${membership} LIMIT 1`));
  const extra = Number(queryIsolatedSql(project, `SELECT other.id FROM planet other JOIN solar_system s ON s.id=other.solar_system_id JOIN galaxy g ON g.id=s.galaxy_id JOIN player_universe pu ON pu.universe_id=g.universe_id WHERE pu.id=${membership} AND other.owner_id IS NULL AND other.npc_planet=0 LIMIT 1`));
  expect(extra).toBeGreaterThan(0);
  runIsolatedSql(project, `UPDATE planet SET owner_id=${membership},name='ColonieSecondaireAvecUnNomTresLongSansEspacesPourVerifierLesCartes' WHERE id=${extra}`);
  runIsolatedSql(project, `UPDATE planet SET name='ColoniePrincipaleAvecUnNomTresLongSansEspacesPourVerifierLesCartes' WHERE id=${home}`);
  runIsolatedSql(project, `INSERT INTO planet_resource (planet_id,resource_type,current_stock,production_rate,last_updated_at) SELECT ${extra},resource_type,current_stock,production_rate,UTC_TIMESTAMP() FROM planet_resource WHERE planet_id=${home}`);
  return { home, extra };
}

test('Captures calendrier admin et colonies multiples', async ({ page, browser }, testInfo) => {
  await enter(page, true);
  const project = process.env.BROWSER_ROUTER;
  const scheduled = Number(queryIsolatedSql(project, 'SELECT id FROM universe ORDER BY id DESC LIMIT 1'));
  runIsolatedSql(project, `UPDATE universe SET starts_at='2030-05-12 09:30:00',ends_at='2030-05-14 18:00:00' WHERE id=${scheduled}`);
  await page.goto('/admin#admin-universes');
  const card = page.locator('.admin-universe-card[data-filter-status="scheduled"]').first();
  await card.locator('.admin-universe-settings>summary').click();
  const start = card.locator('input[name="starts_at"]');
  console.log('Ouverture rendue : ' + await start.getAttribute('value') + ' ; valeur reconnue : ' + await start.inputValue());
  for (const width of [1440, 390]) {
    await page.setViewportSize({ width, height: 900 });
    await card.locator('.admin-universe-settings').scrollIntoViewIfNeeded();
    await page.screenshot({ path: testInfo.outputPath(`universe-calendar-${width}.png`), animations: 'disabled' });
  }
  const player = await browser.newPage();
  const email = await enter(player);
  addColony(email);
  await player.goto('/planetes');
  await expect(player.locator('.planet-index-card')).toHaveCount(2);
  for (const width of [1440, 390]) {
    await player.setViewportSize({ width, height: 900 });
    await player.screenshot({ path: testInfo.outputPath(`colonies-multiple-${width}.png`), fullPage: true, animations: 'disabled' });
  }
  await player.close();
});

test('Univers : création, brouillons conservés, calendrier UTC et univers fermé', async ({ page }, testInfo) => {
  await enter(page, true);
  const project = process.env.BROWSER_ROUTER;
  await page.goto('/admin#admin-universe-create');
  await expect(page.locator('#admin-universes')).toBeVisible();
  const creation = page.locator('#admin-universe-create');
  const existingSlug = queryIsolatedSql(project, 'SELECT slug FROM universe ORDER BY id LIMIT 1');
  const slug = 'ui-world-' + crypto.randomUUID().slice(0, 8);
  await creation.getByLabel('Nom affiché').fill('Univers de vérification UI');
  await creation.getByLabel('Code unique').fill(existingSlug);
  await creation.getByLabel('Profil de règles').selectOption('x2');
  await creation.getByLabel('Nombre de galaxies').fill('2');
  await creation.getByLabel('Systèmes par galaxie').fill('1');
  await creation.getByRole('button', { name: 'Créer l’univers' }).click();
  await expect(page).toHaveURL(/#admin-universe-create$/);
  await expect(page.locator('.flash.error')).toContainText('déjà ce code');
  await expect(creation.getByLabel('Nom affiché')).toHaveValue('Univers de vérification UI');
  await expect(creation.getByLabel('Code unique')).toHaveValue(existingSlug);
  await expect(creation.getByLabel('Profil de règles')).toHaveValue('x2');
  await expect(creation.getByLabel('Nombre de galaxies')).toHaveValue('2');
  await expect(creation.getByLabel('Systèmes par galaxie')).toHaveValue('1');
  await expect(creation.locator('.admin-draft-notice')).toContainText('déjà ce code');
  for (const width of [1920, 1440, 1280, 768, 390]) {
    await page.setViewportSize({ width, height: 900 });
    await expect.poll(() => page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(width);
  }
  await creation.scrollIntoViewIfNeeded();
  await page.screenshot({ path: testInfo.outputPath('universe-creation-error-390.png'), animations: 'disabled' });
  await creation.getByLabel('Code unique').fill(slug);
  await creation.getByLabel('Nombre de galaxies').fill('1');
  await creation.getByRole('button', { name: 'Créer l’univers' }).click();
  const id = Number(queryIsolatedSql(project, `SELECT id FROM universe WHERE slug='${slug}'`));
  expect(id).toBeGreaterThan(0);
  await expect(page).toHaveURL(new RegExp('#admin-universe-' + id + '$'));
  const card = page.locator('#admin-universe-' + id);
  await expect(card).toBeVisible();
  await expect(card).toContainText('Univers de vérification UI');
  await expect(card).toContainText('Immédiate');
  await expect(card).not.toContainText('Configuration à vérifier');
  const config = queryIsolatedSql(project, `SELECT game_settings FROM universe WHERE id=${id}`);
  expect(queryIsolatedSql(project, `SELECT COUNT(*) FROM planet p JOIN solar_system s ON s.id=p.solar_system_id JOIN galaxy g ON g.id=s.galaxy_id WHERE g.universe_id=${id}`)).toBe('9');
  await card.locator('.admin-universe-settings>summary').click();
  const calendar = card.locator('.admin-calendar-form');
  await calendar.getByLabel('Ouverture (UTC)', { exact: true }).fill('2030-06-10T12:30');
  await calendar.getByLabel('Fermeture planifiée (UTC)').fill('2030-06-09T11:15');
  await calendar.getByRole('button', { name: 'Enregistrer le calendrier' }).click();
  await expect(page.locator('.flash.error')).toContainText('postérieure');
  await expect(calendar.locator('.admin-draft-notice')).toContainText('postérieure');
  await expect(card.locator('.admin-universe-settings')).toHaveAttribute('open', '');
  await expect(calendar.getByLabel('Ouverture (UTC)', { exact: true })).toHaveValue('2030-06-10T12:30');
  await expect(calendar.getByLabel('Fermeture planifiée (UTC)')).toHaveValue('2030-06-09T11:15');
  expect(queryIsolatedSql(project, `SELECT starts_at IS NULL AND ends_at IS NULL FROM universe WHERE id=${id}`)).toBe('1');
  await page.setViewportSize({ width: 390, height: 900 });
  await calendar.scrollIntoViewIfNeeded();
  await page.screenshot({ path: testInfo.outputPath('universe-calendar-error-390.png'), animations: 'disabled' });
  await calendar.getByLabel('Fermeture planifiée (UTC)').fill('2030-06-12T18:45');
  await calendar.getByRole('button', { name: 'Enregistrer le calendrier' }).click();
  await expect(page.locator('.flash.success')).toContainText('Calendrier de l’univers enregistré');
  await expect(card).toHaveAttribute('data-filter-status', 'scheduled');
  await expect(card).toContainText('10/06/2030 12:30 UTC');
  await card.locator('.admin-universe-settings>summary').click();
  await expect(calendar.getByLabel('Ouverture (UTC)', { exact: true })).toHaveValue('2030-06-10T12:30');
  await expect(calendar.getByLabel('Fermeture planifiée (UTC)')).toHaveValue('2030-06-12T18:45');
  await page.getByLabel('État', { exact: true }).filter({ has: page.locator('option[value="scheduled"]') }).selectOption('scheduled');
  await expect(page.locator('#admin-universes .admin-universe-card:visible')).toHaveCount(1);
  for (const width of [1920, 1440, 1280, 768, 390]) {
    await page.setViewportSize({ width, height: 900 });
    await expect.poll(() => page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(width);
  }
  await calendar.scrollIntoViewIfNeeded();
  await page.screenshot({ path: testInfo.outputPath('universe-calendar-saved-390.png'), animations: 'disabled' });
  await calendar.getByLabel('Fermeture planifiée (UTC)').fill('');
  await calendar.getByRole('button', { name: 'Enregistrer le calendrier' }).click();
  expect(queryIsolatedSql(project, `SELECT ends_at IS NULL FROM universe WHERE id=${id}`)).toBe('1');
  expect(queryIsolatedSql(project, `SELECT COUNT(*) FROM outbox_message WHERE kind='universe' AND target_id=${id}`)).toBe('0');
  expect(queryIsolatedSql(project, `SELECT game_settings FROM universe WHERE id=${id}`)).toBe(config);
  runIsolatedSql(project, `UPDATE universe SET status='closed' WHERE id=${id}`);
  await page.reload();
  await expect(card).toHaveAttribute('data-filter-status', 'closed');
  await card.locator('.admin-universe-settings>summary').click();
  await expect(calendar.getByLabel('Ouverture (UTC)', { exact: true })).toBeDisabled();
  await expect(calendar.getByLabel('Fermeture planifiée (UTC)')).toBeDisabled();
  await expect(calendar.getByRole('button', { name: 'Enregistrer le calendrier' })).toBeDisabled();
  await expect(calendar).toContainText('ne peut pas être rouvert');
  await calendar.scrollIntoViewIfNeeded();
  await page.screenshot({ path: testInfo.outputPath('universe-calendar-closed-390.png'), animations: 'disabled' });
  const refused = await page.evaluate(async path => (await fetch(path, { method: 'POST', body: new URLSearchParams({ _token: 'invalid', starts_at: '2030-01-01T00:00' }) })).status, `/admin/universes/${id}/schedule`);
  expect(refused).toBe(403);
  expect(queryIsolatedSql(project, `SELECT status FROM universe WHERE id=${id}`)).toBe('closed');
});

test('Colonies : noms longs, renommage, sélection, raccourcis et état vide', async ({ page }, testInfo) => {
  const email = await enter(page);
  const { home, extra } = addColony(email);
  await page.goto('/planetes');
  const card = page.locator('#planet-' + extra);
  for (const width of [1920, 1440, 1280, 768, 390]) {
    await page.setViewportSize({ width, height: 900 });
    await expect.poll(() => page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(width);
    for (const title of await page.locator('.planet-index-title h2').all()) expect(await title.evaluate(element => element.scrollWidth - element.clientWidth)).toBeLessThanOrEqual(1);
  }
  await expect(page.locator('.planet-index-card.is-current')).toHaveCount(1);
  await card.locator('.planet-rename>summary').focus();
  await page.keyboard.press('Enter');
  await card.getByLabel('Nouveau nom').fill('');
  await card.locator('.planet-rename form').evaluate(form => { form.noValidate = true; });
  await card.getByRole('button', { name: /Enregistrer le nom/ }).click();
  await expect(page.locator('.flash.error')).toContainText('entre 1 et 80');
  await card.locator('.planet-rename>summary').click();
  await card.getByLabel('Nouveau nom').fill('Orion — colonie secondaire');
  await card.getByRole('button', { name: /Enregistrer le nom/ }).click();
  await expect(page.locator('.flash.success')).toContainText('Planète renommée');
  await expect(card.getByRole('heading', { level: 2 })).toHaveText('Orion — colonie secondaire');
  await expect(page.locator('#planet-' + home)).toHaveClass(/is-current/);
  await card.getByRole('link', { name: /Sélectionner cette planète/ }).click();
  await expect(page).toHaveURL(new RegExp('/planetes/' + extra + '$'));
  await page.goto('/planetes');
  await expect(card).toHaveClass(/is-current/);
  await card.getByRole('link', { name: 'Bâtiments', exact: true }).click();
  await expect(page).toHaveURL(new RegExp('/planetes/' + extra + '\\?section=buildings'));
  await page.goto('/planetes');
  await expect(card.getByRole('link', { name: 'Chantier', exact: true })).toHaveAttribute('href', new RegExp('/planetes/' + extra + '\\?section=ships'));
  await expect(card.getByRole('link', { name: 'Flotte', exact: true })).toHaveAttribute('href', new RegExp('/flottes\\?origin=' + extra));
  await card.locator('.planet-production>summary').click();
  await expect(card.locator('.planet-production dl>div')).toHaveCount(5);
  await page.evaluate(() => window.scrollTo({ top: 0, behavior: 'instant' }));
  await page.screenshot({ path: testInfo.outputPath('colonies-renamed-390.png'), fullPage: true, animations: 'disabled' });
  const project = process.env.BROWSER_ROUTER;
  runIsolatedSql(project, `UPDATE player SET preferences=JSON_SET(CASE WHEN JSON_TYPE(preferences)='OBJECT' THEN preferences ELSE JSON_OBJECT() END,'$.theme','light') WHERE email='${email}'`);
  await page.goto('/planetes');
  await page.screenshot({ path: testInfo.outputPath('colonies-light-390.png'), fullPage: true, animations: 'disabled' });
  const membership = Number(queryIsolatedSql(project, `SELECT pu.id FROM player_universe pu JOIN player p ON p.id=pu.player_id WHERE p.email='${email}'`));
  runIsolatedSql(project, `UPDATE planet SET owner_id=NULL WHERE owner_id=${membership}`);
  const empty = await page.goto('/planetes');
  expect(empty.status()).toBe(200);
  await expect(page.getByRole('heading', { name: 'Aucune colonie dans cet univers' })).toBeVisible();
  await expect(page.getByRole('link', { name: 'Vue générale' })).toHaveCount(0);
  await page.screenshot({ path: testInfo.outputPath('colonies-empty-390.png'), animations: 'disabled' });
});
