/**
 * Adapter PrestaShop (§21).
 *
 * El lab existe y las comprobaciones estáticas del plan sí se ejecutan. La
 * auditoría dinámica devuelve SKIP mientras no haya un artefacto PrestaShop
 * real, porque el prompt prohíbe declarar PASS sin evidencia (§25, §55).
 */
import path from 'node:path';
import { AUDITOR_ROOT } from '../core/plans.mjs';
import { COMPATIBILITY, STATUS, SUPPORT_STATUS } from '../core/result.mjs';
import { findEntry } from '../core/zip.mjs';

const COMPOSE_FILE = path.join(AUDITOR_ROOT, 'labs', 'prestashop', 'docker-compose.yml');

export const meta = {
  platform: 'prestashop',
  displayName: 'PrestaShop',
  composeFile: COMPOSE_FILE,
  defaults: { psImage: 'prestashop/prestashop:8.1-apache', port: 8182 },
};

export function supportStatus(inspection) {
  const constraint = inspection.requirements.prestashop;
  if (!constraint) {
    return { status: SUPPORT_STATUS.UNKNOWN, detail: 'El artefacto no declara version de PrestaShop.' };
  }
  return { status: SUPPORT_STATUS.UNKNOWN, detail: `Restriccion declarada: ${constraint}.` };
}

/** Comprobaciones estáticas del plan que no requieren levantar el lab. */
export function staticChecks(zip, inspection) {
  const tests = [];
  const add = (name, ok, detail) => tests.push({ name, status: ok ? 'PASS' : 'FAIL', detail });

  add('Existe config.xml del modulo', Boolean(findEntry(zip, (n) => n.endsWith('config.xml'))), 'config.xml');
  add(
    'Clase principal extiende Module o CarrierModule',
    inspection.symbols.classes.length > 0,
    inspection.symbols.classes.slice(0, 5).join(', ') || 'sin clases detectadas',
  );
  add(
    'Declara hooks',
    inspection.integration.hooks.length > 0,
    inspection.integration.hooks.slice(0, 8).join(', ') || 'sin hooks',
  );
  add(
    'Tiene controladores',
    Boolean(findEntry(zip, (n) => /\/controllers\//.test(n))),
    'controllers/',
  );
  return tests;
}

export async function audit(context) {
  const { zip, inspection, options } = context;
  const tests = staticChecks(zip, inspection);
  const support = supportStatus(inspection);

  return {
    status: STATUS.SKIP,
    reason:
      'Auditoria dinamica de PrestaShop no ejecutada: falta un artefacto PrestaShop verificado y la version objetivo del Plan PrestaShop. Las comprobaciones estaticas si se ejecutaron.',
    compatibility: COMPATIBILITY.NOT_EVALUATED,
    supportStatus: support.status,
    supportDetail: support.detail,
    platformVersion: options.platformVersion || 'NO_EJECUTADO',
    pluginVersion: context.pluginVersion,
    fatalDetected: false,
    tests,
    findings: [
      `Lab disponible en ${COMPOSE_FILE}.`,
      'Para habilitar la auditoria completa: definir version objetivo en el Plan PrestaShop y aportar el ZIP oficial del modulo.',
    ],
    recommendations: ['Ejecutar `auditor lab up prestashop` para validar el entorno antes de la primera auditoria real.'],
    regressions: [],
    metadata: { DOCKER_IMAGE: meta.defaults.psImage, RUNTIME: 'docker-compose (no levantado)' },
    checklist: {},
    logs: {},
  };
}
