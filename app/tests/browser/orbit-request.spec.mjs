import { test, expect } from './support/isolated-test.mjs';
import { execFileSync } from 'node:child_process';
import AxeBuilder from '@axe-core/playwright';
import { prepareOwnedColony, runIsolatedSql, queryIsolatedSql, planetCoordinates } from './support/game-fixtures.mjs';

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

test('Ancienne orbite alliée : demande à l’arrivée conservée et stationnement', async ({ browser }) => {
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
    await senderPage.getByLabel('Nom de votre nouvelle guilde').fill(senderGuild);await senderPage.getByLabel('Tag de la guilde', { exact:true }).fill(crypto.randomUUID().replaceAll('-', '').slice(0,5).toUpperCase());
    await senderPage.getByRole('button', { name: 'Fonder une guilde' }).click();
    await expect(senderPage.locator('.guild-hub-hero')).toContainText(senderGuild);
    await hostPage.getByLabel('Nom de votre nouvelle guilde').fill(hostGuild);await hostPage.getByLabel('Tag de la guilde', { exact:true }).fill(crypto.randomUUID().replaceAll('-', '').slice(0,5).toUpperCase());
    await hostPage.getByRole('button', { name: 'Fonder une guilde' }).click();
    await expect(hostPage.locator('.guild-hub-hero')).toContainText(hostGuild);

    await senderPage.goto('/diplomatie?tab=diplomacy&q='+encodeURIComponent(hostGuild));
    const hostGuildCardForSender = senderPage.getByRole('region', { name: 'Résultats de recherche de guildes' }).locator('.guild-card').filter({ has: senderPage.getByRole('heading', { name: hostGuild, exact: true }) });
    await hostGuildCardForSender.getByLabel('Traité à proposer').selectOption('allies');
    await hostGuildCardForSender.getByRole('button', { name: 'Proposer le traité' }).click();
    await expect(hostGuildCardForSender.locator('.badge')).toContainText('Proposition en attente');
    await hostPage.goto('/diplomatie?tab=diplomacy');
    const senderGuildCardForHost = hostPage.locator('.guild-card').filter({ has: hostPage.getByRole('heading', { name: senderGuild, exact: true }) });
    await senderGuildCardForHost.getByRole('button', { name: 'Accepter le traité' }).click();
    await expect(senderGuildCardForHost.locator('.badge')).toContainText('Alliés');
    await senderPage.goto('/diplomatie?tab=diplomacy');
    await expect(senderPage.locator('.guild-card').filter({ has: senderPage.getByRole('heading', { name: hostGuild, exact: true }) }).locator('.badge')).toContainText('Alliés');


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
    await form.getByLabel('Destination personnalisée', { exact: true }).check();await form.locator('[name="coordinates"]').fill(planetCoordinates(project, host.planetId));
    await form.locator('[name="orbit_hours"]').fill('1');
    await form.locator('[name="ratio"]').selectOption('0.1');
    await form.getByRole('button', { name: /Vérifier la mission/ }).click();
    await form.getByRole('button', { name: 'Estimer le trajet' }).click();
    await expect(form.locator('.flight-estimate')).toContainText('Carburant aller-retour');
    await senderPage.evaluate(() => window.scrollTo(0, 0));

    await form.getByRole('button', { name: /Envoyer la flotte/ }).click();
    await expect(senderPage.getByRole('status')).toContainText('Flotte envoyée');
    await expect(senderPage.locator('[data-status="traveling"]')).toHaveCount(1);
    await senderPage.evaluate(() => window.scrollTo(0, 0));


    const accelerate = "UPDATE fleet f INNER JOIN player_universe pu ON pu.id=f.player_universe_id INNER JOIN player p ON p.id=pu.player_id SET f.arrival_at=UTC_TIMESTAMP()-INTERVAL 2 SECOND WHERE p.email='" + sender.email + "' AND f.mission='orbit' AND f.status='traveling' AND f.destination_id=" + host.planetId;
    const expediteExisting = "UPDATE outbox_message o INNER JOIN fleet f ON o.kind='fleet' AND o.target_id=f.id AND o.token=f.dispatch_token INNER JOIN player_universe pu ON pu.id=f.player_universe_id INNER JOIN player p ON p.id=pu.player_id SET o.due_at=UTC_TIMESTAMP()-INTERVAL 2 SECOND WHERE p.email='" + sender.email + "' AND f.mission='orbit' AND f.destination_id=" + host.planetId;
    const dispatchNow = "INSERT IGNORE INTO outbox_message (kind, target_id, token, due_at) SELECT 'fleet', f.id, f.dispatch_token, UTC_TIMESTAMP()-INTERVAL 2 SECOND FROM fleet f INNER JOIN player_universe pu ON pu.id=f.player_universe_id INNER JOIN player p ON p.id=pu.player_id WHERE p.email='" + sender.email + "' AND f.mission='orbit' AND f.status='traveling' AND f.destination_id=" + host.planetId;
    sql(project, "UPDATE fleet f INNER JOIN player_universe pu ON pu.id=f.player_universe_id INNER JOIN player p ON p.id=pu.player_id SET navigation=JSON_REMOVE(navigation,'$.orbit_request') WHERE p.email='" + sender.email + "' AND f.mission='orbit' AND f.status='traveling'");
    sql(project, accelerate);
    sql(project, expediteExisting);
    sql(project, dispatchNow);
    await expect.poll(async () => {
      await senderPage.reload();
      return senderPage.locator('[data-status="orbit_pending"]').count();
    }, { timeout: 45000, intervals: [1000] }).toBe(1);

    await hostPage.goto('/flottes');
    await expect(hostPage.getByRole('heading', { name: 'Demandes d’orbite', exact: true })).toBeVisible();
    await expect(hostPage.locator('[data-live-region="orbit-requests"]')).toContainText('Ancienne mission');
    await hostPage.getByRole('button', { name: /Accepter l’orbite de la flotte/ }).click();
    await expect(hostPage.getByRole('status')).toContainText('Demande d’orbite acceptée.');
    await expect.poll(async () => {
      await senderPage.reload();
      return senderPage.locator('[data-status="orbiting"]').count();
    }, { timeout: 15000, intervals: [500] }).toBe(1);
    await expect(senderPage.locator('[data-status="orbiting"]')).toContainText(/en orbite/i);
    await expect(senderPage.locator('[data-active-fleet] [role="progressbar"]')).toHaveAttribute('aria-label', /Fin d’orbite/);
  } finally {
    await senderPage.close();
    await hostPage.close();
  }
});


