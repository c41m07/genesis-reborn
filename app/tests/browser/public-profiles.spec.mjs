import { test, expect } from './support/isolated-test.mjs';
import { runIsolatedSql, queryIsolatedSql } from './support/game-fixtures.mjs';
import { execFileSync } from 'node:child_process';
import { createHash } from 'node:crypto';
import AxeBuilder from '@axe-core/playwright';

const png=Buffer.from('iVBORw0KGgoAAAANSUhEUgAAABQAAAAUCAIAAAAC64paAAAACXBIWXMAAA7EAAAOxAGVKw4bAAAAEklEQVQ4jWNgGAWjYBSMgqELAATEAAEeAgbGAAAAAElFTkSuQmCC','base64');

async function register(page) {
  const key=crypto.randomUUID().slice(0,8),email='public-'+key+'@example.test',name='Public-'+key,password='Public-browser-2026!';
  await page.goto('/inscription');await page.getByLabel('Adresse email').fill(email);await page.getByLabel('Nom de commandant').fill(name);
  await page.getByLabel('Mot de passe (12 caractères minimum)',{exact:true}).fill(password);await page.getByLabel('Confirmer le mot de passe').fill(password);
  await page.getByRole('button',{name:'Créer mon compte'}).click();
  await page.getByLabel('Adresse email').fill(email);await page.getByLabel('Mot de passe',{exact:true}).fill(password);await page.getByRole('button',{name:/Connexion/}).click();
  await page.locator('.universe-card').filter({has:page.getByRole('heading',{name:/Omega/})}).getByRole('button').click();await expect(page).toHaveURL(/planetes\/\d+$/);
  const planet=Number(new URL(page.url()).pathname.split('/').pop());
  const member=Number(queryIsolatedSql(process.env.BROWSER_ROUTER,`SELECT owner_id FROM planet WHERE id=${planet}`));
  return {email,name,password,member};
}

async function createGuild(page,owner) {
  await page.goto('/diplomatie');const name='Guilde publique '+crypto.randomUUID().slice(0,8);
  await page.getByLabel('Nom de votre nouvelle guilde').fill(name);await page.getByLabel('Tag de la guilde',{exact:true}).fill('PUB');
  await page.getByRole('button',{name:'Fonder une guilde'}).click();
  return {name,id:Number(queryIsolatedSql(process.env.BROWSER_ROUTER,`SELECT id FROM guild WHERE leader_id=${owner.member}`))};
}

async function theme(page,value) {
  await page.goto('/profil/preferences');await page.getByLabel('Thème de l’interface').selectOption(value);await page.getByRole('button',{name:'Enregistrer les paramètres'}).click();
  await expect(page.locator('body')).toHaveAttribute('data-theme',value);
}

test('Profils : accès depuis classement, messages et membres, données publiques seulement',async({page,browser,baseURL},testInfo)=>{
  test.setTimeout(180000);const owner=await register(page);const context=await browser.newContext({baseURL});const peerPage=await context.newPage();
  try {
    const peer=await register(peerPage);const guild=await createGuild(page,owner),project=process.env.BROWSER_ROUTER;
    runIsolatedSql(project,`INSERT INTO guild_member (guild_id,player_universe_id) VALUES (${guild.id},${peer.member}); UPDATE leaderboard_snapshot SET updated_at=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 2 DAY)`);
    await page.goto('/classement');await page.getByRole('link',{name:peer.name,exact:true}).click();
    await expect(page).toHaveURL(new RegExp('/joueurs/'+peer.member+'$'));await expect(page.getByRole('heading',{level:1,name:peer.name})).toBeVisible();
    await expect(page.locator('.social-profile-scores > div')).toHaveCount(5);expect(await page.content()).not.toContain(peer.email);
    await page.getByRole('link',{name:'Écrire à '+peer.name,exact:true}).click();
    await page.getByRole('heading',{name:'Conversation avec '+peer.name}).getByRole('link',{name:peer.name,exact:true}).click();
    await expect(page).toHaveURL(new RegExp('/joueurs/'+peer.member+'$'));
    await page.goto('/diplomatie?tab=members');await page.locator('.guild-members-table').getByRole('link',{name:peer.name,exact:true}).click();
    await expect(page).toHaveURL(new RegExp('/joueurs/'+peer.member+'$'));
    for(const value of ['dark','light']) {
      await theme(page,value);await page.goto('/joueurs/'+peer.member);
      for(const width of [320,390,1440]) {await page.setViewportSize({width,height:900});expect(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth)).toBe(true);if(width===320) await page.screenshot({path:testInfo.outputPath('profil-'+value+'-mobile.png'),fullPage:true,animations:'disabled'});}
      expect((await new AxeBuilder({page}).include('main').analyze()).violations).toEqual([]);
      await page.screenshot({path:testInfo.outputPath('profil-'+value+'.png'),fullPage:true,animations:'disabled'});
    }
    await peerPage.goto('/guildes/'+guild.id);await expect(peerPage.getByRole('heading',{level:1})).toContainText(guild.name);
    await expect(peerPage.getByRole('link',{name:'Personnaliser la guilde'})).toHaveCount(0);
    expect(await peerPage.evaluate(url=>fetch(url).then(response=>response.status),'/guildes/'+guild.id+'/personnaliser')).toBe(403);
  } finally {await context.close();}
});

