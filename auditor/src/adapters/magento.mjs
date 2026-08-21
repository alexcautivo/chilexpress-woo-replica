/**
 * Adapter Magento (§22).
 *
 * Magento necesita imagen propia, Composer autenticado y OpenSearch. Sin esos
 * insumos la auditoría dinámica devuelve SKIP con motivo, nunca PASS.
 */
import path from 'node:path';
import { AUDITOR_ROOT } from '../core/plans.mjs';
import { COMPATIBILITY, STATUS, SUPPORT_STATUS } from '../core/result.mjs';
import { findEntry, readText } from '../core/zip.mjs';

const COMPOSE_FILE = path.join(AUDITOR_ROOT, 'labs', 'magento', 'docker-compose.yml');

export const meta = {
  platform: 'magento',
  displayName: 'Magento',
  composeFile: COMPOSE_FILE,
  defaults: { port: 8183 },
};

export function supportStatus(inspection) {
  const constraint = inspection.requirements.magento;
  if (!constraint) {
    return { status: SUPPORT_STATUS.UNKNOWN, detail: 'El artefacto no declara version de Magento.' };
  }
  return { status: SUPPORT_STATUS.UNKNOWN, detail: `Restriccion declarada: ${constraint}.` };
}

export function staticChecks(zip, inspection) {
  const tests = [];
  const add = (name, ok, detail) => tests.push({ name, status: ok ? 'PASS' : 'FAIL', detail });

  const registration = findEntry(zip, (n) => n.endsWith('registration.php'));
  add('Existe registration.php', Boolean(registration), registration?.name || 'ausente');

  const moduleXml = findEntry(zip, (n) => n.endsWith('etc/module.xml'));
  add('Existe etc/module.xml', Boolean(moduleXml), moduleXml?.name || 'ausente');

  if (moduleXml) {
    const text = readText(zip, moduleXml, 20_000);
    const name = text.match(/name\s*=\s*"([^"]+)"/);
    add('module.xml declara nombre de modulo', Boolean(name), name ? name[1] : 'sin atributo name');
  }

  const composer = inspection.manifests.composer;
  add(
    'composer.json tipo magento2-module',
    composer?.type === 'magento2-module',
    composer?.type || 'sin composer.json',
  );
  add('Declara autoload PSR-4', Boolean(composer?.autoload?.['psr-4']), JSON.stringify(composer?.autoload || {}).slice(0, 200));
  return tests;
}

export async function audit(context) {
  const { zip, inspection, options } = context;
  const tests = staticChecks(zip, inspection);
  const support = supportStatus(inspection);

  return {
    status: STATUS.SKIP,
    reason:
      'Auditoria dinamica de Magento no ejecutada: requiere imagen Magento propia y credenciales de Composer (repo.magento.com). Las comprobaciones estaticas si se ejecutaron.',
    compatibility: COMPATIBILITY.NOT_EVALUATED,
    supportStatus: support.status,
    supportDetail: support.detail,
    platformVersion: options.platformVersion || 'NO_EJECUTADO',
    pluginVersion: context.pluginVersion,
    fatalDetected: false,
    tests,
    findings: [
      `Lab disponible en ${COMPOSE_FILE} (perfil "magento").`,
      'Definir AUDITOR_MAGENTO_IMAGE con una imagen construida previamente para habilitar la auditoria dinamica.',
    ],
    recommendations: [
      'Construir una imagen Magento con el modulo instalable por Composer y fijarla en AUDITOR_MAGENTO_IMAGE.',
    ],
    regressions: [],
    metadata: { RUNTIME: 'docker-compose (no levantado)' },
    checklist: {},
    logs: {},
  };
}
