import { test, expect } from './support/isolated-test.mjs';
import AxeBuilder from '@axe-core/playwright';
import { execFileSync } from 'node:child_process';
import { readFile } from 'node:fs/promises';
import { fileURLToPath } from 'node:url';
import { queryIsolatedSql, runIsolatedSql } from './support/game-fixtures.mjs';

const capturePath = fileURLToPath(new URL('../../public/art/ui-preview-colony.png', import.meta.url));
const quote = value => "'" + String(value).replaceAll("'", "''") + "'";

async function account(page, { join = false, admin = false } = {}) {
  const suffix = crypto.randomUUID().slice(0, 8);
  const email = `information-${suffix}@example.test`;
  await page.goto('/inscription');
  await page.getByLabel('Adresse email').fill(email);
  await page.getByLabel('Nom de commandant').fill(`Information-${suffix}`);
  await page.getByLabel('Mot de passe (12 caractères minimum)', { exact: true }).fill('Browser-Information-2026!');
  await page.getByLabel('Confirmer le mot de passe').fill('Browser-Information-2026!');
  await page.getByRole('button', { name: 'Créer mon compte' }).click();
  if (admin) execFileSync('docker', ['compose', '-p', process.env.BROWSER_ROUTER, '--env-file', '.env.local', 'exec', '-T', 'app', 'php', 'bin/console', 'app:admin:grant', email], { stdio: ['ignore', 'pipe', 'pipe'] });
  await page.getByLabel('Adresse email').fill(email);
  await page.getByLabel('Mot de passe', { exact: true }).fill('Browser-Information-2026!');
  await page.getByRole('button', { name: /Connexion/ }).click();
  await expect(page).toHaveURL(/univers$/);
  if (join) {
    await page.locator('.universe-card').first().getByRole('button').click();
    await expect(page).toHaveURL(/planetes\/\d+$/);
  }
  return email;
}

