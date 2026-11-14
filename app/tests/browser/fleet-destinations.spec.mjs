import { test, expect } from './support/isolated-test.mjs';
import { prepareOwnedColony, revealVirginPlanet, revealNpcPlanet, runIsolatedSql, queryIsolatedSql } from './support/game-fixtures.mjs';

test.use({ hasTouch: true });

async function prepare(page) {
  const id = crypto.randomUUID().slice(0, 8);
  const email = 'details-' + id + '@example.test';
  const password = 'Browser-test-2026!';
  await page.goto('/inscription');
  await page.getByLabel('Adresse email').fill(email);
  await page.getByLabel('Nom de commandant').fill('Destination-' + id);
  await page.getByLabel('Mot de passe (12 caractères minimum)', { exact: true }).fill(password);
  await page.getByLabel('Confirmer le mot de passe').fill(password);
  await page.getByRole('button', { name: 'Créer mon compte' }).click();
  await expect(page).toHaveURL(/connexion/);
  await page.getByLabel('Adresse email').fill(email);
  await page.getByLabel('Mot de passe', { exact: true }).fill(password);
  await page.getByRole('button', { name: /Connexion/ }).click();
  await page.locator('article.universe-card').filter({ has: page.getByRole('heading', { name: /Turbo/ }) }).getByRole('button').click();
  await expect(page).toHaveURL(/planetes\/\d+$/);

  const project = process.env.BROWSER_ROUTER;
  const origin = Number(new URL(page.url()).pathname.split('/').at(-1));
  prepareOwnedColony(project, email);
  const own = revealVirginPlanet(project, email), npc = revealNpcPlanet(project, email);
  runIsolatedSql(project, "UPDATE planet target JOIN planet home ON home.id=" + origin + " SET target.owner_id=home.owner_id,target.name='Destination connue',target.acquired_at=UTC_TIMESTAMP() WHERE target.id=" + own.id);
  runIsolatedSql(project, "INSERT INTO fleet (player_universe_id,home_planet_id,status,mission,speed_ratio,cargo,reserved_fuel,orbit_fuel,orbit_duration_seconds,travel_seconds,dispatch_token) SELECT owner_id,id,'idle','idle',1,'{}',0,0,0,0,'' FROM planet WHERE id=" + origin + " AND NOT EXISTS (SELECT 1 FROM fleet WHERE home_planet_id=" + origin + " AND status='idle')");
  for (const slug of ['probe', 'transport', 'fighter']) runIsolatedSql(project, "INSERT INTO fleet_ship (fleet_id,slug,tier,quantity) SELECT id,'" + slug + "','basic',5 FROM fleet WHERE home_planet_id=" + origin + " AND status='idle' ON DUPLICATE KEY UPDATE quantity=5");
  return { id, own, npc, project, origin };
}

