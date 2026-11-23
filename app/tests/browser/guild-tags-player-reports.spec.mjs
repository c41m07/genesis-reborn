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
  return { playerId, memberId, planetId, universeId, username };
}

test('Tag obligatoire, choix du commandant et préfixe limité au classement', async ({page,browser},testInfo) => {
  test.setTimeout(120000);
  const author=await enter(page), peerPage=await browser.newPage(), peer=await enter(peerPage);
  await page.goto('/guildes/creer');
  await page.getByLabel('Nom de votre nouvelle guilde').fill('Guilde-'+author.playerId);
  await page.getByRole('button',{name:'Fonder une guilde',exact:true}).click();
  await expect(page.getByLabel('Tag de la guilde',{exact:true})).toBeFocused();
  expect(Number(queryIsolatedSql(process.env.BROWSER_ROUTER,'SELECT COUNT(*) FROM guild'))).toBe(0);
  await page.getByLabel('Tag de la guilde',{exact:true}).fill('alpha');
  await page.getByRole('button',{name:'Fonder une guilde',exact:true}).click();
  const guildIdentity='[ALPHA] Guilde-'+author.playerId;
  const guildHeading=page.getByRole('region',{name:'Centre de guilde',exact:true})
    .getByRole('heading',{name:guildIdentity,exact:true});
  await expect(guildHeading).toBeVisible();
  await expect(guildHeading.getByRole('link',{name:guildIdentity,exact:true})).toHaveAttribute('href',/^\/guildes\/\d+$/);
  await page.goto('/classement');
  const ownRow=page.getByRole('row').filter({hasText:author.username});
  await expect(ownRow.locator('th')).toContainText('[ALPHA] '+author.username);
  await page.goto('/guildes/creer');
  await page.goto('/diplomatie?tab=members');
  await expect(page.locator('.guild-members-table')).not.toContainText('[ALPHA]');
  await page.goto('/guildes/gestion');
  await page.getByLabel('Tag de la guilde',{exact:true}).fill('BETA');
  await page.getByRole('button',{name:'Enregistrer le tag',exact:true}).click();
  await page.goto('/classement');
  await expect(page.getByRole('row').filter({hasText:author.username}).locator('th')).toContainText('[BETA] '+author.username);
  await page.setViewportSize({width:390,height:844});
  expect(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1)).toBeTruthy();
  await page.screenshot({path:testInfo.outputPath('classement-tag-mobile.png'),fullPage:true,animations:'disabled'});
  await peerPage.close();
});

test('Ancienne guilde : aucun tag inventé et choix accessible au commandant', async ({page},testInfo) => {
  const user=await enter(page), project=process.env.BROWSER_ROUTER;
  runIsolatedSql(project,"INSERT INTO guild (universe_id,leader_id,name,tag) VALUES ("+user.universeId+","+user.memberId+",'Guilde ancienne',NULL)");
  runIsolatedSql(project,"INSERT INTO guild_member (guild_id,player_universe_id) SELECT id,"+user.memberId+" FROM guild WHERE leader_id="+user.memberId);
  await page.goto('/guildes/gestion');
  await expect(page.getByRole('heading', { name: 'Choisir le tag de la guilde', exact: true })).toBeVisible();
  await page.getByLabel('Tag de la guilde', { exact: true }).focus();
  await expect(page.getByLabel('Tag de la guilde', { exact: true })).toBeFocused();
  await page.getByLabel('Tag de la guilde',{exact:true}).fill('OLD');
  await page.getByRole('button',{name:'Enregistrer le tag',exact:true}).click();
  expect(queryIsolatedSql(project,'SELECT tag FROM guild WHERE leader_id='+user.memberId).trim()).toBe('OLD');
  await page.setViewportSize({width:390,height:844});
  await page.screenshot({path:testInfo.outputPath('tag-ancien-mobile.png'),fullPage:true,animations:'disabled'});
});

