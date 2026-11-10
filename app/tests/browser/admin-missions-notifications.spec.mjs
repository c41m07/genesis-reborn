import { test, expect } from './support/isolated-test.mjs';
import { execFileSync } from 'node:child_process';
import AxeBuilder from '@axe-core/playwright';
import { runIsolatedSql, queryIsolatedSql } from './support/game-fixtures.mjs';

async function enter(page, admin = false, universe = '') {
  const suffix = crypto.randomUUID().slice(0, 8);
  const email = `signals-${suffix}@example.test`, username = `Signals-${suffix}`, password = 'Browser-test-2026!';
  await page.goto('/inscription');
  await page.getByLabel('Adresse email').fill(email);
  await page.getByLabel('Nom de commandant').fill(username);
  await page.getByLabel('Mot de passe (12 caractères minimum)', { exact: true }).fill(password);
  await page.getByLabel('Confirmer le mot de passe').fill(password);
  await page.getByRole('button', { name: 'Créer mon compte' }).click();
  await expect(page).toHaveURL(/connexion/);
  if (admin) execFileSync('docker', ['compose', '-p', process.env.BROWSER_ROUTER, '--env-file', '.env.local', 'exec', '-T', 'app', 'php', 'bin/console', 'app:admin:grant', email], { stdio: 'pipe' });
  await page.getByLabel('Adresse email').fill(email);
  await page.getByLabel('Mot de passe', { exact: true }).fill(password);
  await page.getByRole('button', { name: /Connexion/ }).click();
  const cards = page.locator('article.universe-card');
  await (universe ? cards.filter({ has: page.getByRole('heading', { name: new RegExp(universe) }) }) : cards.first()).getByRole('button').click();
  await expect(page).toHaveURL(/planetes\/\d+$/);
  const project = process.env.BROWSER_ROUTER;
  const playerId = Number(queryIsolatedSql(project, `SELECT id FROM player WHERE email='${email}'`));
  const memberId = Number(queryIsolatedSql(project, `SELECT current_player_universe_id FROM player WHERE id=${playerId}`));
  const planetId = Number(queryIsolatedSql(project, `SELECT id FROM planet WHERE owner_id=${memberId} LIMIT 1`));
  const universeId = Number(queryIsolatedSql(project, `SELECT universe_id FROM player_universe WHERE id=${memberId}`));
  return { playerId, memberId, planetId, universeId };
}

