import { test, expect } from './support/isolated-test.mjs';
import { grantSecondColony, queryIsolatedSql, revealVirginPlanet, runIsolatedSql } from './support/game-fixtures.mjs';
import AxeBuilder from '@axe-core/playwright';

async function enter(page) {
  const suffix = crypto.randomUUID().slice(0, 8);
  const email = `inventory-a-${suffix}@example.test`;
  const password = crypto.randomUUID() + 'aA1!';
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
  return { email, home, member, project };
}

async function verifyView(page, info, key, theme, width) {
  expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(width);
  for (const element of await page.locator('.inventory-redesign .panel:visible, .inventory-pending-list li').all()) {
    expect(await element.evaluate(el => el.scrollWidth <= el.clientWidth + 1)).toBe(true);
  }
  const review = key.startsWith('review');
  if (review) {
    const bounds = await page.getByRole('dialog').boundingBox();
    expect(bounds.x).toBeGreaterThanOrEqual(0);
    expect(bounds.x + bounds.width).toBeLessThanOrEqual(width);
    expect(bounds.height).toBeLessThanOrEqual(900 - 30);
  }
  if (width === 390) {
    expect((await new AxeBuilder({ page }).include('.inventory-redesign').analyze()).violations).toEqual([]);
  }
  if (width !== 320) {
    if (!review) await page.evaluate(() => { document.activeElement?.blur(); window.scrollTo({ top: 0, behavior: 'instant' }); });
    await page.screenshot({ path: info.outputPath(`jeu-L3-A-${key}-${theme}-${width}.png`), fullPage: !review, animations: 'disabled' });
    if (width === 390) {
      if (review) await page.getByRole('dialog').screenshot({ path: info.outputPath(`jeu-L3-A-${key}-${theme}-390-dialogue.png`), animations: 'disabled' });
      else await page.screenshot({ path: info.outputPath(`jeu-L3-A-${key}-${theme}-390-ecran.png`), animations: 'disabled' });
    }
  }
}