test('Signalement joueur : contexte serveur, saisie préservée, modération privée et preuve conservée', async ({page,browser},testInfo) => {
  test.setTimeout(120000);
  const reporter=await enter(page), targetPage=await browser.newPage(), target=await enter(targetPage);
  const denied=await targetPage.goto('/admin/moderation');expect(denied.status()).toBe(403);
  runIsolatedSql(process.env.BROWSER_ROUTER,"UPDATE leaderboard_snapshot SET updated_at=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 2 DAY) WHERE universe_id="+reporter.universeId);
  await page.goto('/classement?category=planets');
  const link=page.getByRole('link',{name:'Signaler '+target.username,exact:true});
  await link.focus();await page.keyboard.press('Enter');
  await expect(page.getByRole('heading',{name:'Signaler un commandant',exact:true})).toBeVisible();
  await expect(page.locator('.player-report-form')).toContainText('Nombre de planètes');
  await page.getByLabel('Motif du signalement',{exact:true}).selectOption('other');
  await page.getByLabel('Commentaire (facultatif)',{exact:true}).fill('Informations à examiner');
  const body=await page.locator('.player-report-form form').evaluate(form=>Object.fromEntries(new FormData(form)));
  await page.setViewportSize({width:390,height:844});
  expect(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1)).toBeTruthy();
  const violations=(await new AxeBuilder({page}).include('.player-report-form').analyze()).violations.filter(v=>['serious','critical'].includes(v.impact));
  expect(violations).toEqual([]);
  await page.screenshot({path:testInfo.outputPath('signalement-mobile.png'),fullPage:true,animations:'disabled'});
  await page.getByRole('button',{name:'Transmettre le signalement',exact:true}).click();
  await expect(page.getByText('Signalement transmis à la modération.',{exact:true})).toBeVisible();
  const project=process.env.BROWSER_ROUTER;
  const reportId=Number(queryIsolatedSql(project,'SELECT id FROM player_report WHERE reporter_id='+reporter.memberId));
  expect(queryIsolatedSql(project,"SELECT JSON_UNQUOTE(JSON_EXTRACT(context,'$.category')) FROM player_report WHERE id="+reportId).trim()).toBe('planets');
  const replay=await page.evaluate(async({url,body})=>(await fetch(url,{method:'POST',body:new URLSearchParams(body)})).status,{url:'/classement/signaler/'+target.memberId+'?category=planets',body:{...body,category:'points',rank:'9999'}});
  expect(replay).toBe(200);
  expect(Number(queryIsolatedSql(project,'SELECT COUNT(*) FROM player_report'))).toBe(1);
  await targetPage.close();
  const adminPage=await browser.newPage();await enter(adminPage,true);
  await adminPage.goto('/admin/moderation');
  const reports=adminPage.locator('#admin-player-reports');
  await expect(reports).toContainText(target.username);await expect(reports).toContainText('Nombre de planètes');
  await expect(reports).toContainText('Informations à examiner');
  await reports.getByRole('button',{name:'Classer sans suite',exact:true}).click();
  expect(queryIsolatedSql(project,'SELECT status FROM player_report WHERE id='+reportId).trim()).toBe('dismissed');
  expect(Number(queryIsolatedSql(project,'SELECT COUNT(*) FROM player_report WHERE id='+reportId))).toBe(1);
  await adminPage.close();
});

