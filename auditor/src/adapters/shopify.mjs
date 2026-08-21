/**
 * Adapter Shopify (§23).
 *
 * Shopify no se instala en Docker: no hay core autoalojado. Las comprobaciones
 * estáticas se ejecutan siempre; las sondas de API exigen credenciales de una
 * tienda de desarrollo. Sin credenciales: SKIP.
 */
import path from 'node:path';
import { AUDITOR_ROOT } from '../core/plans.mjs';
import { COMPATIBILITY, STATUS, SUPPORT_STATUS } from '../core/result.mjs';
import { findEntries, findEntry, readText } from '../core/zip.mjs';

const COMPOSE_FILE = path.join(AUDITOR_ROOT, 'labs', 'shopify', 'docker-compose.yml');
const REQUIRED_SHIPPING_SCOPES = ['write_shipping', 'read_shipping'];

export const meta = {
  platform: 'shopify',
  displayName: 'Shopify',
  composeFile: COMPOSE_FILE,
  defaults: { apiVersion: '2025-01' },
};

export function supportStatus(inspection) {
  const version = inspection.requirements.shopifyApiVersion;
  if (!version) {
    return { status: SUPPORT_STATUS.UNKNOWN, detail: 'El artefacto no declara api_version de Shopify.' };
  }
  return { status: SUPPORT_STATUS.UNKNOWN, detail: `api_version declarada: ${version}.` };
}

export function staticChecks(zip, inspection) {
  const tests = [];
  const add = (name, ok, detail) => tests.push({ name, status: ok ? 'PASS' : 'FAIL', detail });

  const toml = findEntry(zip, (n) => n.endsWith('shopify.app.toml'));
  add('Existe shopify.app.toml', Boolean(toml), toml?.name || 'ausente');

  let scopes = '';
  if (toml) {
    const text = readText(zip, toml, 20_000);
    const match = text.match(/scopes\s*=\s*"([^"]*)"/);
    scopes = match ? match[1] : '';
    add('Declara api_version', /api_version\s*=/.test(text), inspection.requirements.shopifyApiVersion || 'ausente');
    add('Declara scopes', Boolean(scopes), scopes || 'sin scopes');
    // La documentación trata Shipping como requisito cuando el módulo cotiza envíos.
    add(
      'Scopes incluyen Shipping',
      REQUIRED_SHIPPING_SCOPES.some((scope) => scopes.includes(scope)),
      scopes || 'sin scopes',
    );
  }

  const webhooks = findEntries(zip, (n) => /webhook/i.test(n)).length;
  add('Hay definiciones de webhooks', webhooks > 0, `${webhooks} archivo(s) relacionados`);

  const pkg = inspection.manifests.packageJson;
  add(
    'Usa SDK oficial @shopify',
    Boolean(pkg && JSON.stringify(pkg.dependencies || {}).includes('@shopify/')),
    Object.keys(pkg?.dependencies || {}).slice(0, 8).join(', ') || 'sin dependencias',
  );
  return tests;
}

export async function audit(context) {
  const { zip, inspection, options } = context;
  const tests = staticChecks(zip, inspection);
  const support = supportStatus(inspection);
  const hasCredentials = Boolean(process.env.SHOPIFY_SHOP_DOMAIN && process.env.SHOPIFY_ACCESS_TOKEN);

  return {
    status: STATUS.SKIP,
    reason: hasCredentials
      ? 'Sondas de API Shopify no implementadas todavia para este artefacto; no se emite veredicto.'
      : 'Auditoria dinamica de Shopify no ejecutada: faltan SHOPIFY_SHOP_DOMAIN y SHOPIFY_ACCESS_TOKEN de una tienda de desarrollo.',
    compatibility: COMPATIBILITY.NOT_EVALUATED,
    supportStatus: support.status,
    supportDetail: support.detail,
    platformVersion: options.platformVersion || inspection.requirements.shopifyApiVersion || 'NO_EJECUTADO',
    pluginVersion: context.pluginVersion,
    fatalDetected: false,
    tests,
    findings: [
      `Lab de sondas en ${COMPOSE_FILE} (perfil "shopify").`,
      'Shopify no se audita instalando un core: se valida contra una tienda de desarrollo.',
    ],
    recommendations: [
      'Crear una tienda de desarrollo y exportar SHOPIFY_SHOP_DOMAIN, SHOPIFY_ACCESS_TOKEN y SHOPIFY_API_VERSION.',
    ],
    regressions: [],
    metadata: { RUNTIME: 'sondas de API (no ejecutadas)' },
    checklist: {},
    logs: {},
  };
}
