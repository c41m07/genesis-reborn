import { test, expect } from './support/isolated-test.mjs';
import AxeBuilder from '@axe-core/playwright';
import { readFileSync } from 'node:fs';
import { runIsolatedSql } from './support/game-fixtures.mjs';

const guide = JSON.parse(readFileSync('docs/wiki.json', 'utf8'));
const chapterPath = id => `/wiki/chapitres/${id}`;

async function enter(page) {
  const id=crypto.randomUUID().slice(0,8), email=`wiki-${id}@example.test`, password=crypto.randomUUID()+'aA1!';
  await page.goto('/wiki');await expect(page).toHaveURL(/connexion/);
  await page.goto('/inscription');
  await page.getByLabel('Adresse email').fill(email);await page.getByLabel('Nom de commandant').fill(`Wiki-${id}`);
  await page.getByLabel('Mot de passe (12 caractères minimum)',{exact:true}).fill(password);await page.getByLabel('Confirmer le mot de passe').fill(password);
  await page.getByRole('button',{name:'Créer mon compte'}).click();await expect(page).toHaveURL(/connexion/);
  await page.getByLabel('Adresse email').fill(email);await page.getByLabel('Mot de passe',{exact:true}).fill(password);
  await page.getByRole('button',{name:/Connexion/}).click();await expect(page).toHaveURL(/\/wiki$/);
  await expect(page.getByRole('heading',{name:'Wiki du jeu',exact:true})).toBeVisible();
  return {email,password};
}

test('Wiki : chapitres serveur, contenu complet, formules au clavier et sources intactes',async({page})=>{
  test.setTimeout(150000);await enter(page);
  await expect(page.locator('.wiki-chapter-card')).toHaveCount(guide.length);
  await expect(page.locator('.wiki-chapter,.wiki-formula')).toHaveCount(0);
  for (const section of guide) {
    await expect(page.locator('#wiki-'+section.id+' .wiki-chapter-preview')).toContainText(section.paragraphs[0].slice(0,160));
  }
  await page.setViewportSize({width:1440,height:900});
  for(const [index,section] of guide.entries()) {
    const link=page.locator(`.wiki-summary a[href="${chapterPath(section.id)}"]`);
    await link.focus();await expect(link).toBeFocused();await page.keyboard.press('Enter');
    await expect(page).toHaveURL(new RegExp(chapterPath(section.id)+'$'));
    await expect(page.getByRole('heading',{level:1})).toHaveText(section.title);
    await expect(page).toHaveTitle(section.title+' · Wiki · Genesis Reborn');
    await expect(page.locator('.wiki-chapter')).toHaveCount(1);
    await expect(page.locator('.wiki-summary a[aria-current="page"]')).toHaveAttribute('href',chapterPath(section.id));
    const chapter=page.locator('#wiki-'+section.id);
    for(const text of section.paragraphs) await expect(chapter).toContainText(text);
    for(const step of section.steps??[]) await expect(chapter).toContainText(step);
    for(const calculation of section.calculations??[]) {
      const detail=chapter.locator('details').filter({has:page.getByText(calculation.title,{exact:true})});
      await expect(detail).not.toHaveAttribute('open','');await detail.locator('summary').focus();await page.keyboard.press('Enter');
      await expect(detail).toHaveAttribute('open','');await expect(detail.locator('.wiki-formula')).toHaveText(calculation.formula);
      await expect(detail).toContainText(calculation.example);await page.keyboard.press('Space');await expect(detail).not.toHaveAttribute('open','');
    }
    if(index===0) await expect(page.locator('[rel="prev"]')).toHaveCount(0);
    else await expect(page.locator('[rel="prev"]')).toHaveAttribute('href',chapterPath(guide[index-1].id));
    if(index===guide.length-1) await expect(page.locator('[rel="next"]')).toHaveCount(0);
    else await expect(page.locator('[rel="next"]')).toHaveAttribute('href',chapterPath(guide[index+1].id));
  }
  for(const source of ['regles','decisions']) {
    await page.goto(`/wiki/references/${source}`);
    const restored=await page.locator('.wiki-reference-document').evaluate(document=>document.querySelector(':scope > .wiki-reference').textContent+[...document.querySelectorAll(':scope > details')].map(detail=>'## '+detail.querySelector('summary').textContent+detail.querySelector('pre').textContent).join(''));
    expect(restored.replace(/\s+/g,' ').trim()).toBe(readFileSync(`docs/${source==='regles'?'REGLES':'DECISIONS'}.md`,'utf8').replace(/\s+/g,' ').trim());
    await page.locator('.wiki-reference-document>details').first().locator('summary').focus();await page.keyboard.press('Enter');
    await expect(page.locator('.wiki-reference-document>details').first().locator('pre')).toBeVisible();
    await expect(page.locator('.wiki-reference script')).toHaveCount(0);
    await expect(page.getByRole('navigation',{name:'Pages du wiki'}).locator('[aria-current="page"]')).toHaveAttribute('href',`/wiki/references/${source}`);
  }
  for(const route of ['/wiki?chapter=inconnu','/wiki/chapitres/inconnu','/wiki/references/inconnu']) expect((await page.goto(route)).status()).toBe(404);
});