test('Orbite neutre : demande au départ, accord clavier, suivi et retrait tactile', async ({ browser }, testInfo) => {
  test.setTimeout(150000);
  const senderContext=await browser.newContext({viewport:{width:390,height:844},hasTouch:true,isMobile:true});
  const hostContext=await browser.newContext();
  const senderPage=await senderContext.newPage(),hostPage=await hostContext.newPage();
  try {
    const sender=await enter(senderPage,'Neutral-Sender'),host=await enter(hostPage,'Neutral-Host');
    const project=process.env.BROWSER_ROUTER;
    prepareOwnedColony(project,sender.email);
    const senderId=Number(queryIsolatedSql(project,"SELECT pu.id FROM player_universe pu JOIN player p ON p.id=pu.player_id WHERE p.email='"+sender.email+"'"));
    const hostId=Number(queryIsolatedSql(project,"SELECT pu.id FROM player_universe pu JOIN player p ON p.id=pu.player_id WHERE p.email='"+host.email+"'"));
    runIsolatedSql(project,"INSERT INTO fleet (player_universe_id,home_planet_id,status,mission,speed_ratio,cargo,reserved_fuel,orbit_fuel,orbit_duration_seconds,travel_seconds,dispatch_token) VALUES ("+senderId+","+sender.planetId+",'idle','idle',1,'{}',0,0,0,0,'')");
    runIsolatedSql(project,"INSERT INTO fleet_ship (fleet_id,slug,tier,quantity) SELECT id,'probe','basic',1 FROM fleet WHERE player_universe_id="+senderId+" AND status='idle'");
    runIsolatedSql(project,"INSERT IGNORE INTO player_discovery (player_universe_id,solar_system_id) SELECT "+senderId+",solar_system_id FROM planet WHERE id="+host.planetId);
    runIsolatedSql(project,"DELETE d FROM player_discovery d JOIN planet p ON d.solar_system_id=p.solar_system_id WHERE d.player_universe_id="+hostId+" AND p.id="+sender.planetId);
    await hostPage.goto('/flottes');
    await senderPage.goto('/flottes?mission=orbit');
    const form=senderPage.locator('.fleet-form form').first();
    await form.locator('[name="ships[probe]"]').fill('1');
    await form.getByRole('button',{name:/Choisir la destination/}).tap();
    await form.getByLabel('Destination personnalisée',{exact:true}).check();
    await form.locator('[name="coordinates"]').fill(planetCoordinates(project,host.planetId));
    await form.locator('[name="orbit_hours"]').fill('1');
    await form.locator('[name="ratio"]').selectOption('0.1');
    await form.getByRole('button',{name:/Vérifier la mission/}).tap();
    await form.getByRole('button',{name:/Envoyer la flotte/}).tap();
    await expect(senderPage.getByRole('status')).toContainText('Flotte envoyée');
    const fleetId=Number(queryIsolatedSql(project,"SELECT id FROM fleet WHERE player_universe_id="+senderId+" AND status='traveling' AND mission='orbit'"));
    const card=hostPage.locator('[data-orbit-request="'+fleetId+'"]');
    await expect(card).toContainText('Demande reçue',{timeout:15000});
    await expect(card).toContainText('système non découvert');
    await expect(card.locator('[data-orbit-deadline]')).toHaveText(/h.*min.*s/);
    const violations=(await new AxeBuilder({page:hostPage}).include('[data-live-region="orbit-requests"]').analyze()).violations.filter(v=>['serious','critical'].includes(v.impact));
    expect(violations).toEqual([]);
    await hostPage.evaluate(()=>document.body.dataset.theme='light');
    const lightViolations=(await new AxeBuilder({page:hostPage}).include('[data-live-region="orbit-requests"]').analyze()).violations.filter(v=>['serious','critical'].includes(v.impact));
    expect(lightViolations).toEqual([]);
    await hostPage.screenshot({path:testInfo.outputPath('orbite-neutre-clair.png'),fullPage:true,animations:'disabled'});
    await hostPage.evaluate(()=>document.body.dataset.theme='dark');
    const accept=card.getByRole('button',{name:/Accepter l’orbite/});
    await accept.focus();await hostPage.waitForTimeout(5500);await expect(accept).toBeFocused();
    expect(await accept.evaluate(e=>e.matches(':focus-visible') && getComputedStyle(e).outlineStyle!=='none')).toBeTruthy();
    await hostPage.keyboard.press('Enter');
    await expect(hostPage.getByRole('status')).toContainText('Demande d’orbite acceptée');
    expect(queryIsolatedSql(project,'SELECT status FROM fleet WHERE id='+fleetId)).toBe('traveling');
    await expect(senderPage.locator('[data-orbit-request="'+fleetId+'"]')).toContainText('Accord confirmé',{timeout:15000});
    expect(await senderPage.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1)).toBeTruthy();
    await senderPage.screenshot({path:testInfo.outputPath('orbite-neutre-mobile.png'),fullPage:true,animations:'disabled'});
    await hostPage.screenshot({path:testInfo.outputPath('orbite-neutre-bureau.png'),fullPage:true,animations:'disabled'});
    await senderPage.getByRole('link',{name:'Retirer la demande et rappeler la flotte '+fleetId,exact:true}).tap();
    await senderPage.getByRole('button',{name:'Confirmer le rappel',exact:true}).tap();
    await expect(senderPage.getByText('Flotte rappelée. Le retour est programmé.',{exact:true})).toBeVisible();
    expect(queryIsolatedSql(project,"SELECT JSON_UNQUOTE(JSON_EXTRACT(navigation,'$.orbit_request.status')) FROM fleet WHERE id="+fleetId)).toBe('withdrawn');
    await expect(hostPage.locator('[data-orbit-request="'+fleetId+'"]')).toHaveCount(0,{timeout:15000});
  } finally { await senderContext.close();await hostContext.close(); }
});