test('Admin : nouveautés personnelles, total, actualisation et archives masquées', async ({ page, browser }, testInfo) => {
  test.setTimeout(120000);
  const second = await browser.newPage(), visitor = await browser.newPage();
  const own = await enter(page, true), other = await enter(second, true), regular = await enter(visitor);
  const project = process.env.BROWSER_ROUTER;
  runIsolatedSql(project, `INSERT INTO social_message (sender_id,recipient_id,content,created_at) VALUES (${own.memberId},${regular.memberId},'Message signalé',UTC_TIMESTAMP())`);
  const messageId = Number(queryIsolatedSql(project, 'SELECT MAX(id) FROM social_message'));
  runIsolatedSql(project, `INSERT INTO message_report (message_id,reporter_id,reason,status,created_at) VALUES (${messageId},${regular.memberId},'À examiner','open',UTC_TIMESTAMP())`);
  runIsolatedSql(project, `INSERT INTO admin_audit_log (action,target_type,target_id,details,created_at) VALUES ('admin_access_denied','admin_console',0,'{}',UTC_TIMESTAMP())`);
  runIsolatedSql(project, `INSERT INTO user_feedback (player_id,type,priority,title,description,status,created_at) VALUES
    (${regular.playerId},'bug','medium','Nouveau feedback','Description du retour','open',UTC_TIMESTAMP()),
    (${regular.playerId},'other','low','Ancien feedback archivé','Historique à conserver','archived',UTC_TIMESTAMP())`);
  const totalBadge = target => target.locator('.admin-header-link [data-category="total"]');
  await page.goto('/');
  await expect(totalBadge(page)).toHaveText('3');
  await expect(page.locator('.admin-header-link')).toHaveAccessibleName('Admin 3 : ouvrir la console');
  for (const width of [1440,768,390]) {
    await page.setViewportSize({width, height:900});
    await page.goto('/');
    await expect(totalBadge(page)).toBeVisible();
    if (width === 768) await expect(page.locator('.mobile-menu .menu-unread-dot')).toBeVisible();
    if (width === 390) await expect(page.locator('.mobile-bottom-nav .menu-unread-dot')).toBeVisible();
    expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(width);
    await page.screenshot({path:testInfo.outputPath(`admin-badge-${width}.png`)});
  }
  await second.goto('/');
  await expect(totalBadge(second)).toHaveText('3');
  await page.goto('/admin/flottes');
  await expect(page.locator('.admin-sidebar [data-category="players"]')).toHaveText('2');
  await expect(page.locator('.admin-sidebar [data-category="ops"]')).toHaveText('1');
  runIsolatedSql(project, `INSERT INTO user_feedback (player_id,type,priority,title,description,status,created_at) VALUES (${regular.playerId},'bug','high','Second nouveau feedback','Autre retour','open',UTC_TIMESTAMP())`);
  await page.evaluate(() => window.dispatchEvent(new CustomEvent('genesis:refresh')));
  await expect(page.locator('.admin-sidebar [data-category="players"]')).toHaveText('3');
  await page.goto('/admin');
  await page.goto('/');
  await expect(totalBadge(page)).toHaveText('4');
  await page.goto('/admin/moderation');
  await page.goto('/admin/securite');
  await page.goto('/');
  await expect(totalBadge(page)).toHaveText('2');
  await expect(page.locator('.admin-header-link')).toHaveAccessibleName('Admin 2 : ouvrir la console');
  await second.goto('/');
  await expect(totalBadge(second)).toHaveText('4');
  await page.goto('/admin/feedbacks');
  await expect(page.locator('.feedback-admin-card')).toHaveCount(2);
  await expect(page.getByText('Ancien feedback archivé', { exact: true })).toHaveCount(0);
  await page.getByRole('combobox', { name: 'Statut', exact: true }).selectOption('archived');
  await page.getByRole('button', { name: 'Appliquer les filtres' }).click();
  await expect(page.locator('.feedback-admin-card')).toHaveCount(1);
  await expect(page.getByText('Ancien feedback archivé', { exact: true })).toBeVisible();
  await page.getByRole('combobox', { name: 'Statut', exact: true }).selectOption('all');
  await page.getByRole('button', { name: 'Appliquer les filtres' }).click();
  await expect(page.locator('.feedback-admin-card')).toHaveCount(3);
  await page.goto('/');
  await expect(totalBadge(page)).toBeHidden();
  await expect(page.locator('.admin-header-link')).toHaveAccessibleName('Admin : ouvrir la console');
  await expect(page.locator('.mobile-bottom-nav .menu-unread-dot')).toBeHidden();
  runIsolatedSql(project, `INSERT INTO user_feedback (player_id,type,priority,title,description,status,created_at) VALUES (${regular.playerId},'other','low','Retour après lecture','Nouveauté réelle','open',UTC_TIMESTAMP())`);
  await page.evaluate(() => window.dispatchEvent(new CustomEvent('genesis:refresh')));
  await expect(totalBadge(page)).toHaveText('1');
  await expect(page.locator('.admin-header-link')).toHaveAccessibleName('Admin 1 : ouvrir la console');
  await expect(page.locator('.mobile-bottom-nav .menu-unread-dot')).toBeVisible();
  for (const route of ['/admin/flottes', '/admin/notifications']) {
    await visitor.goto(route);
    await expect(visitor.getByRole('heading', { name: 'Accès refusé' })).toBeVisible();
  }
  await second.close();
  await visitor.close();
});

