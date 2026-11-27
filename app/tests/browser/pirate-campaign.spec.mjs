import { test, expect } from './support/isolated-test.mjs';
import { queryIsolatedSql, runIsolatedSql } from './support/game-fixtures.mjs';
import AxeBuilder from '@axe-core/playwright';

const campaignPath = '/evenements/campagne-pirate';

test('Campagne pirate : navigation, briefing discret et reconnaissance sans JavaScript', async ({ page, browser }, info) => {
  test.setTimeout(90000);
  const suffix = crypto.randomUUID().slice(0, 8);
  const email = 'pirate-' + suffix + '@example.test';
  const password = crypto.randomUUID() + '!Aa1';
  const project = process.env.BROWSER_ROUTER;
  await page.goto('/inscription');
  await page.getByLabel('Adresse email').fill(email);
  await page.getByLabel('Nom de commandant').fill('Pilot-' + suffix);
  await page.getByLabel('Mot de passe (12 caractères minimum)', { exact: true }).fill(password);
  await page.getByLabel('Confirmer le mot de passe').fill(password);
  await page.getByRole('button', { name: 'Créer mon compte' }).click();
  await expect(page).toHaveURL(/connexion/);
  await page.getByLabel('Adresse email').fill(email);
  await page.getByLabel('Mot de passe', { exact: true }).fill(password);
  await page.getByRole('button', { name: /Connexion/ }).click();
  await page.locator('article.universe-card').filter({ has: page.getByRole('heading', { name: /Omega/ }) }).getByRole('button').click();
  await expect(page).toHaveURL(/planetes\/\d+$/);
  const origin = Number(new URL(page.url()).pathname.split('/').at(-1));
  const member = Number(queryIsolatedSql(project, 'SELECT owner_id FROM planet WHERE id=' + origin));
  const universe = Number(queryIsolatedSql(project, 'SELECT universe_id FROM player_universe WHERE id=' + member));
  expect(member).toBeGreaterThan(0);
  expect(universe).toBeGreaterThan(0);
  // Une nouvelle colonie n’a pas forcément encore de hangar matérialisé.
  runIsolatedSql(project, "INSERT INTO fleet (player_universe_id,home_planet_id,status,mission,speed_ratio,cargo,reserved_fuel,orbit_fuel,orbit_duration_seconds,travel_seconds,dispatch_token) SELECT owner_id,id,'idle','idle',1,'{}',0,0,0,0,'' FROM planet WHERE id=" + origin + " AND NOT EXISTS (SELECT 1 FROM fleet WHERE home_planet_id=" + origin + " AND status='idle')");
  for (const slug of ['probe', 'fighter']) {
    runIsolatedSql(project, "INSERT INTO fleet_ship (fleet_id,slug,tier,quantity) SELECT id,'" + slug + "','basic',1 FROM fleet WHERE home_planet_id=" + origin + " AND status='idle' ON DUPLICATE KEY UPDATE quantity=1");
  }
  runIsolatedSql(project, "UPDATE planet_resource SET current_stock=5000 WHERE planet_id=" + origin + " AND resource_type='hydrogen'");

  await page.setViewportSize({ width: 1440, height: 900 });
  const navigation = page.getByRole('navigation', { name: 'Navigation principale', exact: true });
  const campaign = navigation.getByRole('link', { name: 'Campagne pirate', exact: true });
  await expect(campaign).toHaveAttribute('href', campaignPath);
  await expect(campaign.locator('xpath=preceding-sibling::*[1]')).toHaveAccessibleName('Flottes');

  // La lecture, même avec le shell complet et JavaScript, ne réarme pas la présence.
  await page.goto(campaignPath);
  runIsolatedSql(project, "UPDATE player_universe SET last_activity_at=DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 HOUR) WHERE id=" + member);
  const beforeVisit = queryIsolatedSql(project, 'SELECT last_activity_at FROM player_universe WHERE id=' + member);
  const background = [];
  const onRequest = request => {
    const url = new URL(request.url());
    if (url.origin === new URL(page.url()).origin && ['fetch', 'xhr'].includes(request.resourceType())) background.push(url.pathname);
  };
  page.on('request', onRequest);
  const response = await page.reload();
  expect(response.status()).toBe(200);
  expect(response.headers()['cache-control']).toMatch(/no-store/);
  await expect(campaign).toHaveAttribute('aria-current', 'page');
  await expect(page.getByRole('heading', { name: 'Campagne pirate', exact: true })).toBeVisible();
  await expect(page.locator('body')).not.toHaveAttribute('data-player-activity-url-value');
  await expect(page.locator('[data-controller="events"], [data-controller="incoming-attacks"], [data-controller="universe-closing"]')).toHaveCount(0);
  const resources = page.locator('[data-live-region="resources"]');
  await expect(resources).toBeVisible();
  await expect(resources).not.toHaveAttribute('data-resource-strip-url-value');
  await expect(page.locator('.header-technology-goal')).toHaveAttribute('href', '/technologies');
  await expect(page.locator('.header-technology-goal')).not.toHaveAttribute('data-controller');
  await expect(page.getByRole('heading', { name: 'Le silence de Balise 17', exact: true })).toBeVisible();
  await expect(page.getByText('Mission 1 / 20', { exact: true })).toBeVisible();
  await expect(page.locator('.pirate-campaign-progress [aria-current="step"]')).toHaveText('Chapitre 1');
  await expect(page.getByRole('progressbar', { name: 'Missions réussies' })).toHaveAttribute('value', '0');
  const missionList = page.getByRole('navigation', { name: 'Dossiers de la campagne', exact: true });
  await expect(missionList.getByText('Verrouillée', { exact: true })).toHaveCount(2);
  await expect(missionList.getByRole('link')).toHaveCount(0);
  await expect(page.getByText('Gain potentiel', { exact: true })).toHaveCount(0);
  await expect(page.getByText('Flotte pirate révélée', { exact: true })).toHaveCount(0);
  await expect(page.locator('.pirate-campaign-intel--embedded')).toHaveCount(0);
  const shipForm = page.getByRole('region', { name: 'Préparer la reconnaissance', exact: true });
  await expect(shipForm.getByRole('button', { name: 'Envoyer la sonde', exact: true })).toBeVisible();
  for (const width of [1440, 768, 390, 360]) {
    await page.setViewportSize({ width, height: 900 });
    await expect.poll(() => page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(width);
    if ([1440, 390].includes(width)) await page.screenshot({ path: info.outputPath('signal-' + width + '.png'), fullPage: true, animations: 'disabled' });
  }
  expect((await new AxeBuilder({ page }).include('.pirate-campaign').withTags(['wcag2a', 'wcag2aa', 'wcag21aa']).analyze()).violations).toEqual([]);
  // Dépasser les anciens sondages de surveillance (5 s) et des ressources (10 s).
  await page.waitForTimeout(10500);
  page.off('request', onRequest);
  expect(background).toEqual([]);
  expect(queryIsolatedSql(project, 'SELECT last_activity_at FROM player_universe WHERE id=' + member)).toBe(beforeVisit);
  expect((await page.goto(campaignPath + '/2')).status()).toBe(404);

  const nativeContext = await browser.newContext({ javaScriptEnabled: false, storageState: await page.context().storageState() });
  const nativePage = await nativeContext.newPage();
  try {
    const base = new URL(page.url()).origin;
    await nativePage.goto(base + '/evenements');
    const transition = nativePage.getByRole('region', { name: 'Campagne pirate', exact: true });
    await transition.getByRole('link', { name: 'Ouvrir le dossier de campagne', exact: true }).click();
    const scout = nativePage.getByRole('button', { name: 'Envoyer la sonde', exact: true });
    await scout.focus();
    await expect(scout).toBeFocused();
    await nativePage.keyboard.press('Enter');
    await expect(nativePage).toHaveURL(/campagne-pirate\/1$/);
    await expect(nativePage.getByText('Reconnaissance en cours', { exact: true })).toBeVisible();
    await nativePage.setViewportSize({ width: 1920, height: 1080 });
    await nativePage.screenshot({ path: info.outputPath('P22-reconnaissance-en-cours-1920x1080.png'), scale: 'css', animations: 'disabled' });
    await expect(nativePage.getByText('Flotte pirate révélée', { exact: true })).toHaveCount(0);
    await expect(nativePage.locator('.pirate-campaign-intel--embedded')).toHaveCount(0);
    const reportId = Number(queryIsolatedSql(project, 'SELECT id FROM pirate_reconnaissance WHERE membership_id=' + member));
    const spyReport = '/rapports/espionnage-pirate/' + reportId;
    const simulator = '/simulateur?pirate_report=' + reportId;
    const reportPage = await nativeContext.newPage();
    try {
      expect((await reportPage.goto(base + spyReport)).status()).toBe(404);
      expect((await reportPage.goto(base + simulator)).status()).toBe(422);
    } finally {
      await reportPage.close();
    }
    // Le vrai départ a figé le reçu d'hydrogène. Avancer les deux horodatages du même écart
    // conserve la durée native. Le relais transmet ensuite le jeton au vrai worker.
    const shift = Number(queryIsolatedSql(project, 'SELECT GREATEST(TIMESTAMPDIFF(SECOND, UTC_TIMESTAMP(), due_at), 0) + 2 FROM pirate_reconnaissance WHERE membership_id=' + member + ' AND reported_at IS NULL'));
    expect(Number.isSafeInteger(shift) && shift >= 2).toBe(true);
    runIsolatedSql(project, 'UPDATE pirate_reconnaissance SET departed_at=DATE_SUB(departed_at,INTERVAL ' + shift + ' SECOND), due_at=DATE_SUB(due_at,INTERVAL ' + shift + ' SECOND) WHERE membership_id=' + member + ' AND reported_at IS NULL');
    runIsolatedSql(project, "INSERT INTO outbox_message (kind,target_id,token,due_at) SELECT 'pirate_reconnaissance',id,dispatch_token,due_at FROM pirate_reconnaissance WHERE id=" + reportId);
    await expect.poll(() => queryIsolatedSql(project, "SELECT COUNT(*) FROM pirate_mission_report WHERE category='espionage' AND reconnaissance_id=" + reportId), { timeout: 20000 }).toBe('1');
    const setFighterQuantity = quantity => runIsolatedSql(project, "UPDATE fleet_ship fs JOIN fleet f ON f.id=fs.fleet_id SET fs.quantity=" + quantity + " WHERE f.home_planet_id=" + origin + " AND f.status='idle' AND fs.slug='fighter' AND fs.tier='basic'");
    setFighterQuantity(0);
    await nativePage.getByRole('link', { name: 'Actualiser le dossier', exact: true }).click();
    const nativeDossier = nativePage.locator('.pirate-campaign-dossier');
    await expect(nativeDossier.getByRole('region', { name: 'Flotte pirate révélée', exact: true })).toContainText('Chasseur de patrouille');
    await expect(nativeDossier.getByRole('region', { name: 'Gain potentiel', exact: true })).toContainText('Ce montant n’a pas été versé.');
    await expect(nativePage.getByRole('button', { name: 'Vérifier le devis d’attaque', exact: true })).toHaveCount(0);
    await nativePage.screenshot({ path: info.outputPath('P05-sans-escorte-1920x1080.png'), scale: 'css', animations: 'disabled' });
    await expect(nativePage.getByRole('link', { name: 'Ouvrir le rapport d’espionnage' })).toHaveAttribute('href', spyReport);
    const intelPage = await nativeContext.newPage();
    try {
      await intelPage.goto(base + '/rapports?type=espionage');
      const centralLink = intelPage.getByRole('link', {name: /Espionnage · Campagne pirate/});
      await expect(centralLink).toHaveCount(1);
      const reportResponse = await intelPage.goto(new URL(await centralLink.getAttribute('href'), base).href);
      expect(reportResponse.status()).toBe(200);
      expect(reportResponse.headers()['cache-control']).toContain('no-store');
      await expect(intelPage.getByRole('heading', { name: 'Renseignement de combat complet' })).toBeVisible();
      await expect(intelPage.getByText(/Aucune défense planétaire, aucune forteresse/)).toBeVisible();
      await intelPage.setViewportSize({ width: 390, height: 844 });
      await expect.poll(() => intelPage.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(390);
      await intelPage.getByRole('link', { name: 'Reprendre ces renseignements dans le simulateur' }).click();
      await expect(intelPage).toHaveURL(new RegExp('/simulateur\\?pirate_report=' + reportId + '$'));
      await expect(intelPage.getByText(/Camp pirate verrouillé/)).toBeVisible();
      await expect(intelPage.locator('[name^="forces[defender]"]')).toHaveCount(0);
      await intelPage.locator('#sim-attacker-ships-fighter-basic').fill('1');
      await intelPage.locator('[name="assumptions"]').check();
      await intelPage.getByRole('button', { name: 'Simuler 10 combats' }).click();
      await expect(intelPage.getByRole('heading', { name: 'Estimation sur 10 combats' })).toBeVisible();
      expect(queryIsolatedSql(project, 'SELECT COUNT(*) FROM pirate_attack_campaign WHERE reconnaissance_id=' + reportId)).toBe('0');
    } finally {
      await intelPage.close();
    }


    // La limite du dossier doit rester distincte du stock affiché.
    setFighterQuantity(5);
    runIsolatedSql(project, "INSERT INTO fleet_ship (fleet_id,slug,tier,quantity) SELECT id,'bomber','basic',1 FROM fleet WHERE home_planet_id=" + origin + " AND status='idle'");
    runIsolatedSql(project, "INSERT INTO fleet_ship (fleet_id,slug,tier,quantity) SELECT id,'fighter','intermediate',2 FROM fleet WHERE home_planet_id=" + origin + " AND status='idle' ON DUPLICATE KEY UPDATE quantity=2");
    runIsolatedSql(project, "INSERT INTO fleet_ship (fleet_id,slug,tier,quantity) SELECT id,'solar_satellite','basic',1 FROM fleet WHERE home_planet_id=" + origin + " AND status='idle' ON DUPLICATE KEY UPDATE quantity=1");
    runIsolatedSql(project, `UPDATE universe SET game_settings=JSON_MERGE_PATCH(game_settings,'{"limits":{"max_fleet_size":3}}') WHERE id=${universe}`);
    await page.setViewportSize({ width: 1920, height: 1080 });
    await page.goto(campaignPath);
    const dossier = page.locator('.pirate-campaign-dossier');
    const intel = dossier.getByRole('region', { name: 'Renseignements de la mission', exact: true });
    const journal = page.getByRole('complementary', { name: 'Journal du dossier', exact: true });
    const assault = dossier.getByRole('button', { name: 'Vérifier le devis d’attaque', exact: true });
    await expect(intel.getByRole('region', { name: 'Flotte pirate révélée', exact: true })).toBeVisible();
    await expect(journal).toContainText('Renseignement reçu');
    await expect(journal).toContainText('Rapport de reconnaissance reçu');
    await expect(journal.getByText('Gain potentiel')).toHaveCount(0);
    await expect(page.locator('#pirate-intelligence-title')).toHaveCount(1);
    await expect(assault).toBeEnabled();
    const escortTable = dossier.getByRole('table', { name: 'Choisir l’escorte', exact: true });
    await expect(escortTable.getByRole('columnheader')).toHaveText(['Élément', 'Basique', 'Intermédiaire', 'Supérieur']);
    await expect(escortTable.getByText(/Satellite/)).toHaveCount(0);
    await expect(escortTable.locator('th[scope=rowgroup]')).toContainText(['Légers', 'Bombardiers']);
    const bomberIcon = escortTable.getByRole('rowheader', { name: /Bombardier/ }).locator('svg');
    await expect(bomberIcon).toBeVisible();
    await expect(bomberIcon.locator('use')).toHaveAttribute('href', /#bomber$/);
    const fighter = escortTable.getByRole('row', { name: /^Chasseur de patrouille/ });
    const [basic, intermediate, superior] = await fighter.getByRole('spinbutton').all();
    await expect(fighter.getByRole('button')).toHaveCount(3);
    const [basicMax, intermediateMax, superiorMax] = await fighter.getByRole('button').all();
    await expect(fighter.locator('[data-fleet-stock-value]')).toHaveText(['(5)', '(2)', '(0)']);
    await expect(basic).toHaveAttribute('max', '3');
    await expect(superior).toBeDisabled();
    await expect(superior).not.toHaveAttribute('name');
    await expect(superiorMax).toBeDisabled();
    await intermediateMax.click();
    await expect(intermediate).toHaveValue('2');
    await expect(basic).toHaveAttribute('max', '1');
    await basicMax.focus();
    await page.keyboard.press('Enter');
    await expect(basic).toHaveValue('1');
    expect((await dossier.getByRole('region', { name: 'Gain potentiel', exact: true }).boundingBox()).y).toBeGreaterThan((await assault.boundingBox()).y);
    await expect(page.getByRole('link', { name: 'Voir les renseignements', exact: true })).toHaveCount(0);
    const desktopDossier = await dossier.boundingBox();
    expect((await journal.boundingBox()).x).toBeGreaterThanOrEqual(desktopDossier.x + desktopDossier.width);
    for (const [width, height] of [[1920, 1080], [390, 844]]) {
      await page.setViewportSize({ width, height });
      await page.screenshot({ path: info.outputPath('P05-rapport-recu-' + width + 'x' + height + '.png'), scale: 'css', animations: 'disabled' });
      expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(width);
      if (width === 390) {
        const mobileDossier = await dossier.boundingBox();
        expect((await journal.boundingBox()).y).toBeGreaterThanOrEqual(mobileDossier.y + mobileDossier.height);
      }
      expect((await assault.boundingBox()).y).toBeGreaterThan((await intel.boundingBox()).y);
      await assault.evaluate(button => button.scrollIntoView({ block: 'center', behavior: 'instant' }));
      await assault.focus();
      await expect(assault).toBeFocused();
      await expect(assault).toBeInViewport({ ratio: 1 });
      expect(await assault.evaluate(button => {
        const rect = button.getBoundingClientRect();
        return button.contains(document.elementFromPoint(rect.x + rect.width / 2, rect.y + rect.height / 2));
      })).toBe(true);
      await page.screenshot({ path: info.outputPath('P05-action-' + width + 'x' + height + '.png'), scale: 'css', animations: 'disabled' });
      await page.evaluate(() => window.scrollTo({ top: 0, behavior: 'instant' }));
    }
    expect((await new AxeBuilder({ page }).include('.pirate-campaign').withTags(['wcag2a', 'wcag2aa', 'wcag21aa']).analyze()).violations).toEqual([]);

    // Les maxima choisis dans les deux rangs arrivent ensemble au devis, sans départ.
    await assault.click();
    await expect(dossier.getByRole('heading', { name: 'Confirmer l’attaque', exact: true })).toBeVisible();
    await expect(dossier.getByText('Hydrogène prélevé sur la colonie', { exact: true })).toBeVisible();
    await expect(dossier.getByText('Pris dans la réserve embarquée', { exact: true })).toHaveCount(0);
    await expect(dossier.getByRole('list', { name: 'Escorte choisie' }).getByRole('listitem')).toHaveText([
      'Chasseur de patrouille : 1',
      'Chasseur multirôle : 2',
    ]);
    expect(queryIsolatedSql(project, 'SELECT COUNT(*) FROM pirate_attack_campaign a JOIN pirate_reconnaissance r ON r.id=a.reconnaissance_id WHERE r.membership_id=' + member)).toBe('0');

    // L’aperçu natif n’envoie rien : la confirmation conserve son agencement existant.
    await nativePage.reload();
    const nativeAssault = nativeDossier.getByRole('button', { name: 'Vérifier le devis d’attaque', exact: true });
    const nativeEscort = nativeDossier.getByRole('table', { name: 'Choisir l’escorte', exact: true });
    await expect(nativeEscort.getByRole('button', { name: /^Max/ })).toHaveCount(0);
    await nativeEscort.getByRole('spinbutton', { name: /Chasseur de patrouille.*Basique/ }).fill('1');
    await nativeAssault.click();
    await expect(nativePage.getByRole('heading', { name: 'Confirmer l’attaque', exact: true })).toBeVisible();
    await expect(nativePage.getByRole('link', { name: 'Voir les renseignements', exact: true })).toHaveAttribute('href', '#pirate-intelligence-title');
    await expect(nativePage.getByRole('button', { name: 'Confirmer et envoyer l’escorte', exact: true })).toBeVisible();
    await expect(nativePage.locator('.pirate-campaign-intel--embedded')).toHaveCount(0);

    runIsolatedSql(project, `UPDATE universe SET game_settings=JSON_MERGE_PATCH(game_settings,'{"features":{"pirate_campaign_enabled":false}}') WHERE id=${universe}`);
    const setting = "JSON_EXTRACT(game_settings,'$.features.pirate_campaign_enabled')";
    expect(queryIsolatedSql(project, `SELECT COUNT(*) FROM universe WHERE id=${universe} AND JSON_TYPE(${setting})='BOOLEAN' AND JSON_UNQUOTE(${setting})='false'`)).toBe('1');
    await nativePage.goto(base + '/evenements');
    await expect(transition).toHaveCount(0);
    await expect(nativePage.getByRole('navigation', { name: 'Navigation principale', exact: true }).getByRole('link', { name: 'Campagne pirate', exact: true })).toHaveCount(0);
    expect((await nativePage.goto(base + campaignPath)).status()).toBe(404);
  } finally {
    runIsolatedSql(project, `UPDATE universe SET game_settings=JSON_MERGE_PATCH(game_settings,'{"features":{"pirate_campaign_enabled":true}}') WHERE id=${universe}`);
    await nativeContext.close();
  }
});
