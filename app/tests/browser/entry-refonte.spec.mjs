import { test, expect } from './support/isolated-test.mjs';
import AxeBuilder from '@axe-core/playwright';
import { queryIsolatedSql, runIsolatedSql } from './support/game-fixtures.mjs';

async function checkBounds(page, width) {
  await expect.poll(() => page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(width);
  const overflows = await page.locator('.entry-workspace :is(input:not([type=hidden]),button,dt,dd), .entry-public-navigation a').evaluateAll(elements => elements.filter(element => {
    if (!element.getClientRects().length) return false;
    const box = element.getBoundingClientRect();
    return box.left < -1 || box.right > document.documentElement.clientWidth + 1 || element.scrollWidth > element.clientWidth + 1;
  }).map(element => element.textContent.trim() || element.getAttribute('name')));
  expect(overflows).toEqual([]);
}

async function checkAccessibility(page) {
  const audit = new AxeBuilder({ page })
    .include('.entry-workspace')
    .withTags(['wcag2a', 'wcag2aa', 'wcag21aa', 'wcag22aa']);
  if (await page.locator('.entry-public-header').count()) audit.include('.entry-public-header');
  const report = await audit.analyze();
  expect.soft(report.violations, page.url()).toEqual([]);
}

test('L8 A : accès directs, petits écrans et deux palettes', async ({ page }, testInfo) => {
  test.setTimeout(180000);
  const paths = [
    ['/', 'home', 200],
    ['/connexion', 'login', 200],
    ['/inscription', 'register', 200],
    ['/mot-de-passe/oublie', 'reset-request', 200],
    ['/inscription/renvoyer', 'verification-request', 200],
    ['/inscription/confirmer/invalid', 'verification', 200],
    ['/inscription/confirmer/invalid', 'verification-invalid', 400, true],
    ['/mot-de-passe/reinitialiser/invalid', 'reset-invalid', 400],
  ];
  for (const [path, name, status, submit = false] of paths) {
    for (const theme of ['dark', 'light']) {
      const response = await page.goto(path);
      if (submit) {
        // La confirmation n’est vérifiée qu’après l’action explicite, jamais à l’ouverture du lien.
        expect(response.status()).toBe(200);
        const confirmation = page.waitForResponse(result => result.request().method() === 'POST' && result.url().endsWith(path));
        await page.getByRole('button', { name: 'Confirmer mon adresse', exact: true }).click();
        expect((await confirmation).status()).toBe(status);
        await expect(page.getByRole('heading', { name: 'Lien invalide ou expiré', exact: true })).toBeVisible();
        await page.waitForLoadState('load');
      } else {
        expect(response.status()).toBe(status);
      }
      if (theme === 'light') {
        // Le visiteur n’a pas de préférence enregistrée : on contrôle ici la palette existante.
        await page.addStyleTag({ url: '/styles/wireframe-theme.css' });
        await page.locator('body').evaluate(element => { element.dataset.theme = 'light'; });
      }
      await expect(page.locator('main h1')).toHaveCount(1);
      for (const width of (name === 'login' ? [1920, 1440, 768, 390, 320] : [1440, 768, 390, 320])) {
        await page.setViewportSize({ width, height: width === 1920 ? 1080 : 900 });
        await checkBounds(page, width);
        if (name === 'home') {
          await expect(page.locator('.entry-home-actions a[href="/inscription"]')).toBeVisible();
          await expect(page.locator('.entry-home-actions a[href="/connexion"]')).toBeVisible();
          await expect(page.locator('.public-previews a[href="/connexion"]')).toHaveCount(0);
          await expect(page.locator('.public-previews img[alt]')).toHaveCount(1);
        } else if (name === 'register') {
          await expect(page.locator('.entry-public-navigation a[href="/inscription"]')).not.toHaveClass(/\bprimary\b/);
          await expect(page.locator('.entry-public-navigation a[href="/connexion"]')).toBeVisible();
          await expect(page.locator('main h1').locator('..').locator('.primary')).toHaveCount(1);
        } else if (name !== 'login') {
          const panel = page.locator('.core-entry-access--compact .entry-access-panel');
          await expect(page.locator('.entry-access-context')).toHaveCount(0);
          const back = panel.getByRole('link', { name: 'Retour à la connexion' });
          await expect(back).toHaveCount(1);
          await expect(back).toHaveAttribute('href', '/connexion');
          const link = await back.boundingBox();
          expect(link.height).toBeGreaterThanOrEqual(44);
          const centre = await panel.evaluate(node => node.getBoundingClientRect().x + node.getBoundingClientRect().width / 2);
          expect(Math.abs(centre - width / 2)).toBeLessThanOrEqual(2);
        }
        if (name === 'login') {
          const scene = page.locator('.entry-access-context');
          await expect(page.locator('.auth-form').getByRole('link', { name: 'Mot de passe oublié ?' })).toBeVisible();
          if (width === 1920) {
            const [form, illustration] = await Promise.all([page.locator('.entry-access-panel').boundingBox(), scene.boundingBox()]);
            expect(form.x + form.width).toBeLessThan(illustration.x);
            const titles = await page.locator('#login-title,.entry-context-title').evaluateAll(nodes => nodes.map(node => parseFloat(getComputedStyle(node).fontSize)));
            expect(titles[0]).toBeGreaterThan(titles[1]);
          }
        }
        if ([1440, 390].includes(width)) {
          await checkAccessibility(page);
          await page.screenshot({ path: testInfo.outputPath(`${name}-${theme}-${width}.png`), fullPage: true, animations: 'disabled' });
        }
      }
    }
  }
  await page.goto('/inscription/renvoyer');
  await page.locator('form').evaluate(form => { form.noValidate = true; });
  await page.getByLabel('Adresse email').fill('adresse-invalide');
  const rejected = page.waitForResponse(response => response.request().method() === 'POST' && response.url().endsWith('/inscription/renvoyer'));
  await page.getByRole('button', { name: 'Envoyer le lien de confirmation' }).click();
  expect((await rejected).status()).toBe(422);
  const field = page.getByLabel('Adresse email');
  await expect(field).toHaveAttribute('aria-invalid', 'true');
  for (const id of (await field.getAttribute('aria-describedby')).split(/\s+/)) {
    await expect(page.locator(`[id="${id}"]`)).toBeVisible();
  }
  await expect(page.getByRole('form', { name: 'Formulaire à corriger' })).toBeFocused();
  await checkAccessibility(page);
});

test('L8 A : règles complètes, calendriers et reprise d’empire', async ({ page }, testInfo) => {
  test.setTimeout(150000);
  const suffix = crypto.randomUUID().slice(0, 8);
  const email = `entry-${suffix}@example.test`;
  const project = process.env.BROWSER_ROUTER;
  await page.goto('/inscription');
  await page.getByLabel('Adresse email').fill(email);
  await page.getByLabel('Nom de commandant').fill(`Entry-${suffix}`);
  await page.getByLabel('Mot de passe (12 caractères minimum)', { exact: true }).fill('Browser-Entry-2026!');
  await page.getByLabel('Confirmer le mot de passe').fill('Browser-Entry-2026!');
  await page.getByRole('button', { name: 'Créer mon compte' }).click();
  await page.getByLabel('Adresse email').fill(email);
  await page.getByLabel('Mot de passe', { exact: true }).fill('Browser-Entry-2026!');
  await page.getByRole('button', { name: /Connexion/ }).click();
  await expect(page).toHaveURL(/univers$/);
  await expect(page.getByRole('heading', { name: 'Choisissez votre univers' })).toBeVisible();
  await expect(page.locator('.resource-event-stack')).toHaveCount(0);
  await page.setViewportSize({ width: 1920, height: 1080 });
  const dimensions = await page.evaluate(() => [innerWidth, innerHeight, devicePixelRatio]);
  expect(dimensions).toEqual([1920, 1080, 1]);
  await page.screenshot({ path: testInfo.outputPath('08-univers-premier-acces-1920.png'), scale: 'css', animations: 'disabled' });

  const omegaId = queryIsolatedSql(project, "SELECT id FROM universe WHERE slug='omega-1'");
  const turboId = queryIsolatedSql(project, "SELECT id FROM universe WHERE slug='turbo-1'");
  expect(omegaId).toMatch(/^\d+$/);
  expect(turboId).toMatch(/^\d+$/);
  const omega = page.locator('.universe-card').filter({ has: page.locator(`#universe-${omegaId}-title`) });
  const turbo = page.locator('.universe-card').filter({ has: page.locator(`#universe-${turboId}-title`) });
  await expect(omega.locator('.universe-rules dt')).toHaveCount(9);
  await expect(turbo.locator('.universe-rules dt')).toHaveCount(9);
  await expect(omega.locator('.universe-rules dd')).toHaveText(['× 1', '× 1', '× 1', '× 1', '× 1', '31', '1 000 000', 'Activées', 'Autorisée']);
  await expect(turbo.locator('.universe-rules dd')).toHaveText(['× 100', '× 100', '× 100', '× 1', '× 100', '31', '1 000 000', 'Activées', 'Autorisée']);

  runIsolatedSql(project, `UPDATE universe SET name='Omega — Exploration et développement des colonies périphériques' WHERE id=${omegaId}`);
  for (const theme of ['dark', 'light']) {
    runIsolatedSql(project, `UPDATE player SET preferences=JSON_OBJECT('theme','${theme}') WHERE email='${email}'`);
    await page.goto('/univers');
    for (const width of [1440, 768, 390, 320]) {
      await page.setViewportSize({ width, height: 900 });
      await checkBounds(page, width);
      if ([1440, 390].includes(width)) {
        await checkAccessibility(page);
        await page.screenshot({ path: testInfo.outputPath(`universes-${theme}-${width}.png`), fullPage: true, animations: 'disabled' });
      }
    }
  }

  runIsolatedSql(project, `UPDATE universe SET starts_at=DATE_ADD(UTC_TIMESTAMP(), INTERVAL 1 DAY) WHERE id=${omegaId}`);
  runIsolatedSql(project, `UPDATE universe SET status='closed' WHERE id=${turboId}`);
  await page.goto('/univers');
  await expect(omega).toContainText('OUVERTURE PROGRAMMÉE');
  await expect(omega.getByRole('timer')).toBeVisible();
  await expect(omega).toContainText('UTC');
  await expect(turbo).toContainText('INACCESSIBLE');
  await expect(page.locator('.universe-card button')).toHaveCount(0);
  await checkBounds(page, 320);
  await checkAccessibility(page);
  await page.screenshot({ path: testInfo.outputPath('universes-scheduled-320.png'), fullPage: true, animations: 'disabled' });

  runIsolatedSql(project, `UPDATE universe SET starts_at=NULL WHERE id=${omegaId}`);
  runIsolatedSql(project, `UPDATE universe SET status='active' WHERE id=${turboId}`);
  await page.goto('/univers');
  await turbo.getByRole('button').click();
  await expect(page).toHaveURL(/planetes\/\d+$/);
  const colonyUrl = page.url();
  await page.goto('/univers');
  await expect(page.getByRole('heading', { name: 'Choisissez votre univers' })).toHaveCount(0);
  await expect(turbo).toContainText('Univers actuel');
  await turbo.getByRole('button', { name: /Reprendre mon empire/ }).click();
  await expect(page).toHaveURL(colonyUrl);
});
