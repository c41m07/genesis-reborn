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

const future=minutes=>new Date(Date.now()+minutes*60000).toISOString().slice(0,16);
async function define(page,model,own,name) {
  await page.goto(`/admin/events/assistant?model=${model}`);await page.getByLabel('Nom de l’événement').fill(name);await page.getByLabel('Description pour les joueurs').fill('Événement préparé avec aperçu.');
  await page.getByLabel('Univers concerné').selectOption(String(own.universeId));await page.getByLabel('Début (UTC)').fill(future(10));await page.getByLabel('Fin (UTC)').fill(future(60));
}

test('Assistant : défi, aperçu sans publication, contributions et boîte attribuée automatiquement',async({page,browser},testInfo)=>{
  test.setTimeout(180000);const peer=await browser.newPage();const other=await enter(peer);const own=await enter(page,true);const project=process.env.BROWSER_ROUTER;const name='Défi guidé '+crypto.randomUUID().slice(0,6);
  await define(page,'challenge',own,name);await page.getByLabel('Objectif collectif à atteindre').fill('3');await page.getByLabel('Contribution minimale par joueur').fill('2');await page.getByLabel('Un objet par participant éligible').selectOption('resource_box');await page.getByLabel('Format si la récompense est une boîte').selectOption('small');await page.getByRole('button',{name:'Voir l’aperçu'}).click();
  await expect(page.getByRole('heading',{name:'Aperçu avant planification'})).toBeVisible();await expect(page.locator('main')).toContainText('4 000,00');expect(Number(queryIsolatedSql(project,"SELECT COUNT(*) FROM boost_event WHERE JSON_UNQUOTE(JSON_EXTRACT(configuration,'$.guided'))='true'"))).toBe(0);
  const key=page.url().split('/').pop();const form=page.locator('main form');const data=await form.evaluate(f=>Object.fromEntries(new FormData(f)));const action=await form.getAttribute('action');
  await page.getByRole('button',{name:'Confirmer et planifier'}).click();await expect(page).toHaveURL(/admin\/defis\/\d+$/);const id=Number(page.url().split('/').pop());
  await expect(page.locator('h1')).toHaveText(name);await post(page,action,data);expect(Number(queryIsolatedSql(project,`SELECT COUNT(*) FROM boost_event WHERE event_key='guided-${key}'`))).toBe(1);expect(await post(page,action,{...data,_token:'invalid'})).toBe(403);
  expect((await peer.goto(`/admin/defis/${id}`)).status()).toBe(403);await peer.goto(`/evenements/defis/${id}`);await expect(peer.locator('h1')).toHaveText(name);
  for(const theme of ['dark','light']) {
    runIsolatedSql(project,`UPDATE player SET preferences=JSON_SET(CASE WHEN JSON_TYPE(preferences)='OBJECT' THEN preferences ELSE JSON_OBJECT() END,'$.theme','${theme}') WHERE id=${own.playerId}`);
    for(const width of [1440,390]) {await page.setViewportSize({width,height:900});await page.reload();await expect(page.locator('body')).toHaveAttribute('data-theme',theme);expect(await page.evaluate(()=>document.documentElement.scrollWidth)).toBeLessThanOrEqual(width);expect((await new AxeBuilder({page}).withTags(['wcag2a','wcag2aa','wcag21aa']).analyze()).violations).toEqual([]);await page.screenshot({path:testInfo.outputPath(`guided-${theme}-${width}.png`),fullPage:true});}
  }
  runIsolatedSql(project,`UPDATE boost_event SET scheduled_at=UTC_TIMESTAMP()-INTERVAL 1 MINUTE WHERE id=${id}`);
  await expect.poll(()=>queryIsolatedSql(project,`SELECT status FROM boost_event WHERE id=${id}`),{timeout:20000}).toBe('active');
  runIsolatedSql(project,`INSERT INTO boost_event_contribution (event_id,player_universe_id,source_key,value) VALUES (${id},${own.memberId},'ui-own',2),(${id},${other.memberId},'ui-other',1)`);
  runIsolatedSql(project,`UPDATE boost_event SET total_contribution=3,ends_at=UTC_TIMESTAMP()-INTERVAL 2 SECOND WHERE id=${id}`);
  await expect.poll(()=>queryIsolatedSql(project,`SELECT status FROM boost_event WHERE id=${id}`),{timeout:20000}).toBe('closed');
  const rewardKey=`event-guided-${key}-player-${own.memberId}`;expect(queryIsolatedSql(project,`SELECT kind FROM player_boost WHERE source_key='${rewardKey}'`)).toBe('resource_box');expect(Number(queryIsolatedSql(project,`SELECT COUNT(*) FROM player_boost WHERE source_key='event-guided-${key}-player-${other.memberId}'`))).toBe(0);
  await page.reload();await expect(page.locator('table')).toContainText('Boîte');await expect(page.locator('table tbody tr')).toHaveCount(2);
  await peer.goto(`/evenements/defis/${id}`);await expect(peer.locator('main')).toContainText('Vous n’avez pas rempli');
  await page.goto(`/evenements/defis/${id}`);await expect(page.locator('main')).toContainText('disponible dans votre inventaire');await page.setViewportSize({width:390,height:900});expect((await new AxeBuilder({page}).withTags(['wcag2a','wcag2aa','wcag21aa']).analyze()).violations).toEqual([]);await page.screenshot({path:testInfo.outputPath('player-challenge-390.png'),fullPage:true});
  await page.goto('/boosts');const box=page.locator('.resource-box-card');await box.getByLabel('Colonie de destination').selectOption(String(own.planetId));await box.getByRole('button').click();await expect(page.locator('.flash.success')).toContainText('Boîte ouverte');expect(queryIsolatedSql(project,`SELECT status FROM player_boost WHERE source_key='${rewardKey}'`)).toBe('used');
  const foreign=await browser.newPage();await enter(foreign,false,'Turbo');expect((await foreign.goto(`/evenements/defis/${id}`)).status()).toBe(404);await foreign.close();await peer.close();
});

