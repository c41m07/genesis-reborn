import { test, expect } from './support/isolated-test.mjs';
import { execFileSync } from 'node:child_process';
import AxeBuilder from '@axe-core/playwright';
import { runIsolatedSql } from './support/game-fixtures.mjs';

async function register(page, name, email, admin = false) {
  await page.goto('/inscription');
  await page.getByLabel('Adresse email').fill(email);
  await page.getByLabel('Nom de commandant').fill(name);
  await page.getByLabel('Mot de passe (12 caractères minimum)', { exact: true }).fill('Guild-projects-2026!');
  await page.getByLabel('Confirmer le mot de passe').fill('Guild-projects-2026!');
  await page.getByRole('button', { name: 'Créer mon compte' }).click();
  await expect(page).toHaveURL(/connexion/);
  if (admin) execFileSync('docker', ['compose', '-p', process.env.BROWSER_ROUTER, '--env-file', '.env.local', 'exec', '-T', 'app', 'php', 'bin/console', 'app:admin:grant', email], { stdio: 'pipe' });
  await page.getByLabel('Adresse email').fill(email);
  await page.getByLabel('Mot de passe', { exact: true }).fill('Guild-projects-2026!');
  await page.getByRole('button', { name: /Connexion/ }).click();
  await page.locator('article.universe-card').filter({ has: page.getByRole('heading', { name: /Omega/ }) }).getByRole('button').click();
}

