import { execFileSync } from 'node:child_process';

function assertIsolatedProject(project) {
  if (!/^genesis-browser-test-[0-9]+-[0-9]+$/.test(project ?? '')) {
    throw new Error('Les fixtures Playwright sont limitées au projet Docker de test isolé.');
  }
}

function databaseCommand(project) {
  assertIsolatedProject(project);
  return [
    'compose', '--project-name', project, '--env-file', '.env.local',
    '-f', 'compose.yaml', '-f', 'compose.browser-test.yaml',
  ];
}

export function runIsolatedSql(project, query) {
  return execFileSync('docker', [
    ...databaseCommand(project), 'exec', '-T', 'app', 'php', 'bin/console', 'dbal:run-sql', query,
  ], { encoding: 'utf8', stdio: ['ignore', 'pipe', 'pipe'] });
}

export function queryIsolatedSql(project, query) {
  return execFileSync('docker', [
    ...databaseCommand(project), 'exec', '-T', 'database', 'sh', '-c',
    'mariadb -N -B --user=root --password="$MARIADB_ROOT_PASSWORD" "$MARIADB_DATABASE" -e "$1"',
    'sh', query,
  ], { encoding: 'utf8', stdio: ['ignore', 'pipe', 'pipe'] }).trim();
}

function quoteSql(value) {
  return "'" + String(value).replaceAll("'", "''") + "'";
}

export function prepareOwnedColony(project, email, { stock = 150000, showLockedItems = false } = {}) {
  const emailValue = quoteSql(email);
  runIsolatedSql(project, `
    UPDATE planet_resource pr
    INNER JOIN planet ON planet.id = pr.planet_id
    INNER JOIN player_universe membership ON membership.id = planet.owner_id
    INNER JOIN player ON player.id = membership.player_id
    SET pr.current_stock = ${Math.max(0, Math.floor(stock))},
        pr.last_updated_at = UTC_TIMESTAMP()
    WHERE player.email = ${emailValue}
  `);

  if (showLockedItems) {
    runIsolatedSql(project, `
      UPDATE player
      SET preferences = JSON_SET(
        CASE WHEN JSON_TYPE(preferences) = 'OBJECT' THEN preferences ELSE JSON_OBJECT() END,
        '$.showLockedItems', true
      )
      WHERE email = ${emailValue}
    `);
  }
}

export function revealNpcPlanet(project, email) {
  const emailValue = quoteSql(email);
  const npc = queryIsolatedSql(project, `
    SELECT target.id,
           CONCAT(REPLACE(galaxy.name, 'Galaxie ', ''), ':',
                  FLOOR(solar_system.coord_y / 3) * 10 + FLOOR(solar_system.coord_x / 3) + 1, ':',
                  target.position)
    FROM player_universe membership
    INNER JOIN player ON player.id = membership.player_id
    INNER JOIN galaxy ON galaxy.universe_id = membership.universe_id
    INNER JOIN solar_system ON solar_system.galaxy_id = galaxy.id
    INNER JOIN planet target ON target.solar_system_id = solar_system.id
    WHERE player.email = ${emailValue}
      AND target.npc_planet = 1
      AND target.owner_id IS NULL
    ORDER BY target.conquest_level ASC, target.id ASC
    LIMIT 1
  `);
  const [id, coordinates] = npc.split('\t');
  if (!id || !coordinates) {
    throw new Error('Aucune planète PNJ libre ne correspond au scénario navigateur.');
  }

  runIsolatedSql(project, `
    INSERT IGNORE INTO player_discovery (player_universe_id, solar_system_id)
    SELECT membership.id, solar_system.id
    FROM player_universe membership
    INNER JOIN player ON player.id = membership.player_id
    INNER JOIN planet target ON target.id = ${Number(id)}
    INNER JOIN solar_system ON solar_system.id = target.solar_system_id
    WHERE player.email = ${emailValue}
  `);

  return { id: Number(id), coordinates };
}

export function planetCoordinates(project, planetId) {
  const coordinates = queryIsolatedSql(project, `
    SELECT CONCAT(REPLACE(galaxy.name, 'Galaxie ', ''), ':',
                  FLOOR(solar_system.coord_y / 3) * 10 + FLOOR(solar_system.coord_x / 3) + 1, ':',
                  planet.position)
    FROM planet
    INNER JOIN solar_system ON solar_system.id = planet.solar_system_id
    INNER JOIN galaxy ON galaxy.id = solar_system.galaxy_id
    WHERE planet.id = ${Number(planetId)}
  `);
  if (!coordinates) {
    throw new Error('La planète de destination du scénario navigateur est introuvable.');
  }
  return coordinates;
}
export function grantSecondColony(project, email) {
  const emailValue = quoteSql(email);
  runIsolatedSql(project, `
    INSERT INTO player_technology (player_universe_id, slug, level)
    SELECT membership.id, 'colonization', 1
    FROM player_universe membership
    INNER JOIN player ON player.id = membership.player_id
    WHERE player.email = ${emailValue}
    ON DUPLICATE KEY UPDATE level = GREATEST(level, VALUES(level))
  `);
}

