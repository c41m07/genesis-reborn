import { test, expect } from './support/isolated-test.mjs';
import { runIsolatedSql } from './support/game-fixtures.mjs';

async function enterFreshColony(page) {
  const suffix = crypto.randomUUID().slice(0, 8);
  const email = 'max-lab-' + suffix + '@example.test';
  const password = crypto.randomUUID() + crypto.randomUUID().toUpperCase();
  await page.goto('/inscription');
  await page.getByLabel('Adresse email').fill(email);
  await page.getByLabel('Nom de commandant').fill('Maximum-' + suffix);
  await page.getByLabel('Mot de passe (12 caractères minimum)', { exact: true }).fill(password);
  await page.getByLabel('Confirmer le mot de passe').fill(password);
  await page.getByRole('button', { name: 'Créer mon compte' }).click();
  await expect(page).toHaveURL(/connexion/);
  await page.getByLabel('Adresse email').fill(email);
  await page.getByLabel('Mot de passe', { exact: true }).fill(password);
  await page.getByRole('button', { name: /Connexion/ }).click();
  await page.locator('article.universe-card').first().getByRole('button').click();
  await expect(page).toHaveURL(/planetes[/][0-9]+$/);
  const colony = new URL(page.url()).pathname;
  const planet = Number(colony.split('/').pop());
  const sql = query => runIsolatedSql(process.env.BROWSER_ROUTER, query);
  return { colony, planet, sql };
}

test('La fiche du laboratoire indique le bonus cumulé acquis quand un rang est au maximum', async ({ page }) => {
  const { colony, planet, sql } = await enterFreshColony(page);
  const setLevel = (rank, level) => sql("INSERT INTO planet_building(planet_id,slug,tier,level) VALUES (" + planet + ",'lab','" + rank + "'," + level + ") ON DUPLICATE KEY UPDATE level=VALUES(level)");
  const rank = page.locator('#item-lab-basic');
  const opener = rank.getByRole('button', { name: 'Informations sur Laboratoire colonial', exact: true });
  const panel = rank.getByRole('dialog', { name: 'Laboratoire colonial', exact: true });
  const open = async () => {
    const url = colony + '?section=buildings&focusItem=1#item-lab-basic';
    if (page.url().endsWith(url)) await page.reload();
    else await page.goto(url);
    await opener.focus();
    await page.keyboard.press('Enter');
    await expect(panel).toBeVisible();
  };
  const close = async () => {
    await page.keyboard.press('Escape');
    await expect(panel).not.toBeVisible();
    await expect(opener).toBeFocused();
  };
  const bonus = panel.getByRole('row', { name: /^Réduction du temps de recherche/ });

  setLevel('basic', 25);
  await page.setViewportSize({ width: 1366, height: 768 });
  await open();
  await expect(panel).toContainText('Niveau maximum atteint');
  await expect(panel.getByRole('table', { name: 'Effet cumulé des rangs' }).getByRole('columnheader')).toHaveText(['Effet', 'Actuellement']);
  await expect(bonus.getByRole('cell')).toHaveText('26,87 %');
  await expect(panel.getByRole('heading', { name: /Gain du niveau/ })).toHaveCount(0);
  await expect(panel.getByRole('region', { name: 'Coût et durée' })).toHaveCount(0);
  await close();

  await page.setViewportSize({ width: 390, height: 844 });
  setLevel('intermediate', 25);
  setLevel('superior', 25);
  await open();
  await expect(bonus.getByRole('cell')).toHaveText('70 %');
  const bounds = await panel.boundingBox();
  expect(bounds.x).toBeGreaterThanOrEqual(0);
  expect(bounds.x + bounds.width).toBeLessThanOrEqual(390);
  await close();

  setLevel('intermediate', 0);
  setLevel('superior', 0);
  setLevel('basic', 24);
  await open();
  await expect(panel.getByRole('heading', { name: 'Gain du niveau 25' })).toBeVisible();
  const comparison = panel.getByRole('row').filter({ hasText: 'Réduction du temps de recherche' });
  await expect(comparison.getByRole('cell')).toHaveText(['25,99 %', '26,87 %']);
  await close();

  sql("INSERT INTO queue_item (planet_id,player_universe_id,queue_type,item_slug,tier,item_level,quantity,duration_seconds,started_at,queued_at,dispatch_token) SELECT id,owner_id,'building','lab','basic',25,1,86400,UTC_TIMESTAMP(),UTC_TIMESTAMP(),UUID() FROM planet WHERE id="+planet);
  await open();
  await expect(panel).toContainText('Maximum en file');
  await expect(panel).not.toContainText('Niveau maximum atteint');
  await expect(bonus.getByRole('cell')).toHaveText('25,99 %');
  await expect(panel.getByRole('heading', { name: /Gain du niveau/ })).toHaveCount(0);
  await close();
});

