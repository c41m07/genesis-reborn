import { test, expect } from './support/isolated-test.mjs';
import { prepareOwnedColony, revealVirginPlanet, queryIsolatedSql, runIsolatedSql } from './support/game-fixtures.mjs';
import AxeBuilder from '@axe-core/playwright';

async function enter(page) {
  const suffix = crypto.randomUUID().slice(0, 8);
  const email = `colonies-a-${suffix}@example.test`;
  const password = crypto.randomUUID() + 'aA1!';
  await page.goto('/inscription');
  await page.getByLabel('Adresse email').fill(email);
  await page.getByLabel('Nom de commandant').fill('Colonies-' + suffix);
  await page.getByLabel('Mot de passe (12 caractères minimum)', { exact: true }).fill(password);
  await page.getByLabel('Confirmer le mot de passe').fill(password);
  await page.getByRole('button', { name: 'Créer mon compte' }).click();
  await page.getByLabel('Adresse email').fill(email);
  await page.getByLabel('Mot de passe', { exact: true }).fill(password);
  await page.getByRole('button', { name: /Connexion/ }).click();
  await page.locator('.universe-card').filter({ has: page.getByRole('heading', { name: /Omega/ }) }).getByRole('button').click();
  await expect(page).toHaveURL(/planetes\/\d+$/);
  const project = process.env.BROWSER_ROUTER;
  const planet = Number(page.url().split('/').pop());
  const member = Number(queryIsolatedSql(project, `SELECT current_player_universe_id FROM player WHERE email='${email}'`));
  return { project, planet, member, email };
}

async function assertGuideBelowAction(page, control) {
  const guide = page.locator('.app-frame > .first-steps-popup-enhanced');
  await expect(guide.locator('.first-steps-popup-mount')).toBeVisible();
  await control.scrollIntoViewIfNeeded();
  const [action, help] = await Promise.all([control.boundingBox(), guide.boundingBox()]);
  expect(help.y).toBeGreaterThanOrEqual(action.y + action.height);
  expect(await control.evaluate(el => {
    const { x, y, width, height } = el.getBoundingClientRect();
    return el.contains(document.elementFromPoint(x + width / 2, y + height / 2));
  })).toBe(true);
}

async function capture(page, info, key, theme, width, audit = true) {
  await expect(page.locator('.page-contents')).toHaveCount(0);
  expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(width);
  for (const panel of await page.locator('.colony-redesign .panel:visible').all()) {
    expect(await panel.evaluate(el => el.scrollWidth <= el.clientWidth + 1), `${key} ${theme} ${width} ${await panel.getAttribute('class')}`).toBe(true);
  }
  if (audit && width === 390) expect((await new AxeBuilder({ page }).include('.colony-redesign').analyze()).violations).toEqual([]);
  if (width !== 320) {
    await page.evaluate(() => { document.activeElement?.blur(); window.scrollTo({ top: 0, behavior: 'instant' }); });
    await page.screenshot({ path: info.outputPath(`jeu-L7A-A-${key}-${theme}-${width}.png`), fullPage: true, animations: 'disabled' });
    if (width === 390) await page.screenshot({ path: info.outputPath(`jeu-L7A-A-${key}-${theme}-390-ecran.png`), animations: 'disabled' });
  }
}

