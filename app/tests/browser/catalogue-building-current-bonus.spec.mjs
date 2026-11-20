import { test, expect } from './support/isolated-test.mjs';
import { runIsolatedSql } from './support/game-fixtures.mjs';

test('La fiche du laboratoire indique le bonus cumulé acquis quand un rang est au maximum', async ({ page }) => {
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
  const bonus = panel.locator('dl > div').filter({ hasText: 'Réduction du temps de recherche' });

  setLevel('basic', 25);
  await page.setViewportSize({ width: 1366, height: 768 });
  await open();
  await expect(panel).toContainText('Niveau maximum atteint');
  await expect(bonus.locator('dd')).toHaveText('26,87 %');
  await expect(panel.getByRole('heading', { name: /Gain du niveau/ })).toHaveCount(0);
  await expect(panel.getByRole('region', { name: 'Coût et durée' })).toHaveCount(0);
  await close();

  await page.setViewportSize({ width: 390, height: 844 });
  setLevel('intermediate', 25);
  setLevel('superior', 25);
  await open();
  await expect(bonus.locator('dd')).toHaveText('70 %');
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
  await expect(bonus.locator('dd')).toHaveText('25,99 %');
  await expect(panel.getByRole('heading', { name: /Gain du niveau/ })).toHaveCount(0);
  await close();
});
