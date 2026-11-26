import { execFileSync } from 'node:child_process';
import { test, expect } from './support/isolated-test.mjs';
import { queryIsolatedSql, runIsolatedSql } from './support/game-fixtures.mjs';

test('Observatoire : dépenses remboursées, carburant et isolation des univers', async ({ page }) => {
  test.setTimeout(90000);
  const project = process.env.BROWSER_ROUTER;
  const suffix = crypto.randomUUID().slice(0, 8);
  const name = 'Expense-' + suffix;
  const email = name.toLowerCase() + '@example.test';
  const password = crypto.randomUUID() + 'aA1!';
  await page.goto('/inscription');
  await page.getByLabel('Adresse email').fill(email);
  await page.getByLabel('Nom de commandant').fill(name);
  await page.getByLabel('Mot de passe (12 caractères minimum)', { exact: true }).fill(password);
  await page.getByLabel('Confirmer le mot de passe').fill(password);
  await page.getByRole('button', { name: 'Créer mon compte' }).click();
  await expect(page).toHaveURL(/connexion/);
  execFileSync('docker', ['compose', '-p', project, '--env-file', '.env.local', 'exec', '-T', 'app', 'php', 'bin/console', 'app:admin:grant', email], { stdio: 'pipe' });
  await page.getByLabel('Adresse email').fill(email);
  await page.getByLabel('Mot de passe', { exact: true }).fill(password);
  await page.getByRole('button', { name: /Connexion/ }).click();
  await page.locator('article.universe-card').first().getByRole('button').click();
  await expect(page).toHaveURL(/planetes\/\d+$/);

  const [member, world, planet] = queryIsolatedSql(project, `
    SELECT u.id,u.universe_id,c.id FROM player_universe u
    JOIN player p ON p.id=u.player_id JOIN planet c ON c.owner_id=u.id WHERE p.email='${email}'
  `).split('\t').map(Number);
  const foreign = Number(queryIsolatedSql(project, `SELECT id FROM universe WHERE id != ${world} LIMIT 1`));
  expect(Math.min(member, world, planet, foreign)).toBeGreaterThan(0);

  // Les tests de domaine démontrent l’écriture. Ces reçus synthétiques isolent la lecture et le rendu.
  runIsolatedSql(project, 'UPDATE expense_collection SET available_since = UTC_TIMESTAMP() - INTERVAL 10 DAY');
  runIsolatedSql(project, `INSERT INTO queue_expense (queue_item_id,player_universe_id,planet_id,category,item_slug,tier,item_level,quantity,paid,paid_at,refunded,refunded_at)
    VALUES (987654,${member},${planet},'buildings','mine_metal','basic',1,1,JSON_OBJECT('metal',100),UTC_TIMESTAMP()-INTERVAL 1 DAY,JSON_OBJECT('metal',25),UTC_TIMESTAMP()-INTERVAL 1 HOUR)`);
  runIsolatedSql(project, `INSERT INTO fleet_fuel_expense (transition_id,player_universe_id,fleet_id,phase,hydrogen,burned_at)
    VALUES (SHA2('fixture-expenses',256),${member},999,'return',80,UTC_TIMESTAMP()-INTERVAL 1 HOUR)`);

  const route = '/admin/universes/' + world + '/observatoire?tab=economy&days=7';
  await page.goto(route);
  const panel = page.locator('#observatory-expenses');
  await panel.getByLabel('Rechercher un pseudo').fill(name);
  await panel.getByRole('button', { name: 'Appliquer' }).click();
  await panel.getByLabel('Commandant').selectOption(String(member));
  await panel.getByRole('button', { name: 'Appliquer' }).click();
  await expect(panel.getByRole('row', { name: /^Métal/ }).first().getByRole('cell')).toHaveText(['100,00', '25,00', '75,00']);
  await expect(panel.getByRole('row', { name: /^Hydrogène/ }).first().getByRole('cell')).toHaveText(['80,00', '0,00', '80,00']);
  await panel.getByText('Voir les montants par catégorie et ressource', { exact: true }).click();
  await expect(panel.getByRole('region', { name: 'Dépenses : Carburant consommé' })).toBeVisible();
  await page.setViewportSize({ width: 390, height: 844 });
  expect(await panel.evaluate(node => node.scrollWidth)).toBeLessThanOrEqual(await panel.evaluate(node => node.clientWidth + 1));

  await page.goto('/admin/universes/' + foreign + '/observatoire?tab=economy&expense_member=' + member);
  await expect(panel).toContainText('Le commandant sélectionné n’est pas disponible dans cet univers.');
  await expect(panel.getByRole('heading', { name: 'Détail par ressource' })).toHaveCount(0);
});