test('L7A A : colonies, renommage, comparaison et bilan complets sur quatre formats', async ({ page, browser, baseURL }, info) => {
  test.setTimeout(240000);
  const errors = [];
  page.on('pageerror', error => errors.push(error.message));
  const user = await enter(page);
  const other = revealVirginPlanet(user.project, user.email);
  runIsolatedSql(user.project, `UPDATE planet SET owner_id=${user.member},name='Orion',acquired_at=NULL WHERE id=${other.id}`);
  runIsolatedSql(user.project, `UPDATE planet SET name='Horizon' WHERE id=${user.planet}`);
  prepareOwnedColony(user.project, user.email, { stock: 9000 });
  runIsolatedSql(user.project, `INSERT INTO player_technology(player_universe_id,slug,tier,level) VALUES (${user.member},'colonial_expansion','basic',1) ON DUPLICATE KEY UPDATE level=GREATEST(level,1)`);
  runIsolatedSql(user.project, `INSERT INTO planet_building(planet_id,slug,tier,level) VALUES (${user.planet},'mine_metal','basic',4) ON DUPLICATE KEY UPDATE level=4`);
  runIsolatedSql(user.project, `INSERT INTO planet_defense(planet_id,slug,tier,quantity) VALUES (${user.planet},'ground_turret_anti_light','basic',3)`);
  runIsolatedSql(user.project, `INSERT INTO fleet(player_universe_id,home_planet_id,status,mission,speed_ratio,cargo,reserved_fuel,orbit_fuel,orbit_duration_seconds,travel_seconds,dispatch_token) VALUES (${user.member},${user.planet},'idle','idle',1,'{"metal":200,"hydrogen":50}',0,0,0,0,'')`);
  const fleet = Number(queryIsolatedSql(user.project, `SELECT MAX(id) FROM fleet WHERE home_planet_id=${user.planet}`));
  runIsolatedSql(user.project, `INSERT INTO fleet_ship(fleet_id,slug,tier,quantity) VALUES (${fleet},'fighter','basic',5)`);
  runIsolatedSql(user.project, `INSERT INTO queue_item(planet_id,player_universe_id,queue_type,item_slug,tier,item_level,quantity,duration_seconds,started_at,queued_at,dispatch_token,paid_costs) VALUES (${user.planet},${user.member},'building','mine_metal','basic',5,1,86400,UTC_TIMESTAMP(),UTC_TIMESTAMP(),'${crypto.randomUUID()}','{"metal":500}')`);
  const abandon = `/planetes/${user.planet}/abandonner`;
  for (const theme of ['dark', 'light']) {
    runIsolatedSql(user.project, `UPDATE player SET preferences=JSON_SET(CASE WHEN JSON_TYPE(preferences)='OBJECT' THEN preferences ELSE JSON_OBJECT() END,'$.theme','${theme}') WHERE email='${user.email}'`);
    for (const width of [1440, 768, 390, 320]) {
      await page.setViewportSize({ width, height: 900 });
      await page.goto('/planetes');
      await expect(page.locator('.planet-index-card')).toHaveCount(2);
      const card = page.locator('#planet-' + user.planet);
      await expect(card.getByRole('link', { name: 'Bâtiments', exact: true })).toHaveAttribute('href', `/planetes/${user.planet}?section=buildings`);
      await expect(card.getByRole('link', { name: 'Chantier', exact: true })).toHaveAttribute('href', `/planetes/${user.planet}?section=ships`);
      await expect(card.getByRole('link', { name: 'Flotte', exact: true })).toHaveAttribute('href', `/flottes?origin=${user.planet}`);
      await expect(page.locator('#planet-' + other.id)).toContainText('Ancienneté approximative');
      const [hero, art, body, actions] = await Promise.all([
        card.locator('.planet-board-hero').boundingBox(),
        card.locator('.planet-index-art').boundingBox(),
        card.locator('.planet-index-body').boundingBox(),
        card.locator('.planet-index-actions').boundingBox(),
      ]);
      expect(art).toEqual(hero);
      expect(body.y).toBeGreaterThan(hero.y + hero.height);
      expect(actions.y).toBeGreaterThanOrEqual(body.y + body.height);
      const [first, second] = await Promise.all((await page.locator('.planet-index-card').all()).map(row => row.boundingBox()));
      if (width === 1440) {
        expect(second.x).toBeGreaterThan(first.x + first.width);
        expect(Math.abs(second.y - first.y)).toBeLessThan(2);
      } else {
        expect(second.y).toBeGreaterThan(first.y + first.height);
        expect(Math.abs(second.x - first.x)).toBeLessThan(2);
      }
      await capture(page, info, 'planetes', theme, width);
      await card.locator('.planet-rename summary').click();
      await expect(card.getByLabel('Nouveau nom')).toHaveValue('Horizon');
      await expect(card.getByLabel('Nouveau nom')).toHaveAttribute('maxlength', '80');
      await capture(page, info, 'renommage', theme, width);
      await card.locator('.planet-production summary').click();
      await expect(card.locator('.planet-production')).toContainText('Effets de la position orbitale');
      await expect(card.locator('.planet-production dl')).not.toBeEmpty();
      await page.goto('/planetes?view=table&resource=metal');
      await expect(page.locator('.colony-comparison tbody tr')).toHaveCount(2);
      const shortcuts = page.locator('.colony-comparison-cards');
      const horizon = shortcuts.getByRole('article', { name: 'Horizon' });
      const orion = shortcuts.getByRole('article', { name: 'Orion' });
      await expect(horizon).toContainText('Colonie active');
      await expect(horizon).toContainText('Mine à ciel ouvert');
      await expect(horizon).toContainText('Niveau 5');
      await expect(horizon.locator('.planet-index-art img')).toHaveAttribute('src', /colony-overview\.webp/);
      await expect(orion).toContainText('Aucune opération en cours');
      await expect(orion.locator('.planet-index-art img')).toHaveAttribute('src', /planet-desert\.webp/);
      await expect(orion.getByRole('link', { name: /Ouvrir cette colonie/ })).toHaveAttribute('href', '/planetes/' + other.id);
      if (width >= 1440) {
        const controls = horizon.locator('.colony-comparison-card-actions');
        const open = controls.getByRole('link', { name: /Ouvrir cette colonie/ });
        const manage = controls.getByRole('link', { name: /Renommer ou consulter ses bonus/ });
        await open.focus();
        await page.keyboard.press('Tab');
        await expect(manage).toBeFocused();
        await assertGuideBelowAction(page, manage);
        await assertGuideBelowAction(page, page.locator('.colony-comparison tbody td[data-comparison-queue="shipyard"]').last());
      }
      if (width === 1440) await expect(page.locator('.colony-comparison td[data-resource="nano"]').first()).toBeVisible();
      else await expect(page.locator('.colony-comparison td[data-resource="nano"]').first()).toBeHidden();
      const horizonWork = page.locator('.colony-comparison tbody tr')
        .filter({ has: page.getByRole('link', { name: 'Horizon', exact: true }) })
        .locator('[data-comparison-queue="building"]');
      await expect(horizonWork).toContainText('Mine à ciel ouvert');
      await expect(horizonWork).toContainText('Niveau 5');
      await expect(horizonWork).toContainText('En cours');
      await expect(horizonWork.locator('time')).toHaveAttribute('datetime', /T/);
      if (width === 390) {
        const resource = page.getByLabel('Ressource à comparer');
        await resource.focus();
        await assertGuideBelowAction(page, resource);
        await page.keyboard.press('Tab');
        const compare = page.getByRole('button', { name: 'Comparer cette ressource' });
        await expect(compare).toBeFocused();
        await assertGuideBelowAction(page, compare);
      }
      await capture(page, info, 'comparaison', theme, width);
      await page.goto(abandon);
      for (const title of ['Un abandon définitif', 'Bilan des pertes', 'Ressources', 'Bâtiments', 'Défenses', 'Vaisseaux présents', 'Ressources embarquées présentes', 'Commandes annulées sans remboursement', 'Confirmer l’abandon']) {
        await expect(page.getByRole('heading', { name: title, exact: true })).toBeVisible();
      }
      await expect(page.locator('.colony-abandon-panel')).toContainText(`Flotte ${fleet}`);
      await expect(page.locator('.colony-abandon-panel')).toContainText('Mine à ciel ouvert');
      await expect(page.locator('.colony-abandon-panel')).toContainText('Chasseur de patrouille');
      await expect(page.locator('.abandon-loss-grid dl > div')).toHaveCount(5);
      await expect(page.locator('.abandon-confirmation input[type="hidden"]')).toHaveCount(4);
      const losses = await page.locator('.colony-abandon-panel').boundingBox();
      const confirmation = await page.locator('.colony-abandon-confirmation').boundingBox();
      if (width === 1440) expect(confirmation.x).toBeGreaterThanOrEqual(losses.x + losses.width);
      else expect(confirmation.y).toBeGreaterThanOrEqual(losses.y + losses.height);
      await capture(page, info, 'abandon', theme, width);
    }
  }
  await page.setViewportSize({ width: 390, height: 900 });
  // Le nom long et le dépassement de capacité sont des états existants, créés dans la seule base jetable.
  const longName = 'Étoile'.repeat(13);
  runIsolatedSql(user.project, `UPDATE planet SET name='${longName}' WHERE id=${user.planet}`);
  runIsolatedSql(user.project, `UPDATE planet_resource SET current_stock=100000000,last_updated_at=UTC_TIMESTAMP() WHERE planet_id=${user.planet} AND resource_type='metal'`);
  await page.goto('/planetes');
  await capture(page, info, 'nom-long', 'light', 390);
  await page.goto('/planetes?view=table&resource=metal');
  await expect(page.locator('.storage-warning').first()).toBeVisible();
  await capture(page, info, 'capacite-depassee', 'light', 390);
  await page.goto(abandon);
  await capture(page, info, 'abandon-nom-long', 'light', 390);
  await page.goto('/planetes/' + user.planet);
  await expect(page.locator('.dashboard-metrics')).toContainText('2/2');
  const deadline = page.locator('.core-empire-watch').getByRole('link', { name: /Mine à ciel ouvert/ });
  await deadline.focus();
  await expect(deadline).toBeFocused();
  await assertGuideBelowAction(page, deadline);

  const native = await browser.newContext({
    baseURL, javaScriptEnabled: false, storageState: await page.context().storageState(),
    viewport: { width: 390, height: 844 },
  });
  try {
    const fallback = await native.newPage();
    await fallback.goto('/planetes?view=table&resource=metal');
    const select = fallback.getByLabel('Ressource à comparer');
    await select.selectOption('nano');
    await select.focus();
    await fallback.keyboard.press('Tab');
    await expect(fallback.getByRole('button', { name: 'Comparer cette ressource' })).toBeFocused();
    await fallback.keyboard.press('Enter');
    await expect(fallback).toHaveURL(/resource=nano/);
    await expect(fallback.locator('th[data-resource="nano"]')).toBeVisible();
    await expect(fallback.locator('th[data-resource="metal"]')).toBeHidden();
    await expect(fallback.locator('.colony-comparison tbody tr')).toHaveCount(2);
    await expect(fallback.locator('[data-comparison-queue="building"]').filter({ hasText: 'Mine à ciel ouvert' })).toHaveCount(1);
  } finally {
    await native.close();
  }
  expect(errors).toEqual([]);
});

