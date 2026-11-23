import { execFileSync } from 'node:child_process';
import { test, expect } from './support/isolated-test.mjs';
import { queryIsolatedSql, runIsolatedSql } from './support/game-fixtures.mjs';

const path = '/evenements/campagne-pirate';
const sizes = [{ width:1920, height:1080 }, { width:390, height:844 }];
const themes = ['dark','light'];

async function syntheticAccount(page) {
  const suffix = crypto.randomUUID().slice(0,8);
  const email = 'p22-'+suffix+'@example.test';
  const password = crypto.randomUUID()+'!Aa1';
  await page.goto('/inscription');
  await page.getByLabel('Adresse email').fill(email);
  await page.getByLabel('Nom de commandant').fill('Pilote-'+suffix);
  await page.getByLabel('Mot de passe (12 caractères minimum)',{ exact:true }).fill(password);
  await page.getByLabel('Confirmer le mot de passe').fill(password);
  await page.getByRole('button',{ name:'Créer mon compte' }).click();
  await page.getByLabel('Adresse email').fill(email);
  await page.getByLabel('Mot de passe',{ exact:true }).fill(password);
  await page.getByRole('button',{ name:/Connexion/ }).click();
  await page.locator('.universe-card').filter({ has:page.getByRole('heading',{ name:/Omega/ }) }).getByRole('button').click();
  await expect(page).toHaveURL(/planetes\/\d+$/);
  return { email, origin:Number(new URL(page.url()).pathname.split('/').at(-1)) };
}

test('P22 : états privés de campagne en lecture seule', async ({ page, browser, baseURL }, info) => {
  test.setTimeout(120000);
  const project = process.env.BROWSER_ROUTER;
  expect(project).toMatch(/^genesis-browser-test-[0-9]+-[0-9]+$/);
  const forbidden = [];
  const onlyGet = async route => {
    if (route.request().method()==='GET') return route.continue();
    forbidden.push(route.request().method());
    return route.abort();
  };
  const stockFor = origin => queryIsolatedSql(project, 'SELECT resource_type,current_stock,last_updated_at FROM planet_resource WHERE planet_id='+origin+' ORDER BY resource_type');
  const prepare = (origin, state) => execFileSync('docker',['exec','-u','www-data','-e','P22_TEST_PROJECT='+project,project+'-app-1','php','-d','display_errors=stderr','/app/tests/browser/support/p22-private-states.php',String(origin),state],{encoding:'utf8'});
  const snapshot = async (tab, owner, suffix, verify) => {
    for (const theme of themes) {
      runIsolatedSql(project,`UPDATE player SET preferences=JSON_SET(CASE WHEN JSON_TYPE(preferences)='OBJECT' THEN preferences ELSE JSON_OBJECT() END,'$.theme','${theme}') WHERE email='${owner.email}'`);
      for (const viewport of sizes) {
        await tab.setViewportSize(viewport);
        const response = await tab.goto(path+(suffix.startsWith('A')||suffix.startsWith('B')?'':'/1'));
        expect(response.status()).toBe(200);
        expect(response.headers()['cache-control']).toContain('no-store');
        await expect(tab.locator('body')).toHaveAttribute('data-theme',theme);
        const anchor = await verify(tab);
        await expect.poll(()=>tab.evaluate(()=>document.documentElement.scrollWidth)).toBeLessThanOrEqual(viewport.width);
        await anchor.evaluate(el=>el.scrollIntoView({block:'center',behavior:'instant'}));
        await tab.screenshot({path:info.outputPath(`P22-${suffix}-${theme}-${viewport.width}.png`),animations:'disabled',scale:'css'});
      }
    }
  };
  const owner = await syntheticAccount(page);
  await page.route('**/evenements/campagne-pirate**',onlyGet);
  const before = stockFor(owner.origin);
  await snapshot(page,owner,'A-verrouille',async tab=>{
    const rows = tab.locator('.pirate-campaign-mission-row--locked');
    await expect(rows).toHaveCount(2);
    await expect(rows.locator('a')).toHaveCount(0);
    await expect(rows.getByText('Verrouillée',{exact:true})).toHaveCount(2);
    await expect(tab.getByText('Gain potentiel',{exact:true})).toHaveCount(0);
    return rows.first();
  });
  runIsolatedSql(project,"INSERT INTO fleet (player_universe_id,home_planet_id,status,mission,speed_ratio,cargo,reserved_fuel,orbit_fuel,orbit_duration_seconds,travel_seconds,dispatch_token) SELECT owner_id,id,'idle','idle',1,'{}',0,0,0,0,'' FROM planet WHERE id="+owner.origin+" AND NOT EXISTS (SELECT 1 FROM fleet WHERE home_planet_id="+owner.origin+" AND status='idle')");
  runIsolatedSql(project,"INSERT INTO fleet_ship (fleet_id,slug,tier,quantity) SELECT id,'fighter','basic',1 FROM fleet WHERE home_planet_id="+owner.origin+" AND status='idle' ON DUPLICATE KEY UPDATE quantity=1");
  prepare(owner.origin,'report');
  await snapshot(page,owner,'B-potentiel',async tab=>{
    const quote = tab.getByRole('region',{name:'Gain potentiel',exact:true});
    await expect(quote).toContainText('Ce montant n’a pas été versé.');
    await expect(tab.getByRole('button',{name:'Vérifier le devis d’attaque',exact:true})).toBeVisible();
    return quote;
  });
  prepare(owner.origin,'travelling');
  await snapshot(page,owner,'C-convoi',async tab=>{
    const convoy = tab.getByRole('region',{name:'Convoi de remerciement',exact:true});
    await expect(convoy).toContainText('En convoi');
    await expect(convoy).toContainText('Arrivée prévue');
    await expect(convoy.getByRole('link',{name:'Voir le trajet dans Flottes',exact:true})).toHaveAttribute('href','/flottes');
    await expect(convoy.getByRole('button')).toHaveCount(0);
    await expect(tab.getByRole('region',{name:'Gain potentiel',exact:true})).toHaveCount(0);
    return convoy;
  });
  expect(stockFor(owner.origin)).toBe(before);

  const isolated = await browser.newContext({baseURL});
  try {
    const other = await isolated.newPage();
    const full = await syntheticAccount(other);
    const beforeFull = stockFor(full.origin);
    await other.route('**/evenements/campagne-pirate**',onlyGet);
    prepare(full.origin,'report');
    prepare(full.origin,'full');
    await snapshot(other,full,'D-depot-empeche',async tab=>{
      const convoy = tab.getByRole('region',{name:'Convoi de remerciement',exact:true});
      await expect(convoy).toContainText('espace insuffisant lors du dernier contrôle');
      await expect(convoy.getByRole('button',{name:'Retenter le dépôt dans la même colonie'})).toBeVisible();
      await expect(convoy.getByText('Lot crédité',{exact:true})).toHaveCount(0);
      return convoy;
    });
    expect(stockFor(full.origin)).toBe(beforeFull);
  } finally { await isolated.close(); }
  expect(forbidden).toEqual([]);
  expect(queryIsolatedSql(project,"SELECT COUNT(*) FROM outbox_message WHERE kind LIKE 'pirate_%'")).toBe('0');
});