test('Assistant : bonus annoncé, annulation confirmée et ancien aperçu protégé',async({page})=>{
  test.setTimeout(120000);const own=await enter(page,true);const project=process.env.BROWSER_ROUTER;const name='Bonus guidé '+crypto.randomUUID().slice(0,6);
  await define(page,'bonus',own,name);await page.getByLabel('Bonus de production (%)').fill('15');await page.getByRole('button',{name:'Voir l’aperçu'}).click();await expect(page.locator('main')).toContainText('Production des ressources +15 %');await page.getByRole('link',{name:'← Modifier les réglages'}).click();await expect(page.getByLabel('Nom de l’événement')).toHaveValue(name);await expect(page.getByLabel('Bonus de production (%)')).toHaveValue('15');await page.getByRole('button',{name:'Voir l’aperçu'}).click();const key=page.url().split('/').pop();await page.getByRole('button',{name:'Confirmer et planifier'}).click();
  const id=Number(queryIsolatedSql(project,`SELECT id FROM game_event WHERE JSON_UNQUOTE(JSON_EXTRACT(parameters,'$.guided_key'))='${key}'`));const article=page.locator('article.panel').filter({has:page.getByRole('heading',{name,exact:true})});await expect(article).toContainText('Production +15 %');
  await article.getByLabel('Motif d’annulation').fill('Report de la fenêtre');await article.getByRole('button',{name:'Annuler cet événement guidé'}).click();expect(queryIsolatedSql(project,`SELECT status FROM game_event WHERE id=${id}`)).toBe('planned');
  await article.getByRole('checkbox').check();await article.getByRole('button',{name:'Annuler cet événement guidé'}).click();await expect(page.locator('.flash.success')).toContainText('Événement annulé');expect(queryIsolatedSql(project,`SELECT status FROM game_event WHERE id=${id}`)).toBe('cancelled');
});

test('Assistant sans JavaScript : annonce globale et parcours clavier',async({browser})=>{
  test.setTimeout(120000);const context=await browser.newContext({javaScriptEnabled:false});const page=await context.newPage();const own=await enter(page,true);const name='Annonce sans JS '+crypto.randomUUID().slice(0,6);
  await define(page,'announcement',own,name);await page.getByLabel('Univers concerné').selectOption('0');await page.getByRole('button',{name:'Voir l’aperçu'}).click();await expect(page.locator('main')).toContainText('Tous les univers');await page.getByRole('button',{name:'Confirmer et planifier'}).focus();await page.keyboard.press('Enter');await expect(page.locator('main')).toContainText(name);await context.close();
});