test('L7A A : clavier, toucher, historique et formulaires sans JavaScript', async ({ page, browser }, info) => {
  test.setTimeout(180000);
  page.setDefaultTimeout(15000);
  const user = await enter(page);
  await page.goto('/planetes');
  const card = page.locator('#planet-' + user.planet);
  const trigger = card.locator('.empire-name');
  await trigger.focus();
  const details = page.getByRole('region', { name: /Vaisseaux et ressources de/ });
  await expect(details).toBeVisible();
  await page.keyboard.press('Escape');
  await expect(details).toBeHidden();
  await expect(trigger).toBeFocused();
  const rename = card.locator('.planet-rename summary');
  await rename.focus();
  await page.keyboard.press('Enter');
  await page.keyboard.press('Tab');
  await expect(card.getByLabel('Nouveau nom')).toBeFocused();
  await card.getByLabel('Nouveau nom').fill('Horizon renommé');
  await page.keyboard.press('Tab');
  const save = card.getByRole('button', { name: /Enregistrer le nom de/ });
  await expect(save).toBeFocused();
  expect(await save.evaluate(el => el.matches(':focus-visible') && getComputedStyle(el).outlineStyle !== 'none')).toBe(true);
  await page.keyboard.press('Enter');
  await expect(page).toHaveURL(new RegExp(`/planetes#planet-${user.planet}$`));
  await expect(page.locator('#planet-' + user.planet + ' h2')).toHaveText('Horizon renommé');
  await page.getByRole('link', { name: 'Comparer les colonies', exact: true }).click();
  await page.setViewportSize({ width: 768, height: 900 });
  await expect(page.locator('[data-controller~="colony-comparison"]')).toHaveClass(/comparison-ready/);
  const select = page.getByLabel('Ressource à comparer');
  for (const resource of ['nano', 'hydrogen', 'antimatter', 'electricity']) {
    await select.selectOption(resource);
    await expect(page).toHaveURL(new RegExp('resource=' + resource));
    await expect(page.locator(`.colony-comparison td[data-resource="${resource}"]`)).toBeVisible();
  }
  await page.goBack();
  await expect(select).toHaveValue('antimatter');
  await page.reload();
  await expect(select).toHaveValue('antimatter');
  const link = page.locator('.colony-comparison th[scope="row"] a');
  await link.focus();
  await page.keyboard.press('Enter');
  await expect(page).toHaveURL(new RegExp(`/planetes/${user.planet}$`));
  await page.goBack();
  await expect(page).toHaveURL(/view=table/);
  const touchContext = await browser.newContext({ storageState: await page.context().storageState(), viewport: { width: 390, height: 900 }, hasTouch: true, isMobile: true });
  try {
    const touch = await touchContext.newPage();
    await touch.goto('/planetes');
    await touch.locator('.empire-name').tap();
    await expect(touch.getByRole('region', { name: /Vaisseaux et ressources de/ })).toBeVisible();
    await touch.keyboard.press('Escape');
    await touch.locator('.planet-production summary').tap();
    await expect(touch.locator('.planet-production dl')).toBeVisible();
  } finally {
    await touchContext.close();
  }
  const context = await browser.newContext({ storageState: await page.context().storageState(), javaScriptEnabled: false, viewport: { width: 390, height: 900 }, hasTouch: true });
  try {
    const plain = await context.newPage();
    plain.setDefaultTimeout(15000);
    await plain.goto('/planetes?view=table&resource=metal');
    await plain.getByLabel('Ressource à comparer').selectOption('electricity');
    await plain.getByRole('button', { name: 'Comparer cette ressource', exact: true }).tap();
    await expect(plain).toHaveURL(/view=table&resource=electricity/);
    await expect(plain.locator('.colony-comparison td[data-resource="electricity"]')).toBeVisible();
    const cards = plain.getByRole('navigation', { name: 'Présentation des colonies' }).getByRole('link', { name: 'Mes colonies', exact: true });
    await expect(cards).toHaveAttribute('href', '/planetes');
    await cards.tap();
    await expect(cards).toHaveAttribute('aria-current', 'page');
    await plain.locator('.planet-rename summary').tap();
    await plain.getByLabel('Nouveau nom').fill('Horizon sans script');
    await plain.getByRole('button', { name: /Enregistrer le nom de/ }).tap();
    await expect(plain.locator('.planet-index-title h2')).toHaveText('Horizon sans script');
    await plain.getByRole('link', { name: /Préparer l’abandon de/ }).tap();
    await expect(plain.getByRole('alert')).toContainText('au moins une planète');
    await expect(plain.locator('.abandon-confirmation')).toHaveCount(0);
    // Axe injecte du JavaScript ; ce parcours de repli vérifie les formulaires et libellés natifs.
    await capture(plain, info, 'derniere-colonie-sans-script', 'dark', 390, false);
    await plain.getByRole('link', { name: 'Retour aux planètes', exact: true }).tap();
    await expect(plain).toHaveURL(/\/planetes$/);
  } finally {
    await context.close();
  }
});