test('Wiki : recherche globale, anciennes adresses, retour arrière et parcours sans JavaScript',async({page,browser,baseURL})=>{
  test.setTimeout(120000);await enter(page);
  await page.getByLabel('Rechercher dans le manuel').fill('colonisation');await page.getByRole('button',{name:'Rechercher',exact:true}).click();
  await expect(page).toHaveURL(/\/wiki\/recherche\?q=colonisation$/);
  await expect(page.locator('.wiki-search-result').first()).toContainText(/colonisation/i);
  await expect(page.locator('.wiki-chapter,.wiki-formula')).toHaveCount(0);
  await page.locator('.wiki-search-result a').first().click();await expect(page.locator('.wiki-chapter')).toHaveCount(1);
  await page.goBack();await expect(page).toHaveURL(/\/wiki\/recherche\?q=colonisation$/);await expect(page.getByLabel('Rechercher dans le manuel')).toHaveValue('colonisation');
  await page.getByLabel('Rechercher dans le manuel').fill('aucunchapitrexyz');await page.getByRole('button',{name:'Rechercher',exact:true}).click();
  await expect(page.getByText('Aucun chapitre ne correspond.',{exact:false})).toBeVisible();
  await page.goto('/wiki?q=colonisation');await expect(page).toHaveURL(/\/wiki\/recherche\?q=colonisation$/);
  await page.goto('/wiki?chapter=missions');await expect(page).toHaveURL(new RegExp(chapterPath('missions')+'$'));
  await page.goto('/wiki');await page.getByRole('link',{name:'Commencer le manuel',exact:true}).click();
  await page.goto('/wiki#wiki-missions');await expect(page).toHaveURL(new RegExp(chapterPath('missions')+'#wiki-missions$'));
  await page.goBack();await expect(page).toHaveURL(new RegExp(chapterPath('debuter')+'$'));
  await page.goto('/wiki#wiki-title-missions');await expect(page).toHaveURL(new RegExp(chapterPath('missions')+'#wiki-title-missions$'));
  await page.goto('/wiki?q=colonisation#wiki-missions');await expect(page).toHaveURL(new RegExp(chapterPath('missions')+'\\?q=colonisation#wiki-missions$'));
  await page.goto('/wiki');await expect(page.locator('.wiki-manual')).toHaveClass(/wiki-chapters-ready/);
  await page.evaluate(()=>{location.hash='wiki-economie';});await expect(page).toHaveURL(new RegExp(chapterPath('economie')+'#wiki-economie$'));
  await page.reload();await expect(page.getByRole('heading',{level:1})).toHaveText('Lire votre économie');
  for(const fragment of ['wiki-inconnu','wiki-constructor']) {await page.goto('/wiki#'+fragment);await expect(page.getByRole('heading',{level:1})).toHaveText('Wiki du jeu');await expect(page).toHaveURL(new RegExp('/wiki#'+fragment+'$'));}
  const context=await browser.newContext({baseURL,storageState:await page.context().storageState(),javaScriptEnabled:false,viewport:{width:390,height:900}});
  try {
    const native=await context.newPage();await native.goto('/wiki#wiki-missions');
    await expect(native.locator('#wiki-missions.wiki-chapter-card')).toBeInViewport();
    await native.locator('#wiki-missions a').click();await expect(native).toHaveURL(new RegExp(chapterPath('missions')+'$'));
    await expect(native.locator('.wiki-chapter')).toHaveCount(1);
    await native.getByLabel('Choisir un chapitre').selectOption('calcul-production');await native.getByRole('button',{name:'Ouvrir le chapitre'}).click();
    await expect(native).toHaveURL(new RegExp(chapterPath('calcul-production')+'$'));
    const calculation=native.locator('.wiki-chapter details').first();await calculation.locator('summary').focus();await native.keyboard.press('Enter');await expect(calculation.locator('.wiki-formula')).toBeVisible();
    await native.getByRole('navigation',{name:'Pages du wiki'}).getByRole('link',{name:'Recherche globale',exact:true}).click();
    await native.getByLabel('Rechercher dans le manuel').fill('colonisation');await native.getByRole('button',{name:'Rechercher',exact:true}).click();
    await expect(native.locator('.wiki-search-result').first()).toContainText(/colonisation/i);await expect(native.locator('.wiki-chapter')).toHaveCount(0);
    await native.getByRole('navigation',{name:'Pages du wiki'}).getByRole('link',{name:'Règles de référence',exact:true}).click();await expect(native.getByRole('heading',{level:1})).toHaveText('Règles de référence');
    await expect.poll(()=>native.evaluate(()=>document.documentElement.scrollWidth)).toBeLessThanOrEqual(390);
  } finally {await context.close();}
});