test('Les trois modes de destination et les suggestions fonctionnent au clavier et au toucher', async ({ page }, testInfo) => {
  const { id, own, npc } = await prepare(page);
  await page.goto('/flottes');
  const form = page.locator('.fleet-form form').first();
  await expect(form.locator('[name="coordinates"]')).toBeDisabled();
  for (const slug of ['probe', 'transport', 'fighter']) await form.locator('[name="ships[' + slug + ']"]').fill('1');
  await form.getByRole('button', { name: /Choisir la destination/ }).click();
  await expect(form.getByLabel('Mes planètes et alliés', { exact: true })).toBeChecked();
  await form.getByLabel('Planète de mon empire ou alliée', { exact: true }).selectOption(String(own.id));
  await form.getByRole('button', { name: /Vérifier la mission/ }).click();
  await expect(form.locator('[data-fleet-wizard-target="summary"]')).toContainText(own.coordinates.split(':').slice(0, 2).join(':'));
  await form.getByRole('button', { name: /Modifier la destination/ }).click();
  await form.locator('[name="mission"]').selectOption('attack');
  await form.getByLabel('Ennemis', { exact: true }).check();
  await form.getByLabel('Planète ennemie ou PNJ', { exact: true }).selectOption(String(npc.id));
  await expect(form.locator('[name="coordinates"]')).toBeDisabled();
  await form.getByLabel('Destination personnalisée', { exact: true }).check();
  await form.locator('[name="mission"]').selectOption('transport');
  const search = form.getByRole('combobox', { name: 'Pseudonyme du commandant' });
  await search.fill('Destination-' + id);
  await expect(search).toHaveAttribute('aria-expanded', 'true');
  await search.press('ArrowDown');
  await expect(search).toHaveAttribute('aria-activedescendant', /destination-results/);
  await search.press('Enter');
  await expect(form.locator('[name="coordinates"]')).toHaveValue(own.coordinates);
  await expect(search).toHaveAttribute('aria-expanded', 'false');
  await form.locator('[name="mission"]').selectOption('attack');
  await search.press('ArrowDown');
  await expect(search).toHaveAttribute('aria-expanded', 'false');
  await search.press('Enter');
  await expect(form.locator('[data-fleet-destination-target="searchStatus"]')).toContainText('Aucune planète découverte');
  await form.locator('[name="mission"]').selectOption('transport');
  await search.fill('zzzz-introuvable');
  await expect(form.locator('[name="coordinates"]')).toHaveValue('');
  await expect(form.locator('[data-fleet-destination-target="searchStatus"]')).toContainText('Aucune planète découverte');
  await page.setViewportSize({ width: 390, height: 844 });
  await search.fill('Destination-' + id);
  const option = form.getByRole('option', { name: /Destination connue/ });
  await expect(option).toBeVisible();
  await page.screenshot({ path: testInfo.outputPath('destinations-mobile.png'), animations: 'disabled' });
  await option.tap();
  await expect(form.locator('[name="coordinates"]')).toHaveValue(own.coordinates);
  await expect.poll(() => page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
});

test('Une attaque PNJ peut être envoyée depuis Ennemis sans capturer la planète ni dupliquer les vaisseaux', async ({ page }) => {
  const { npc, project, origin } = await prepare(page);
  const total = () => Number(queryIsolatedSql(project, 'SELECT COALESCE(SUM(s.quantity),0) FROM fleet_ship s JOIN fleet f ON f.id=s.fleet_id WHERE f.player_universe_id=(SELECT owner_id FROM planet WHERE id=' + origin + ')'));
  const before = total();
  await page.goto('/flottes');
  const form = page.locator('.fleet-form form').first();
  await expect(form.locator('[name="coordinates"]')).toBeDisabled();
  for (const slug of ['probe', 'transport', 'fighter']) await form.locator('[name="ships[' + slug + ']"]').fill('1');
  await form.getByRole('button', { name: /Choisir la destination/ }).click();
  await form.locator('[name="mission"]').selectOption('attack');
  await form.locator('[name="ratio"]').selectOption('0.1');
  await form.getByLabel('Ennemis', { exact: true }).check();
  await form.getByLabel('Planète ennemie ou PNJ', { exact: true }).selectOption(String(npc.id));
  await form.getByRole('button', { name: /Vérifier la mission/ }).click();
  await expect(form.locator('.flight-estimate')).toContainText('Carburant aller-retour');
  await expect(form.getByRole('button', { name: /Envoyer la flotte/ })).toBeEnabled();
  await form.getByRole('button', { name: /Envoyer la flotte/ }).click();
  await expect(page.locator('.flash[role="status"]')).toContainText('Flotte envoyée');
  expect(queryIsolatedSql(project, "SELECT mission,destination_id FROM fleet WHERE player_universe_id=(SELECT owner_id FROM planet WHERE id=" + origin + ") AND mission='attack' ORDER BY id DESC LIMIT 1")).toBe('attack\t' + npc.id);
  expect(total()).toBe(before);
  expect(queryIsolatedSql(project, 'SELECT owner_id IS NULL FROM planet WHERE id=' + npc.id)).toBe('1');
  expect(queryIsolatedSql(project, 'SELECT COUNT(*) FROM planet_occupation WHERE planet_id=' + npc.id)).toBe('0');
});

test('Flottes : colonie active, compteurs et actualisation restent cohérents',async({page})=>{
  const {own,project,origin}=await prepare(page);
  runIsolatedSql(project,`INSERT INTO fleet(player_universe_id,home_planet_id,status,mission,speed_ratio,cargo,reserved_fuel,orbit_fuel,orbit_duration_seconds,travel_seconds,dispatch_token) SELECT owner_id,id,'idle','idle',1,'{}',0,0,0,0,'' FROM planet WHERE id=${own.id}`);
  runIsolatedSql(project,`INSERT INTO fleet_ship(fleet_id,slug,tier,quantity) SELECT MAX(id),'probe','basic',2 FROM fleet WHERE home_planet_id=${own.id} AND status='idle'`);
  const flights=[];
  for(const colony of [origin,own.id]) {
    runIsolatedSql(project,`INSERT INTO fleet(player_universe_id,home_planet_id,destination_system_id,status,mission,speed_ratio,cargo,reserved_fuel,orbit_fuel,orbit_duration_seconds,travel_seconds,departure_at,arrival_at,dispatch_token) SELECT owner_id,id,solar_system_id,'traveling','explore',1,'{}',0,0,0,86400,UTC_TIMESTAMP(),DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 DAY),'scope-${colony}' FROM planet WHERE id=${colony}`);
    flights.push(Number(queryIsolatedSql(project,`SELECT MAX(id) FROM fleet WHERE home_planet_id=${colony} AND status='traveling'`)));
  }
  for(const [colony,visible,hidden] of [[origin,flights[0],flights[1]],[own.id,flights[1],flights[0]]]) {
    await page.goto('/planetes/'+colony);await page.goto('/flottes');
    await expect(page.locator('form[action="/flottes/envoyer"]')).toHaveCount(1);
    await expect(page.locator('form[action="/flottes/envoyer"] input[name="origin"]')).toHaveValue(String(colony));
    await expect(page.locator('[data-active-fleet="'+visible+'"]')).toBeVisible();
    await expect(page.locator('[data-active-fleet="'+hidden+'"]')).toHaveCount(0);
    await expect(page.getByRole('link',{name:'Missions actives (1)',exact:true})).toBeVisible();
    await expect(page.locator('main').getByRole('link',{name:'Simuler un combat',exact:true})).toHaveCount(0);
    await expect(page.locator('main a.inline-link').filter({hasText:'Mes compositions'})).toBeVisible();
    const polling=await page.waitForResponse(response=>response.url().includes('/flottes/surveillance')&&new URL(response.url()).searchParams.get('colony')===String(colony));
    expect(polling.status()).toBe(200);
    await page.evaluate(()=>window.dispatchEvent(new CustomEvent('genesis:refresh')));
    await expect(page.locator('[data-active-fleet="'+hidden+'"]')).toHaveCount(0);
  }
});
