import { test, expect } from './support/isolated-test.mjs';
import { execFileSync } from 'node:child_process';
import AxeBuilder from '@axe-core/playwright';
import { queryIsolatedSql, runIsolatedSql } from './support/game-fixtures.mjs';
async function signIn(page, account) {
  await page.goto('/connexion');
  await page.getByLabel('Adresse email').fill(account.email);
  await page.getByLabel('Mot de passe', {
    exact: true
  }).fill(account.password);
  await page.getByRole('button', {
    name: /Connexion/
  }).click();
}
async function createAccount(page) {
  const suffix = crypto.randomUUID().slice(0, 8);
  const user = {
    email: `navigation-final-${suffix}@example.test`,
    password: crypto.randomUUID() + 'aA1!',
    suffix
  };
  await page.goto('/inscription');
  await page.getByLabel('Adresse email').fill(user.email);
  await page.getByLabel('Nom de commandant').fill('Navigation-' + suffix);
  await page.getByLabel('Mot de passe (12 caractères minimum)', {
    exact: true
  }).fill(user.password);
  await page.getByLabel('Confirmer le mot de passe').fill(user.password);
  await page.getByRole('button', {
    name: 'Créer mon compte'
  }).click();
  await expect(page).toHaveURL(/connexion/);
  await signIn(page, user);
  await page.goto('/univers');
  await page.locator('article.universe-card').filter({
    has: page.getByRole('heading', {
      name: /Omega/
    })
  }).getByRole('button').click();
  await expect(page).toHaveURL(/planetes\/\d+$/);
  const project = process.env.BROWSER_ROUTER;
  const [membership, universe, planet] = queryIsolatedSql(project, `SELECT pu.id,pu.universe_id,planet.id FROM player p JOIN player_universe pu ON pu.id=p.current_player_universe_id JOIN planet ON planet.owner_id=pu.id WHERE p.email='${user.email}' ORDER BY planet.id LIMIT 1`).split('\t').map(Number);
  expect(membership).toBeGreaterThan(0);
  return {
    ...user,
    project,
    membership,
    universe,
    planet
  };
}
async function setTheme(user, theme) {
  runIsolatedSql(user.project, `UPDATE player SET preferences=JSON_SET(CASE WHEN JSON_TYPE(preferences)='OBJECT' THEN preferences ELSE JSON_OBJECT() END,'$.theme','${theme}') WHERE email='${user.email}'`);
}
async function inspectPage(page, user, row, width, theme) {
  const response = await page.goto(row.url);
  expect(response.status(), row.url).toBe(200);
  await expect(page.locator('body')).toHaveAttribute('data-theme', theme);
  await expect(page.getByRole('heading', {
    level: 1
  })).toHaveText(row.title);
  await expect(page).toHaveTitle(new RegExp((row.documentTitle ?? row.title).replace(/[.*+?^${}()|[\]\\]/g, '\\$&')));
  await expect(page.locator('body')).toHaveAttribute('data-menu-notifications-membership-value', String(user.membership));
  await expect(page.locator('#sidebar-colony')).toHaveValue(String(user.planet));
  await expect(page.locator('.page-contents')).toHaveCount(0);
  if (row.entry) {
    const active = page.locator('#command-navigation nav[aria-label="Navigation principale"] a[aria-current="page"]');
    await expect(active).toHaveCount(1);
    await expect(active).toHaveAttribute('href', row.entry);
  } else await expect(page.locator('.header-tools a[href="/wiki"]')).toHaveAttribute('aria-current', row.url === '/wiki' ? 'page' : 'location');
  if (row.space) {
    const active = page.getByRole('navigation', {
      name: row.space,
      exact: true
    }).locator('a[aria-current="page"]');
    await expect(active).toHaveCount(1);
    await expect(active).toHaveAttribute('href', row.active ?? row.url);
  }
  await expect.poll(() => page.evaluate(() => document.documentElement.scrollWidth), {
    message: row.url
  }).toBeLessThanOrEqual(width);
  if (width === 390 && row.audit) {
    const result = await new AxeBuilder({
      page
    }).include('main').withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa']).analyze();
    expect(result.violations, row.url).toEqual([]);
  }
}
// Les valeurs saisies dans les textarea ne sont pas couvertes par le relevé des seuls textes du document.
async function expectReadableDescriptions(page, theme) {
  const contrasts = await page.locator('.admin-event-form textarea').evaluateAll(fields => {
    const luminance = color => color.match(/[\d.]+/g).slice(0, 3).map(Number)
      .map(value => value / 255)
      .map(value => value <= .04045 ? value / 12.92 : ((value + .055) / 1.055) ** 2.4)
      .reduce((sum, value, index) => sum + value * [.2126, .7152, .0722][index], 0);
    return fields.map(field => {
      const style = getComputedStyle(field);
      const foreground = luminance(style.color), background = luminance(style.backgroundColor);
      return {field: field.id || field.name, background, ratio: (Math.max(foreground, background) + .05) / (Math.min(foreground, background) + .05)};
    });
  });
  for (const contrast of contrasts) {
    expect(contrast.ratio, contrast.field).toBeGreaterThanOrEqual(4.5);
    expect(contrast.background > .5, contrast.field + ' : fond adapté au thème').toBe(theme === 'light');
  }
}

