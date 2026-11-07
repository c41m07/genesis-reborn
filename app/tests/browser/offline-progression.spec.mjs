import { test, expect } from './support/isolated-test.mjs';
import { execFileSync } from 'node:child_process';
import { prepareOwnedColony, accelerateQueuedItem, queryIsolatedSql } from './support/game-fixtures.mjs';

test('Construction puis recherche sans client connecté, après redémarrage des traitements', async ({ browser }, testInfo) => {
  test.setTimeout(process.env.BROWSER_PERSISTENCE === '1' ? 300000 : 180000);
  const project = process.env.BROWSER_ROUTER;
  expect(project).toMatch(/^genesis-browser-test-[0-9]+-[0-9]+$/);
  const id = crypto.randomUUID().slice(0, 8);
  const email = `offline-${id}@example.test`;
  const password = 'Browser-test-2026!';
  let context = await browser.newContext();
  let page = await context.newPage();
  await page.goto('/inscription');
  await page.getByLabel('Adresse email').fill(email);
  await page.getByLabel('Nom de commandant').fill(`Offline-${id}`);
  await page.getByLabel('Mot de passe (12 caractères minimum)', { exact: true }).fill(password);
  await page.getByLabel('Confirmer le mot de passe').fill(password);
  await page.getByRole('button', { name: 'Créer mon compte' }).click();
  await expect(page).toHaveURL(/connexion/);
  const login = async () => {
    await page.goto('/connexion');
    await page.getByLabel('Adresse email').fill(email);
    await page.getByLabel('Mot de passe', { exact: true }).fill(password);
    await page.getByRole('button', { name: /Connexion/ }).click();
    await expect(page).not.toHaveURL(/connexion/);
  };
  await login();
  await page.locator('article.universe-card').filter({ has: page.getByRole('heading', { name: /Turbo/ }) }).getByRole('button').click();
  await expect(page).toHaveURL(/planetes\/\d+$/);
  const planetUrl = page.url();
  prepareOwnedColony(project, email, { showLockedItems: true });
  const compose = ['compose', '-p', project, '--env-file', '.env.local', '-f', 'compose.yaml', '-f', 'compose.browser-test.yaml'];
  const persistent = process.env.BROWSER_PERSISTENCE === '1';
  if (persistent) compose.push('-f', 'compose.browser-persistence.yaml');
  const docker = args => execFileSync('docker', [...compose, ...args], { encoding: 'utf8', stdio: 'pipe' });
  const membership = `SELECT pu.id FROM player_universe pu JOIN player p ON p.id=pu.player_id WHERE p.email='${email}'`;
  const proofs = [];
  for (const [type, slug, link, button, table, owner] of [
    ['building', 'lab', 'Bâtiments', /Améliorer .* niveau 1/, 'planet_building', `planet_id=${Number(new URL(planetUrl).pathname.split('/').pop())}`],
    ['research', 'engineering_foundations', 'Recherche', /Rechercher .* niveau 1/, 'player_technology', `player_universe_id IN (${membership})`],
  ]) {
    // L'arrêt précède la commande : sa présence dans l'outbox est vérifiable.
    docker(['stop', '--timeout', '3', 'worker', 'relay', 'notifier']);
    await page.getByRole('link', { name: link, exact: true }).click();
    await page.locator(`#item-${slug}-basic`).getByRole('button', { name: button }).click();
    await expect.poll(() => Number(queryIsolatedSql(project, `SELECT COUNT(*) FROM queue_item WHERE player_universe_id IN (${membership}) AND item_slug='${slug}'`))).toBe(1);
    const previousNotifications = Number(queryIsolatedSql(project, `SELECT COUNT(*) FROM notification WHERE recipient_id IN (${membership})`));
    await context.close();
    expect(browser.contexts()).toHaveLength(0);
    accelerateQueuedItem(project, email, type, slug);
    if (type === 'research') {
      // Le relais publie sans consommateur ; son redémarrage ne doit pas perdre l'événement.
      docker(['up', '-d', '--wait', '--wait-timeout', '45', 'relay', 'notifier']);
      await expect.poll(() => Number(queryIsolatedSql(project, `SELECT COUNT(*) FROM outbox_message o JOIN queue_item q ON o.kind='queue' AND o.target_id=q.id WHERE q.player_universe_id IN (${membership})`)), { timeout: 30000 }).toBe(0);
      expect(queryIsolatedSql(project, `SELECT COUNT(*) FROM queue_item WHERE player_universe_id IN (${membership}) AND item_slug='${slug}'`)).toBe('1');
      docker(['restart', '--timeout', '3', 'relay', 'notifier']);
    }
    if (persistent) {
      // Uniquement les volumes du projet généré ; mails et alertes restent neutralisés.
      docker(['stop', '--timeout', '3', 'app', 'worker', 'relay', 'notifier', 'database', 'rabbitmq', 'mercure']);
      docker(['up', '-d', '--wait', '--wait-timeout', '90', 'app', 'database', 'rabbitmq', 'mercure']);
      expect(queryIsolatedSql(project, `SELECT COUNT(*) FROM queue_item WHERE player_universe_id IN (${membership}) AND item_slug='${slug}'`)).toBe('1');
    }
    docker(['up', '-d', '--wait', '--wait-timeout', '45', 'worker', 'relay', 'notifier']);
    // Aucun chargement HTTP ne peut achever la file pendant ces assertions SQL.
    await expect.poll(() => queryIsolatedSql(project, `SELECT level FROM ${table} WHERE ${owner} AND slug='${slug}'`), { timeout: 45000 }).toBe('1');
    await expect.poll(() => Number(queryIsolatedSql(project, `SELECT COUNT(*) FROM queue_item WHERE player_universe_id IN (${membership})`)), { timeout: 15000 }).toBe(0);
    await expect.poll(() => Number(queryIsolatedSql(project, `SELECT COUNT(*) FROM notification WHERE recipient_id IN (${membership})`)), { timeout: 30000 }).toBe(previousNotifications + 1);
    proofs.push({ type, level: 1, connectedContexts: browser.contexts().length, notificationPersisted: true, persistentInfrastructureRestarted: persistent, processesRestarted: ['worker', 'relay', 'notifier'], recoveryFrom: type === 'research' ? 'message déjà publié' : 'outbox persistée' });
    context = await browser.newContext();
    page = await context.newPage();
    await login();
    await page.goto(planetUrl);
    await page.getByRole('link', { name: link, exact: true }).click();
    await expect(page.locator(`#item-${slug}-basic .badge`)).toContainText('NIVEAU 1');
  }
  await context.close();
  await testInfo.attach('preuves-hors-connexion.json', { body: JSON.stringify(proofs, null, 2), contentType: 'application/json' });
});