async function layout(page, testInfo, name, scope = '.information-workspace') {
  for (const width of [1440, 768, 390, 320]) {
    await page.setViewportSize({ width, height: 900 });
    await expect.poll(() => page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(width);
    if (width===1440 && await page.locator('.feedback-layout').count()) {
      const container = await page.locator('.feedback-workspace').boundingBox();
      const form = await page.locator('.feedback-layout').boundingBox();
      expect(Math.abs(form.x-container.x)).toBeLessThanOrEqual(1);
      expect(Math.abs(form.width-container.width)).toBeLessThanOrEqual(1);
    }
    if ([1440, 390].includes(width)) {
      const audit = await new AxeBuilder({ page }).include(scope).withTags(['wcag2a', 'wcag2aa', 'wcag21aa', 'wcag22aa']).analyze();
      expect.soft(audit.violations, name+' '+width).toEqual([]);
      await page.screenshot({ path: testInfo.outputPath(`${name}-${width}.png`), fullPage: true, animations: 'disabled' });
    }
  }
}

async function fillFeedback(page) {
  await page.getByLabel('Type', { exact: true }).selectOption('bug');
  await page.getByLabel('Priorité ressentie').selectOption('high');
  await page.getByLabel('Titre', { exact: true }).fill('Une difficulté sur la page de ma colonie');
  await page.getByLabel('Description détaillée').fill('Je consulte la page puis je recharge. Voici les étapes et le résultat attendu.');
}

test('L9 A : aperçu local, agrandissement, retrait et reconnexion', async ({ page }, testInfo) => {
  test.setTimeout(120000);
  const email = await account(page);
  await page.addInitScript(() => {
    window.captureUrls = { created: [], revoked: [] };
    const create = URL.createObjectURL.bind(URL);
    const revoke = URL.revokeObjectURL.bind(URL);
    URL.createObjectURL = file => { const url = create(file); window.captureUrls.created.push(url); return url; };
    URL.revokeObjectURL = url => { window.captureUrls.revoked.push(url); revoke(url); };
  });
  for (const theme of ['dark', 'light']) {
    runIsolatedSql(process.env.BROWSER_ROUTER, `UPDATE player SET preferences=JSON_OBJECT('theme','${theme}') WHERE email=${quote(email)}`);
    await page.goto('/feedback?context_url=https%3A%2F%2Fgenesis-reborn.fr%2Fplanetes%2F42');
    await expect(page.locator('.resource-event-stack')).toHaveCount(0);
    await fillFeedback(page);
    await page.getByLabel('Joindre une capture').setInputFiles(capturePath);
    await expect(page.getByRole('button', { name: 'Agrandir la capture' })).toBeVisible();
    await layout(page, testInfo, `feedback-${theme}`);
  }
  const firstUrl = await page.locator('.feedback-preview img').getAttribute('src');
  await page.getByRole('button', { name: 'Agrandir la capture' }).click();
  await expect(page.getByRole('dialog', { name: 'Capture choisie' })).toBeVisible();
  await page.keyboard.press('Escape');
  await expect(page.getByRole('button', { name: 'Agrandir la capture' })).toBeFocused();
  const payload = { name: 'Capture-'+'Colonie'.repeat(20)+'.png', mimeType: 'image/png', buffer: await readFile(capturePath) };
  await page.getByLabel('Joindre une capture').setInputFiles(payload);
  await expect(page.locator('.feedback-preview figcaption')).toHaveText(payload.name);
  expect(await page.evaluate(url => window.captureUrls.revoked.includes(url), firstUrl)).toBe(true);
  await page.getByRole('button', { name: 'Retirer la capture' }).click();
  await expect(page.getByLabel('Joindre une capture')).toBeFocused();
  await expect(page.locator('.feedback-preview')).toBeHidden();
  await page.getByLabel('Joindre une capture').setInputFiles(capturePath);
  await expect(page.locator('.feedback-preview')).toBeVisible();
  const lastUrl = await page.locator('.feedback-preview img').getAttribute('src');
  await page.locator('[data-controller="feedback-attachment"]').evaluate(element => { window.captureParent = element.parentElement; window.removedCapture = element; element.remove(); });
  await expect.poll(() => page.evaluate(url => window.captureUrls.revoked.includes(url), lastUrl)).toBe(true);
  await page.evaluate(() => window.captureParent.append(window.removedCapture));
  await expect(page.locator('.feedback-preview')).toBeVisible();
  await page.getByLabel('Joindre une capture').setInputFiles({ name: 'invalide.png', mimeType: 'image/png', buffer: Buffer.from('Ce fichier n’est pas une image.') });
  await expect(page.locator('[data-feedback-attachment-target=status]')).toContainText('Impossible d’afficher cet aperçu');
  await expect(page.locator('.feedback-preview')).toBeHidden();
  await page.goto('/feedback');
  await page.locator('[data-feedback-attachment-target=image]').evaluate(image => {
    const decode = image.decode.bind(image);
    let first = true;
    image.decode = () => {
      if (!first) return decode();
      first = false;
      return new Promise(resolve => { window.releasePreviousDecode = resolve; });
    };
  });
  await page.getByLabel('Joindre une capture').setInputFiles(capturePath);
  const pendingUrl = await page.locator('[data-feedback-attachment-target=image]').getAttribute('src');
  await page.locator('[data-controller="feedback-attachment"]').evaluate(element => { window.captureParent = element.parentElement; window.removedCapture = element; element.remove(); });
  await expect.poll(() => page.evaluate(url => window.captureUrls.revoked.includes(url), pendingUrl)).toBe(true);
  await page.evaluate(() => window.captureParent.append(window.removedCapture));
  await expect(page.getByRole('button', { name: 'Retirer la capture' })).toBeVisible();
  await page.getByLabel('Joindre une capture').setInputFiles({ ...payload, name: 'capture-recente.png' });
  await expect(page.locator('.feedback-preview figcaption')).toHaveText('capture-recente.png');
  await page.evaluate(async () => {
    window.releasePreviousDecode();
    await new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve)));
  });
  await expect(page.locator('.feedback-preview figcaption')).toHaveText('capture-recente.png');
});

