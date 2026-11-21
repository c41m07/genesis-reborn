import { test, expect } from './support/isolated-test.mjs';
import { prepareOwnedColony, revealVirginPlanet, runIsolatedSql, queryIsolatedSql } from './support/game-fixtures.mjs';
import AxeBuilder from '@axe-core/playwright';

async function prepare(page) {
  const suffix = crypto.randomUUID().slice(0, 8);
  const email = 'fleet-design-' + suffix + '@example.test';
  const password = crypto.randomUUID() + 'aA1!';
  await page.goto('/inscription');
  await page.getByLabel('Adresse email').fill(email);
  await page.getByLabel('Nom de commandant').fill('Orion-' + suffix);
  await page.getByLabel('Mot de passe (12 caractères minimum)', { exact: true }).fill(password);
  await page.getByLabel('Confirmer le mot de passe').fill(password);
  await page.getByRole('button', { name: 'Créer mon compte' }).click();
  await page.getByLabel('Adresse email').fill(email);
  await page.getByLabel('Mot de passe', { exact: true }).fill(password);
  await page.getByRole('button', { name: /Connexion/ }).click();
  await page.locator('.universe-card').filter({ has: page.getByRole('heading', { name: /Omega/ }) }).getByRole('button').click();
  await expect(page).toHaveURL(/planetes\/\d+$/);
  const project = process.env.BROWSER_ROUTER;
  const origin = Number(new URL(page.url()).pathname.split('/').at(-1));
  prepareOwnedColony(project, email);
  const target = revealVirginPlanet(project, email);
  const member = Number(queryIsolatedSql(project, `SELECT owner_id FROM planet WHERE id=${origin}`));
  runIsolatedSql(project, `UPDATE planet SET owner_id=${member}, name='Avant-poste des Constellations Oubliées', acquired_at=UTC_TIMESTAMP() WHERE id=${target.id}`);
  runIsolatedSql(project, `INSERT INTO fleet (player_universe_id,home_planet_id,status,mission,speed_ratio,cargo,reserved_fuel,orbit_fuel,orbit_duration_seconds,travel_seconds,dispatch_token) VALUES (${member},${origin},'idle','idle',1,'{}',0,0,0,0,'')`);
  for (const slug of ['probe', 'fighter', 'transport', 'heavy_fighter', 'colony_ship']) {
    runIsolatedSql(project, `INSERT INTO fleet_ship (fleet_id,slug,tier,quantity) SELECT MAX(id),'${slug}','basic',12 FROM fleet WHERE home_planet_id=${origin} AND status='idle'`);
  }
  // Un véritable envoi fournit les données de navigation exigées par le devis de rappel.
  await page.goto('/flottes/preparer');
  const form = page.locator('.fleet-form form').first();
  await form.locator('[name="ships[transport]"]').fill('1');
  await form.getByRole('button', { name: /Choisir la destination/ }).click();
  await form.locator('[name="mission"]').selectOption('transport');
  await form.locator('[name="ratio"]').selectOption('0.1');
  await form.locator('[name="owned_destination"]').selectOption(String(target.id));
  await form.getByRole('button', { name: /Vérifier la mission/ }).click();
  const send = form.getByRole('button', { name: /Envoyer la flotte/ });
  await expect(send).toBeEnabled();
  await send.click();
  await expect(page).toHaveURL(/flottes$/);
  const sentId = Number(queryIsolatedSql(project, `SELECT MAX(id) FROM fleet WHERE player_universe_id=${member} AND status='traveling'`));
  expect(sentId).toBeGreaterThan(0);
  const flights = [sentId];
  // L'échéance est repoussée seulement pour stabiliser les captures ; le rappel réel est testé séparément.
  runIsolatedSql(project, `UPDATE fleet SET arrival_at=DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 DAY),cargo=JSON_SET(cargo,'$.metal',120) WHERE id=${sentId}`);
  // Ces états stables servent uniquement à la recette visuelle dans la base jetable.
  for (const [status, mission] of [['returning', 'explore'], ['attacking', 'attack']]) {
    runIsolatedSql(project, `INSERT INTO fleet (player_universe_id,home_planet_id,destination_id,destination_system_id,status,mission,speed_ratio,cargo,reserved_fuel,orbit_fuel,orbit_duration_seconds,travel_seconds,departure_at,arrival_at,dispatch_token) SELECT ${member},${origin},id,solar_system_id,'${status}','${mission}',0.1,'{"metal":120,"hydrogen":10000}',10000,0,0,86400,DATE_SUB(UTC_TIMESTAMP(),INTERVAL 30 SECOND),DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 DAY),'design-${status}' FROM planet WHERE id=${target.id}`);
    const id = Number(queryIsolatedSql(project, `SELECT MAX(id) FROM fleet WHERE player_universe_id=${member}`));
    runIsolatedSql(project, `INSERT INTO fleet_ship (fleet_id,slug,tier,quantity) VALUES (${id},'transport','basic',3)`);
    flights.push(id);
  }
  return { project, email, origin, target, flights };
}

async function checkLayout(page, width) {
  expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(width);
  for (const selector of ['.fleet-form', '.fleet-recall-panel', '[data-active-fleet]']) {
    for (const element of await page.locator(selector).all()) {
      expect(await element.evaluate(node => node.scrollWidth <= node.clientWidth + 1)).toBe(true);
    }
  }
  if (width === 390) {
    expect((await new AxeBuilder({ page }).include('.fleet-redesign').analyze()).violations).toEqual([]);
  }
}

async function capture(page, info, name, theme, width) {
  if (width === 320) return;
  await page.screenshot({ path: info.outputPath(`jeu-L2A-A-${name}-${theme}-${width}.png`), fullPage: true, animations: 'disabled' });
  if (width === 390) {
    await page.screenshot({ path: info.outputPath(`jeu-L2A-A-${name}-${theme}-390-ecran.png`), animations: 'disabled' });
  }
}

