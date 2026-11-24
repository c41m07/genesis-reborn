import AxeBuilder from '@axe-core/playwright';
import { expect } from './isolated-test.mjs';
import { runIsolatedSql } from './game-fixtures.mjs';

export async function captureDecision(page, data, info, kind, url) {
  const cards = page.locator('.fleet-decision-layout');
  for (const theme of ['dark', 'light']) {
    runIsolatedSql(data.project, `UPDATE player SET preferences=JSON_SET(CASE WHEN JSON_TYPE(preferences)='OBJECT' THEN preferences ELSE JSON_OBJECT() END,'$.theme','${theme}') WHERE email='${data.email}'`);
    for (const [width, height] of [[1920, 1080], [390, 844], [320, 800]]) {
      await page.setViewportSize({ width, height });
      expect((await page.goto(url)).status()).toBe(200);
      await expect(page.locator('body')).toHaveAttribute('data-theme', theme);
      await expect(cards.locator('form[method="get"] select')).toBeEnabled();
      const compute = cards.locator('form[method="get"] button');
      const confirm = cards.locator('form[method="post"] button');
      await expect(compute).toBeEnabled();
      await expect(confirm).toBeEnabled();
      if (kind === 'rappel') {
        for (const warning of [confirm, cards.locator('#fleet-recall-consequence')]) {
          const redBorder = await warning.evaluate(element => {
            const border = getComputedStyle(element).borderTopColor;
            const ctx = document.createElement('canvas').getContext('2d');
            ctx.fillStyle = border;
            ctx.fillRect(0, 0, 1, 1);
            const [red, green, blue] = ctx.getImageData(0, 0, 1, 1).data;
            return red > green + 10 && red > blue + 10;
          });
          expect(redBorder).toBe(true);
        }
      }
      const help = page.locator('.first-steps-popup-enhanced');
      await expect(help).toHaveAttribute('open', '');
      const bounds = await help.boundingBox();
      for (const action of [compute, confirm]) {
        const button = await action.boundingBox();
        expect(bounds.y).toBeGreaterThanOrEqual(button.y + button.height);
      }
      const selection = await cards.locator('.fleet-decision-selection').boundingBox();
      const quote = await cards.locator('.fleet-decision-quote').boundingBox();
      if (width >= 1101) {
        expect(Math.abs(quote.y - selection.y)).toBeLessThanOrEqual(1);
        expect(quote.x).toBeGreaterThanOrEqual(selection.x + selection.width);
        expect(selection.width).toBeGreaterThan(quote.width);
      } else {
        expect(quote.y).toBeGreaterThanOrEqual(selection.y + selection.height);
      }
      expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(width);
      if (width !== 320) {
        expect((await new AxeBuilder({ page }).include('.fleet-decision-layout').analyze()).violations).toEqual([]);
        await page.screenshot({ path: info.outputPath(`${kind}-${theme}-${width}.png`), fullPage: width < 1101, animations: 'disabled' });
        await compute.click({ trial: true });
        await confirm.click({ trial: true });
      }
    }
  }
}