test('Suppression : défi inutilisé supprimé, événement commencé archivé et accès protégés',async({page,browser},testInfo)=>{
  test.setTimeout(120000);const own=await enter(page,true), project=process.env.BROWSER_ROUTER;
  await define(page,'challenge',own,'Défi à supprimer');await page.getByLabel('Objectif collectif à atteindre').fill('3');
  await page.getByRole('button',{name:'Voir l’aperçu'}).click();await page.getByRole('button',{name:'Confirmer et planifier'}).click();
  await expect(page).toHaveURL(/admin\/defis\/\d+$/);const id=Number(page.url().split('/').pop());
  expect(await post(page,`/admin/defis/${id}/retirer`,{_token:'invalid',confirmed:'1',reason:'Test'})).toBe(403);
  const removal=page.locator('form[action$="/retirer"]');const data=await removal.evaluate(f=>Object.fromEntries(new FormData(f)));
  await post(page,`/admin/defis/${id}/retirer`,{...data,reason:'Sans confirmation'});
  expect(Number(queryIsolatedSql(project,`SELECT COUNT(*) FROM boost_event WHERE id=${id}`))).toBe(1);
  await page.goto(`/admin/defis/${id}`);await page.locator('details').getByText('Supprimer ce défi',{exact:true}).click();
  await page.getByLabel('Motif de suppression').fill('Doublon');await page.getByLabel('Je confirme la suppression ou le retrait de cet élément.').check();
  await page.getByRole('button',{name:'Supprimer le défi',exact:true}).click();await expect(page).toHaveURL(/admin\/defis$/);
  expect(Number(queryIsolatedSql(project,`SELECT COUNT(*) FROM boost_event WHERE id=${id}`))).toBe(0);
  expect(Number(queryIsolatedSql(project,`SELECT COUNT(*) FROM game_event WHERE JSON_EXTRACT(parameters,'$.challenge_id')=${id}`))).toBe(0);
  runIsolatedSql(project,`INSERT INTO game_event (universe_id,name,description,type,status,starts_at,ends_at,parameters,visible_to_players) VALUES (${own.universeId},'Annonce à retirer','Test','announcement','active',UTC_TIMESTAMP()-INTERVAL 1 HOUR,UTC_TIMESTAMP()+INTERVAL 1 DAY,'{}',1)`);
  const eventId=Number(queryIsolatedSql(project,"SELECT id FROM game_event WHERE name='Annonce à retirer'"));
  await page.goto('/admin/events');const article=page.locator('article').filter({has:page.locator(`form[action='/admin/events/${eventId}/retirer']`)});
  await article.locator('summary').click();await article.getByLabel('Motif de suppression').fill('Nettoyage');await article.getByLabel('Je confirme la suppression ou le retrait de cet élément.').check();
  await page.setViewportSize({width:390,height:900});expect((await new AxeBuilder({page}).withTags(['wcag2a','wcag2aa','wcag21aa']).analyze()).violations).toEqual([]);
  await article.getByRole('button',{name:'Supprimer l’événement',exact:true}).click();await expect(page.locator('article')).toHaveCount(0);
  await page.getByRole('link',{name:'Historique des retraits',exact:true}).click();await expect(page.locator('main')).toContainText('Annonce à retirer');
  await page.screenshot({path:testInfo.outputPath('event-removal-history-390.png'),fullPage:true});
  const peer=await browser.newPage();await enter(peer);
  expect(await post(peer,`/admin/events/${eventId}/retirer`,{_token:'invalid',confirmed:'1',reason:'Interdit'})).toBe(403);
  await peer.close();
});


