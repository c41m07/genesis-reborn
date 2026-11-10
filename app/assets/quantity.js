const units = [[1e12, 'Bn'], [1e9, 'Md'], [1e6, 'M'], [1e3, 'k']];

export function exactNumber(value, decimals = 0) {
    return Number(value).toLocaleString('fr-FR', {
        minimumFractionDigits: decimals, maximumFractionDigits: decimals,
    }).replaceAll('\u202f', ' ').replaceAll('\u00a0', ' ');
}

export function compactNumber(value, decimals = 0) {
    if (Math.abs(value) < 10000) return exactNumber(value, decimals);
    const [divisor, suffix] = units.find(([divisor]) => Math.abs(value) >= divisor || Math.round(Math.abs(value) / (divisor / 1000) * 100) >= 100000);
    return exactNumber(value / divisor, 2) + ' ' + suffix;
}

export function displayQuantity(element, value, decimals = 0) {
    element.textContent = compactNumber(value, decimals);
    element.title = exactNumber(value, decimals);
    element.setAttribute('aria-label', element.title);
}
