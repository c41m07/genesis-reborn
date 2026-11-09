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

async function post(page, url, form) {
  return page.evaluate(async ({url,form}) => (await fetch(url,{method:'POST',body:new URLSearchParams(form)})).status,{url,form});
}

test('Dossiers : création depuis joueur, notes immuables, clôture, historique et accès', async ({page,browser},testInfo) => {
  test.setTimeout(150000);const visitor=await browser.newPage();const user=await enter(visitor);const admin=await enter(page,true);const project=process.env.BROWSER_ROUTER;
  await page.goto(`/admin/dossiers/nouveau?membership_id=${user.memberId}`);
  await page.getByLabel('Titre du dossier').fill('Contrôle <script>interdit</script>');await page.getByLabel('Observations initiales').fill('Vérification sans conclusion automatique.');
  const creation=await page.locator('main form').evaluate(f=>Object.fromEntries(new FormData(f)));await page.getByRole('button',{name:'Créer le dossier',exact:true}).click();
  await expect(page).toHaveURL(/admin\/dossiers\/\d+$/);const id=Number(page.url().split('/').pop());
  await expect(page.locator('h1')).toHaveText('Contrôle <script>interdit</script>');
  expect(await post(page,'/admin/dossiers/nouveau',creation)).toBe(200);
  expect(Number(queryIsolatedSql(project,`SELECT COUNT(*) FROM admin_case WHERE creation_key='${creation.request_key}'`))).toBe(1);
  const form=page.locator('main form');await page.getByLabel('État du dossier').selectOption('investigating');await page.getByLabel('Nouvelle note').fill('Première observation conservée.');
  const update=await form.evaluate(f=>Object.fromEntries(new FormData(f)));await page.getByRole('button',{name:'Enregistrer le suivi'}).click();
  await expect(page.locator('ol li')).toHaveCount(1);await post(page,`/admin/dossiers/${id}/suivi`,update);
  expect(Number(queryIsolatedSql(project,`SELECT COUNT(*) FROM admin_case_note WHERE case_file_id=${id}`))).toBe(1);
  const stale=await form.evaluate(f=>Object.fromEntries(new FormData(f)));
  await post(page,`/admin/dossiers/${id}/suivi`,{...stale,status:'open',expected_status:'open',body:'Ancienne fiche'});
  expect(queryIsolatedSql(project,`SELECT status FROM admin_case WHERE id=${id}`)).toBe('investigating');
  await page.getByLabel('État du dossier').selectOption('closed');await page.getByLabel('Nouvelle note').fill('Examen terminé, dossier clos.');await page.getByRole('button',{name:'Enregistrer le suivi'}).click();
  await expect(page.locator('ol li')).toHaveCount(2);await expect(page.locator('ol')).toContainText('Première observation conservée.');
  expect(await post(page,`/admin/dossiers/${id}/suivi`,{...update,_token:'invalid'})).toBe(403);
  expect((await visitor.goto(`/admin/dossiers/${id}`)).status()).toBe(403);
  await page.goto('/admin/dossiers');await expect(page.locator(`a[href='/admin/dossiers/${id}']`)).toHaveCount(0);
  await page.getByRole('combobox',{name:'État',exact:true}).selectOption('');await page.getByRole('button',{name:'Filtrer les dossiers'}).click();await expect(page.locator(`a[href='/admin/dossiers/${id}']`)).toBeVisible();
  await page.locator(`a[href='/admin/dossiers/${id}']`).click();
  for(const theme of ['dark','light']) {
    runIsolatedSql(project,`UPDATE player SET preferences=JSON_SET(CASE WHEN JSON_TYPE(preferences)='OBJECT' THEN preferences ELSE JSON_OBJECT() END,'$.theme','${theme}') WHERE id=${admin.playerId}`);
    for(const width of [1440,390]) {
      await page.setViewportSize({width,height:900});await page.reload();await expect(page.locator('body')).toHaveAttribute('data-theme',theme);expect(await page.evaluate(()=>document.documentElement.scrollWidth)).toBeLessThanOrEqual(width);
      expect((await new AxeBuilder({page}).withTags(['wcag2a','wcag2aa','wcag21aa']).analyze()).violations).toEqual([]);
      await page.screenshot({path:testInfo.outputPath(`case-${theme}-${width}.png`),fullPage:true});
    }
  }
  await visitor.close();
});