test('Rotations : créer, activer, suivre depuis son univers et annuler sans recréation',async({page,browser},testInfo)=>{
  test.setTimeout(240000);const own=await enter(page,true),peer=await browser.newPage(),other=await enter(peer),project=process.env.BROWSER_ROUTER;
  const tick=()=>execFileSync('docker',['compose','-p',project,'--env-file','.env.local','exec','-T','app','php','bin/console','app:boost-events:tick'],{stdio:'pipe'});
  await page.goto(`/admin/rotations?universe=${own.universeId}`);
  for (const key of ['exploration','combat','transport']) {
    const form=page.locator('form').filter({has:page.locator(`input[name="key"][value="${key}"]`)});
    await form.getByLabel('Motif du changement').fill('Préparer une rotation dédiée');await form.getByRole('button',{name:'Désactiver ce modèle'}).click();
  }
  // Préparer un historique terminé pour réserver la semaine courante au modèle créé dans ce scénario.
  // Le moteur a pu créer une rotation avant la désactivation des modèles.
  runIsolatedSql(project,`UPDATE boost_event SET event_key=CONCAT('weekly-',DATE_FORMAT(DATE_SUB(scheduled_at,INTERVAL 7 DAY),'%Y%m%d'),'-u',universe_id,'-',template_key),scheduled_at=DATE_SUB(scheduled_at,INTERVAL 7 DAY),status='closed',ends_at=UTC_TIMESTAMP()-INTERVAL 1 SECOND,closed_at=UTC_TIMESTAMP()-INTERVAL 1 SECOND WHERE universe_id=${own.universeId} AND event_key LIKE 'weekly-%'`);
  const historyCount=Number(queryIsolatedSql(project,`SELECT COUNT(*) FROM boost_event WHERE universe_id=${own.universeId}`));
  const name='Rotation navigateur '+crypto.randomUUID().slice(0,6);
  await page.getByLabel('Nom de la rotation').fill(name);await page.getByLabel('Consignes pour les joueurs').fill('Découvrir des systèmes pour entrer au classement.');
  await page.getByLabel('Objectif suivi').selectOption('exploration');await page.getByLabel('Format de la rotation').selectOption('competitive');await page.getByLabel('Attribution du boost').selectOption('top3');
  await page.getByLabel('Activer ce modèle dès sa création').uncheck();await page.getByLabel('Motif de création').fill('Nouvelle animation');
  await page.getByRole('button',{name:'Créer la rotation hebdomadaire'}).click();await expect(page.getByRole('alert')).toContainText('objectif global');await expect(page.getByLabel('Nom de la rotation')).toHaveValue(name);
  await page.getByLabel('Objectif global (obligatoire pour le Top 3)').fill('10');await page.getByRole('button',{name:'Créer la rotation hebdomadaire'}).click();
  const article=page.locator('article').filter({has:page.getByRole('heading',{name,exact:true})});await expect(article).toContainText('Désactivé');tick();
  expect(Number(queryIsolatedSql(project,`SELECT COUNT(*) FROM boost_event WHERE universe_id=${own.universeId} AND status IN ('scheduled','active')`))).toBe(0);
  await article.getByLabel('Motif du changement').fill('Lancement');await article.getByRole('button',{name:'Activer ce modèle'}).click();tick();
  const id=Number(queryIsolatedSql(project,`SELECT id FROM boost_event WHERE universe_id=${own.universeId} AND status='active'`));
  runIsolatedSql(project,`INSERT INTO boost_event_contribution (event_id,player_universe_id,source_key,value) VALUES (${id},${own.memberId},'own-test',2),(${id},${other.memberId},'peer-test',3);UPDATE boost_event SET total_contribution=5 WHERE id=${id}`);
  await page.goto(`/evenements/defis/${id}`);await expect(page.locator('main')).toContainText('Votre classement : #2');
  for (const theme of ['dark','light']) {
    runIsolatedSql(project,`UPDATE player SET preferences=JSON_SET(CASE WHEN JSON_TYPE(preferences)='OBJECT' THEN preferences ELSE JSON_OBJECT() END,'$.theme','${theme}') WHERE id=${own.playerId}`);await page.reload();await expect(page.locator('body')).toHaveAttribute('data-theme',theme);
    for (const width of [1440,390]) {
      await page.setViewportSize({width,height:900});const drawer=page.locator('.event-drawer'),summary=drawer.locator('summary');
      const before=await page.locator('.resource-event-stack').boundingBox(),mainBefore=await page.locator('main').boundingBox();
      await summary.focus();await page.keyboard.press('Enter');await expect(drawer).toHaveAttribute('open','');
      const after=await page.locator('.resource-event-stack').boundingBox(),mainAfter=await page.locator('main').boundingBox();
      expect(Math.abs(after.height-before.height)).toBeLessThan(1);expect(Math.abs(mainAfter.y-mainBefore.y)).toBeLessThan(1);
      await expect(drawer).toContainText(name);await expect(drawer).toContainText('Découvrir de nouveaux systèmes');await expect(drawer).toContainText('#2');
      await expect(drawer).toContainText('2,00');await expect(drawer).toContainText('5,00 / 10,00');
      const universeName=queryIsolatedSql(project,`SELECT name FROM universe WHERE id=${own.universeId}`);await expect(drawer).not.toContainText(universeName);
      expect((await new AxeBuilder({page}).withTags(['wcag2a','wcag2aa','wcag21aa']).analyze()).violations).toEqual([]);
      expect(await page.evaluate(()=>document.documentElement.scrollWidth)).toBeLessThanOrEqual(width);
      await page.screenshot({path:testInfo.outputPath(`weekly-panel-${theme}-${width}.png`),fullPage:true});await page.keyboard.press('Escape');await expect(summary).toBeFocused();await expect(drawer).not.toHaveAttribute('open','');
    }
  }
  await page.goto('/evenements');await expect(page.locator('main').getByRole('link',{name,exact:true})).toBeVisible();
  expect(await post(page,`/admin/rotations/occurrences/${id}/annuler`,{_token:'invalid',confirmed:'1',reason:'Interdit'})).toBe(403);
  await page.goto(`/admin/defis/${id}`);const cancel=page.locator('form[action$="/annuler"]');const data=await cancel.evaluate(f=>Object.fromEntries(new FormData(f)));
  await post(page,`/admin/rotations/occurrences/${id}/annuler`,{...data,reason:'Sans confirmation'});expect(queryIsolatedSql(project,`SELECT status FROM boost_event WHERE id=${id}`)).toBe('active');
  await page.getByLabel('Motif d’annulation de la rotation').fill('Fin de l’animation');await page.getByLabel('Je confirme l’annulation de cette occurrence hebdomadaire.').check();await page.getByRole('button',{name:'Annuler cette rotation'}).click();
  await expect(page.locator('main')).toContainText('Annulé');tick();tick();expect(queryIsolatedSql(project,`SELECT status FROM boost_event WHERE id=${id}`)).toBe('cancelled');
  expect(Number(queryIsolatedSql(project,`SELECT COUNT(*) FROM boost_event WHERE universe_id=${own.universeId}`))).toBe(historyCount+1);expect(Number(queryIsolatedSql(project,`SELECT COUNT(*) FROM boost_event_contribution WHERE event_id=${id}`))).toBe(2);
  await peer.goto(`/evenements/defis/${id}`);await expect(peer.locator('main')).toContainText('annulé');await expect(peer.locator('.event-drawer')).toHaveCount(0);
  expect((await peer.goto('/admin/rotations')).status()).toBe(403);expect(await post(peer,`/admin/rotations/${own.universeId}/activation`,{_token:'invalid',key:'exploration',enabled:'1',reason:'Interdit'})).toBe(403);
  await peer.close();
});