test('L3 A : inventaire guidé, confirmations, états vides et vacances dans les deux thèmes', async ({ page, browser, baseURL }, info) => {
  test.setTimeout(300000);
  const errors = [];
  page.on('pageerror', error => errors.push(error.message));
  const owner = await enter(page);
  const { home, member, project } = owner;
  const pages = [['boosts', '/boosts'], ['boxes', '/inventaire/boites'], ['pending', '/inventaire/recompenses']];
  await page.setViewportSize({ width: 390, height: 900 });
  for (const [key, url] of pages) {
    await page.goto(url);
    await verifyView(page, info, key + '-vide', 'dark', 390);
  }
  grantSecondColony(project, owner.email);
  const second = revealVirginPlanet(project, owner.email);
  runIsolatedSql(project, `UPDATE planet SET name='Horizon' WHERE id=${home}`);
  runIsolatedSql(project, `UPDATE planet SET owner_id=${member},name='Avant-poste des Constellations Oubliées',acquired_at=UTC_TIMESTAMP() WHERE id=${second.id}`);
  runIsolatedSql(project, `INSERT IGNORE INTO planet_resource(planet_id,resource_type,current_stock,production_rate,last_updated_at) SELECT ${second.id},resource_type,1000,0,UTC_TIMESTAMP() FROM planet_resource WHERE planet_id=${home}`);
  const rows = [];
  for (let i = 0; i < 12; i++) rows.push(`(${member},'event','inventory-a-boost-${i}','available',UTC_TIMESTAMP(),'boost',NULL)`);
  for (const [index, quantity] of [[1, 50], [2, 30], [3, 8]]) {
    const payload = JSON.stringify({ format: 'small', label: index === 3 ? 'Boîte des Constellations Oubliées' : 'Boîte de ressources ' + index, resources: { metal: index * 1000, nano: 600, hydrogen: 250, antimatter: 0, electricity: 100 } });
    for (let i = 0; i < quantity; i++) rows.push(`(${member},'event','inventory-a-box-${index}-${i}','available',UTC_TIMESTAMP(),'resource_box','${payload}')`);
  }
  for (let i = 0; i < 3; i++) rows.push(`(${member},'event','inventory-a-pending-${i}','pending',DATE_SUB(UTC_TIMESTAMP(),INTERVAL ${3 - i} MINUTE),'boost',NULL)`);
  runIsolatedSql(project, `INSERT INTO player_boost(player_universe_id,source_type,source_key,status,earned_at,kind,payload) VALUES ${rows.join(',')}`);
  runIsolatedSql(project, `UPDATE player_boost SET gift_unread=1 WHERE player_universe_id=${member} AND source_key IN ('inventory-a-box-1-0','inventory-a-pending-0')`);
  for (const [planet, type, slug] of [[home, 'building', 'mine_metal'], [second.id, 'research', 'engineering_foundations']]) {
    runIsolatedSql(project, `INSERT INTO queue_item(planet_id,player_universe_id,queue_type,item_slug,tier,item_level,quantity,duration_seconds,started_at,queued_at,dispatch_token,paid_costs) VALUES (${planet},${member},'${type}','${slug}','basic',1,1,86400,UTC_TIMESTAMP(),UTC_TIMESTAMP(),'${crypto.randomUUID().replaceAll('-', '')}','{}')`);
  }
  const queue = Number(queryIsolatedSql(project, `SELECT id FROM queue_item WHERE planet_id=${home} AND queue_type='building'`));
  const research = Number(queryIsolatedSql(project, `SELECT id FROM queue_item WHERE planet_id=${second.id} AND queue_type='research'`));
  runIsolatedSql(project, `INSERT INTO fleet(player_universe_id,home_planet_id,destination_id,destination_system_id,status,mission,speed_ratio,cargo,reserved_fuel,orbit_fuel,orbit_duration_seconds,departure_at,arrival_at,travel_seconds,dispatch_token) SELECT ${member},${home},id,solar_system_id,'returning','transport',1,'{}',0,0,0,UTC_TIMESTAMP(),DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 DAY),86400,'${crypto.randomUUID().replaceAll('-', '')}' FROM planet WHERE id=${second.id}`);

  for (const theme of ['dark', 'light']) {
    runIsolatedSql(project, `UPDATE player SET preferences=JSON_SET(CASE WHEN JSON_TYPE(preferences)='OBJECT' THEN preferences ELSE JSON_OBJECT() END,'$.theme','${theme}') WHERE email='${owner.email}'`);
    for (const width of [1440, 768, 390, 320]) {
      await page.setViewportSize({ width, height: 900 });
      for (const [key, url] of pages) {
        await page.goto(url);
        await expect(page.locator('body')).toHaveAttribute('data-theme', theme);
        await expect(page.getByRole('progressbar')).toHaveAttribute('value', '100');
        const nav = page.locator('nav[aria-label="Navigation de l’inventaire"]');
        await expect(nav.locator(`a[href="${url}"]`)).toHaveAttribute('aria-current', 'page');
        for (const [href, count] of [['/boosts', '12'], ['/inventaire/boites', '88'], ['/inventaire/recompenses', '3']]) {
          await expect(nav.locator(`a[href="${href}"] .badge`)).toHaveText(count);
        }
        await expect(nav.locator('[data-page="boxes"]')).toHaveText('1 nouveau');
        await expect(nav.locator('[data-page="pending"]')).toHaveText('1 nouveau');
        if (key === 'boosts') {
          const picker = page.getByLabel('Opération à accélérer', { exact: true });
          await picker.selectOption('queue-' + research);
          await expect(page.locator('.boost-operation:visible')).toHaveCount(1);
          await expect(page.locator('.boost-operation:visible')).toContainText('Avant-poste des Constellations Oubliées');
          await picker.selectOption('queue-' + queue);
          const timings = page.locator('.boost-operation:visible .inventory-preview-times');
          await expect(timings).toContainText('Avant');
          await expect(timings).toContainText('Après');
          const values = await timings.locator('dd').allTextContents();
          expect(values).toHaveLength(2);
          expect(values[0]).not.toEqual(values[1]);
          await expect(page.locator('.boost-operation:visible')).toHaveCount(1);
          if (width === 1440) {
            const stock = await page.locator('.inventory-boost-stock').boundingBox();
            expect((await page.locator('.inventory-boost-use').boundingBox()).x).toBeGreaterThanOrEqual(stock.x + stock.width);
          }
        }
        if (key === 'boxes') {
          await expect(page.locator('.resource-box-card')).toHaveCount(3);
          for (const select of await page.locator('.resource-box-form select').all()) await select.selectOption(String(second.id));
          if (width === 1440) {
            const description = await page.locator('.resource-box-description').first().boundingBox();
            expect((await page.locator('.resource-box-use').first().boundingBox()).x).toBeGreaterThanOrEqual(description.x + description.width);
          }
        }
        await verifyView(page, info, key, theme, width);
        if (key === 'boosts' || key === 'boxes') {
          const launcher = key === 'boosts' ? page.locator(`form[action$="/queue/${queue}"]`).getByRole('button') : page.locator('.resource-box-form').first().getByRole('button');
          if (key === 'boosts' && [1440, 1920, 390].includes(width)) {
            // Le guide d’un nouveau membre reste ouvert sans recouvrir l’action.
            const guide = page.locator('.first-steps-popup-enhanced[open] .first-steps-popup-panel:visible');
            await expect(guide).toBeVisible();
            await page.getByLabel('Opération à accélérer', { exact: true }).focus();
            await page.keyboard.press('Tab');
            await expect(launcher).toBeFocused();
            await launcher.scrollIntoViewIfNeeded();
            const panel = await guide.boundingBox(), action = await launcher.boundingBox();
            expect(panel.y).toBeGreaterThanOrEqual(action.y + action.height);
            expect(await launcher.evaluate(button => {
              const box = button.getBoundingClientRect();
              return button.contains(document.elementFromPoint(box.x + box.width / 2, box.y + box.height / 2));
            })).toBe(true);
          }
          await launcher.click();
          const dialog = page.getByRole('dialog');
          await expect(dialog).toBeVisible();
          await expect(dialog.getByRole('button', { name: 'Revenir à l’inventaire' })).toBeFocused();
          await expect(dialog).toContainText(key === 'boosts' ? '25,00 %' : 'Avant-poste des Constellations Oubliées');
          await verifyView(page, info, key === 'boosts' ? 'review-boost' : 'review-box', theme, width);
          await page.keyboard.press('Escape');
          await expect(dialog).not.toBeVisible();
          await expect(launcher).toBeFocused();
        }
      }
    }
  }
  expect(queryIsolatedSql(project, `SELECT COUNT(*) FROM player_boost WHERE player_universe_id=${member} AND status='used'`)).toBe('0');
  await page.setViewportSize({ width: 390, height: 900 });
  await page.goto('/boosts');
  await page.locator('.space-navigation-menu > summary').click();
  const boxesLink = page.getByRole('navigation', { name: 'Navigation de l’inventaire' }).getByRole('link', { name: /^Boîtes/ });
  await page.keyboard.press('Tab');
  await boxesLink.focus();
  expect(await boxesLink.evaluate(el => el.matches(':focus-visible') && getComputedStyle(el).outlineStyle !== 'none')).toBe(true);
  await page.keyboard.press('Enter');
  await expect(page).toHaveURL(/inventaire\/boites$/);
  await page.reload();
  await expect(page.locator('.resource-box-card')).toHaveCount(3);
  await page.goBack();
  await expect(page).toHaveURL(/\/boosts$/);

  const plainContext = await browser.newContext({ baseURL, javaScriptEnabled: false, viewport: { width: 390, height: 900 }, storageState: await page.context().storageState() });
  try {
    const plain = await plainContext.newPage();
    await plain.goto('/boosts');
    await expect(plain.locator('#inventory-operation')).toBeHidden();
    const operations = plain.locator('.boost-operation:visible');
    await expect(operations).toHaveCount(2);
    for (const [title, label, id] of [
      ['Mine à ciel ouvert · Horizon', 'Mine à ciel ouvert', queue],
      ['Génie civil colonial · Avant-poste des Constellations Oubliées', 'Génie civil colonial', research],
    ]) {
      const operation = operations.filter({ has: plain.getByRole('heading', { name: title, exact: true }) });
      await expect(operation).toHaveCount(1);
      const form = operation.locator('form[method="post"][action="/boosts/utiliser/queue/' + id + '"]');
      await expect(form.getByRole('button', { name: 'Utiliser un boost pour ' + label, exact: true })).toBeVisible();
    }
    await expect(plain.locator('form[action^="/boosts/utiliser/fleet/"], option[value^="fleet-"]')).toHaveCount(0);
    await expect(plain.getByRole('navigation', { name: 'Navigation de l’inventaire' })).toBeVisible();
  } finally { await plainContext.close(); }

  runIsolatedSql(project, `INSERT INTO vacation_period(membership_id,started_at,scheduled_end_at,ended_at) VALUES (${member},DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 MINUTE),DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 DAY),NULL)`);
  for (const [key, url] of pages.slice(0, 2)) {
    await page.goto(url);
    await expect(page.locator(key === 'boosts' ? '.inventory-boost-use button' : '.resource-box-form')).toHaveCount(0);
    await expect(page.locator('.inventory-redesign')).toContainText(key === 'boosts' ? 'Les accélérations sont indisponibles pendant les vacances' : 'Ouverture bloquée pendant les vacances');
    await verifyView(page, info, key + '-vacances', 'light', 390);
  }
  expect(errors).toEqual([]);
});