// Le signalement n’est jamais soumis : on mesure le vrai formulaire depuis le classement.
test('S20 : signalement accessible avec le guide ouvert et retour sans soumission', async ({page,browser},info) => {
  test.setTimeout(120000);
  const reporter = await enter(page);
  const targetPage = await browser.newPage();
  const target = await enter(targetPage);
  await targetPage.close();
  const project = process.env.BROWSER_ROUTER;
  runIsolatedSql(project, "UPDATE leaderboard_snapshot SET updated_at=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 2 DAY) WHERE universe_id="+reporter.universeId);
  let reportPosts = 0;
  await page.route('**/classement/signaler/**', async route => {
    if (route.request().method() === 'POST') {
      reportPosts += 1;
      return route.abort();
    }
    return route.continue();
  });
  const reportCount = () => Number(queryIsolatedSql(project,'SELECT COUNT(*) FROM player_report WHERE reporter_id='+reporter.memberId));
  const bounds = (control) => control.evaluate(el => {
    const rect = el.getBoundingClientRect();
    const popup = document.querySelector('.first-steps-popup[open] .first-steps-popup-mount');
    const p = popup.getBoundingClientRect();
    const visible = rect.top >= 0 && rect.bottom <= innerHeight && rect.left >= 0 && rect.right <= innerWidth;
    const intersection = Math.max(0,Math.min(rect.right,p.right)-Math.max(rect.left,p.left))*
      Math.max(0,Math.min(rect.bottom,p.bottom)-Math.max(rect.top,p.top));
    const top = document.elementFromPoint(rect.left+rect.width/2, rect.top+rect.height/2);
    return {visible, intersection, hits: Boolean(top && el.contains(top))};
  });
  const initialCount = reportCount();
  for (const [width,height] of [[1920,1080],[390,844]]) {
    await page.setViewportSize({width,height});
    await page.goto('/classement?category=planets&direction=asc&page=1');
    const filter = new URL(page.url());
    expect(filter.searchParams.get('category')).toBe('planets');
    expect(filter.searchParams.get('direction')).toBe('asc');
    const launch = page.getByRole('link',{name:'Signaler '+target.username,exact:true});
    await launch.focus();
    await page.keyboard.press('Enter');
    const form = page.getByRole('region',{name:'Commandant signalé : '+target.username});
    await expect(form).toContainText('Nombre de planètes');
    await expect(page.getByRole('heading',{name:'Signaler un commandant',exact:true})).toBeVisible();
    const guide = page.locator('details.first-steps-popup');
    if (await guide.getAttribute('open') === null) {
      await guide.locator('summary').focus();
      await page.keyboard.press('Enter');
    }
    await expect(guide).toHaveAttribute('open','');
    await expect(guide.locator('.first-steps-popup-mount')).toBeVisible();
    await page.evaluate(()=>window.scrollTo(0,0));
    await page.screenshot({path:info.outputPath('S20-'+width+'-depuis-entete.png'),animations:'disabled'});
    const reason = form.getByLabel('Motif du signalement',{exact:true});
    const comment = form.getByLabel('Commentaire (facultatif)',{exact:true});
    const submit = form.getByRole('button',{name:'Transmettre le signalement',exact:true});
    const cancel = form.getByRole('link',{name:'Revenir au classement',exact:true});
    for (const [label,item] of Object.entries({reason,comment,submit,cancel})) {
      // Contrôle en bas de la zone utile, au-dessus de la barre mobile.
      await item.evaluate(el => {
        const rect = el.getBoundingClientRect();
        const center = rect.top + rect.height / 2;
        const target = innerWidth <= 740 ? innerHeight - 165 : innerHeight / 2;
        window.scrollBy({top: center - target, behavior: 'instant'});
      });
      const result = await bounds(item);
      expect.soft(result.visible, width+' '+label+' is visible').toBe(true);
      expect.soft(result.hits, width+' '+label+' centre clickable').toBe(true);
      expect.soft(result.intersection, width+' '+label+' not overlapped by open guide').toBe(0);
    }
    await reason.selectOption('other');
    await comment.focus();
    await page.keyboard.type('Informations synthétiques à examiner');
    await expect(comment).toHaveValue('Informations synthétiques à examiner');
    await page.keyboard.press('Tab');
    await expect(submit).toBeFocused();
    await page.keyboard.press('Tab');
    await expect(cancel).toBeFocused();
    await expect(guide).toHaveAttribute('open','');
    await page.screenshot({path:info.outputPath('S20-'+width+'-actions-guide-ouvert.png'),animations:'disabled'});
    await page.keyboard.press('Enter');
    await expect(page).toHaveURL(url => url.pathname === '/classement' && ['category','direction','page'].every(k => (url.searchParams.get(k) ?? (k === 'page' ? '1' : null)) === (filter.searchParams.get(k) ?? (k === 'page' ? '1' : null))));
    await expect(page.getByRole('link',{name:'Signaler '+target.username,exact:true})).toBeAttached();
    expect(reportPosts).toBe(0);
    expect(reportCount()).toBe(initialCount);
  }
});