test('L2A A : missions en lignes, trois étapes, devis et rappel sur ordinateur et mobile', async ({ page }, testInfo) => {
  test.setTimeout(300000);
  const data = await prepare(page);
  for (const theme of ['light', 'dark']) {
    runIsolatedSql(data.project, `UPDATE player SET preferences=JSON_SET(CASE WHEN JSON_TYPE(preferences)='OBJECT' THEN preferences ELSE JSON_OBJECT() END,'$.theme','${theme}') WHERE email='${data.email}'`);
    for (const width of [390, 1440, 768, 320]) {
      await page.setViewportSize({ width, height: 900 });
      await page.goto('/flottes');
      await expect(page.locator('[data-active-fleet]')).toHaveCount(3);
      await expect(page.locator('.flight-details-action')).toHaveCount(3);
      await expect(page.locator('.fleet-recall-link')).toHaveCount(1);
      await expect(page.locator('.incoming-empty-message')).toBeVisible();
      await checkLayout(page, width);
      await capture(page, testInfo, 'missions', theme, width);

      await page.goto('/flottes/preparer');
      const form = page.locator('.fleet-form form').first();
      await form.locator('[name="ships[transport]"]').fill('8');
      await checkLayout(page, width);
      await capture(page, testInfo, 'composition', theme, width);
      await form.getByRole('button', { name: /Choisir la destination/ }).click();
      await expect(form.getByRole('heading', { name: 'Choisissez la destination' })).toBeFocused();
      await form.locator('[name="mission"]').selectOption('transport');
      await form.locator('[name="owned_destination"]').selectOption(String(data.target.id));
      const cargo = form.locator('details[data-fleet-fields-target="cargo"]');
      await expect(cargo).toHaveAttribute('open', '');
      await expect(cargo.locator('input').first()).toBeVisible();
      const order = await page.locator('.resource-strip [data-compact-resource]').evaluateAll(items => items.map(item => item.dataset.compactResource));
      expect(order).toEqual(['metal', 'nano', 'hydrogen', 'antimatter', 'electricity']);
      expect(await cargo.locator('input[data-resource]').evaluateAll(inputs => inputs.map(input => input.dataset.resource))).toEqual(order);
      await form.locator('[name="cargo[metal]"]').fill('1000');
      await checkLayout(page, width);
      await capture(page, testInfo, 'destination', theme, width);
      await form.getByRole('button', { name: /Vérifier la mission/ }).click();
      await expect(form.getByRole('heading', { name: 'Confirmez votre mission' })).toBeFocused();
      await expect(form.locator('[data-fleet-preview-target="result"]')).toContainText('Carburant aller-retour');
      await expect(form.getByRole('button', { name: /Envoyer la flotte/ })).toBeEnabled();
      await expect(form.locator('[data-fleet-wizard-target="summary"]')).toContainText(/Métal\s*:\s*1\s*000/);
      await checkLayout(page, width);
      await capture(page, testInfo, 'confirmation', theme, width);
      await form.getByRole('button', { name: /Modifier la destination/ }).click();
      await expect(form.locator('[name="cargo[metal]"]')).toHaveValue('1000');
      await form.getByRole('button', { name: /Retour à la composition/ }).click();
      await expect(form.locator('[name="ships[transport]"]')).toHaveValue('8');

      const response = await page.goto('/flottes/' + data.flights[0] + '/rappel');
      expect(response.status()).toBe(200);
      await expect(page.getByRole('button', { name: 'Confirmer le rappel', exact: true })).toBeVisible();
      await checkLayout(page, width);
      await capture(page, testInfo, 'rappel', theme, width);
    }
  }
  await page.goto('/flottes/preparer');
  const form = page.locator('.fleet-form form').first();
  await form.getByRole('button', { name: /Choisir la destination/ }).click();
  await expect(form.getByRole('alert')).toHaveText('Choisissez au moins un vaisseau pour poursuivre.');
  await expect(form.locator('[data-fleet-wizard-target="quantity"]').first()).toBeFocused();
  const input = form.locator('[name="ships[transport]"]');
  await input.fill('2');
  await input.focus();
  const refresh = page.waitForResponse(response => new URL(response.url()).pathname === '/flottes/preparer' && response.request().headers()['x-genesis-live'] === '1');
  await page.evaluate(() => window.dispatchEvent(new CustomEvent('genesis:refresh')));
  await refresh;
  await expect(input).toHaveValue('2');
  await expect(input).toBeFocused();
  await page.goto('/flottes');
  const detail = page.locator(`[data-active-fleet="${data.flights[0]}"]`);
  const trigger = detail.getByRole('button', { name: /Composition et fret/ });
  await trigger.focus();
  await page.keyboard.press('Space');
  await expect(detail.locator('.empire-details')).toBeVisible();
  await expect(detail.locator('.empire-details')).toContainText('120');
  await page.keyboard.press('Escape');
  await expect(detail.locator('.empire-details')).toBeHidden();
  const menu = page.locator('.space-navigation-menu');
  await expect(menu).not.toHaveAttribute('open');
  await menu.locator('summary').focus();
  await page.keyboard.press('Enter');
  await expect(menu.getByRole('link', { name: 'Préparer une mission', exact: true })).toBeVisible();
  await page.keyboard.press('Escape');
  await expect(menu.locator('summary')).toBeFocused();
  await menu.locator('summary').click();
  await menu.getByRole('link', { name: 'Préparer une mission', exact: true }).click();
  await page.reload();
  await expect(page.locator('h1')).toHaveText('Préparer une mission');
  await page.goBack();
  await expect(page).toHaveURL(/flottes$/);
});
