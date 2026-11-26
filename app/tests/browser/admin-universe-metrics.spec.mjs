import { test, expect } from './support/isolated-test.mjs';
import { execFileSync } from 'node:child_process';
import AxeBuilder from '@axe-core/playwright';
import { prepareOwnedColony, queryIsolatedSql, runIsolatedSql } from './support/game-fixtures.mjs';

test('Mesures par univers : accès admin, lecture seule, données manquantes et mobile', async ({ page, browser }, testInfo) => {
  test.setTimeout(150000);
  const project = process.env.BROWSER_ROUTER;
  expect(project).toMatch(/^genesis-browser-test-[0-9]+-[0-9]+$/);
  const id = crypto.randomUUID().slice(0, 8);
  const email = `metrics-${id}@example.test`;
  const password = 'Browser-test-2026!';
  await page.goto('/inscription');
  await page.getByLabel('Adresse email').fill(email);
  await page.getByLabel('Nom de commandant').fill(`Metrics-${id}`);
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
  await page.locator('article.universe-card').first().getByRole('button').click();
  await expect(page).toHaveURL(/planetes\/\d+$/);
  prepareOwnedColony(project, email);
  const universe = Number(queryIsolatedSql(project, `SELECT pu.universe_id FROM player_universe pu JOIN player p ON p.id=pu.player_id WHERE p.email='${email}'`));
  const url = `/admin/universes/${universe}/mesures`;
  await page.goto(url);
  await expect(page).toHaveURL(/acces-refuse/);
  await page.goto(url.replace('/mesures', '/observatoire'));
  await expect(page).toHaveURL(/acces-refuse/);
  const anonymous = await browser.newContext();
  const anonymousPage = await anonymous.newPage();
  await anonymousPage.goto(url);
  await expect(anonymousPage).toHaveURL(/connexion/);
  await anonymous.close();
  execFileSync('docker', ['compose', '-p', project, '--env-file', '.env.local', 'exec', '-T', 'app', 'php', 'bin/console', 'app:admin:grant', email], { stdio: 'pipe' });
  await page.context().clearCookies();
  await login();
  await page.goto('/admin');
  await page.getByRole('navigation', { name: 'Console d’administration' }).getByRole('link', { name: 'Univers et activité', exact: true }).click();
  await page.getByRole('navigation', { name: 'Console d’administration' }).getByRole('link', { name: 'Univers', exact: true }).click();
  const stocks = () => queryIsolatedSql(project, `SELECT r.resource_type, r.current_stock, r.last_updated_at FROM planet_resource r JOIN planet ON planet.id=r.planet_id JOIN player_universe pu ON pu.id=planet.owner_id JOIN player p ON p.id=pu.player_id WHERE p.email='${email}' ORDER BY r.id`);
  const before = stocks();
  await page.locator(`a[href="${url}"]`).click();
  await expect(page.getByRole('heading', { name: 'Mesures de l’univers', exact: true })).toBeVisible();
  await expect(page.locator('h1')).toHaveCount(1);
  await expect(page.locator('#metrics-progression')).toContainText('Temps pour atteindre un jalon : non disponible.');
  await expect(page.locator('#metrics-resources')).toContainText('1 colonie observée');
  await expect(page.locator('#metrics-progression')).toContainText('0 sur 1 (0,0 %)');
  await expect(page.locator('#metrics-pirates')).toContainText('Aucune reconnaissance partie');
  await expect(page.locator('#metrics-recovery')).toContainText('Durée pour reconstituer les forces après une défaite : non disponible.');
  await page.reload();
  await page.keyboard.press('Tab');
  await expect(page.getByRole('link', { name: 'Aller au contenu', exact: true })).toBeFocused();
  await page.keyboard.press('Enter');
  await expect(page.locator('#admin-main')).toBeFocused();
  const resources = page.getByRole('region', { name: 'Ressources et productivité', exact: true });
  const resourcesLink = page.getByRole('navigation', { name: 'Mesures de l’univers' }).getByRole('link', { name: 'Ressources', exact: true });
  await resourcesLink.focus();
  await page.keyboard.press('Enter');
  await expect(page).toHaveURL(/#metrics-resources$/);
  await expect(resources.locator('.admin-resource-observation')).toHaveCount(5);
  for (const theme of ['dark', 'light']) {
    runIsolatedSql(project, `UPDATE player SET preferences=JSON_SET(CASE WHEN JSON_TYPE(preferences)='OBJECT' THEN preferences ELSE JSON_OBJECT() END, '$.theme', '${theme}') WHERE email='${email}'`);
    await page.reload();
    await expect(page.locator('body')).toHaveAttribute('data-theme', theme);
    for (const width of [1440, 768, 390]) {
      await page.setViewportSize({ width, height: 900 });
      await expect.poll(() => page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(width);
      for (const grid of ['.admin-observation-comparison', '.admin-observation-resources']) {
        expect(await page.locator(grid).evaluate(element => getComputedStyle(element).gridTemplateColumns.split(' ').length)).toBe(width === 1440 ? 2 : 1);
      }
      const audit = await new AxeBuilder({ page }).include('#admin-main').analyze();
      expect(audit.violations).toEqual([]);
      await page.evaluate(() => window.scrollTo({ top: 0, behavior: 'instant' }));
      await page.screenshot({ path: testInfo.outputPath(`mesures-${theme}-${width}.png`), fullPage: true, animations: 'disabled' });
    }
  }
  expect(stocks()).toBe(before);
  runIsolatedSql(project, `UPDATE universe SET status='closed' WHERE id=${universe}`);
  await page.reload();
  await expect(page.locator('main')).toContainText('Univers fermé.');
  await expect(resources).toContainText('Non applicable');
  expect(stocks()).toBe(before);
  await page.screenshot({ path: testInfo.outputPath('mesures-mobile.png'), fullPage: true });
  // Les colonies sans lignes de ressources doivent rester indisponibles, jamais devenir des stocks nuls.
  const membership = Number(queryIsolatedSql(project, `SELECT pu.id FROM player_universe pu JOIN player p ON p.id=pu.player_id WHERE p.email='${email}'`));
  const freePlanets = queryIsolatedSql(project, `SELECT p.id FROM planet p JOIN solar_system s ON s.id=p.solar_system_id JOIN galaxy g ON g.id=s.galaxy_id WHERE g.universe_id=${universe} AND p.owner_id IS NULL ORDER BY p.id LIMIT 25`).split(/\s+/).map(Number);
  expect(freePlanets).toHaveLength(25);
  runIsolatedSql(project, `UPDATE planet SET owner_id=${membership} WHERE id IN (${freePlanets.join(',')})`);
  // Fixtures sans message associé : le relevé doit uniquement lire ces lignes, sans jouer ni solder de mission.
  const colony = Number(queryIsolatedSql(project, `SELECT planet_id FROM planet_resource r JOIN planet p ON p.id=r.planet_id WHERE p.owner_id=${membership} ORDER BY planet_id LIMIT 1`));
  runIsolatedSql(project, `
    INSERT INTO queue_item (planet_id, player_universe_id, queue_type, item_slug, tier, quantity, duration_seconds, started_at, queued_at, dispatch_token, first_steps_guided)
    VALUES (${colony}, ${membership}, 'building', 'snapshot-building', 'basic', 1, 3600, UTC_TIMESTAMP(), UTC_TIMESTAMP() - INTERVAL 1 MINUTE, UUID(), 0),
           (${colony}, ${membership}, 'research', 'snapshot-research', 'basic', 1, 3600, NULL, UTC_TIMESTAMP() - INTERVAL 1 MINUTE, UUID(), 0),
           (${colony}, ${membership}, 'shipyard', 'snapshot-shipyard', 'basic', 1, 3600, UTC_TIMESTAMP(), UTC_TIMESTAMP() - INTERVAL 1 MINUTE, UUID(), 0)
  `);
  runIsolatedSql(project, `
    INSERT INTO pirate_reconnaissance (membership_id, owner_id, system_id, mission_key, departed_at, due_at, dispatch_token, reported_at, composition, build_costs)
    SELECT pu.id, pu.player_id, p.solar_system_id, fixture.mission_key,
           UTC_TIMESTAMP() - INTERVAL 10 MINUTE, UTC_TIMESTAMP() - INTERVAL 8 MINUTE,
           UUID(), CASE WHEN fixture.mission_key <> 'metrics-scout' THEN UTC_TIMESTAMP() - INTERVAL 8 MINUTE ELSE NULL END,
           JSON_OBJECT(), JSON_OBJECT()
    FROM player_universe pu JOIN planet p ON p.id=${colony}
    CROSS JOIN (SELECT 'metrics-scout' AS mission_key UNION ALL SELECT 'metrics-win' UNION ALL SELECT 'metrics-pending') fixture
    WHERE pu.id=${membership}
  `);
  runIsolatedSql(project, `
    INSERT INTO pirate_attack_campaign
      (reconnaissance_id, origin_id, travel_seconds, round_seconds, scenario, timeline, reserved_ships, report, departed_at, arrival_at, return_blocked)
    SELECT id, ${colony}, 60, 30, JSON_OBJECT(),
           CASE WHEN mission_key='metrics-win'
                THEN JSON_OBJECT('resolved', CONCAT(DATE_FORMAT(UTC_TIMESTAMP() - INTERVAL 2 MINUTE, '%Y-%m-%dT%H:%i:%s'), '+00:00')) ELSE JSON_OBJECT() END,
           JSON_OBJECT(), CASE WHEN mission_key='metrics-win' THEN JSON_OBJECT('outcome', 'attacker_wins') ELSE NULL END,
           UTC_TIMESTAMP() - INTERVAL 5 MINUTE, UTC_TIMESTAMP() - INTERVAL 4 MINUTE, 0
    FROM pirate_reconnaissance WHERE membership_id=${membership} AND mission_key IN ('metrics-win', 'metrics-pending')
  `);
  const nativeContext = await browser.newContext({ storageState: await page.context().storageState(), javaScriptEnabled: false, viewport: { width: 390, height: 900 } });
  const native = await nativeContext.newPage();
  await native.goto(url);
  await expect(native.locator('#metrics-resources')).toContainText('26 colonies possédées');
  await expect(native.locator('#metrics-progression')).toContainText('1 sur 1 (100,0 %)');
  await expect(native.locator('#metrics-wait dd', { hasText: '1 en cours · 0 en attente' })).toHaveCount(2);
  await expect(native.locator('#metrics-wait dd', { hasText: '0 en cours · 1 en attente' })).toHaveCount(1);
  const pirates = native.getByRole('region', { name: 'Jalons de la campagne pirate' });
  await expect(pirates.getByRole('listitem')).toHaveText([
    'Reconnaissances parties : 3', 'Rapports de reconnaissance reçus : 2', 'Escortes parties après le rapport : 2',
  ]);
  await expect(pirates.locator('dd')).toHaveText(['1', '0', '0', '1']);
  await expect(native.locator('#metrics-resources')).toContainText('colonie(s) non exploitable(s)');
  await native.getByRole('link', { name: 'Page suivante', exact: true }).click();
  await expect(native).toHaveURL(/page=2#metrics-resources$/);
  await expect(native.locator('#metrics-resources')).toContainText('Page 2 sur 2');
  await native.getByRole('link', { name: 'Actualiser le relevé', exact: true }).click();
  await expect(native).toHaveURL(/page=2$/);
  await native.getByRole('link', { name: 'Page précédente', exact: true }).click();
  await expect(native.locator('#metrics-resources')).toContainText('Page 1 sur 2');
  expect(stocks()).toBe(before);
  const emptyUniverse = Number(queryIsolatedSql(project, `SELECT id FROM universe WHERE id<>${universe} ORDER BY id LIMIT 1`));
  await native.goto(`/admin/universes/${emptyUniverse}/mesures`);
  await expect(native.locator('#metrics-resources')).toContainText('Aucune colonie exploitable');
  await expect(native.locator('.admin-resource-observation')).toHaveCount(0);
  await expect(native.locator('#metrics-progression')).toContainText('Non disponible');
  await expect(native.locator('#metrics-progression')).toContainText('Aucune participation pilotée');
  await expect(native.locator('#metrics-pirates')).toContainText('Aucune reconnaissance partie');
  await expect(native.locator('#metrics-wait dd', { hasText: '0 en cours · 0 en attente' })).toHaveCount(3);
  // Tester la navigation réelle de la maquette B et la conservation des filtres sans JavaScript.
  await native.goto(url);
  await native.getByRole('link', { name: 'Ouvrir l’Observatoire' }).click();
  await expect(native.getByRole('heading', { name: 'Observatoire', exact: true })).toBeVisible();
  await expect(native.getByRole('heading', { name: 'Économie · ressources actuelles' })).toBeVisible();
  // Le scénario a ajouté 25 colonies sans stock. Seule la page contenant la colonie initiale doit avoir cinq lignes.
  if (freePlanets.every((id) => id < colony)) {
    await expect(native.getByText('Aucune colonie exploitable sur cette page.')).toBeVisible();
    await native.getByRole('navigation', { name: 'Pages des mesures économiques' }).getByRole('link', { name: 'Suivante' }).click();
    await expect(native).toHaveURL(/page=2/);
  }
  await expect(native.locator('.observatory-table tbody tr')).toHaveCount(5);
  await native.getByRole('link', { name: '90 j', exact: true }).click();
  await native.getByRole('navigation', { name: 'Rubriques de l’Observatoire' }).getByRole('link', { name: 'Audience' }).click();
  await expect(native).toHaveURL(/days=90/);
  await expect(native.getByRole('heading', { name: 'Audience · évolution quotidienne' })).toBeVisible();
  await native.getByText('Données du graphique', { exact: true }).click();
  await expect(native.locator('.observatory-table tbody tr')).toHaveCount(90);
  await expect(native.locator('.observatory-table tbody tr').first()).toContainText('Non disponible');
  await native.getByRole('navigation', { name: 'Rubriques de l’Observatoire' }).getByRole('link', { name: 'Progression' }).click();
  await expect(native.getByRole('heading', { name: 'Progression · Premiers pas' })).toBeVisible();
  await native.getByRole('navigation', { name: 'Rubriques de l’Observatoire' }).getByRole('link', { name: 'Combats' }).click();
  await expect(native.getByRole('heading', { name: 'Combats · bilan des vaisseaux par rang' })).toBeVisible();
  await expect(native.getByRole('heading', { name: 'Combats · bilan des défenses par rang' })).toBeVisible();
  await expect(native.getByRole('columnheader', { name: 'Reconstruites après combat' })).toBeVisible();
  await expect(native.locator('.observatory-table tbody tr').first()).toContainText('Non disponible');

  await nativeContext.close();

  const missing = await page.goto('/admin/universes/2147483647/mesures');
  expect(missing.status()).toBe(404);
});