test('Admin : huit missions, univers, statuts, pagination, clavier et responsive', async ({ page, browser }, testInfo) => {
  test.setTimeout(120000);
  const peer = await browser.newPage();
  const own = await enter(page, true), other = await enter(peer, false, 'Turbo');
  expect(own.universeId).not.toBe(other.universeId);
  const project = process.env.BROWSER_ROUTER;
  const missions = [['explore','completed'],['transport','returning'],['colonize','traveling'],['attack','attacking'],['spy','completed'],['defend','defending'],['orbit','orbit_pending'],['conquer','occupying'],['orbit','orbiting'],['attack','completed']];
  const row = (member, mission, status) => `(${member.memberId},${member.planetId},${member.planetId},'${mission}','${status}',1,'{}',0,0,0,UTC_TIMESTAMP(),${status === 'completed' ? 'NULL' : 'DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 HOUR)'},3600,UUID())`;
  const columns = '(player_universe_id,home_planet_id,destination_id,mission,status,speed_ratio,cargo,reserved_fuel,orbit_fuel,orbit_duration_seconds,departure_at,arrival_at,travel_seconds,dispatch_token)';
  runIsolatedSql(project, `INSERT INTO fleet ${columns} VALUES ${missions.map(([mission,status], index) => row(index % 2 === 0 ? own : other, mission, status)).join(',')}`);
  await page.goto('/admin/flottes');
  await expect(page.locator('tbody tr')).toHaveCount(7);
  await page.getByRole('combobox', { name: 'Statut', exact: true }).selectOption('');
  await page.getByRole('button', { name: 'Filtrer les missions' }).click();
  await expect(page.locator('tbody tr')).toHaveCount(10);
  await page.getByRole('combobox', { name: 'Mission', exact: true }).selectOption('transport');
  await page.getByRole('combobox', { name: 'Statut', exact: true }).selectOption('returning');
  await page.getByRole('button', { name: 'Filtrer les missions' }).click();
  await expect(page.locator('tbody tr')).toHaveCount(1);
  await page.getByRole('combobox', { name: 'Univers', exact: true }).selectOption(String(own.universeId));
  await page.getByRole('button', { name: 'Filtrer les missions' }).click();
  await expect(page.getByText('Aucune mission ne correspond aux filtres.', { exact: true })).toBeVisible();
  runIsolatedSql(project, `INSERT INTO fleet ${columns} VALUES ${Array.from({length:55}, () => row(own,'explore','completed')).join(',')}`);
  await page.getByRole('combobox', { name: 'Mission', exact: true }).selectOption('explore');
  await page.getByRole('combobox', { name: 'Statut', exact: true }).selectOption('completed');
  await page.getByRole('button', { name: 'Filtrer les missions' }).click();
  await expect(page.locator('tbody tr')).toHaveCount(50);
  await page.getByRole('link', { name: 'Page suivante' }).click();
  await expect(page.locator('tbody tr')).toHaveCount(6);
  await expect(page.getByRole('combobox', { name: 'Univers', exact: true })).toHaveValue(String(own.universeId));
  await expect(page.getByRole('combobox', { name: 'Mission', exact: true })).toHaveValue('explore');
  for (const theme of ['dark','light']) {
    runIsolatedSql(project, `UPDATE player SET preferences=JSON_SET(CASE WHEN JSON_TYPE(preferences)='OBJECT' THEN preferences ELSE JSON_OBJECT() END,'$.theme','${theme}') WHERE id=${own.playerId}`);
    for (const width of [1440,768,390]) {
      await page.setViewportSize({ width, height:900 });
      await page.reload();
      expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(width);
      await page.screenshot({ path:testInfo.outputPath(`missions-${theme}-${width}.png`), fullPage:true });
    }
    const axe = await new AxeBuilder({page}).withTags(['wcag2a','wcag2aa','wcag21aa']).analyze();
    expect(axe.violations).toEqual([]);
  }
  const region = page.getByRole('region', {name:'Historique des missions, tableau défilant'});
  await region.focus();
  await page.keyboard.press('ArrowRight');
  await expect.poll(() => region.evaluate(node => node.scrollLeft)).toBeGreaterThan(0);
  await peer.close();
});

