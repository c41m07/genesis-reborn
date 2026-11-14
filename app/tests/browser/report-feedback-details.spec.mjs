import { test, expect } from './support/isolated-test.mjs';
import { execFileSync } from 'node:child_process';
import { runIsolatedSql, queryIsolatedSql } from './support/game-fixtures.mjs';

const quote = value => "'" + String(value).replaceAll("'", "''") + "'";

async function enter(page, admin = false) {
  const id = crypto.randomUUID().slice(0, 8);
  const email = `details-${id}@example.test`;
  const password = 'Browser-test-2026!';
  await page.goto('/inscription');
  await page.getByLabel('Adresse email').fill(email);
  await page.getByLabel('Nom de commandant').fill(`Details-${id}`);
  await page.getByLabel('Mot de passe (12 caractères minimum)', { exact: true }).fill(password);
  await page.getByLabel('Confirmer le mot de passe').fill(password);
  await page.getByRole('button', { name: 'Créer mon compte' }).click();
  await expect(page).toHaveURL(/connexion/);
  if (admin) {
    execFileSync('docker', ['compose', '--project-name', process.env.BROWSER_ROUTER,
      '--env-file', '.env.local', 'exec', '-T', 'app', 'php', 'bin/console', 'app:admin:grant', email],
    { stdio: ['ignore', 'pipe', 'pipe'] });
  }
  await page.getByLabel('Adresse email').fill(email);
  await page.getByLabel('Mot de passe', { exact: true }).fill(password);
  await page.getByRole('button', { name: /Connexion/ }).click();
  await page.locator('article.universe-card').first().getByRole('button').click();
  await expect(page).toHaveURL(/planetes\/\d+$/);
  return email;
}

test('Renseignements : rangs actuels, ancien format, ressources et détection', async ({ page }, testInfo) => {
  const email = await enter(page);
  const project = process.env.BROWSER_ROUTER;
  const snapshot = {
    resources: { metal: { label: 'Métal', stock: 12000, rate: 1.5, capacity: 50000 } },
    buildings: { mine: { basic: { label: 'Mine basique', level: 4 }, intermediate: { label: 'Mine orbitale', level: 2 } } },
    research: { legacy: { label: 'Recherche historique', level: 3 }, current: { superior: { label: 'Recherche supérieure', level: 5 } } },
    fleets: { fighter: { label: 'Escadre', quantity: 4000 } }, defenses: {},
  };
  runIsolatedSql(project, `INSERT INTO espionage_report (sender_id,target_id,result,created_at,probes_sent,intel_categories,detection_chance,sender_espionage_level,target_espionage_level,target_counter_espionage_level,snapshot)
    SELECT pu.id, planet.id, 'success', UTC_TIMESTAMP(), 5, 5, 10, 5, 0, 0, ${quote(JSON.stringify(snapshot))}
    FROM player_universe pu JOIN player p ON p.id=pu.player_id JOIN planet ON planet.owner_id=pu.id
    WHERE p.email=${quote(email)} LIMIT 1`);
  const id = queryIsolatedSql(project, 'SELECT MAX(id) FROM espionage_report');
  await page.goto(`/rapports/espionnage/${id}`);
  await expect(page.locator('.intel-section[open]')).toHaveCount(0);
  for(const summary of await page.locator('.intel-section > summary').all()) { await summary.focus(); await page.keyboard.press('Enter'); }
  await expect(page.locator('.intel-section[open]')).toHaveCount(5);
  for (const [label, level] of [['Mine basique', 4], ['Mine orbitale', 2], ['Recherche historique', 3], ['Recherche supérieure', 5]]) {
    await expect(page.locator('.intel-card').filter({ hasText: label })).toContainText(`Niveau ${level}`);
  }
  await expect(page.locator('.intel-card').filter({ hasText: 'Métal' })).toContainText('12,00 k');
  await expect(page.locator('.intel-card').filter({ hasText: 'Métal' }).locator('abbr').first()).toHaveAttribute('title', '12 000');
  await expect(page.locator('.intel-card').filter({ hasText: 'Escadre' })).toContainText('4 000');
  await expect(page.getByText('Aucun élément détecté dans cette catégorie.')).toBeVisible();
  for (const width of [1920, 1280, 768, 390]) {
    await page.setViewportSize({ width, height: 900 });
    expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(width);
  }
  await page.screenshot({ path: testInfo.outputPath('intel-390.png'), fullPage: true, animations: 'disabled' });
  runIsolatedSql(project, `UPDATE espionage_report SET result='detected', intel_categories=0, snapshot='{}' WHERE id=${Number(id)}`);
  await page.reload();
  await expect(page.getByRole('status')).toContainText('Sondes détectées');
  await expect(page.locator('.intel-section')).toHaveCount(0);
});

