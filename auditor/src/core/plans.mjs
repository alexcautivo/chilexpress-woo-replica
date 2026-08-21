/**
 * Lectura de los planes de plataforma (§2). Los planes son la especificación:
 * se leen, no se reescriben.
 */
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const here = path.dirname(fileURLToPath(import.meta.url));
export const REPO_ROOT = path.resolve(here, '..', '..', '..');
export const AUDITOR_ROOT = path.resolve(here, '..', '..');

const PLAN_SOURCES = {
  woocommerce: [
    'incidents/planes/laboratorio-multicliente/PLAN-importar-incidencias.md',
    'incidents/planes/SR-108688/README.md',
    'incidents/planes/SR-108688/01-por-que-fallo.md',
    'incidents/planes/SR-108688/02-plan-accion.md',
    'incidents/planes/SR-108688/03-mejoras-plugin-chilexpress.md',
  ],
  prestashop: ['incidents/planes/laboratorio-multiplataforma/PLAN-prestashop.md'],
  magento: ['incidents/planes/laboratorio-multiplataforma/PLAN-magento.md'],
  shopify: ['incidents/planes/laboratorio-multiplataforma/PLAN-shopify.md'],
};

const COMMON_PLAN = 'incidents/planes/laboratorio-multiplataforma/PLAN-laboratorio-multiplataforma.md';

export function loadPlan(platform) {
  const files = PLAN_SOURCES[platform] || [];
  const documents = [];
  for (const relative of [...files, COMMON_PLAN]) {
    const absolute = path.join(REPO_ROOT, relative);
    if (!fs.existsSync(absolute)) continue;
    documents.push({ file: relative, content: fs.readFileSync(absolute, 'utf8') });
  }
  return {
    platform,
    found: documents.length > 0,
    documents: documents.map((doc) => ({ file: doc.file, bytes: doc.content.length })),
    requirements: extractPlanRequirements(documents),
    missing: files.filter((relative) => !fs.existsSync(path.join(REPO_ROOT, relative))),
  };
}

function extractPlanRequirements(documents) {
  const lines = [];
  for (const doc of documents) {
    for (const line of doc.content.split(/\r?\n/)) {
      if (/\b(PHP|WordPress|WooCommerce|PrestaShop|Magento|Shopify|Chilexpress)\b.*\d+\.\d+/.test(line)) {
        const clean = line.replace(/^[\s|*#-]+/, '').trim();
        if (clean && clean.length < 240) lines.push(`${doc.file}: ${clean}`);
      }
    }
  }
  return [...new Set(lines)].slice(0, 80);
}

export function planExists(platform) {
  return (PLAN_SOURCES[platform] || []).some((relative) => fs.existsSync(path.join(REPO_ROOT, relative)));
}