test('Les modèles de projets conservent la saisie et les paliers fonctionnent sans JavaScript', async ({ page, browser, baseURL }, testInfo) => {
  test.setTimeout(150000);
  const id = crypto.randomUUID().slice(0, 8);
  const name = 'Modele-' + id;
  await register(page, 'AdminProj-' + id, 'admin-project-' + id + '@example.test', true);
  await page.goto('/admin/projets-guilde');
  await expect(page.getByRole('heading', { name: 'Projets de guilde', exact: true })).toBeVisible();
  await expect(page.locator('meta[name="robots"]')).toHaveAttribute('content', /noindex/);
  await page.getByLabel('Nom du projet', { exact: true }).fill(name);
  const universeId = await page.getByRole('combobox', { name: 'Univers', exact: true }).locator('option').filter({ hasText: 'Omega' }).getAttribute('value');
  await page.getByRole('combobox', { name: 'Univers', exact: true }).selectOption(universeId);
  await page.getByLabel('Nom du palier 1', { exact: true }).fill('Structure');
  await page.getByLabel('Métal · palier 1', { exact: true }).fill('10');
  await page.getByRole('button', { name: 'Ajouter un palier', exact: true }).click();
  await expect(page.getByLabel('Nom du palier 2', { exact: true })).toBeFocused();
  await expect(page.getByLabel('Métal · palier 1', { exact: true })).toHaveValue('10');
  await page.getByLabel('Nom du palier 2', { exact: true }).fill('Énergie');
  await page.getByLabel('Hydrogène · palier 2', { exact: true }).fill('4');
  await page.getByLabel('Minimum Métal', { exact: true }).fill('11');
  await page.getByLabel('Minimum Hydrogène', { exact: true }).fill('1');
  await page.getByLabel('Disponible pour de nouveaux lancements').check();
  await page.getByRole('button', { name: 'Enregistrer le modèle', exact: true }).click();
  await expect(page.locator('.flash.error')).toContainText('dépasse le coût total');
  await expect(page.getByLabel('Nom du projet', { exact: true })).toHaveValue(name);
  await expect(page.getByLabel('Minimum Métal', { exact: true })).toHaveValue('11');
  await page.getByLabel('Minimum Métal', { exact: true }).fill('2');
  await page.getByRole('button', { name: 'Enregistrer le modèle', exact: true }).click();
  await expect(page.locator('.flash.success')).toContainText('Modèle enregistré');
  await expect(page.getByRole('article').filter({ has: page.getByRole('heading', { name, exact: true }) })).toContainText('au maximum 4 récompense');
  await page.getByRole('link', { name: 'Modifier ' + name, exact: true }).click();
  for (const width of [1440, 768, 390]) {
    await page.setViewportSize({ width, height: 900 });
    await expect.poll(() => page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(width);
    const result = await new AxeBuilder({ page }).include('[aria-labelledby="project-definition-title"]').withTags(['wcag2a', 'wcag2aa', 'wcag21aa']).analyze();
    expect(result.violations).toEqual([]);
  }
  await page.screenshot({ path: testInfo.outputPath('guild-project-admin-390.png'), fullPage: true });
  const noJsContext = await browser.newContext({ baseURL, storageState: await page.context().storageState(), javaScriptEnabled: false });
  try {
    const noJs = await noJsContext.newPage();
    await noJs.goto(page.url());
    await noJs.getByRole('button', { name: 'Ajouter un palier', exact: true }).click();
    await expect(noJs.getByLabel('Nom du palier 3', { exact: true })).toBeVisible();
    await noJs.getByRole('button', { name: 'Retirer le dernier palier', exact: true }).click();
    await expect(noJs.getByLabel('Nom du palier 3', { exact: true })).toHaveCount(0);
    await expect(noJs.getByLabel('Métal · palier 1', { exact: true })).toHaveValue('10');
  } finally { await noJsContext.close(); }
});

test('Les membres financent deux paliers et le traitement attribue les boosts une seule fois', async ({ page, browser, baseURL }, testInfo) => {
  test.setTimeout(150000);
  const id = crypto.randomUUID().slice(0, 8);
  const guildName = 'ProjetGuilde-' + id;
  const projectName = 'Projet-' + id;
  const otherContext = await browser.newContext({ baseURL });
  const other = await otherContext.newPage();
  try {
    await register(page, 'ProjLeader-' + id, 'proj-leader-' + id + '@example.test');
    await page.goto('/guildes/creer');
    await page.getByLabel('Nom de votre nouvelle guilde').fill(guildName);await page.getByLabel('Tag de la guilde', { exact:true }).fill(crypto.randomUUID().replaceAll('-', '').slice(0,5).toUpperCase());
    await page.getByRole('button', { name: 'Fonder une guilde' }).click();
    await register(other, 'ProjMember-' + id, 'proj-member-' + id + '@example.test');
    await other.goto('/diplomatie?tab=diplomacy&q=' + encodeURIComponent(guildName));
    await other.getByRole('button', { name: 'Rejoindre · recrutement ouvert' }).click();
    const project = process.env.BROWSER_ROUTER;
    const stages = [{ name: 'Structure', costs: { metal: 10 } }, { name: 'Énergie', costs: { hydrogen: 4 } }];
    runIsolatedSql(project, `INSERT INTO guild_project_definition (universe_id,name,description,stages,minimums,enabled,created_at,updated_at) SELECT id,'${projectName}','Projet de test','${JSON.stringify(stages)}','{"metal":2,"hydrogen":1}',1,UTC_TIMESTAMP(),UTC_TIMESTAMP() FROM universe WHERE slug='omega-1'`);
    const denied = await other.goto('/admin/projets-guilde');
    expect(denied.status()).toBe(403);
    await other.goto('/guildes/projets/disponibles');
    await expect(other.getByRole('button', { name: 'Lancer ' + projectName })).toHaveCount(0);
    await page.goto('/guildes/creer');
    await page.getByRole('link', { name: 'Consulter les projets', exact: true }).click();
    await page.getByRole('link', { name: 'Découvrir les projets disponibles', exact: true }).click();
    await page.getByRole('button', { name: 'Lancer ' + projectName, exact: true }).click();
    const card = page.locator('.guild-project');
    await expect(card.getByRole('heading', { name: /Palier 1 sur 2/ })).toBeVisible();
    const detailUrl = page.url();
    for (const theme of ['dark', 'light']) {
      runIsolatedSql(process.env.BROWSER_ROUTER, `UPDATE player SET preferences=JSON_SET(CASE WHEN JSON_TYPE(preferences)='OBJECT' THEN preferences ELSE JSON_OBJECT() END,'$.theme','${theme}') WHERE email='proj-leader-${id}@example.test'`);
      for (const width of [1440, 390, 320]) {
        await page.setViewportSize({ width, height: 900 });
        for (const url of ['/guildes/projets', '/guildes/projets/disponibles', '/guildes/projets/historique', detailUrl]) {
          await page.goto(url);
          await expect(page.locator('body')).toHaveAttribute('data-theme', theme);
          await expect(page.locator('.sidebar a[href="/guildes"]')).toHaveAttribute('aria-current', 'page');
          await expect(page.getByRole('navigation', { name: 'Navigation de guilde', exact: true }).getByRole('link', { name: 'Projets collectifs', exact: true })).toHaveAttribute('aria-current', 'page');
          await expect(page.getByRole('navigation', { name: 'Navigation des projets collectifs', exact: true }).locator('[aria-current]')).toHaveCount(1);
          await expect(page.locator('.page-contents')).toHaveCount(0);
          await expect.poll(() => page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(width);
          const result = await new AxeBuilder({ page }).include('main').withTags(['wcag2a', 'wcag2aa', 'wcag21aa']).analyze();
          expect(result.violations).toEqual([]);
          if (width === 320 || width === 1440) await page.screenshot({ path: testInfo.outputPath(`project-${new URL(page.url()).pathname.split('/').pop()}-${theme}-${width}.png`), fullPage: true });
        }
      }
    }
    await page.setViewportSize({ width: 1440, height: 900 });
    await page.goto('/guildes/projets#available-projects-title');
    await expect(page).toHaveURL(/\/guildes\/projets\/disponibles#available-projects-title$/);
    await page.goto('/guildes/projets');
    const availableLink = page.getByRole('navigation', { name: 'Navigation des projets collectifs', exact: true }).getByRole('link', { name: 'Projets disponibles', exact: true });
    await availableLink.focus();
    await page.keyboard.press('Enter');
    await expect(page).toHaveURL(/\/projets\/disponibles$/);
    await page.reload();
    await expect(page.getByRole('heading', { name: 'Projets disponibles', exact: true })).toBeVisible();
    await page.goBack();
    await expect(page).toHaveURL(/\/guildes\/projets$/);
    await page.goto('/guildes/projets#completed-projects-title');
    await expect(page).toHaveURL(/\/projets\/historique#completed-projects-title$/);
    await page.goto(detailUrl);

    await card.getByLabel('Don Métal', { exact: true }).fill('8');
    await card.getByRole('button', { name: 'Donner ces ressources' }).focus();
    await page.keyboard.press('Enter');
    await expect(page.locator('.flash.success')).toContainText('Don enregistré');
    const stale = await card.locator('form').evaluate(form => ({ url: form.action, data: Object.fromEntries(new FormData(form)) }));
    await other.goto('/guildes/projets');
    await other.getByRole('link', { name: 'Consulter et contribuer', exact: true }).click();
    const peerCard = other.locator('.guild-project');
    await peerCard.getByLabel('Don Métal', { exact: true }).fill('2');
    await peerCard.getByRole('button', { name: 'Donner ces ressources' }).click();
    await expect(peerCard.getByRole('heading', { name: /Palier 2 sur 2/ })).toBeVisible();
    await peerCard.getByLabel('Don Hydrogène', { exact: true }).fill('1');
    await peerCard.getByRole('button', { name: 'Donner ces ressources' }).click();
    const staleResult = await page.evaluate(async ({ url, data }) => (await fetch(url, { method: 'POST', body: new URLSearchParams({ ...data, 'resources[metal]': '1' }) })).text(), stale);
    expect(staleResult).toContain('Le projet a progressé');
    await page.reload();
    await card.getByLabel('Don Hydrogène', { exact: true }).fill('3');
    await card.getByRole('button', { name: 'Donner ces ressources' }).click();
    await expect(card.locator('.badge')).toHaveText('Terminé');
    await expect(card).toContainText('Vous avez rempli les conditions à la clôture');
    await card.getByText('Contributions des membres', { exact: true }).click();
    await expect(card).toContainText('ProjMember-' + id);
    await expect.poll(async () => { await other.goto('/boosts');return other.getByRole('heading', { name: 'Boost de réduction de durée', exact: true }).count(); }, { timeout: 20000 }).toBe(1);
    await expect(other.locator('.mission-card').filter({ has: other.getByRole('heading', { name: 'Boost de réduction de durée', exact: true }) }).locator('.badge')).toHaveText('Quantité : 1');
    await page.goto('/guildes/projets');
    await expect(page.getByRole('button', { name: 'Lancer ' + projectName, exact: true })).toHaveCount(0);
    await expect(page.locator('.guild-project')).toHaveCount(0);
    await page.getByRole('navigation', { name: 'Navigation des projets collectifs', exact: true }).getByRole('link', { name: 'Historique', exact: true }).click();
    await expect(page.locator('.guild-project')).toContainText('Vous avez déjà gagné le boost');
    await page.getByRole('link', { name: 'Consulter le bilan', exact: true }).click();
    for (const width of [1440, 768, 390]) {
      await page.setViewportSize({ width, height: 900 });
      await expect.poll(() => page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(width);
      const result = await new AxeBuilder({ page }).include('.guild-project').withTags(['wcag2a', 'wcag2aa', 'wcag21aa']).analyze();
      expect(result.violations).toEqual([]);
    }
    await page.screenshot({ path: testInfo.outputPath('guild-project-player-390.png'), fullPage: true });
  } finally { await otherContext.close(); }
});


test('Projets sans JavaScript : lancement, don et historique restent accessibles', async ({ browser, baseURL }) => {
  const context = await browser.newContext({ baseURL, javaScriptEnabled: false, viewport: { width: 390, height: 900 } });
  try {
    const page = await context.newPage();
    const id = crypto.randomUUID().slice(0, 8);
    await register(page, 'NativeProj-' + id, 'native-project-' + id + '@example.test');
    await page.goto('/guildes/creer');
    await page.getByLabel('Nom de votre nouvelle guilde').fill('Native-' + id);
    await page.getByLabel('Tag de la guilde', { exact: true }).fill(id.slice(0, 5).toUpperCase());
    await page.getByRole('button', { name: 'Fonder une guilde' }).click();
    const name = 'Natif-' + id;
    runIsolatedSql(process.env.BROWSER_ROUTER, `INSERT INTO guild_project_definition (universe_id,name,description,stages,minimums,enabled,created_at,updated_at) SELECT id,'${name}','Projet natif','[{"name":"Structure","costs":{"metal":10}}]','{"metal":2}',1,UTC_TIMESTAMP(),UTC_TIMESTAMP() FROM universe WHERE slug='omega-1'`);
    await page.goto('/guildes/projets');
    await page.getByRole('navigation', { name: 'Navigation des projets collectifs', exact: true }).getByRole('link', { name: 'Projets disponibles', exact: true }).click();
    await page.getByRole('button', { name: 'Lancer ' + name, exact: true }).click();
    await expect(page.getByRole('heading', { name, exact: true })).toBeVisible();
    await page.getByLabel('Don Métal', { exact: true }).fill('10');
    await page.getByRole('button', { name: 'Donner ces ressources', exact: true }).click();
    await expect(page.locator('.flash.success')).toContainText('Le projet est terminé');
    await page.getByRole('navigation', { name: 'Navigation des projets collectifs', exact: true }).getByRole('link', { name: 'Historique', exact: true }).click();
    await expect(page.locator('.guild-project-grid')).toContainText(name);
    await page.getByRole('link', { name: 'Consulter le bilan', exact: true }).click();
    await expect(page.getByRole('heading', { name, exact: true })).toBeVisible();
    await expect(page.getByRole('button', { name: 'Donner ces ressources', exact: true })).toHaveCount(0);
    await expect.poll(() => page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(390);
  } finally { await context.close(); }
});