test('Wiki : rendu mobile et bureau, thèmes, navigation active et accès depuis la colonie',async({page},testInfo)=>{
  test.setTimeout(240000);const {email}=await enter(page);
  await page.goto('/univers');await page.locator('article.universe-card').first().getByRole('button').click();await expect(page).toHaveURL(/planetes\/\d+$/);
  const home=page.url(),membership=await page.locator('body').getAttribute('data-menu-notifications-membership-value');
  expect(Number(membership)).toBeGreaterThan(0);await expect(page.getByRole('link',{name:'Comprendre le jeu : consulter le wiki',exact:true})).toHaveCount(0);
  await page.getByRole('link',{name:'Consulter le wiki',exact:true}).click();await expect(page.getByRole('heading',{name:'Wiki du jeu',exact:true})).toBeVisible();
  const routes=[['home','/wiki'],['chapter',chapterPath('calcul-production')],['search','/wiki/recherche?q=colonisation'],['rules','/wiki/references/regles'],['decisions','/wiki/references/decisions']];
  for(const theme of ['dark','light']) {
    runIsolatedSql(process.env.BROWSER_ROUTER,`UPDATE player SET preferences=JSON_SET(CASE WHEN JSON_TYPE(preferences)='OBJECT' THEN preferences ELSE JSON_OBJECT() END,'$.theme','${theme}') WHERE email='${email}'`);
    for(const [name,route] of routes) {
      await page.goto(route);await expect(page.locator('body')).toHaveAttribute('data-theme',theme);await expect(page.locator('.wiki-manual')).toHaveClass(/wiki-chapters-ready/);
      await expect(page.locator('body')).toHaveAttribute('data-menu-notifications-membership-value',membership);
      await expect(page.locator('meta[name="robots"]')).toHaveAttribute('content','noindex');await expect(page.locator('h1')).toHaveCount(1);await expect(page.locator('.page-contents')).toHaveCount(0);
      if(name==='chapter') for(const summary of await page.locator('.wiki-chapter .wiki-calculation summary').all()) {await summary.focus();await expect(summary).toBeFocused();await page.keyboard.press('Enter');}
      for(const width of [1440,768,390]) {
        await page.setViewportSize({width,height:900});
        if(width<1100) {await expect(page.getByLabel('Choisir un chapitre')).toBeVisible();await expect(page.getByRole('navigation',{name:'Sommaire du wiki'})).toBeHidden();}
        else {await expect(page.getByRole('navigation',{name:'Sommaire du wiki'})).toBeVisible();expect(await page.locator('.wiki-navigation').evaluate(element=>getComputedStyle(element).position)).toBe('sticky');}
        await expect.poll(()=>page.evaluate(()=>document.documentElement.scrollWidth)).toBeLessThanOrEqual(width);
        if(name==='home') expect((await page.locator('.wiki-card-grid').first().evaluate(element=>getComputedStyle(element).gridTemplateColumns)).split(' ').length).toBe(1);
        expect((await new AxeBuilder({page}).include('main').analyze()).violations).toEqual([]);
        await page.evaluate(()=>{if(document.activeElement instanceof HTMLElement) document.activeElement.blur();window.scrollTo({top:0,left:0,behavior:'instant'});});
        await expect.poll(()=>page.evaluate(()=>window.scrollY)).toBe(0);
        await page.screenshot({path:testInfo.outputPath(`wiki-${name}-${theme}-${width}.png`),fullPage:true,animations:'disabled'});
      }
    }
  }
  await page.goto(home);await expect(page).toHaveURL(home);
});