test('Alertes : trois signaux, dossier relié et seuils propres à l’univers', async ({page,browser},testInfo) => {
  test.setTimeout(150000);const peer=await browser.newPage();const target=await enter(peer);const own=await enter(page,true);const project=process.env.BROWSER_ROUTER;
  expect(own.universeId).toBe(target.universeId);
  const columns='(player_universe_id,home_planet_id,destination_id,mission,status,speed_ratio,cargo,reserved_fuel,orbit_fuel,orbit_duration_seconds,departure_at,arrival_at,travel_seconds,dispatch_token)';
  const row=mission=>`(${own.memberId},${own.planetId},${target.planetId},'${mission}','completed',1,'{}',0,0,0,UTC_TIMESTAMP(),NULL,3600,UUID())`;
  runIsolatedSql(project,`INSERT INTO fleet ${columns} VALUES ${[...Array(5).fill('transport'),...Array(10).fill('attack')].map(row).join(',')}`);
  runIsolatedSql(project,`INSERT INTO fleet_mission_trace (fleet_id,universe_id,sender_id,recipient_id,mission,target_was_player,departed_at,snapshot) SELECT id,${own.universeId},${own.memberId},${target.memberId},mission,1,UTC_TIMESTAMP(),'{}' FROM fleet WHERE player_universe_id=${own.memberId} AND status='completed'`);
  runIsolatedSql(project,`INSERT INTO fleet ${columns} VALUES (${own.memberId},${own.planetId},${target.planetId},'transport','returning',1,'{}',0,0,0,UTC_TIMESTAMP()-INTERVAL 1 HOUR,UTC_TIMESTAMP()-INTERVAL 8 MINUTE,3600,UUID())`);
  await page.goto(`/admin/surveillance?universe=${own.universeId}`);
  await expect(page.getByText('5 transports en 24 h',{exact:false})).toBeVisible();await expect(page.getByText('10 attaques en 24 h',{exact:false})).toBeVisible();
  await expect(page.getByRole('link',{name:'Ouvrir un dossier pour ce signal'})).toHaveCount(3);
  await page.getByRole('link',{name:'Ouvrir un dossier pour ce signal'}).first().click();await page.getByRole('button',{name:'Créer le dossier',exact:true}).click();
  await expect(page.locator('main')).toContainText('Signal observé');const caseUrl=page.url();
  await page.goto(`/admin/surveillance?universe=${own.universeId}`);await expect(page.locator(`a[href='${new URL(caseUrl).pathname}']`)).toBeVisible();
  await page.getByLabel('Retard de mission supérieur à (minutes)').fill('10');await page.getByLabel('Transports entre deux joueurs en 24 h').fill('6');await page.getByLabel('Attaques contre un joueur en 24 h').fill('11');await page.getByRole('button',{name:'Enregistrer les seuils'}).click();
  await expect(page.locator('main')).toContainText('Aucun signal ne dépasse');
  const stored=JSON.parse(queryIsolatedSql(project,`SELECT game_settings FROM universe WHERE id=${own.universeId}`));expect(stored.admin_surveillance).toEqual({late_minutes:10,transport_count:6,attack_count:11});
  expect(Number(queryIsolatedSql(project,`SELECT COUNT(*) FROM admin_case WHERE universe_id=${own.universeId}`))).toBe(1);
  await page.setViewportSize({width:390,height:900});expect((await new AxeBuilder({page}).withTags(['wcag2a','wcag2aa','wcag21aa']).analyze()).violations).toEqual([]);
  await page.screenshot({path:testInfo.outputPath('alerts-390.png'),fullPage:true});await peer.close();
});
