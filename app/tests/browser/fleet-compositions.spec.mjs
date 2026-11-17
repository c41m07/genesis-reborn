import { test, expect } from './support/isolated-test.mjs';
import { grantSecondColony, queryIsolatedSql, revealVirginPlanet, runIsolatedSql } from './support/game-fixtures.mjs';
import AxeBuilder from '@axe-core/playwright';

async function enter(page) {
  const suffix = crypto.randomUUID().slice(0, 8);
  const email = `composition-${suffix}@example.test`;
  const password = crypto.randomUUID() + 'aA1!';
  await page.goto('/inscription');
  await page.getByLabel('Adresse email').fill(email);
  await page.getByLabel('Nom de commandant').fill('Escorte-' + suffix);
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

async function checkView(page, info, key, theme, width) {
  expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(width);
  for (const selector of ['.composition-card', '.composition-table', '.composition-origin-panel']) {
    for (const element of await page.locator(selector).all()) {
      expect(await element.evaluate(el => el.scrollWidth <= el.clientWidth + 1), selector).toBe(true);
    }
  }
  if (width === 390) {
    expect((await new AxeBuilder({ page }).include('.fleet-compositions').analyze()).violations).toEqual([]);
  }
  if (width !== 320) {
    await page.evaluate(() => { document.activeElement?.blur(); window.scrollTo({ top: 0, behavior: 'instant' }); });
    await page.screenshot({ path: info.outputPath(`jeu-L2C-A-${key}-${theme}-${width}.png`), fullPage: true, animations: 'disabled' });
    if (width === 390) await page.screenshot({ path: info.outputPath(`jeu-L2C-A-${key}-${theme}-390-ecran.png`), animations: 'disabled' });
  }
}

test('L2C A : quatre vues adaptatives, rangs réunis, saisie conservée et liens natifs', async ({ page, browser, baseURL }, info) => {
  test.setTimeout(300000);
  const errors = [];
  page.on('pageerror', error => errors.push(error.message));
  const owner = await enter(page);
  const { home, member, project } = owner;
  const title = 'Escorte des frontières lointaines';
  await page.goto('/flottes/compositions');
  await expect(page.getByText('Aucune composition enregistrée.', { exact: false })).toBeVisible();
  await page.getByRole('link', { name: 'Créer une composition', exact: true }).click();
  await page.getByLabel('Nom de la composition', { exact: true }).fill(title);
  for (const [slug, tier, count] of [['fighter', 'basic', 80], ['transport', 'intermediate', 8], ['probe', 'superior', 4]]) {
    await page.locator(`#composition-${slug}-${tier}`).fill(String(count));
  }
  await page.getByRole('button', { name: 'Enregistrer la composition' }).click();
  await expect(page.locator('.flash.success')).toBeVisible();
  const composition = Number(queryIsolatedSql(project, `SELECT MAX(id) FROM fleet_composition WHERE owner_id=${member}`));
  for (const name of ['Transport régional', 'X'.repeat(80)]) {
    runIsolatedSql(project, `INSERT INTO fleet_composition(owner_id,name,ships,request_key,version) VALUES (${member},'${name}','{"transport":{"basic":8}}','${crypto.randomUUID().replaceAll('-', '')}',1)`);
  }
  grantSecondColony(project, owner.email);
  const second = revealVirginPlanet(project, owner.email);
  runIsolatedSql(project, `UPDATE planet SET owner_id=${member},name='Avant-poste des Constellations Oubliées',acquired_at=UTC_TIMESTAMP() WHERE id=${second.id}`);
  runIsolatedSql(project, `INSERT IGNORE INTO planet_resource (planet_id,resource_type,current_stock,production_rate,last_updated_at) SELECT ${second.id},resource_type,0,0,UTC_TIMESTAMP() FROM planet_resource WHERE planet_id=${home}`);
  runIsolatedSql(project, `INSERT INTO fleet(player_universe_id,home_planet_id,status,mission,speed_ratio,cargo,reserved_fuel,orbit_fuel,orbit_duration_seconds,travel_seconds,dispatch_token) VALUES (${member},${home},'idle','idle',1,'{}',0,0,0,0,'')`);
  const fleet = Number(queryIsolatedSql(project, `SELECT MAX(id) FROM fleet WHERE player_universe_id=${member}`));
  runIsolatedSql(project, `INSERT INTO fleet_ship(fleet_id,slug,tier,quantity) VALUES (${fleet},'fighter','basic',60),(${fleet},'transport','intermediate',8)`);

  const views = [
    ['list', '/flottes/compositions', 'Mes compositions'],
    ['new', '/flottes/compositions/creer', 'Créer une composition'],
    ['edit', `/flottes/compositions/${composition}/modifier`, 'Modifier la composition'],
    ['load', `/flottes/compositions/${composition}/charger?origin=${home}`, 'Charger ' + title],
  ];
  for (const theme of ['dark', 'light']) {
    runIsolatedSql(project, `UPDATE player SET preferences=JSON_SET(CASE WHEN JSON_TYPE(preferences)='OBJECT' THEN preferences ELSE JSON_OBJECT() END,'$.theme','${theme}') WHERE email='${owner.email}'`);
    for (const [key, url, heading] of views) {
      for (const width of [1440, 768, 390, 320]) {
        await page.setViewportSize({ width, height: 900 });
        await page.goto(url);
        await expect(page.getByRole('heading', { name: heading, exact: true })).toBeVisible();
        await expect(page.locator('body')).toHaveAttribute('data-theme', theme);
        await expect(page.locator('.space-navigation a[aria-current="page"]')).toHaveText('Mes compositions');
        if (key === 'list') {
          await expect(page.locator('.composition-card')).toHaveCount(3);
          const card = page.locator(`[data-composition="${composition}"]`);
          await expect(card).toContainText('Basique : 80');
          await expect(card).toContainText('Intermédiaire : 8');
          await expect(card).toContainText('Supérieur : 4');
        }
        if (key === 'new' || key === 'edit') {
          const rows = page.locator('.composition-editor tbody tr');
          expect(await page.locator('.composition-editor input').count()).toBe((await rows.count()) * 3);
          await expect(page.locator('#composition-fighter-basic')).toHaveValue(key === 'new' ? '0' : '80');
          if (width <= 600) {
            const cells = rows.filter({ has: page.locator('#composition-fighter-basic') });
            const fighter = cells.locator('input');
            const boxes = await Promise.all((await fighter.all()).map(input => input.boundingBox()));
            expect(Math.max(...boxes.map(box => box.y)) - Math.min(...boxes.map(box => box.y))).toBeLessThan(2);
            for (const label of await cells.locator('.composition-quantity-label').all()) await expect(label).toBeVisible();
          }
        }
        if (key === 'load') {
          await expect(page.locator('#composition-origin')).toHaveValue(String(home));
          await expect(page.getByRole('status').filter({ hasText: 'Des vaisseaux manquent' })).toBeVisible();
          await expect(page.locator('.composition-load-action')).toHaveAttribute('href', /adjust=1/);
        }
        await checkView(page, info, key, theme, width);
      }
    }
  }

  // Le menu commun conserve l’accès au clavier après changement de format.
  await page.setViewportSize({ width: 390, height: 900 });
  await page.goto(`/flottes/compositions/${composition}/modifier`);
  await page.locator('.space-navigation-menu > summary').click();
  const listLink = page.getByRole('navigation', { name: 'Navigation des flottes' }).getByRole('link', { name: 'Mes compositions', exact: true });
  await page.keyboard.press('Tab');
  await listLink.focus();
  expect(await listLink.evaluate(el => el.matches(':focus-visible') && getComputedStyle(el).outlineStyle !== 'none')).toBe(true);
  await page.keyboard.press('Enter');
  await expect(page).toHaveURL(/\/flottes\/compositions$/);
  await page.getByRole('link', { name: 'Créer une composition', exact: true }).click();
  const requestKey = await page.locator('[name="request_key"]').inputValue();
  await page.getByLabel('Nom de la composition', { exact: true }).fill('Brouillon conservé');
  const rejected = page.waitForResponse(response => response.request().method() === 'POST' && response.url().endsWith('/flottes/compositions/creer'));
  await page.getByRole('button', { name: 'Enregistrer la composition' }).click();
  expect((await rejected).status()).toBe(422);
  await expect(page.getByRole('alert')).toContainText('La composition doit contenir des vaisseaux');
  await expect(page.getByLabel('Nom de la composition', { exact: true })).toHaveValue('Brouillon conservé');
  await expect(page.locator('[name="request_key"]')).toHaveValue(requestKey);
  await checkView(page, info, 'erreur', 'light', 390);

  await page.goto(`/flottes/compositions/${composition}/charger?origin=${home}`);
  await page.getByLabel('Colonie de départ', { exact: true }).selectOption(String(second.id));
  await page.getByRole('button', { name: 'Vérifier cette colonie' }).click();
  await expect(page.locator('.composition-availability')).toContainText('Aucun vaisseau de cette composition');
  await expect(page.locator('.composition-load-action')).toHaveCount(0);
  await expect(page.getByRole('link', { name: 'Construire au chantier spatial' })).toHaveAttribute('href', `/planetes/${second.id}?section=ships`);
  await checkView(page, info, 'sans-vaisseau', 'light', 390);

  await page.goto(`/flottes/compositions/${composition}/charger?origin=${home}`);
  const stale = await page.locator('.composition-load-action').getAttribute('href');
  runIsolatedSql(project, `UPDATE fleet_ship SET quantity=80 WHERE fleet_id=${fleet} AND slug='fighter' AND tier='basic'`);
  runIsolatedSql(project, `INSERT INTO fleet_ship(fleet_id,slug,tier,quantity) VALUES (${fleet},'probe','superior',4)`);
  await page.goto(stale);
  await expect(page).toHaveURL(new RegExp(`/flottes/compositions/${composition}/charger\\?origin=${home}$`));
  await expect(page.locator('.flash.error')).toContainText('Les disponibilités ou la composition ont changé');
  await expect(page.getByRole('link', { name: 'Charger exactement cette composition' })).toHaveAttribute('href', /adjust=0/);
  await page.getByRole('link', { name: 'Charger exactement cette composition' }).click();
  await expect(page.locator('[name="require_exact_composition"]')).toHaveValue('1');
  expect(Number(queryIsolatedSql(project, `SELECT COUNT(*) FROM fleet WHERE player_universe_id=${member} AND status='traveling'`))).toBe(0);

  const context = await browser.newContext({ baseURL, javaScriptEnabled: false, viewport: { width: 390, height: 900 }, storageState: await page.context().storageState() });
  try {
    const plain = await context.newPage();
    await plain.goto('/flottes/compositions/creer');
    await plain.getByLabel('Nom de la composition', { exact: true }).fill('Modèle sans JavaScript');
    await plain.locator('#composition-transport-basic').fill('1');
    await plain.getByRole('button', { name: 'Enregistrer la composition' }).click();
    const card = plain.locator('.composition-card').filter({ has: plain.getByRole('heading', { name: 'Modèle sans JavaScript', exact: true }) });
    await card.locator('summary').click();
    await expect(card.getByRole('button', { name: 'Supprimer cette composition' })).toBeVisible();
    await card.getByRole('button', { name: 'Supprimer cette composition' }).click();
    await expect(plain.getByRole('heading', { name: 'Modèle sans JavaScript', exact: true })).toHaveCount(0);
    await plain.reload();
    await expect(plain.locator('.composition-card')).toHaveCount(3);
  } finally {
    await context.close();
  }
  expect(Number(queryIsolatedSql(project, `SELECT SUM(quantity) FROM fleet_ship WHERE fleet_id=${fleet}`))).toBe(92);
  expect(errors).toEqual([]);
});