test('Surveillance : fiches privées, transports unifiés, rapports et consultation sans action sur la flotte', async ({ page, browser }, testInfo) => {
  test.setTimeout(120000);
  const playerPage = await browser.newPage();
  const admin = await enter(page, true), player = await enter(playerPage);
  const project = process.env.BROWSER_ROUTER;
  const fleet = (mission, status, due) => {
    runIsolatedSql(project, `INSERT INTO fleet (player_universe_id,home_planet_id,destination_id,mission,status,speed_ratio,cargo,reserved_fuel,orbit_fuel,orbit_duration_seconds,departure_at,arrival_at,travel_seconds,dispatch_token)
      VALUES (${player.memberId},${player.planetId},${admin.planetId},'${mission}','${status}',0.5,'{"metal":100,"hydrogen":250}',50,10,3600,DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 HOUR),${due},3600,UUID())`);
    return Number(queryIsolatedSql(project, 'SELECT MAX(id) FROM fleet'));
  };
  const transportId = fleet('transport','returning','DATE_SUB(UTC_TIMESTAMP(),INTERVAL 5 MINUTE)');
  const attackId = fleet('attack','returning','DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 HOUR)');
  const spyId = fleet('spy','completed','NULL');
  runIsolatedSql(project, `INSERT INTO fleet_ship (fleet_id,slug,tier,quantity) VALUES (${transportId},'transport','basic',3)`);
  const snapshot = JSON.stringify({sender:'Commandant inspecté',recipient:'Destinataire',origin:'Colonie de départ',destination:'Colonie cible',sent:{metal:100},delivered:{metal:80},returned:{metal:20}});
  runIsolatedSql(project, `INSERT INTO transport_report (fleet_id,sender_id,recipient_id,status,snapshot,created_at,resolved_at) VALUES (${transportId},${player.memberId},${admin.memberId},'delivered','${snapshot}',UTC_TIMESTAMP(),UTC_TIMESTAMP())`);
  runIsolatedSql(project, `INSERT INTO combat_event (planet_id,status,started_at,resolve_at,seed,dispatch_token,current_round,combat_snapshot) VALUES (${admin.planetId},'resolved',DATE_SUB(UTC_TIMESTAMP(),INTERVAL 10 MINUTE),DATE_SUB(UTC_TIMESTAMP(),INTERVAL 5 MINUTE),123,UUID(),1,'{}')`);
  const eventId = Number(queryIsolatedSql(project, 'SELECT MAX(id) FROM combat_event'));
  runIsolatedSql(project, `INSERT INTO combat_participant (combat_event_id,fleet_id,player_universe_id,role,arrival_at,arrival_round) VALUES (${eventId},${attackId},${player.memberId},'attacker',UTC_TIMESTAMP(),1)`);
  const combatSnapshot = JSON.stringify({participants:[{fleet_id:attackId,player_universe_id:player.memberId,role:'attacker',camp:'attackers',units:[]}],losses:{},survivors:{},defenses:[],loot:{fleets:{}},capture:null});
  runIsolatedSql(project, `INSERT INTO combat_log (combat_event_id,outcome,rounds,snapshot,resolved_at) VALUES (${eventId},'attacker_wins','[]','${combatSnapshot}',UTC_TIMESTAMP())`);
  const combatId = Number(queryIsolatedSql(project, 'SELECT MAX(id) FROM combat_log'));
  const intel = JSON.stringify({resources:{metal:{label:'Métal',stock:500,rate:0.15,capacity:200000}}});
  runIsolatedSql(project, `INSERT INTO espionage_report (sender_id,target_id,result,created_at,probes_sent,intel_categories,detection_chance,sender_espionage_level,target_espionage_level,target_counter_espionage_level,snapshot)
    VALUES (${player.memberId},${admin.planetId},'success',UTC_TIMESTAMP(),1,1,0,1,0,0,'${intel}')`);
  const intelId = Number(queryIsolatedSql(project, 'SELECT MAX(id) FROM espionage_report'));
  const before = queryIsolatedSql(project, `SELECT MD5(CONCAT(status,dispatch_token,cargo,reserved_fuel)) FROM fleet WHERE id=${transportId}`);
  await page.goto('/admin/flottes');
  await expect(page.locator('.admin-sidebar a[href="/admin/transports"]')).toHaveCount(0);
  await expect(page.locator('tbody tr')).toHaveCount(2);
  const senderName = queryIsolatedSql(project, `SELECT username FROM player WHERE id=${player.playerId}`);
  await page.getByRole('searchbox', {name:'Joueur ou cible', exact:true}).fill(senderName);
  await page.getByRole('button', {name:'Filtrer les missions'}).click();
  await expect(page.locator('tbody tr')).toHaveCount(2);
  await page.getByRole('button', {name:'Actualiser le suivi'}).click();
  await expect(page.getByRole('status')).toHaveText('Suivi actualisé.');
  await page.getByRole('link', {name:`n° ${transportId} · Transport`, exact:true}).click();
  await expect(page.getByRole('heading', {name:`Flotte n° ${transportId}`,exact:true})).toBeVisible();
  await expect(page.getByRole('heading', {name:'Rapport du transport',exact:true})).toBeVisible();
  await expect(page.getByRole('region', {name:'Composition actuelle de la flotte'}).getByRole('cell', {name:'3',exact:true})).toBeVisible();
  await expect(page.getByRole('status')).toContainText('Échéance dépassée');
  await expect(page.getByRole('region', {name:'Ressources transportées, défilement horizontal'})).toContainText('80,00');
  expect(queryIsolatedSql(project, `SELECT MD5(CONCAT(status,dispatch_token,cargo,reserved_fuel)) FROM fleet WHERE id=${transportId}`)).toBe(before);
  expect(Number(queryIsolatedSql(project, `SELECT COUNT(*) FROM admin_audit_log WHERE action='admin_fleet_view' AND target_id=${transportId}`))).toBeGreaterThan(0);
  for (const width of [1440,768,390]) {
    await page.setViewportSize({width,height:900});
    expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(width);
    await page.screenshot({path:testInfo.outputPath(`mission-detail-${width}.png`),fullPage:true});
  }
  await page.goto('/admin/transports');
  await expect(page).toHaveURL(/admin\/flottes\?mission=transport/);
  await expect(page.locator('tbody tr')).toHaveCount(1);
  await page.goto(`/admin/flottes/${attackId}/combat/${combatId}`);
  await expect(page.getByRole('heading', {name:'Rapport de combat',exact:true})).toBeVisible();
  await expect(page.locator('body')).toHaveClass(/admin-console/);
  expect((await page.goto(`/admin/flottes/${transportId}/combat/${combatId}`)).status()).toBe(404);
  await page.goto(`/admin/flottes/${spyId}/renseignement/${intelId}`);
  await expect(page.getByRole('heading', {name:'Rapport d’espionnage',exact:true})).toBeVisible();
  await expect(page.locator('.admin-monitor-note')).toContainText('mission d’origine n’est pas identifiée');
  expect((await page.goto(`/admin/flottes/${transportId}/renseignement/${intelId}`)).status()).toBe(404);
  for (const path of [`/admin/flottes/${transportId}`, `/admin/flottes/${attackId}/combat/${combatId}`, `/admin/flottes/${spyId}/renseignement/${intelId}`]) {
    await playerPage.goto(path);
    await expect(playerPage.getByRole('heading', {name:'Accès refusé'})).toBeVisible();
  }
  await playerPage.goto(`/rapports/espionnage/${intelId}`);
  await expect(playerPage.getByRole('heading', {name:'Rapport d’espionnage',exact:true})).toBeVisible();
  await playerPage.close();
});

