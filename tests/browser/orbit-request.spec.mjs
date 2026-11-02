import { test, expect } from './support/isolated-test.mjs';
import { execFileSync } from 'node:child_process';
import { planetCoordinates } from './support/game-fixtures.mjs';

async function enter(page, prefix) {
  const id = crypto.randomUUID().slice(0, 8);
  const email = prefix.toLowerCase() + '-' + id + '@example.test';
  const username = prefix + '-' + id;
  const password = 'Browser-test-2026!';
  await page.goto('/inscription');
  await page.getByLabel('Adresse email').fill(email);
  await page.getByLabel('Nom de commandant').fill(username);
  await page.getByLabel('Mot de passe (12 caractères minimum)', { exact: true }).fill(password);
  await page.getByLabel('Confirmer le mot de passe').fill(password);
  await page.getByRole('button', { name: 'Créer mon compte' }).click();
  await expect(page).toHaveURL(/connexion/);
  await page.getByLabel('Adresse email').fill(email);
  await page.getByLabel('Mot de passe', { exact: true }).fill(password);
  await page.getByRole('button', { name: /Connexion/ }).click();
  await page.locator('article.universe-card').first().getByRole('button').click();
  await expect(page).toHaveURL(/planetes\/\d+$/);
  return { email, username, planetId: Number(page.url().split('/').pop()) };
}

function sql(project, query) {
  return execFileSync('docker', ['compose', '--project-name', project, '--env-file', '.env.local', 'exec', '-T', 'app', 'php', 'bin/console', 'dbal:run-sql', query], { encoding: 'utf8', stdio: ['ignore', 'pipe', 'pipe'] });
}

