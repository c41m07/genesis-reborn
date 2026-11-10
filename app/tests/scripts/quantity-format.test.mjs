import { readFile } from 'node:fs/promises';
import assert from 'node:assert/strict';
import { test } from 'node:test';

const source = await readFile(new URL('../../assets/quantity.js', import.meta.url), 'utf8');
const { compactNumber, displayQuantity } = await import('data:text/javascript;base64,' + Buffer.from(source).toString('base64'));
const cases = JSON.parse(await readFile(new URL('../Fixtures/quantity-format.json', import.meta.url), 'utf8'));

test('Seuils et suffixes identiques au rendu Twig', () => {
    for (const [value, expected] of cases) assert.equal(compactNumber(value), expected);
});
test('La valeur exacte suit les actualisations de quantité', () => {
    const target = { setAttribute(name, value) { this[name] = value; } };
    displayQuantity(target, 123456);
    assert.equal(target.textContent, '123,46 k');
    assert.equal(target.title, '123 456');
    assert.equal(target['aria-label'], '123 456');
    displayQuantity(target, 9999);
    assert.equal(target.textContent, '9 999');
    assert.equal(target.title, '9 999');
});