test('À la limite, le tableau des docks et des recherches conserve tous les effets acquis et leurs unités', async ({ page }) => {
  const { colony, planet, sql } = await enterFreshColony(page);
  sql("INSERT INTO planet_building(planet_id,slug,tier,level) VALUES ("+planet+",'shipyard','basic',13),("+planet+",'shipyard','intermediate',25) ON DUPLICATE KEY UPDATE level=VALUES(level)");
  sql("INSERT INTO player_technology(player_universe_id,slug,tier,level) SELECT owner_id,'vector_propulsion','basic',24 FROM planet WHERE id="+planet+" UNION ALL SELECT owner_id,'colonial_expansion','basic',5 FROM planet WHERE id="+planet);
  sql("INSERT INTO queue_item (planet_id,player_universe_id,queue_type,item_slug,tier,item_level,quantity,duration_seconds,started_at,queued_at,dispatch_token) SELECT id,owner_id,'research','vector_propulsion','basic',25,1,86400,UTC_TIMESTAMP(),UTC_TIMESTAMP(),UUID() FROM planet WHERE id="+planet);
  const read = async (section, slug, label, tier = 'basic') => {
    const hash = 'item-'+slug+'-'+tier;
    await page.goto(colony+'?section='+section+'&showLockedItems=1&focusItem=1#'+hash);
    const opener = page.locator('#'+hash).getByRole('button', { name: 'Informations sur '+label, exact: true });
    const dialog = page.getByRole('dialog', { name: label, exact: true });
    await opener.focus();
    await page.keyboard.press('Enter');
    await expect(dialog).toBeVisible();
    const table = dialog.getByRole('table', { name: 'Effet cumulé des rangs' });
    await expect(table.getByRole('columnheader')).toHaveText(['Effet', 'Actuellement']);
    return { table, dialog, close: async () => {
      await page.keyboard.press('Escape');
      await expect(dialog).not.toBeVisible();
      await expect(opener).toBeFocused();
    } };
  };
  await page.setViewportSize({ width: 1366, height: 768 });
  const docks = await read('buildings', 'shipyard', 'Docks orbitaux modulaires', 'intermediate');
  await expect(docks.table.getByRole('row', { name: /^Réduction du temps de construction des vaisseaux/ }).getByRole('cell')).toHaveText(/^[1-9][0-9](,\d+)? %$/);
  await docks.close();

  await page.setViewportSize({ width: 390, height: 844 });
  const propulsion = await read('technologies', 'vector_propulsion', 'Propulseurs vectoriels');
  await expect(propulsion.dialog).toContainText('Maximum en file');
  await expect(propulsion.table.getByRole('rowheader')).toHaveText([/Vitesse dans le système/, /Vitesse entre systèmes/]);
  await expect(propulsion.table.getByRole('cell')).toHaveText(['9,28 %', '9,28 %']);
  const bounds = await propulsion.dialog.boundingBox();
  expect(bounds.x).toBeGreaterThanOrEqual(0);
  expect(bounds.x+bounds.width).toBeLessThanOrEqual(390);
  await propulsion.close();

  const colonisation = await read('technologies', 'colonial_expansion', 'Protocoles de colonisation');
  await expect(colonisation.table.getByRole('row', { name: /^Limite totale de planètes/ }).getByRole('cell')).toHaveText('6 planètes');
  await colonisation.close();
});