export function accelerateQueuedItem(project, email, queueType, slug) {
  const emailValue = quoteSql(email);
  const typeValue = quoteSql(queueType);
  const slugValue = quoteSql(slug);
  const joins = "INNER JOIN player_universe membership ON membership.id = item.player_universe_id INNER JOIN player ON player.id = membership.player_id";
  const where = "player.email = " + emailValue + " AND item.queue_type = " + typeValue + " AND item.item_slug = " + slugValue + " AND item.started_at IS NOT NULL";
  runIsolatedSql(project, "UPDATE queue_item item " + joins + " SET item.duration_seconds = 1, item.started_at = UTC_TIMESTAMP() - INTERVAL 10 SECOND WHERE " + where);
  runIsolatedSql(project, "UPDATE outbox_message message INNER JOIN queue_item item ON message.kind = 'queue' AND message.target_id = item.id " + joins + " SET message.due_at = UTC_TIMESTAMP() - INTERVAL 2 SECOND WHERE " + where);
  runIsolatedSql(project, "INSERT INTO outbox_message (kind, target_id, token, due_at) SELECT 'queue', item.id, item.dispatch_token, UTC_TIMESTAMP() - INTERVAL 2 SECOND FROM queue_item item " + joins + " WHERE " + where + " AND NOT EXISTS (SELECT 1 FROM outbox_message WHERE kind = 'queue' AND target_id = item.id AND token = item.dispatch_token)");
}

export function accelerateTravelingMission(project, email, mission) {
  const emailValue = quoteSql(email);
  const missionValue = quoteSql(mission);
  const fleetId = Number(queryIsolatedSql(project,
    "SELECT fleet.id FROM fleet INNER JOIN player_universe membership ON membership.id = fleet.player_universe_id INNER JOIN player ON player.id = membership.player_id WHERE player.email = " + emailValue + " AND fleet.mission = " + missionValue + " AND fleet.status IN ('traveling', 'returning') ORDER BY fleet.id DESC LIMIT 1"));
  if (!fleetId) {
    throw new Error('Aucune flotte active à accélérer dans ce scénario.');
  }
  runIsolatedSql(project, "UPDATE fleet SET arrival_at = UTC_TIMESTAMP() - INTERVAL 2 SECOND WHERE id = " + fleetId);
  runIsolatedSql(project, "UPDATE outbox_message SET due_at = UTC_TIMESTAMP() - INTERVAL 2 SECOND WHERE kind = 'fleet' AND target_id = " + fleetId);
  runIsolatedSql(project, "INSERT INTO outbox_message (kind, target_id, token, due_at) SELECT 'fleet', fleet.id, fleet.dispatch_token, UTC_TIMESTAMP() - INTERVAL 2 SECOND FROM fleet WHERE fleet.id = " + fleetId + " AND NOT EXISTS (SELECT 1 FROM outbox_message WHERE kind = 'fleet' AND target_id = fleet.id AND token = fleet.dispatch_token)");
}

export function revealVirginPlanet(project, email) {
  const emailValue = quoteSql(email);
  const target = queryIsolatedSql(project,
    "SELECT target.id, CONCAT(REPLACE(galaxy.name, 'Galaxie ', ''), ':', FLOOR(solar_system.coord_y / 3) * 10 + FLOOR(solar_system.coord_x / 3) + 1, ':', target.position) " +
    "FROM player_universe membership INNER JOIN player ON player.id = membership.player_id INNER JOIN galaxy ON galaxy.universe_id = membership.universe_id INNER JOIN solar_system ON solar_system.galaxy_id = galaxy.id INNER JOIN planet target ON target.solar_system_id = solar_system.id " +
    "WHERE player.email = " + emailValue + " AND target.npc_planet = 0 AND target.owner_id IS NULL ORDER BY target.id LIMIT 1");
  const [id, coordinates] = target.split('\t');
  if (!id || !coordinates) {
    throw new Error('Aucune planète vierge ne correspond au scénario navigateur.');
  }
  runIsolatedSql(project,
    "INSERT IGNORE INTO player_discovery (player_universe_id, solar_system_id) SELECT membership.id, solar_system.id FROM player_universe membership INNER JOIN player ON player.id = membership.player_id INNER JOIN planet target ON target.id = " + Number(id) + " INNER JOIN solar_system ON solar_system.id = target.solar_system_id WHERE player.email = " + emailValue);
  return { id: Number(id), coordinates };
}