test('Accueil admin : rubriques séparées, anciens liens, clavier et thèmes accessibles', async ({ page }, testInfo) => {
  test.setTimeout(120000);
  const admin = await enter(page, true);
  await page.goto('/admin#admin-universes');
  await expect(page).toHaveURL(/admin\/univers#admin-universes$/);
  await expect(page.locator('#admin-universes')).toBeVisible();
  const routes = {'joueurs':'Joueurs','univers':'Univers','moderation':'Modération','securite':'Sécurité','exploitation':'Exploitation','journal':'Journal des actions'};
  for (const [route,title] of Object.entries(routes)) {
    await page.goto('/admin/' + route);
    await expect(page.getByRole('heading', {name:title,exact:true,level:1})).toBeVisible();
    await expect(page.getByRole('navigation', {name:'Console d’administration'}).locator('a[aria-current="page"]')).toHaveCount(1);
  }
  for (const theme of ['dark','light']) {
    runIsolatedSql(process.env.BROWSER_ROUTER, `UPDATE player SET preferences=JSON_SET(CASE WHEN JSON_TYPE(preferences)='OBJECT' THEN preferences ELSE JSON_OBJECT() END,'$.theme','${theme}') WHERE id=${admin.playerId}`);
    for (const width of [1440,768,390]) {
      await page.setViewportSize({width,height:900});
      await page.goto('/admin');
      await expect(page.getByRole('heading', {name:'Vue d’ensemble',exact:true})).toBeVisible();
      await expect(page.locator('meta[name="robots"]')).toHaveAttribute('content','noindex');
      expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(width);
      await page.screenshot({path:testInfo.outputPath(`admin-home-${theme}-${width}.png`),fullPage:true});
    }
    const axe = await new AxeBuilder({page}).withTags(['wcag2a','wcag2aa','wcag21aa']).analyze();
    expect(axe.violations).toEqual([]);
  }
  await page.getByRole('button', {name:'Menu d’administration'}).focus();
  await page.keyboard.press('Enter');
  await page.getByRole('navigation', {name:'Console d’administration'}).getByRole('link', {name:/^Joueurs et modération/}).click();
  await page.getByRole('button', {name:'Menu d’administration'}).click();
  await page.getByRole('navigation', {name:'Console d’administration'}).getByRole('link', {name:'Joueurs',exact:true}).focus();
  await page.keyboard.press('Enter');
  await expect(page).toHaveURL(/admin\/joueurs$/);
  await expect(page.getByRole('button', {name:'Menu d’administration'})).toHaveAttribute('aria-expanded','false');
});