test('L9 A : feedback sans JS, refus, quota et capture privée', async ({ browser }, testInfo) => {
  test.setTimeout(150000);
  const context = await browser.newContext({ javaScriptEnabled: false, viewport: { width: 390, height: 900 } });
  const page = await context.newPage();
  const email = await account(page);
  await page.goto('/feedback?context_url=https%3A%2F%2Fgenesis-reborn.fr%2Fplanetes%2F42');
  await fillFeedback(page);
  await page.getByLabel('Joindre une capture').setInputFiles({ name: 'texte.jpg', mimeType: 'image/jpeg', buffer: Buffer.from('Contenu non image') });
  await page.getByRole('button', { name: 'Envoyer mon retour' }).click();
  await expect(page.getByRole('alert')).toContainText('Format refusé');
  await expect(page.getByLabel('Titre', { exact: true })).toHaveValue('Une difficulté sur la page de ma colonie');
  await expect(page.getByLabel('Lien de la page concernée (facultatif)')).toHaveValue('https://genesis-reborn.fr/planetes/42');
  await expect(page.getByLabel('Type', { exact: true })).toHaveValue('bug');
  await expect(page.getByLabel('Priorité ressentie')).toHaveValue('high');
  await expect(page.getByRole('button', { name: 'Retirer la capture' })).toBeHidden();
  await page.getByLabel('Joindre une capture').setInputFiles(capturePath);
  await page.getByRole('button', { name: 'Envoyer mon retour' }).click();
  await expect(page.locator('.flash.success')).toContainText('Votre feedback a bien été envoyé');
  const id = queryIsolatedSql(process.env.BROWSER_ROUTER, 'SELECT MAX(id) FROM user_feedback');
  const capture = `/feedback/${id}/capture`;
  const response = await page.goto(capture);
  expect(response.status()).toBe(200);
  expect(response.headers()['content-type']).toContain('image/png');
  expect(response.headers()['x-content-type-options']).toBe('nosniff');
  await page.goto('/feedback');
  for (let i=0; i<3; i++) {
    await fillFeedback(page);
    await page.getByRole('button', { name: 'Envoyer mon retour' }).click();
  }
  await expect(page.getByRole('alert')).toContainText('Réessayez dans une heure');
  expect(queryIsolatedSql(process.env.BROWSER_ROUTER, `SELECT COUNT(*) FROM user_feedback f JOIN player p ON p.id=f.player_id WHERE p.email=${quote(email)}`)).toBe('3');
  await page.screenshot({ path: testInfo.outputPath('feedback-quota-nojs-390.png'), fullPage: true });
  const otherContext = await browser.newContext();
  const other = await otherContext.newPage();
  await account(other);
  expect((await other.goto(capture)).status()).toBe(403);
  await otherContext.close();
  const adminContext = await browser.newContext();
  const adminPage = await adminContext.newPage();
  await account(adminPage, { admin: true });
  expect((await adminPage.goto(capture)).status()).toBe(200);
  await adminContext.close();
  await context.close();
});

test('L9 A : publications, détail au clavier et état de lecture', async ({ page }, testInfo) => {
  test.setTimeout(120000);
  const email = await account(page, { join: true });
  await page.goto('/patchnotes');
  await expect(page.getByRole('heading', { name: 'Aucune patchnote publiée' })).toBeVisible();
  runIsolatedSql(process.env.BROWSER_ROUTER, "INSERT INTO patch_note (title,version,summary,content,status,published_at,created_at,updated_at) VALUES ('Version publiée','Test-1','Résumé lisible','AMÉLIORATIONS\n• Contenu <script>alert(1)</script> conservé en texte.','published',DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 HOUR),UTC_TIMESTAMP(),UTC_TIMESTAMP()),('Publication future','Test-2','Future','Invisible','published',DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 DAY),UTC_TIMESTAMP(),UTC_TIMESTAMP()),('Brouillon privé','Test-3','Brouillon','Invisible','draft',NULL,UTC_TIMESTAMP(),UTC_TIMESTAMP())");
  for (const theme of ['dark', 'light']) {
    runIsolatedSql(process.env.BROWSER_ROUTER, `UPDATE player SET preferences=JSON_OBJECT('theme','${theme}') WHERE email=${quote(email)}`);
    await page.goto('/patchnotes');
    await expect(page.getByRole('heading', { name: 'Version publiée', exact: true })).toBeVisible();
    await expect(page.locator('main')).not.toContainText('Publication future');
    await expect(page.locator('main')).not.toContainText('Brouillon privé');
    const details = page.locator('details.patchnote-details');
    const summary = details.locator('summary');
    await summary.focus();
    await page.keyboard.press('Enter');
    await expect(details).toHaveAttribute('open', '');
    await expect(details.locator('script')).toHaveCount(0);
    await expect(details).toContainText('<script>alert(1)</script>');
    await layout(page, testInfo, `patchnotes-${theme}`);
    await summary.focus();
    await page.keyboard.press('Enter');
    await expect(details).not.toHaveAttribute('open');
    expect(queryIsolatedSql(process.env.BROWSER_ROUTER, `SELECT JSON_EXTRACT(preferences,'$.last_patch_note_read_id') FROM player WHERE email=${quote(email)}`)).toBe(queryIsolatedSql(process.env.BROWSER_ROUTER, "SELECT id FROM patch_note WHERE version='Test-1'"));
  }
});

