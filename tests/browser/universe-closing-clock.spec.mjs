import { test, expect } from '@playwright/test';
import { readFileSync } from 'node:fs';
const source = readFileSync('assets/controllers/universe_closing_controller.js', 'utf8')
  .replace("import { Controller } from '@hotwired/stimulus';", 'class Controller {}');
const { default: ClosingController } = await import('data:text/javascript;base64,' + Buffer.from(source).toString('base64'));

test('Fermeture : seuil exact, horloge du poste ignorée, échéance et absence de date', () => {
  const controller = new ClosingController();
  Object.assign(controller, { element: {}, labelTarget: {}, timeTarget: {}, dateTarget: {} });
  const serverNow = '2030-01-01T00:00:00Z';
  const previousNow = Date.now;
  Date.now = () => 0;
  try {
    controller.apply({ serverNow, endsAt: '2030-01-02T01:00:01+01:00', status: 'active' });
    expect(controller.element.hidden).toBe(true);
    controller.observedAt -= 1001;
    controller.tick();
    expect(controller.element.hidden).toBe(false);
    controller.apply({ serverNow, endsAt: '2030-01-02T01:00:00+01:00', status: 'active' });
    expect(controller.element.hidden).toBe(false);
    expect(controller.timeTarget.textContent).toBe('24 h 0 min 0 s');
    controller.observedAt -= 86401000;
    controller.tick();
    expect(controller.labelTarget.textContent).toBe('Échéance de fermeture atteinte');
    expect(controller.timeTarget.textContent).toBe('');
    controller.apply({ serverNow, endsAt: null, status: 'active' });
    expect(controller.element.hidden).toBe(true);
    controller.apply({ serverNow, endsAt: '2029-01-01T00:00:00Z', status: 'closed' });
    expect(controller.element.hidden).toBe(true);
  } finally { Date.now = previousNow; }
});