test('Catalogues : filtres actuels par rôle, classe et effet, sans résumé des planètes',async({page},testInfo)=>{
  test.setTimeout(180000);const own=await enter(page);const project=process.env.BROWSER_ROUTER;
  runIsolatedSql(project,`UPDATE player SET preferences=JSON_SET(CASE WHEN JSON_TYPE(preferences)='OBJECT' THEN preferences ELSE JSON_OBJECT() END,'$.showLockedItems',true) WHERE id=${own.playerId}`);
  const path=`/planetes/${own.planetId}`;
  const cases=[['buildings','Production',['power_plant','mine_metal','mine_nano','mine_hydrogen','mine_antimatter']],
    ['technologies','Propulsion et logistique',['vector_propulsion','cryogenic_reserves','modular_cargo_bays']],['technologies','Colonisation',['colonial_expansion']],
    ['technologies','Défense',['automated_defense_repair']],['ships','Transport',['transport']],['ships','Colonisation',['colony_ship']],['ships','Espionnage',['probe']],
    ['ships','Légers',['fighter','interceptor','stealth_fighter']],['ships','Capitaux',['capital']],['defenses','Anti-légers',['ground_turret_anti_light']],['defenses','Anti-capitaux',['ground_turret_anti_capital']]];
  for (const [group,label,expected] of cases) {
    await page.goto(`${path}?section=${group}`);await page.locator('.catalog-filters').getByRole('button',{name:label,exact:true}).click();
    await expect.poll(async()=>await page.locator('.building-card:visible').evaluateAll(cards=>cards.map(card=>card.dataset.slug).sort())).toEqual([...expected].sort());
    await expect(page.locator('.catalog-filters').getByRole('button',{name:label,exact:true})).toHaveAttribute('aria-pressed','true');
  }
  for (const group of ['buildings','technologies','ships','defenses']) {
    await page.goto(`${path}?section=${group}`);await page.setViewportSize({width:390,height:900});
    expect(await page.evaluate(()=>document.documentElement.scrollWidth)).toBeLessThanOrEqual(390);
    expect((await new AxeBuilder({page}).withTags(['wcag2a','wcag2aa','wcag21aa']).analyze()).violations).toEqual([]);
    await page.screenshot({path:testInfo.outputPath(`filters-${group}-390.png`),fullPage:true});
  }
  await page.goto('/planetes');await expect(page.locator('.planet-index-summary')).toHaveCount(0);await expect(page.locator('.planet-index-card')).toHaveCount(1);
});
