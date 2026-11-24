import { test, expect } from './support/isolated-test.mjs';
import AxeBuilder from '@axe-core/playwright';
import { execFileSync } from 'node:child_process';
import { queryIsolatedSql, runIsolatedSql } from './support/game-fixtures.mjs';

// Captures alignées sur le viewport de la maquette, sans recadrage ni page entière.
async function capture(page, testInfo, label, width, height) {
  await page.setViewportSize({ width, height });
  await page.evaluate(() => document.fonts.ready);
  const actual = await page.evaluate(() => [innerWidth, innerHeight, devicePixelRatio]);
  expect(actual).toEqual([width, height, 1]);
  await expect.poll(() => page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(width);
  await page.screenshot({
    path: testInfo.outputPath(label + '-' + width + '.png'),
    animations: 'disabled',
    scale: 'css',
  });
}

test('Maquette publique : proportions de l’accueil, connexion et mode étroit', async ({ page }, testInfo) => {
  await page.setViewportSize({ width: 1920, height: 1080 });
  await page.goto('/');
  const hero = page.locator('.entry-home--audit .entry-hero');
  const box = await hero.boundingBox();
  expect(box.x).toBeGreaterThanOrEqual(74);
  expect(box.x).toBeLessThanOrEqual(105);
  expect(box.y).toBeLessThan(135);
  expect(box.height).toBeGreaterThan(540);
  const preview = await hero.locator('.public-hero-preview').boundingBox();
  const intro = await page.locator('.entry-game-introduction').boundingBox();
  const galaxy = await page.locator('.public-previews article').boundingBox();
  expect(preview.x).toBeGreaterThan(900);
  expect(preview.width).toBeGreaterThan(650);
  expect(galaxy.x).toBeGreaterThan(intro.x + intro.width);
  expect(Math.abs(galaxy.y - intro.y)).toBeLessThan(4);
  await expect(page.getByRole('link', { name: 'Ouvrir l’aperçu de la galaxie' })).toHaveAttribute('href', /ui-preview-galaxy[.]png$/);
  await capture(page, testInfo, '01-accueil', 1920, 1080);
  await capture(page, testInfo, '01-accueil', 390, 844);

  await page.setViewportSize({ width: 1920, height: 1080 });
  await page.goto('/connexion');
  await expect(page.getByRole('heading', { level: 1, name: 'Reprendre le commandement' })).toBeVisible();
  const form = await page.locator('.entry-access-panel').boundingBox();
  const scene = await page.locator('.entry-access-context').boundingBox();
  expect(form.x).toBeGreaterThan(135);
  expect(form.x).toBeLessThan(190);
  expect(form.width).toBeGreaterThan(550);
  expect(form.width).toBeLessThan(650);
  expect(scene.x).toBeGreaterThan(form.x + form.width);
  expect(scene.height).toBeGreaterThan(700);
  expect(scene.height).toBeLessThan(820);
  expect((await page.locator('footer').boundingBox()).y).toBeLessThan(1070);
  await expect(page.getByRole('navigation', { name: 'Accès au compte' }).getByRole('link', { name: 'Se connecter' })).toHaveAttribute('aria-current', 'page');
  expect(Math.abs(scene.y - form.y)).toBeLessThan(4);
  await capture(page, testInfo, '02-connexion', 1920, 1080);
  await page.setViewportSize({ width: 390, height: 844 });
  await expect(page.locator('.entry-access-context')).toBeHidden();
  await expect(page.getByRole('button', { name: 'Connexion' })).toBeVisible();
  await capture(page, testInfo, '02-connexion', 390, 844);
});

test('Documents publics : sommaires, textes conservés et ancres natives sans JavaScript', async ({ browser, baseURL }, testInfo) => {
  const context = await browser.newContext({ baseURL, javaScriptEnabled: false, viewport: { width: 1920, height: 1080 } });
  try {
    const page = await context.newPage();
    for (const [route, first, sections] of [
      ['/mentions-legales', 'Le site et son éditeur', 4],
      ['/cgu', 'Présentation du service', 6],
      ['/confidentialite', 'Responsable et contact', 6],
      ['/cookies', 'Connexion et sécurité', 4],
    ]) {
      const response = await page.goto(route);
      expect(response.status()).toBe(200);
      const toc = page.getByRole('navigation', { name: 'Dans cette page' });
      const article = page.locator('.legal-content');
      await expect(toc.locator('li a')).toHaveCount(sections);
      const targets = await toc.locator('li a').evaluateAll(links => links.map(link => link.getAttribute('href')));
      for (const target of targets) await expect(article.locator(target)).toHaveCount(1);
      const [left, right] = await Promise.all([toc.boundingBox(), article.boundingBox()]);
      expect(left.x + left.width).toBeLessThan(right.x);
      expect(left.width).toBeGreaterThan(320);
      expect(left.width).toBeLessThan(380);
      expect(right.width).toBeGreaterThan(1150);
      const warning = page.getByRole('complementary', { name: 'État de cette information' });
      await expect(warning.getByRole('heading', { name: 'Information provisoire et incomplète' })).toBeVisible();
      const warningBox = await warning.boundingBox();
      expect(warningBox.x).toBeGreaterThan(left.x + left.width);
      expect(warningBox.y + warningBox.height).toBeLessThanOrEqual(right.y);
      await toc.getByRole('link', { name: first }).click();
      expect(new URL(page.url()).hash).toBe(targets[0]);
      await expect(article.getByRole('heading', { name: first, exact: true })).toBeVisible();
      await toc.getByRole('link', { name: 'Revenir en haut' }).click();
      expect(new URL(page.url()).hash).toBe('#legal-title');
      if (route !== '/confidentialite') {
        await page.goto(route);
        await capture(page, testInfo, '07-' + route.slice(1), 1920, 1080);
      }
      if (route === '/confidentialite') {
        await page.goto(route);
        await capture(page, testInfo, '06-confidentialite', 1920, 1080);
        await page.setViewportSize({ width: 390, height: 844 });
        const [before, after] = await Promise.all([toc.boundingBox(), article.boundingBox()]);
        const notice = await warning.boundingBox();
        expect(notice.y + notice.height).toBeLessThan(before.y);
        expect(before.y + before.height).toBeLessThan(after.y);
        const readingOrder = await page.locator('.legal-audit-layout').evaluate(node => Array.from(node.children, child => child.tagName));
        expect(readingOrder).toEqual(['ASIDE', 'NAV', 'DIV']);
        await capture(page, testInfo, '06-confidentialite', 390, 844);
        await page.setViewportSize({ width: 1920, height: 1080 });
      }
    }
  } finally {
    await context.close();
  }
  const contextAxe = await browser.newContext({ baseURL, viewport: { width: 390, height: 844 } });
  try {
    const page = await contextAxe.newPage();
    await page.goto('/confidentialite');
    const { violations } = await new AxeBuilder({ page })
      .include('.legal-page--audit')
      .withTags(['wcag2a', 'wcag2aa', 'wcag21aa', 'wcag22aa'])
      .analyze();
    expect(violations).toEqual([]);
  } finally {
    await contextAxe.close();
  }
});

test('Premier accès : trois états universitaires dans le viewport de la maquette', async ({ page }, testInfo) => {
  const suffix = crypto.randomUUID().slice(0, 8);
  const fixtureEmail = `public-maquette-${suffix}@example.test`;
  const fixturePassword = crypto.randomUUID() + 'Aa!';
  const project = process.env.BROWSER_ROUTER;
  const closedSlug = `maquette-closed-${suffix}`;

  await page.setViewportSize({ width: 1920, height: 1080 });
  await page.goto('/inscription');
  await page.getByLabel('Adresse email').fill(fixtureEmail);
  await page.getByLabel('Nom de commandant').fill(`Public-${suffix}`);
  await page.getByLabel('Mot de passe (12 caractères minimum)', { exact: true }).fill(fixturePassword);
  await page.getByLabel('Confirmer le mot de passe').fill(fixturePassword);
  await page.getByRole('button', { name: 'Créer mon compte' }).click();
  await expect(page).toHaveURL(/connexion$/);
  await page.getByLabel('Adresse email').fill(fixtureEmail);
  await page.getByLabel('Mot de passe', { exact: true }).fill(fixturePassword);
  await page.getByRole('button', { name: 'Connexion' }).click();
  await expect(page).toHaveURL(/univers$/);

  // Le jeu importe deux univers. Le troisième état n’existe que dans cette copie.
  const startsAt = queryIsolatedSql(project, "SELECT COALESCE(DATE_FORMAT(starts_at,'%Y-%m-%d %H:%i:%s'),'NULL') FROM universe WHERE slug='turbo-1'");
  expect(startsAt).toMatch(/^(NULL|\d{4}-\d\d-\d\d \d\d:\d\d:\d\d)$/);
  const restore = startsAt === 'NULL' ? 'NULL' : "'"+startsAt+"'";
  try {
    runIsolatedSql(project, "UPDATE universe SET starts_at=DATE_ADD(UTC_TIMESTAMP(), INTERVAL 1 DAY) WHERE slug='turbo-1'");
    runIsolatedSql(project, `INSERT INTO universe (slug,name,status,game_settings) VALUES ('${closedSlug}','Univers saisonnier','closed',JSON_OBJECT())`);
    await page.goto('/univers');
    await expect(page.getByRole('heading', { level: 1, name: 'Choisissez votre univers' })).toBeVisible();
    const cards = page.locator('.universe-card');
    await expect(cards).toHaveCount(3);
    const initialStates = await cards.locator('.entry-universe-state').allTextContents();
    expect(initialStates.map(s => s.trim())).toEqual(expect.arrayContaining(['OUVERT', 'OUVERTURE PROGRAMMÉE', 'INACCESSIBLE']));
    await expect(cards.locator('button')).toHaveCount(1);
    const rowYs = await cards.evaluateAll(nodes => nodes.map(node => node.getBoundingClientRect().y));
    expect(Math.max(...rowYs) - Math.min(...rowYs)).toBeLessThan(2);
    await capture(page, testInfo, '08-univers-premier-acces', 1920, 1080);
    await page.setViewportSize({ width: 390, height: 844 });
    const mobileYs = await cards.evaluateAll(nodes => nodes.map(node => node.getBoundingClientRect().y));
    expect(new Set(mobileYs).size).toBe(3);
    await capture(page, testInfo, '08-univers-premier-acces', 390, 844);

    // L'univers ouvert reste le seul joignable. Rejouer une reprise avant d’afficher la fermeture.
    const openCard = cards.filter({ has: page.getByText('OUVERT', { exact: true }) });
    await openCard.getByRole('button', { name: /Créer mon empire/ }).click();
    await expect(page).toHaveURL(/planetes\/\d+$/);
    const colony = page.url();
    const omegaEnds = queryIsolatedSql(project, "SELECT COALESCE(DATE_FORMAT(ends_at,'%Y-%m-%d %H:%i:%s'),'NULL') FROM universe WHERE slug='omega-1'");
    expect(omegaEnds).toMatch(/^(NULL|\d{4}-\d\d-\d\d \d\d:\d\d:\d\d)$/);
    const restoreEnd = omegaEnds === 'NULL' ? 'NULL' : "'" + omegaEnds + "'";
    try {
      runIsolatedSql(project, "UPDATE universe SET ends_at=DATE_ADD(UTC_TIMESTAMP(), INTERVAL 12 HOUR) WHERE slug='omega-1'");
      await page.goto('/univers');
      const current = cards.filter({ has: page.getByText('Univers actuel', { exact: true }) });
      await expect(current.getByRole('button', { name: /Reprendre mon empire/ })).toBeVisible();
      const closing = current.locator('.universe-closing');
      await expect(closing).toBeVisible();
      await expect(closing.locator('[data-universe-closing-target=date]')).toContainText('UTC');
      await capture(page, testInfo, '09-univers-reprise-fermeture', 1920, 1080);
      await capture(page, testInfo, '09-univers-reprise-fermeture', 390, 844);
      await current.getByRole('button', { name: /Reprendre mon empire/ }).click();
      await expect(page).toHaveURL(colony);
    } finally {
      runIsolatedSql(project, `UPDATE universe SET ends_at=${restoreEnd} WHERE slug='omega-1'`);
    }
  } finally {
    runIsolatedSql(project, `DELETE FROM universe WHERE slug='${closedSlug}'`);
    runIsolatedSql(project, `UPDATE universe SET starts_at=${restore} WHERE slug='turbo-1'`);
  }
});

test.describe('Inscription et récupération avec confirmation en attente', () => {
  test.use({ verifyRegisteredAccounts: false });
  test('États publics : avis neutres et erreur de connexion dans la carte', async ({ page }, testInfo) => {
    test.setTimeout(120000);
    const suffix = crypto.randomUUID().slice(0, 8);
    const email = 'public-absence-' + suffix + '@example.test';
    await page.setViewportSize({ width: 1920, height: 1080 });
    await page.goto('/inscription');
    await capture(page, testInfo, '03-inscription', 1920, 1080);
    for (const [path, action, label] of [
      ['/inscription/renvoyer', 'Envoyer le lien de confirmation', '03-renvoyer'],
      ['/mot-de-passe/oublie', 'Envoyer le lien', '04-oubli'],
    ]) {
      await page.goto(path);
      await page.getByLabel('Adresse email').fill(email);
      await page.getByRole('button', { name: action, exact: true }).click();
      const notice = page.locator('.entry-access-panel').getByRole('status');
      await expect(notice).toContainText('Demande reçue');
      await expect(notice).toContainText('Si cette adresse correspond');
      await expect(notice).not.toContainText(email);
      await expect(page.getByLabel('Adresse email')).toBeVisible();
      await capture(page, testInfo, label + '-accuse', 1920, 1080);
      await capture(page, testInfo, label + '-accuse', 390, 844);
      await page.setViewportSize({ width: 1920, height: 1080 });
    }
    for (const [path, title, label] of [
      ['/inscription/confirmer/' + suffix, 'Lien invalide ou expiré', '03-confirmation-invalide'],
      ['/mot-de-passe/reinitialiser/' + suffix, 'Lien expiré ou invalide', '04-reinitialisation-invalide'],
    ]) {
      await page.goto(path);
      const confirm = page.getByRole('button', { name: 'Confirmer mon adresse' });
      if (await confirm.isVisible()) {
        await capture(page, testInfo, '03-confirmer-adresse', 1920, 1080);
        await confirm.click();
      }
      await expect(page.getByRole('heading', { name: title, exact: true })).toBeVisible();
      await capture(page, testInfo, label, 1920, 1080);
      await capture(page, testInfo, label, 390, 844);
      await page.setViewportSize({ width: 1920, height: 1080 });
    }
    await page.goto('/connexion');
    await page.getByLabel('Adresse email').fill(email);
    await page.getByLabel('Mot de passe', { exact: true }).fill(crypto.randomUUID() + 'Aa!');
    await page.getByRole('button', { name: 'Connexion' }).click();
    await expect(page.locator('.entry-access-panel').getByRole('alert')).toContainText('Connexion impossible');
    await expect(page.getByLabel('Adresse email')).toHaveAttribute('aria-describedby', 'login-error');
    await capture(page, testInfo, '10-connexion-erreur', 390, 844);
    await capture(page, testInfo, '10-connexion-erreur', 360, 800);

    // Ces jetons sont des données aléatoires du projet jetable ; aucun courriel réel n’est émis.
    const fixturePassword = crypto.randomUUID() + 'Aa!';
    await page.goto('/inscription');
    await page.getByLabel('Adresse email').fill(email);
    await page.getByLabel('Nom de commandant').fill('Public-' + suffix);
    await page.getByLabel('Mot de passe (12 caractères minimum)', { exact: true }).fill(fixturePassword);
    await page.getByLabel('Confirmer le mot de passe').fill(fixturePassword);
    await page.getByRole('button', { name: 'Créer mon compte' }).click();
    await expect(page.locator('.entry-access-panel').getByRole('status')).toContainText('Compte créé');
    await capture(page, testInfo, '03-compte-cree', 1920, 1080);

    const project = process.env.BROWSER_ROUTER;
    const verifyLink = crypto.randomUUID() + crypto.randomUUID().slice(0, 8);
    const resetLink = crypto.randomUUID() + crypto.randomUUID().slice(0, 8);
    runIsolatedSql(project, `INSERT INTO player_email_verification_token (player_id,token_hash,expires_at) SELECT id,SHA2('${verifyLink}',256),DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 HOUR) FROM player WHERE email='${email}'`);
    runIsolatedSql(project, `INSERT INTO player_password_reset_token (player_id,token_hash,created_at,expires_at) SELECT id,SHA2('${resetLink}',256),UTC_TIMESTAMP(),DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 HOUR) FROM player WHERE email='${email}'`);
    await page.goto('/inscription/confirmer/' + verifyLink);
    await page.getByRole('button', { name: 'Confirmer mon adresse' }).click();
    await expect(page.locator('.entry-access-panel').getByRole('status')).toContainText('Adresse email confirmée');
    await capture(page, testInfo, '03-confirmation-reussie', 1920, 1080);
    await page.goto('/mot-de-passe/reinitialiser/' + resetLink);
    const recovery = page.locator('.entry-password-pair input[type=password]');
    await expect(recovery).toHaveCount(2);
    await capture(page, testInfo, '04-nouveau-mot-de-passe', 1920, 1080);
    const newPassword = crypto.randomUUID() + 'Aa!';
    await recovery.nth(0).fill(newPassword);
    await recovery.nth(1).fill(newPassword);
    await page.getByRole('button', { name: 'Modifier le mot de passe' }).click();
    await expect(page.locator('.entry-access-panel').getByRole('status')).toContainText('Mot de passe modifié');
    await capture(page, testInfo, '04-reinitialisation-reussie', 1920, 1080);
  });
});

test('Erreurs publiques : repères de reprise sans dépendance applicative', async ({ browser, baseURL, page }, testInfo) => {
  const french = await browser.newContext({ baseURL, javaScriptEnabled: false, viewport: { width: 1920, height: 1080 } });
  try {
    const standalone = await french.newPage();
    const response = await standalone.goto('/page-publique-de-test-absente');
    expect(response.status()).toBe(404);
    await expect(standalone.getByRole('link', { name: /^Genesis Reborn$/i })).toHaveAttribute('href', '/');
    await expect(standalone.locator('main').getByRole('link', { name: /Retour à l’accueil/ })).toHaveAttribute('href', '/');
    await expect(standalone.getByRole('navigation', { name: 'Informations juridiques' }).getByRole('link', { name: 'Confidentialité' })).toHaveAttribute('href', '/confidentialite');
    await capture(standalone, testInfo, '05-erreur-404', 1920, 1080);
    await capture(standalone, testInfo, '05-erreur-404', 390, 844);

    // Rendu Twig autonome, interposé dans Chromium : le serveur ne subit aucun incident.
    const project = process.env.BROWSER_ROUTER;
    expect(project).toMatch(/^genesis-browser-test-[0-9]+-[0-9]+$/);
    const html = execFileSync('docker', [
      'compose', '-p', project, '--env-file', '.env.local', 'exec', '-T', 'app', 'php', '-r',
      'require "/app/vendor/autoload.php"; $twig = new Twig\\Environment(new Twig\\Loader\\FilesystemLoader("/app/templates")); echo $twig->render("bundles/TwigBundle/Exception/error500.html.twig");',
    ], { encoding: 'utf8' });
    await standalone.route('**/rendu-local-incident', route => route.fulfill({ status: 500, contentType: 'text/html; charset=utf-8', body: html }));
    const rendered = await standalone.goto('/rendu-local-incident');
    expect(rendered.status()).toBe(500);
    await expect(standalone.getByRole('heading', { name: 'Une erreur est survenue', exact: true })).toBeVisible();
    await capture(standalone, testInfo, '05-erreur-500', 1920, 1080);
    await capture(standalone, testInfo, '05-erreur-500', 390, 844);
    await standalone.locator('main').getByRole('link', { name: /Retour à l’accueil/ }).click();
    await expect(standalone).toHaveURL('/');
  } finally {
    await french.close();
  }
  await page.goto('/acces-refuse');
  await expect(page.getByRole('heading', { name: 'Accès refusé', exact: true })).toBeVisible();
  await expect(page.locator('main').getByRole('link', { name: 'Se connecter', exact: true })).toHaveAttribute('href', '/connexion');
  await capture(page, testInfo, '05-acces-refuse', 1920, 1080);
});