test('Combats : filtre économique, pertes lisibles et défilement clavier', async ({ page }, testInfo) => {
  const email = await enter(page);
  const project = process.env.BROWSER_ROUTER;
  const [planetId, membershipId] = queryIsolatedSql(project, `SELECT planet.id, pu.id FROM planet
    JOIN player_universe pu ON pu.id=planet.owner_id JOIN player p ON p.id=pu.player_id
    WHERE p.email=${quote(email)} LIMIT 1`).split('\t').map(Number);
  runIsolatedSql(project, `INSERT INTO combat_event (planet_id,status,started_at,resolve_at,seed,dispatch_token,current_round,combat_snapshot)
    VALUES (${planetId},'resolved',UTC_TIMESTAMP(),UTC_TIMESTAMP(),123,'${crypto.randomUUID()}',1,'{}')`);
  const eventId = Number(queryIsolatedSql(project, 'SELECT MAX(id) FROM combat_event'));
  const snapshot = { participants: [{ player_universe_id: membershipId, fleet_id: 1, role: 'defender',
    units: [{ slug: 'fighter', label: 'Escadre de défense', quantity: 12000 }] }], losses: { '1:fighter': 1234 } };
  runIsolatedSql(project, `INSERT INTO combat_log (combat_event_id,outcome,rounds,snapshot,resolved_at)
    VALUES (${eventId},'defender_wins','[]',${quote(JSON.stringify(snapshot))},UTC_TIMESTAMP())`);
  await page.goto('/rapports?type=economy');
  await expect(page.locator('a[href*="/rapports/combat/"]')).toHaveCount(0);
  await expect(page.getByRole('heading', { name: 'Aucun rapport économique' })).toBeVisible();
  await page.goto('/rapports?type=combat');
  const report = page.locator('a[href*="/rapports/combat/"]').first();
  await expect(report).toBeVisible();
  await expect(page.getByRole('link', { name: 'Combats', exact: true })).toHaveAttribute('aria-current', 'page');
  await page.screenshot({ path: testInfo.outputPath('reports-combat.png'), fullPage: true, animations: 'disabled' });
  await report.click();
  await expect(page.getByRole('rowheader', { name: 'Escadre de défense' })).toBeVisible();
  await expect(page.getByRole('cell', { name: '12,00 k', exact: true })).toBeVisible();
  await expect(page.getByRole('cell', { name: '12,00 k', exact: true }).locator('abbr')).toHaveAttribute('title', '12 000');
  await expect(page.getByRole('cell', { name: '1 234', exact: true })).toBeVisible();
  const losses = page.getByRole('region', { name: 'Bilan des pertes, défilement horizontal' });
  await losses.focus();
  await expect(losses).toBeFocused();
  for (const width of [1280, 768, 390]) {
    await page.setViewportSize({ width, height: 900 });
    await expect.poll(() => page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(width);
  }
  await page.screenshot({ path: testInfo.outputPath('combat-detail-390.png'), fullPage: true, animations: 'disabled' });
  await expect(page.getByText('Le détail des rounds n’est pas disponible pour ce rapport historique.')).toBeVisible();

  snapshot.participants[0].camp = 'defenders';
  snapshot.participants[0].units.push(
    { slug: 'fighter', tier: 'intermediate', label: 'Chasseur intermédiaire', quantity: 2 },
    { slug: 'fighter', tier: 'superior', label: 'Chasseur supérieur', quantity: 1 },
  );
  snapshot.participants.push({ player_universe_id: 0, fleet_id: 2, role: 'attacker', camp: 'enemy',
    units: [{ slug: 'frigate', label: 'Frégate ennemie', quantity: 3 }] });
  snapshot.defenses = [{ id: '77', slug: 'laser', tier: 'intermediate', label: 'Tourelle ionique', quantity: 10 }];
  snapshot.losses = { '1:fighter:intermediate': 1, '2:frigate': 1, 'planet:77:laser:intermediate': 10 };
  snapshot.defense_rebuilt = { 'laser:intermediate': 3 };
  const rounds = [{ number: 1, shots: [
    { attacker: '1:defender:fighter:intermediate:1', target: '2:attacker:frigate:1', hit: true, destroyed: true },
    { attacker: '2:attacker:frigate:2', target: '1:defender:fighter:intermediate:1', hit: true, destroyed: true },
    ...Array.from({ length: 10 }, (_, i) => ({ attacker: '2:attacker:frigate:3',
      target: `planet:77:static_defense:laser:intermediate:${i + 1}`, hit: true, destroyed: true })),
  ] }];
  const logId = queryIsolatedSql(project, `SELECT id FROM combat_log WHERE combat_event_id=${eventId}`);
  runIsolatedSql(project, `UPDATE combat_log SET snapshot=${quote(JSON.stringify(snapshot))}, rounds=${quote(JSON.stringify(rounds))} WHERE id=${Number(logId)}`);
  await page.reload();
  const own = page.getByRole('table', { name: 'Votre camp · Round 1', exact: true });
  const enemy = page.getByRole('table', { name: 'Camp adverse · Round 1', exact: true });
  await expect(own.getByRole('row')).toHaveCount(5);
  await expect(enemy.getByRole('row')).toHaveCount(2);
  const intermediate = own.getByRole('row').filter({ hasText: 'Chasseur intermédiaire' });
  await expect(intermediate.getByRole('cell')).toHaveText(['2', '1', '1', '1', '1', '0', '100 %']);
  const rebuilt = page.getByRole('table', { name: 'Bilan des défenses planétaires par type et par rang', exact: true });
  await expect(rebuilt.getByRole('row').filter({ hasText: 'Tourelle ionique' }).getByRole('cell')).toHaveText(['Intermédiaire', '10', '3', '7']);
  const versus = page.getByRole('region', { name: 'Round 1, comparaison des camps, défilement horizontal', exact: true });
  for (const width of [1440, 768, 390, 320]) {
    await page.setViewportSize({ width, height: 900 });
    await expect.poll(() => page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(width);
  }
  await versus.focus();
  await expect(versus).toBeFocused();
  await page.keyboard.press('ArrowRight');
  await expect.poll(() => versus.evaluate(element => element.scrollLeft)).toBeGreaterThan(0);
  await page.screenshot({ path: testInfo.outputPath('combat-versus-320.png'), fullPage: true, animations: 'disabled' });
});

test('Retour admin : titre long, note lisible et sauvegarde du suivi', async ({ page }, testInfo) => {
  const email = await enter(page, true);
  const project = process.env.BROWSER_ROUTER;
  const title = 'Signalement' + 'TrèsLong'.repeat(15);
  runIsolatedSql(project, `INSERT INTO user_feedback (player_id,type,priority,title,description,status,created_at)
    SELECT id, 'bug', 'high', ${quote(title.slice(0, 120))}, 'Description de test', 'open', UTC_TIMESTAMP()
    FROM player WHERE email=${quote(email)}`);
  const id = queryIsolatedSql(project, 'SELECT MAX(id) FROM user_feedback');
  await page.goto(`/admin/feedbacks/${id}`);
  const note = page.getByLabel(/Note interne/);
  for (const width of [1920, 1280, 930, 768, 390]) {
    await page.setViewportSize({ width, height: 900 });
    expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(width);
    await expect(note).toBeVisible();
  }
  await note.focus();
  await expect(note).toBeFocused();
  await note.fill('Vérification réalisée en base temporaire.');
  await page.getByLabel('Statut', { exact: true }).selectOption('in_progress');
  await page.screenshot({ path: testInfo.outputPath('feedback-detail-390.png'), fullPage: true, animations: 'disabled' });
  await page.getByRole('button', { name: 'Enregistrer les changements' }).click();
  await expect(note).toHaveValue('Vérification réalisée en base temporaire.');
  await expect(page.getByLabel('Statut', { exact: true })).toHaveValue('in_progress');
  await expect(page.locator('.feedback-internal-note')).toContainText('Vérification réalisée');
});