test('La boîte commerciale affiche son décompte puis bloque l’ouverture à échéance', async ({ page }) => {
  const { project, member } = await enter(page);
  runIsolatedSql(project, `INSERT INTO player_boost (player_universe_id, source_type, source_key, status, kind, payload, earned_at)
    VALUES (${member}, 'market_overflow', 'market-overflow-browser', 'available', 'resource_box',
      JSON_OBJECT('format','market_overflow','label','Boîte de surplus de la Bourse','resources',JSON_OBJECT('metal',10),
      'expires_at', UNIX_TIMESTAMP()+120), UTC_TIMESTAMP()-INTERVAL 86300 SECOND)`);
  await page.clock.install();
  await page.goto('/inventaire/boites');
  const card = page.locator('.resource-box-card').filter({ hasText: 'Boîte de surplus de la Bourse' });
  const timer = card.locator('[data-countdown-target="time"]');
  await expect(timer).toContainText(/0h [12]m/);
  const before = await timer.textContent();
  await page.clock.fastForward(3000);
  await expect(timer).not.toHaveText(before);
  await page.clock.fastForward(120000);
  await expect(timer).toHaveText('Boîte expirée');
  await expect(card.getByRole('button', { name: 'Ouvrir cette boîte' })).toBeDisabled();
});