const commonPages = [{
  url: '/guildes',
  title: 'Trouver une guilde',
  entry: '/guildes',
  space: 'Navigation de guilde',
  audit: true
}, {
  url: '/guildes/creer',
  title: 'Créer une guilde',
  entry: '/guildes',
  space: 'Navigation de guilde'
}, {
  url: '/diplomatie',
  title: 'Relations entre commandants',
  entry: '/diplomatie',
  space: 'Navigation de diplomatie',
  active: '/diplomatie/commandants',
  audit: true
}, {
  url: '/diplomatie/commandants',
  title: 'Relations entre commandants',
  entry: '/diplomatie',
  space: 'Navigation de diplomatie'
}, ...[['/flottes', 'Missions actives'], ['/flottes/preparer', 'Préparer une mission'], ['/flottes/orbites', 'Demandes d’orbite'], ['/flottes/quai', 'À quai et stationnements'], ['/flottes/compositions', 'Mes compositions'], ['/flottes/compositions/creer', 'Créer une composition']].map(([url, title]) => ({
  url,
  title,
  entry: '/flottes',
  space: 'Navigation des flottes',
  active: url.endsWith('/creer') ? '/flottes/compositions' : url,
  audit: url === '/flottes/preparer'
})), ...[['/boosts', 'Boosts'], ['/inventaire/boites', 'Boîtes de ressources'], ['/inventaire/recompenses', 'Récompenses en attente']].map(([url, title]) => ({
  url,
  title,
  entry: '/boosts',
  space: 'Navigation de l’inventaire',
  audit: url === '/inventaire/boites'
})), ...[['/profil', 'Mes identifiants'], ['/profil/preferences', 'Préférences'], ['/profil/vacances', 'Mode vacances'], ['/profil/supprimer', 'Suppression du compte']].map(([url, title]) => ({
  url,
  title,
  entry: '/profil',
  space: 'Compte et préférences',
  audit: url === '/profil/vacances'
})), ...[['/evenements', 'Événements et bonus'], ['/evenements/defis', 'Défis et rotations']].map(([url, title]) => ({
  url,
  title,
  entry: '/evenements',
  space: 'Navigation des événements',
  audit: url === '/evenements/defis'
})), {
  url: '/wiki',
  title: 'Wiki du jeu',
  audit: true
}, {
  url: '/wiki/chapitres/missions',
  title: 'Flottes et exploration'
}, {
  url: '/wiki/recherche?q=colonisation',
  title: 'Recherche dans le manuel'
}];
test('Recette finale : espaces sans guilde, colonie active, thèmes, clavier et parcours natif', async ({
  page,
  browser,
  baseURL
}, testInfo) => {
  test.setTimeout(300000);
  const user = await createAccount(page);
  // Une seconde colonie de fixture rend visible une perte de contexte qui passerait inaperçue avec une seule planète.
  const second = Number(queryIsolatedSql(user.project, `SELECT planet.id FROM planet JOIN solar_system s ON s.id=planet.solar_system_id JOIN galaxy g ON g.id=s.galaxy_id WHERE g.universe_id=${user.universe} AND planet.owner_id IS NULL AND planet.npc_planet=0 ORDER BY planet.id LIMIT 1`));
  expect(second).toBeGreaterThan(0);
  runIsolatedSql(user.project, `UPDATE planet SET owner_id=${user.membership},name='Colonie de recette' WHERE id=${second}; INSERT INTO planet_resource (planet_id,resource_type,current_stock,production_rate,last_updated_at) SELECT ${second},resource_type,current_stock,production_rate,UTC_TIMESTAMP() FROM planet_resource WHERE planet_id=${user.planet}`);
  user.planet = second;
  await page.goto('/planetes/' + second);
  for (const theme of ['dark', 'light']) {
    await setTheme(user, theme);
    for (const width of [1440, 390, 320]) {
      await page.setViewportSize({
        width,
        height: 900
      });
      for (const row of commonPages) await inspectPage(page, user, row, width, theme);
      if (width === 390) await page.screenshot({
        path: testInfo.outputPath(`navigation-wiki-${theme}-390.png`),
        animations: 'disabled'
      });
    }
  }
  await page.goto('/flottes');
  const prepare = page.getByRole('navigation', {
    name: 'Navigation des flottes',
    exact: true
  }).getByRole('link', {
    name: 'Préparer une mission',
    exact: true
  });
  await prepare.focus();
  await expect(prepare).toBeFocused();
  expect(await prepare.evaluate(element => getComputedStyle(element).outlineStyle)).not.toBe('none');
  await page.keyboard.press('Enter');
  await expect(page).toHaveURL(/\/flottes\/preparer$/);
  await expect(page.getByRole('heading', {
    level: 1
  })).toHaveText('Préparer une mission');
  await page.waitForLoadState('load');
  await page.reload();
  await expect(page.locator('#sidebar-colony')).toHaveValue(String(second));
  await page.goBack();
  await expect(page).toHaveURL(/\/flottes$/);
  const context = await browser.newContext({
    baseURL,
    javaScriptEnabled: false,
    storageState: await page.context().storageState(),
    viewport: {
      width: 390,
      height: 900
    }
  });
  try {
    const native = await context.newPage();
    // Axe exécute du JavaScript : ses audits sont déjà faits dans les deux thèmes.
    // Le parcours natif vérifie séparément le HTML et les liens sans réactiver les scripts.
    for (const row of commonPages.filter(row => row.url !== '/diplomatie' && !row.url.endsWith('/creer'))) await inspectPage(native, user, {
      ...row,
      audit: false
    }, 390, 'light');
  } finally {
    await context.close();
  }
  expect((await page.goto('/admin/univers')).status()).toBe(403);
});
test('Recette finale : guilde, relations personnelles et projets sur des pages distinctes', async ({
  page
}, testInfo) => {
  test.setTimeout(180000);
  const user = await createAccount(page);
  await page.goto('/guildes/creer');
  await page.getByLabel('Nom de votre nouvelle guilde').fill('Guilde de recette ' + user.suffix);
  await page.getByLabel('Tag de la guilde', {
    exact: true
  }).fill(user.suffix.slice(0, 5));
  await page.getByRole('button', {
    name: 'Fonder une guilde'
  }).click();
  await expect(page.getByRole('heading', {
    level: 1
  })).toHaveText('Ma guilde');
  const personalization = await page.getByRole('navigation', {
    name: 'Navigation de guilde',
    exact: true
  }).getByRole('link', {
    name: 'Personnalisation',
    exact: true
  }).getAttribute('href');
  const rows = [...[['/guildes', 'Ma guilde'], ['/guildes/membres', 'Membres de ma guilde'], ['/guildes/grades', 'Grades et permissions'], ['/guildes/gestion', 'Gestion de ma guilde'], [personalization, 'Personnaliser la guilde']].map(([url, title]) => ({
    url,
    title,
    entry: '/guildes',
    space: 'Navigation de guilde',
    documentTitle: url === personalization ? `Personnaliser Guilde de recette ${user.suffix}` : title,
    audit: url === '/guildes/gestion'
  })), ...[['/guildes/projets', 'Projets en cours'], ['/guildes/projets/disponibles', 'Projets disponibles'], ['/guildes/projets/historique', 'Historique des projets']].map(([url, title]) => ({
    url,
    title,
    entry: '/guildes',
    space: 'Navigation des projets collectifs',
    audit: url === '/guildes/projets'
  })), {
    url: '/diplomatie',
    title: 'Relations entre guildes',
    entry: '/diplomatie',
    space: 'Navigation de diplomatie',
    audit: true
  }, {
    url: '/diplomatie/commandants',
    title: 'Relations entre commandants',
    entry: '/diplomatie',
    space: 'Navigation de diplomatie'
  }];
  for (const theme of ['dark', 'light']) {
    await setTheme(user, theme);
    for (const width of [1440, 390, 320]) {
      await page.setViewportSize({
        width,
        height: 900
      });
      for (const row of rows) await inspectPage(page, user, row, width, theme);
    }
  }
  await page.goto('/guildes');
  await expect(page.getByText('Consulter cet aperçu ne marque pas le canal comme lu.')).toBeVisible();
  await page.screenshot({
    path: testInfo.outputPath('navigation-guilde-light-320.png'),
    fullPage: true,
    animations: 'disabled'
  });
});
test('Recette finale : listes et formulaires administratifs, éditions et accès refusés', async ({
  page
}, testInfo) => {
  test.setTimeout(180000);
  const user = await createAccount(page);
  expect((await page.goto('/admin')).status()).toBe(403);
  execFileSync('docker', ['compose', '-p', user.project, '--env-file', '.env.local', 'exec', '-T', 'app', 'php', 'bin/console', 'app:admin:grant', user.email], {
    stdio: ['ignore', 'pipe', 'pipe']
  });
  await page.context().clearCookies();
  await signIn(page, user);
  runIsolatedSql(user.project, `INSERT INTO guild_project_definition (universe_id,name,description,stages,minimums,enabled,version,created_at,updated_at) VALUES (${user.universe},'Projet de recette','Modèle de test','[{"name":"Palier de test","costs":{"metal":1}}]','{}',0,1,UTC_TIMESTAMP(),UTC_TIMESTAMP()); INSERT INTO game_event (universe_id,name,description,type,status,starts_at,ends_at,parameters,visible_to_players) VALUES (${user.universe},'Annonce de recette','Brouillon de test','announcement','draft',DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 DAY),DATE_ADD(UTC_TIMESTAMP(),INTERVAL 2 DAY),'{}',0)`);
  const model = Number(queryIsolatedSql(user.project, "SELECT id FROM guild_project_definition WHERE name='Projet de recette'")),
    event = Number(queryIsolatedSql(user.project, "SELECT id FROM game_event WHERE name='Annonce de recette'"));
  const rows = [['/admin/univers', 'Univers'], ['/admin/universes/nouveau', 'Créer un univers'], [`/admin/universes/${user.universe}/gerer`, 'Gérer Omega · univers standard'], [`/admin/universes/${user.universe}/catalogue/ships/fighter`, 'Modifier Chasseur de patrouille'], ['/admin/events', 'Événements'], ['/admin/events/nouveau', 'Créer un événement avancé'], [`/admin/events/${event}/edit`, 'Modifier Annonce de recette'], ['/admin/projets-guilde', 'Modèles de projets de guilde'], ['/admin/projets-guilde/nouveau', 'Créer un modèle de projet'], [`/admin/projets-guilde/${model}/modifier`, 'Modifier Projet de recette'], [`/admin/rotations?universe=${user.universe}`, 'Rotations hebdomadaires'], [`/admin/rotations/nouveau?universe=${user.universe}`, 'Créer une rotation hebdomadaire'], [`/admin/rotations/${user.universe}/exploration/modifier`, null]];
  for (const theme of ['dark', 'light']) {
    await setTheme(user, theme);
    for (const width of [1440, 390]) {
      await page.setViewportSize({
        width,
        height: 900
      });
      for (const [url, title] of rows) {
        expect((await page.goto(url)).status(), url).toBe(200);
        await expect(page.locator('h1')).toHaveCount(1);
        if (title) await expect(page.locator('h1')).toHaveText(title);
        await expect(page.locator('body')).toHaveAttribute('data-theme', theme);
        await expect(page.locator('.admin-primary-link[aria-current]')).toHaveCount(1);
        await expect(page.locator('.page-contents')).toHaveCount(0);
        await expect.poll(() => page.evaluate(() => document.documentElement.scrollWidth), {
          message: url
        }).toBeLessThanOrEqual(width);
        if (width === 390) await expectReadableDescriptions(page, theme);
      }
      await page.screenshot({path:testInfo.outputPath(`navigation-admin-${theme}-${width}.png`),animations:'disabled'});
    }
  }
  await page.goto(`/admin/projets-guilde/${model}/modifier`);
  await expect(page.getByLabel('Nom du projet', {
    exact: true
  })).toHaveValue('Projet de recette');
  await page.screenshot({
    path: testInfo.outputPath('navigation-admin-light-390.png'),
    fullPage: true,
    animations: 'disabled'
  });
});
