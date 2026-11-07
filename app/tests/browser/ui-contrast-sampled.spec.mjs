import { test, expect } from './support/isolated-test.mjs';
import { execFileSync } from 'node:child_process';

test('Contraste des textes sur fonds unis échantillonnés', async ({ page }) => {
  test.setTimeout(120000);
  const id = crypto.randomUUID().slice(0, 8);
  const email = 'contrast-' + id + '@example.test';
  const password = 'Browser-test-2026!';
  await page.goto('/inscription');
  await page.getByLabel('Adresse email').fill(email);
  await page.getByLabel('Nom de commandant').fill('Contrast-' + id);
  await page.getByLabel('Mot de passe (12 caractères minimum)', { exact: true }).fill(password);
  await page.getByLabel('Confirmer le mot de passe').fill(password);
  await page.getByRole('button', { name: 'Créer mon compte' }).click();
  await page.getByLabel('Adresse email').fill(email);
  await page.getByLabel('Mot de passe', { exact: true }).fill(password);
  await page.getByRole('button', { name: /Connexion/ }).click();
  await page.locator('article.universe-card').filter({ has: page.getByRole('heading', { name: /Turbo/ }) }).getByRole('button').click();
  const project = process.env.BROWSER_ROUTER;
  execFileSync('docker', ['compose', '--project-name', project, '--env-file', '.env.local', 'exec', '-T', 'app', 'php', 'bin/console', 'app:admin:grant', email], { encoding: 'utf8', stdio: ['ignore', 'pipe', 'pipe'] });
  await page.context().clearCookies();
  await page.goto('/connexion');
  await page.getByLabel('Adresse email').fill(email);
  await page.getByLabel('Mot de passe', { exact: true }).fill(password);
  await page.getByRole('button', { name: /Connexion/ }).click();

  const routes = await page.locator('.sidebar nav a').evaluateAll(links => [...new Set(links.map(link => new URL(link.href).pathname + new URL(link.href).search))]);
  const violations = [];
  for (const width of [1440, 768, 390]) {
    await page.setViewportSize({ width, height: 900 });
    for (const route of routes) {
    await page.goto(route);
    const routeFindings = await page.evaluate(() => {
      const parse = value => {
        const m = value.match(/rgba?\(([^)]+)\)/);
        if (!m) return null;
        const values = m[1].split(',').map(x => Number.parseFloat(x.trim()));
        return [values[0], values[1], values[2], values[3] ?? 1];
      };
      const blend = (front, back) => {
        const a = front[3] + back[3] * (1 - front[3]);
        if (!a) return [0,0,0,0];
        return [(front[0]*front[3]+back[0]*back[3]*(1-front[3]))/a,(front[1]*front[3]+back[1]*back[3]*(1-front[3]))/a,(front[2]*front[3]+back[2]*back[3]*(1-front[3]))/a,a];
      };
      const luminance = c => {
        const rgb=c.slice(0,3).map(v=>v/255).map(v=>v<=.04045?v/12.92:((v+.055)/1.055)**2.4);
        return .2126*rgb[0]+.7152*rgb[1]+.0722*rgb[2];
      };
      const ratio = (a,b) => { const x=luminance(a), y=luminance(b); return (Math.max(x,y)+.05)/(Math.min(x,y)+.05); };
      const output=[];
      for (const el of document.querySelectorAll('body *')) {
        const direct=[...el.childNodes].filter(n=>n.nodeType===Node.TEXT_NODE).map(n=>n.textContent.trim()).filter(Boolean).join(' ').replace(/\s+/g,' ');
        const rect=el.getBoundingClientRect(), style=getComputedStyle(el);
        if (!direct || el.closest('[aria-hidden=true]') || rect.width===0 || rect.height===0 || style.visibility==='hidden' || style.display==='none' || Number(style.opacity)<.95) continue;
        const fg=parse(style.color); if (!fg) continue;
        const layers=[];
        for(let node=el;node;node=node.parentElement){const bg=parse(getComputedStyle(node).backgroundColor);if(bg&&bg[3]>0)layers.push(bg);if(node===document.body)break;}
        const base=getComputedStyle(document.documentElement).getPropertyValue('--bg').trim();
      const hex=base.match(/^#([0-9a-f]{6})$/i);
      let bg=hex?[parseInt(hex[1].slice(0,2),16),parseInt(hex[1].slice(2,4),16),parseInt(hex[1].slice(4,6),16),1]:[9,15,27,1];
        for(const layer of layers.reverse()) bg=blend(layer,bg);
        const size=Number.parseFloat(style.fontSize), large=size>=24 || (size>=18.66 && Number(style.fontWeight)>=700);
        const value=ratio(fg,bg);
        if(value<(large?3:4.5)) output.push({text:direct.slice(0,70),fg:style.color,bg:bg.slice(0,3).map(Math.round).join(','),ratio:Number(value.toFixed(2)),large});
      }
      return output;
    });
    violations.push(...routeFindings.map(item=>({route,width,...item})));
    }
  }
  expect(violations, JSON.stringify(violations, null, 2)).toEqual([]);
});