test('Orbite alliée : demande entrante, accord du propriétaire et stationnement', async ({ browser }) => {
  test.setTimeout(150000);
  const senderPage = await browser.newPage();
  const hostPage = await browser.newPage();
  try {
    const sender = await enter(senderPage, 'Orbit-Sender');
    const host = await enter(hostPage, 'Orbit-Host');
    const commander = (page, name) => page.locator('.commander-card').filter({ has: page.getByRole('heading', { name, exact: true }) });

    await senderPage.goto('/diplomatie');
    await hostPage.goto('/diplomatie');
    const senderGuild = 'Orbite-Sender-' + crypto.randomUUID().slice(0, 6);
    const hostGuild = 'Orbite-Host-' + crypto.randomUUID().slice(0, 6);
    await senderPage.getByLabel('Nom de votre nouvelle guilde').fill(senderGuild);
    await senderPage.getByRole('button', { name: 'Fonder une guilde' }).click();
    await expect(senderPage.locator('.guild-banner')).toContainText(senderGuild);
    await hostPage.getByLabel('Nom de votre nouvelle guilde').fill(hostGuild);
    await hostPage.getByRole('button', { name: 'Fonder une guilde' }).click();
    await expect(hostPage.locator('.guild-banner')).toContainText(hostGuild);

    await senderPage.reload();
    const hostGuildCardForSender = senderPage.locator('.guild-card').filter({ has: senderPage.getByRole('heading', { name: hostGuild, exact: true }) });
    await hostGuildCardForSender.getByLabel('Relation réciproque').selectOption('allies');
    await hostGuildCardForSender.getByRole('button', { name: 'Mettre à jour' }).click();
    await expect(hostGuildCardForSender.locator('.badge')).toContainText('Proposition en attente');
    await hostPage.reload();
    const senderGuildCardForHost = hostPage.locator('.guild-card').filter({ has: hostPage.getByRole('heading', { name: senderGuild, exact: true }) });
    await senderGuildCardForHost.getByLabel('Relation réciproque').selectOption('allies');
    await senderGuildCardForHost.getByRole('button', { name: 'Mettre à jour' }).click();
    await expect(senderGuildCardForHost.locator('.badge')).toContainText('Alliés');
    await senderPage.reload();
    await expect(senderPage.locator('.guild-card').filter({ has: senderPage.getByRole('heading', { name: hostGuild, exact: true }) }).locator('.badge')).toContainText('Alliés');
    await senderPage.screenshot({ path: 'docs/validation/diplomatie-guildes-etats.png', fullPage: true });

    const project = process.env.BROWSER_ROUTER;
    expect(project).toMatch(/^genesis-browser-test-[0-9]+-[0-9]+$/);
    const createFleet = "INSERT INTO fleet (status, mission, speed_ratio, cargo, reserved_fuel, orbit_fuel, orbit_duration_seconds, travel_seconds, dispatch_token, player_universe_id, home_planet_id) SELECT 'idle', 'idle', 1, '{}', 0, 0, 0, 0, '00000000-0000-4000-8000-000000000002', pu.id, home.id FROM player_universe pu INNER JOIN player p ON p.id=pu.player_id INNER JOIN planet home ON home.owner_id=pu.id WHERE p.email='" + sender.email + "' LIMIT 1";
    sql(project, createFleet);
    const stockShip = "INSERT INTO fleet_ship (fleet_id, slug, quantity) SELECT f.id, 'probe', 1 FROM fleet f INNER JOIN player_universe pu ON pu.id=f.player_universe_id INNER JOIN player p ON p.id=pu.player_id WHERE p.email='" + sender.email + "' AND f.status='idle' LIMIT 1 ON DUPLICATE KEY UPDATE quantity=VALUES(quantity)";
    sql(project, stockShip);
    const discovery = "INSERT IGNORE INTO player_discovery (player_universe_id, solar_system_id) SELECT pu.id, target.solar_system_id FROM player_universe pu INNER JOIN player p ON p.id=pu.player_id INNER JOIN planet target ON target.id=" + host.planetId + " WHERE p.email='" + sender.email + "'";
    sql(project, discovery);

    await senderPage.goto('/flottes?mission=orbit');
    const form = senderPage.locator('.fleet-form form').first();
    await form.locator('[name="ships[probe]"]').fill('1');
    await form.getByRole('button', { name: /Choisir la destination/ }).click();
    await form.locator('[name="mission"]').selectOption('orbit');
    await form.locator('[name="coordinates"]').fill(planetCoordinates(project, host.planetId));
    await form.locator('[name="orbit_hours"]').fill('1');
    await form.locator('[name="ratio"]').selectOption('0.1');
    await form.getByRole('button', { name: /Vérifier la mission/ }).click();
    await form.getByRole('button', { name: 'Estimer le trajet' }).click();
    await expect(form.locator('.flight-estimate')).toContainText('Carburant aller-retour');
    await senderPage.evaluate(() => window.scrollTo(0, 0));
    await senderPage.screenshot({ path: 'docs/validation/flottes-mission-orbite.png', fullPage: true });
    await form.getByRole('button', { name: /Envoyer la flotte/ }).click();
    await expect(senderPage.getByRole('status')).toContainText('Flotte envoyée');
    await expect(senderPage.locator('[data-status="traveling"]')).toHaveCount(1);
    await senderPage.evaluate(() => window.scrollTo(0, 0));
    await senderPage.screenshot({ path: 'docs/validation/flottes-en-route.png', fullPage: true });

    const accelerate = "UPDATE fleet f INNER JOIN player_universe pu ON pu.id=f.player_universe_id INNER JOIN player p ON p.id=pu.player_id SET f.arrival_at=UTC_TIMESTAMP()-INTERVAL 2 SECOND WHERE p.email='" + sender.email + "' AND f.mission='orbit' AND f.status='traveling' AND f.destination_id=" + host.planetId;
    const expediteExisting = "UPDATE outbox_message o INNER JOIN fleet f ON o.kind='fleet' AND o.target_id=f.id AND o.token=f.dispatch_token INNER JOIN player_universe pu ON pu.id=f.player_universe_id INNER JOIN player p ON p.id=pu.player_id SET o.due_at=UTC_TIMESTAMP()-INTERVAL 2 SECOND WHERE p.email='" + sender.email + "' AND f.mission='orbit' AND f.destination_id=" + host.planetId;
    const dispatchNow = "INSERT IGNORE INTO outbox_message (kind, target_id, token, due_at) SELECT 'fleet', f.id, f.dispatch_token, UTC_TIMESTAMP()-INTERVAL 2 SECOND FROM fleet f INNER JOIN player_universe pu ON pu.id=f.player_universe_id INNER JOIN player p ON p.id=pu.player_id WHERE p.email='" + sender.email + "' AND f.mission='orbit' AND f.status='traveling' AND f.destination_id=" + host.planetId;
    sql(project, accelerate);
    sql(project, expediteExisting);
    sql(project, dispatchNow);
    await expect.poll(async () => {
      await senderPage.reload();
      return senderPage.locator('[data-status="orbit_pending"]').count();
    }, { timeout: 45000, intervals: [1000] }).toBe(1);

    await hostPage.goto('/flottes');
    await expect(hostPage.getByRole('heading', { name: 'Demandes d’orbite', exact: true })).toBeVisible();
    await expect(hostPage.locator('[data-live-region="orbit-requests"]')).toContainText('Souhaite stationner 1 h');
    await hostPage.getByRole('button', { name: 'Accepter', exact: true }).click();
    await expect(hostPage.getByRole('status')).toContainText('Demande d’orbite acceptée.');
    await expect.poll(async () => {
      await senderPage.reload();
      return senderPage.locator('[data-status="orbiting"]').count();
    }, { timeout: 15000, intervals: [500] }).toBe(1);
    await expect(senderPage.locator('[data-status="orbiting"]')).toContainText('En orbite');
    await expect(senderPage.locator('[data-active-fleet] [role="progressbar"]')).toHaveAttribute('aria-label', /Fin d’orbite/);
  } finally {
    await senderPage.close();
    await hostPage.close();
  }
});
