/**
 * Matriz de compatibilidad (§16). Cada combinación es independiente y produce
 * su propio AUDIT_ID; una fila no contamina a otra.
 */

const DEFAULT_MATRIX = {
  woocommerce: ['9.8.5', '10.6.2', '11.0.1'],
  prestashop: ['8.1.7'],
  magento: ['2.4.7'],
  shopify: ['2025-01'],
};

export function defaultVersionsFor(platform) {
  return DEFAULT_MATRIX[platform] ? [...DEFAULT_MATRIX[platform]] : [];
}

export function parseVersions(input, platform) {
  if (!input) return defaultVersionsFor(platform);
  return String(input)
    .split(',')
    .map((value) => value.trim())
    .filter(Boolean);
}

export function summarize(rows) {
  const counts = rows.reduce((acc, row) => {
    acc[row.STATUS] = (acc[row.STATUS] || 0) + 1;
    return acc;
  }, {});
  const worst = rows.some((row) => row.STATUS === 'FAIL')
    ? 'FAIL'
    : rows.some((row) => row.STATUS === 'ERROR')
      ? 'ERROR'
      : rows.every((row) => row.STATUS === 'SKIP')
        ? 'SKIP'
        : rows.some((row) => row.STATUS === 'UNKNOWN')
          ? 'UNKNOWN'
          : 'PASS';
  return { counts, worst, total: rows.length };
}

export function renderTable(rows) {
  const header = ['PLATFORM', 'PLATFORM_VERSION', 'PLUGIN_VERSION', 'SUPPORT_STATUS', 'STATUS', 'EXIT'];
  const body = rows.map((row) => [
    row.PLATFORM,
    row.PLATFORM_VERSION,
    row.PLUGIN_VERSION,
    row.SUPPORT_STATUS,
    row.STATUS,
    String(row.EXIT_CODE),
  ]);
  const widths = header.map((label, index) =>
    Math.max(label.length, ...body.map((cells) => String(cells[index]).length)),
  );
  const line = (cells) => cells.map((cell, index) => String(cell).padEnd(widths[index])).join('  ');
  return [line(header), line(widths.map((width) => '-'.repeat(width))), ...body.map(line)].join('\n');
}