test('Guilde : images contrôlées, présentation accessible et persistance isolée',async({page},testInfo)=>{
  test.setTimeout(240000);const owner=await register(page),guild=await createGuild(page,owner),project=process.env.BROWSER_ROUTER;
  await page.goto('/guildes/'+guild.id);await page.getByRole('link',{name:'Personnaliser la guilde'}).focus();await page.keyboard.press('Enter');
  await page.getByLabel('Description',{exact:true}).fill('<script>window.guildXss=true</script>\nBienvenue dans notre guilde.');
  await page.getByLabel('Logo',{exact:true}).setInputFiles({name:'logo.png',mimeType:'image/png',buffer:png});
  await page.getByLabel('Bannière',{exact:true}).setInputFiles({name:'banner.png',mimeType:'image/png',buffer:png});
  await page.getByRole('button',{name:'Enregistrer la présentation'}).click();
  await expect(page).toHaveURL(new RegExp('/guildes/'+guild.id+'$'));await expect(page.locator('.guild-public-description')).toContainText('Bienvenue');
  expect(await page.evaluate(()=>window.guildXss)).toBeUndefined();await expect(page.locator('.guild-public-description script')).toHaveCount(0);
  await expect.poll(()=>page.locator('.guild-public-logo').evaluate(image=>image.naturalWidth)).toBe(20);
  const imageUrl='/guildes/'+guild.id+'/image/logo';
  const readImage=()=>page.evaluate(async url=>{const response=await fetch(url);return {status:response.status,mime:response.headers.get('content-type'),bytes:Array.from(new Uint8Array(await response.arrayBuffer()))};},imageUrl);
  const before=await readImage();expect(before.mime).toBe('image/png');
  const digest=createHash('sha256').update(Buffer.from(before.bytes)).digest('hex');
  for(const value of ['dark','light']) {
    await theme(page,value);await page.goto('/guildes/'+guild.id);
    for(const width of [320,390,1440]) {await page.setViewportSize({width,height:900});expect(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth)).toBe(true);if(width===320) await page.screenshot({path:testInfo.outputPath('guilde-'+value+'-mobile.png'),fullPage:true,animations:'disabled'});}
    expect((await new AxeBuilder({page}).include('main').analyze()).violations).toEqual([]);
    await page.screenshot({path:testInfo.outputPath('guilde-'+value+'.png'),fullPage:true,animations:'disabled'});
    await page.getByRole('link',{name:'Personnaliser la guilde'}).click();
    expect((await new AxeBuilder({page}).include('main').analyze()).violations).toEqual([]);
  }
  if(process.env.BROWSER_PERSISTENCE==='1') {
    if(!/^genesis-browser-test-[0-9]+-[0-9]+$/.test(project??'')) throw new Error('Redémarrage refusé hors du projet isolé.');
    const args=['compose','-p',project,'--env-file','.env.local','-f','compose.yaml','-f','compose.browser-test.yaml','-f','compose.browser-persistence.yaml'];
    execFileSync('docker',[...args,'restart','--timeout','3','app'],{stdio:'pipe'});
    execFileSync('docker',[...args,'up','-d','--no-deps','--wait','--wait-timeout','45','app'],{stdio:'pipe'});
    // Le routeur découvre à nouveau le conteneur après son redémarrage.
    await expect.poll(async()=> (await page.goto('/guildes/'+guild.id)).status(),{timeout:45000}).toBe(200);
    if(new URL(page.url()).pathname==='/connexion') {
      await page.getByLabel('Adresse email').fill(owner.email);await page.getByLabel('Mot de passe',{exact:true}).fill(owner.password);await page.getByRole('button',{name:/Connexion/}).click();
    }
    const after=await readImage();expect(after.status).toBe(200);
    expect(createHash('sha256').update(Buffer.from(after.bytes)).digest('hex')).toBe(digest);
    await page.goto('/guildes/'+guild.id);await expect(page.locator('.guild-public-description')).toContainText('Bienvenue');
  }
});