test('P20 : feedback non envoyé et deux patchnotes publiées', async ({ page, browser, baseURL }, info) => {
  test.setTimeout(120000);
  const email = await account(page, { join: true });
  const project = process.env.BROWSER_ROUTER;
  const count = () => queryIsolatedSql(project, `SELECT COUNT(*) FROM user_feedback f JOIN player p ON p.id=f.player_id WHERE p.email=${quote(email)}`);
  runIsolatedSql(project, "INSERT INTO patch_note (title,version,summary,content,status,published_at,created_at,updated_at) VALUES ('Lisibilité des rapports','0.9.2','Les filtres et le type de rapport sont affichés séparément.','Détail de l’amélioration des rapports.','published',DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 HOUR),UTC_TIMESTAMP(),UTC_TIMESTAMP()),('Classement et navigation','0.9.1','Repères de navigation harmonisés.','Détail du classement.','published',DATE_SUB(UTC_TIMESTAMP(),INTERVAL 2 HOUR),UTC_TIMESTAMP(),UTC_TIMESTAMP())");
  for (const theme of ['dark', 'light']) {
    runIsolatedSql(project, `UPDATE player SET preferences=JSON_SET(CASE WHEN JSON_TYPE(preferences)='OBJECT' THEN preferences ELSE JSON_OBJECT() END,'$.theme','${theme}') WHERE email=${quote(email)}`);
    for (const viewport of [{ width: 1920, height: 1080 }, { width: 390, height: 844 }]) {
      await page.setViewportSize(viewport);
      await page.goto('/feedback');
      await expect(page.locator('body')).toHaveAttribute('data-theme', theme);
      await page.getByLabel('Type', { exact: true }).selectOption('improvement');
      await page.getByLabel('Titre', { exact: true }).fill('Contraste de l’indicateur de file');
      const description = page.getByLabel('Description détaillée');
      const descriptionText = 'Dans la vue condensée, le temps restant se confond avec le fond.';
      await description.fill(descriptionText);
      await expect.poll(() => page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(viewport.width);
      await page.evaluate(() => window.scrollTo({ top: 0, behavior: 'instant' }));
      if (viewport.width === 1920) {
        await expect(page.getByRole('button', { name: 'Envoyer mon retour' })).toBeInViewport({ ratio: 1 });
      }
      await page.screenshot({ path: info.outputPath(`P20-A-feedback-${theme}-${viewport.width}.png`), animations:'disabled', scale:'css' });
      if (viewport.width === 390) {
        await page.getByRole('button', { name: 'Envoyer mon retour' }).scrollIntoViewIfNeeded();
        await page.screenshot({ path: info.outputPath(`P20-A-actions-${theme}-390.png`), animations:'disabled', scale:'css' });
      }
      await page.getByLabel('Titre', { exact: true }).fill('   ');
      await page.getByRole('button', { name: 'Envoyer mon retour' }).press('Enter');
      const errors = page.locator('.feedback-errors');
      await expect(errors).toContainText('Le titre est obligatoire');
      await expect(errors).toBeFocused();
      await expect.poll(() => errors.getByRole('heading').evaluate(element => {
        const box = element.getBoundingClientRect();
        return document.elementFromPoint(box.x + 8, box.y + box.height / 2) === element;
      })).toBe(true);
      await expect(description).toHaveValue(descriptionText);
      await expect(page.getByLabel('Type', { exact: true })).toHaveValue('improvement');
      expect(count()).toBe('0');
      await page.screenshot({ path: info.outputPath(`P20-A-erreur-${theme}-${viewport.width}.png`), animations:'disabled', scale:'css' });

      await page.goto('/patchnotes');
      const journal = page.locator('.player-patchnotes-board');
      await expect(journal.getByRole('heading', { level: 2 })).toHaveText(['Lisibilité des rapports', 'Classement et navigation']);
      await expect(journal.locator('time[datetime]')).toHaveCount(2);
      await expect(journal.locator('.patchnote-version')).toHaveText(['Version 0.9.2','Version 0.9.1']);
      await expect(journal.locator('.patchnote-version--current')).toHaveText('Version 0.9.2');
      expect((await new AxeBuilder({ page }).include('.patchnote-version').analyze()).violations).toEqual([]);
      const details = journal.locator('summary').first();
      await details.press('Enter');
      await expect(journal.locator('details').first()).toHaveAttribute('open','');
      await expect(journal).toContainText('Détail de l’amélioration des rapports.');
      await details.press('Enter');
      await expect(journal.locator('details').first()).not.toHaveAttribute('open');
      await expect.poll(() => page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(viewport.width);
      await page.evaluate(() => window.scrollTo({ top: 0, behavior: 'instant' }));
      await page.screenshot({ path: info.outputPath(`P20-B-patchnotes-${theme}-${viewport.width}.png`), animations:'disabled', scale:'css' });
    }
  }
  const native = await browser.newContext({ baseURL, storageState: await page.context().storageState(), javaScriptEnabled: false });
  try {
    const tab = await native.newPage();
    await tab.goto('/patchnotes');
    const details = tab.locator('.patchnote-details').first();
    await details.locator('summary').press('Enter');
    await expect(details).toHaveAttribute('open','');
  } finally {
    await native.close();
  }
  expect(count()).toBe('0');
});

test('L9 A : documents publics, ancres, métadonnées et contexte connecté', async ({ page }, testInfo) => {
  test.setTimeout(180000);
  for (const theme of ['dark', 'light']) {
    for (const path of ['/mentions-legales', '/cgu', '/confidentialite', '/cookies']) {
      const response = await page.goto(path);
      expect(response.status()).toBe(200);
      expect(await response.text()).toContain('property="og:image"');
      await expect(page.locator('meta[name=robots][content*=noindex]')).toHaveCount(0);
      if (theme==='light') {
        await page.addStyleTag({ url: '/styles/wireframe-theme.css' });
        await page.locator('body').evaluate(element => { element.dataset.theme='light'; });
      }
      await expect(page.locator('main h1')).toHaveCount(1);
      await expect(page.getByRole('heading', { name: 'Information provisoire et incomplète' })).toBeVisible();
      const warning = page.getByRole('complementary', { name: 'État de cette information' });
      await expect(warning.getByRole('link', { name: 'contact@genesis-reborn.fr' })).toBeVisible();
      const pad = await warning.evaluate(node => [
        parseFloat(getComputedStyle(node).paddingTop),
        parseFloat(getComputedStyle(node).paddingBottom),
      ]);
      expect(pad).toEqual([14, 14]);
      const firstTopic = page.locator('.legal-content > h2').first();
      await expect(firstTopic).toBeVisible();
      if (path === '/confidentialite') await expect(firstTopic).toHaveAttribute('id', 'responsable-contact');
      await expect(page.locator('.legal-audit-layout > aside.legal-warning')).toHaveCount(1);
      const warningAndText = await page.locator('.legal-audit-layout').evaluate(node => ({
        before: node.querySelector('.legal-warning').getBoundingClientRect().bottom,
        after: node.querySelector('.legal-content > h2').getBoundingClientRect().top,
      }));
      expect(warningAndText.before).toBeLessThanOrEqual(warningAndText.after);
      expect(await firstTopic.evaluate(node => getComputedStyle(node).marginTop)).toBe('0px');
      await expect(page.locator('main nav[aria-label="Informations juridiques"] a[aria-current=page]')).toHaveCount(1);
      await layout(page, testInfo, `legal-${path.slice(1)}-${theme}`);
      if (path==='/confidentialite') {
        await page.getByRole('link', { name: 'Vos droits', exact: true }).click();
        await expect(page).toHaveURL(/#droits$/);
        await expect(page.locator('#droits')).toBeInViewport();
        await page.getByRole('link', { name: 'Revenir en haut' }).click();
        await expect(page.locator('#legal-title')).toBeInViewport();
      }
    }
  }
  await account(page, { join: true });
  await page.goto('/confidentialite');
  await expect(page.locator('body')).toHaveClass(/authenticated/);
  await expect(page.locator('.resource-event-stack')).toBeVisible();
  await layout(page, testInfo, 'legal-connected');
});