test('Comparateur : trois files privées lisibles au clavier et sans JavaScript', async ({ page, browser, baseURL }) => {
  const user = await enter(page);
  const other = await revealVirginPlanet(user.project, user.email);
  runIsolatedSql(user.project, `UPDATE planet SET owner_id=${user.member},name='Boréal comparaison' WHERE id=${other.id}`);
  runIsolatedSql(user.project, `UPDATE planet SET name='Aster comparaison' WHERE id=${user.planet}`);
  runIsolatedSql(user.project, `INSERT INTO queue_item(planet_id,player_universe_id,queue_type,item_slug,tier,item_level,quantity,duration_seconds,started_at,queued_at,dispatch_token) VALUES
    (${user.planet},${user.member},'building','mine_metal','basic',2,1,3600,UTC_TIMESTAMP(),UTC_TIMESTAMP(),'compare-build'),
    (${user.planet},${user.member},'building','mine_metal','basic',3,1,7200,NULL,UTC_TIMESTAMP(),'compare-wait'),
    (${other.id},${user.member},'research','engineering_foundations','basic',2,1,3600,UTC_TIMESTAMP(),UTC_TIMESTAMP(),'compare-research'),
    (${user.planet},${user.member},'research','signature_reduction','basic',2,1,7200,UTC_TIMESTAMP(),UTC_TIMESTAMP(),'compare-research-parallel'),
    (${other.id},${user.member},'shipyard','ground_turret_anti_light','basic',NULL,7,600,NULL,UTC_TIMESTAMP(),'compare-defense')`);
  const plain = await browser.newContext({
    baseURL,
    storageState: await page.context().storageState(),
    javaScriptEnabled: false,
    viewport: { width: 390, height: 844 },
  });
  try {
    const mobile = await plain.newPage();
    await mobile.goto('/planetes?view=table&resource=hydrogen');
    const region = mobile.getByRole('region', { name: 'Comparaison des colonies, défilement horizontal' });
    const table = region.getByRole('table');
    for (const name of ['Bâtiments', 'Recherche', 'Chantier spatial']) {
      await expect(table.getByRole('columnheader', { name, exact: true })).toBeVisible();
    }
    const aster = table.getByRole('row').filter({ hasText: 'Aster comparaison' });
    const boreal = table.getByRole('row').filter({ hasText: 'Boréal comparaison' });
    await expect(aster.locator('[data-comparison-queue="building"]')).toContainText('Niveau 2');
    await expect(aster.locator('[data-comparison-queue="building"]')).toContainText('+1 en attente');
    await expect(aster.locator('[data-comparison-queue="building"] time')).toHaveAttribute('datetime', /T/);
    await expect(aster.locator('[data-comparison-queue="research"]')).toContainText('Commune à cet empire');
    await expect(boreal.locator('[data-comparison-queue="research"]')).toContainText('départ de cette colonie');
    await expect(table.locator('td[data-comparison-queue="research"] time')).toHaveCount(1);
    await expect(boreal.locator('[data-comparison-queue="shipyard"]')).toContainText('× 7');
    await expect(boreal.locator('[data-comparison-queue="shipyard"] time')).toHaveCount(0);
    expect(await mobile.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(390);
    await region.focus();
    await expect(region).toBeFocused();
    await mobile.keyboard.press('ArrowRight');
    await expect.poll(() => region.evaluate(element => element.scrollLeft)).toBeGreaterThan(0);
    await mobile.getByLabel('Ressource à comparer').selectOption('electricity');
    await mobile.getByRole('button', { name: 'Comparer cette ressource', exact: true }).click();
    await expect(mobile).toHaveURL(/view=table&resource=electricity$/);
    await expect(mobile.locator('th[data-resource="electricity"]')).toBeVisible();
    await expect(mobile.locator('th[data-resource="hydrogen"]')).toBeHidden();
  } finally {
    await plain.close();
  }
  await page.goto('/planetes?view=table');
  await expect(page.locator('td[data-comparison-queue="research"] time')).toHaveCount(1);
  await expect(page.locator('th[data-resource]')).toHaveCount(5);
  runIsolatedSql(user.project, `INSERT INTO vacation_period(membership_id,started_at,scheduled_end_at) VALUES
    (${user.member},DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 MINUTE),DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 HOUR))`);
  await page.reload();
  const pausedResearch = page.locator('td[data-comparison-queue="research"]').filter({ hasText: 'départ de cette colonie' });
  await expect(pausedResearch).toContainText('Suspendue');
  await expect(pausedResearch).toContainText('+1 déjà lancée');
  await expect(pausedResearch.locator('time')).toHaveCount(0);
});
