/**
 * Reportes (§46), metadata (§47) y observabilidad (§52).
 * Deja siempre claro qué es determinista y qué viene de IA (§42).
 */
import fs from 'node:fs';
import path from 'node:path';
import { AUDITOR_ROOT } from './plans.mjs';

export const REPORTS_DIR = path.join(AUDITOR_ROOT, 'reports');

export function createReportDir(auditId) {
  const dir = path.join(REPORTS_DIR, auditId);
  fs.mkdirSync(path.join(dir, 'logs'), { recursive: true });
  fs.mkdirSync(path.join(dir, 'artifacts'), { recursive: true });
  return dir;
}

export function writeJson(dir, name, data) {
  const file = path.join(dir, name);
  fs.mkdirSync(path.dirname(file), { recursive: true });
  fs.writeFileSync(file, `${JSON.stringify(data, null, 2)}\n`);
  return file;
}

export function writeLog(dir, name, text) {
  const file = path.join(dir, 'logs', name);
  fs.mkdirSync(path.dirname(file), { recursive: true });
  fs.writeFileSync(file, typeof text === 'string' ? text : JSON.stringify(text, null, 2));
  return file;
}

function line(label, value) {
  return `${label.padEnd(22)}${value === undefined || value === null || value === '' ? '—' : value}`;
}

export function renderReportText({ result, metadata, diagnostics, tests, ai }) {
  const out = [];
  out.push('# AUDITOR CHILEXPRESS');
  out.push('');
  out.push(line('AUDIT_ID:', result.AUDIT_ID));
  out.push(line('MODE:', result.MODE));
  out.push(line('TIMESTAMP:', result.TIMESTAMP));
  out.push(line('ARTIFACT:', result.ARTIFACT));
  out.push(line('ARTIFACT_ORIGIN:', result.ARTIFACT_ORIGIN));
  out.push(line('SHA256:', result.SHA256));
  out.push(line('PLATFORM:', result.PLATFORM));
  out.push(line('PLATFORM_VERSION:', result.PLATFORM_VERSION));
  out.push(line('PLUGIN_VERSION:', result.PLUGIN_VERSION));
  out.push(line('SUPPORT_STATUS:', result.SUPPORT_STATUS));
  out.push('');
  out.push('## Resultado determinista');
  out.push('');
  out.push(line('DETERMINISTIC_RESULT:', result.STATUS));
  out.push(line('COMPATIBILITY:', result.COMPATIBILITY));
  out.push(line('FATAL_DETECTED:', result.FATAL_DETECTED ? 'YES' : 'NO'));
  out.push(line('ERROR_CLASS:', result.ERROR_CLASS));
  out.push(line('ERROR_FILE:', result.ERROR_FILE));
  out.push(line('ERROR_LINE:', result.ERROR_LINE));
  out.push(line('EXIT_CODE:', result.EXIT_CODE));
  if (result.REASON) {
    out.push('');
    out.push(`MOTIVO: ${result.REASON}`);
  }

  out.push('');
  out.push('## Diagnostico tecnico');
  out.push('');
  if (metadata?.RUNTIME) out.push(line('RUNTIME:', metadata.RUNTIME));
  if (metadata?.PHP_VERSION) out.push(line('PHP_VERSION:', metadata.PHP_VERSION));
  if (metadata?.DATABASE_VERSION) out.push(line('DATABASE_VERSION:', metadata.DATABASE_VERSION));
  if (metadata?.DOCKER_IMAGE) out.push(line('DOCKER_IMAGE:', metadata.DOCKER_IMAGE));
  if (metadata?.DOCKER_DIGEST) out.push(line('DOCKER_DIGEST:', metadata.DOCKER_DIGEST));
  for (const item of diagnostics?.findings || []) {
    out.push(`- ${item}`);
  }
  if (!diagnostics?.findings?.length) out.push('- Sin hallazgos adicionales.');

  out.push('');
  out.push('## Pruebas');
  out.push('');
  const cases = tests?.cases || [];
  if (!cases.length) {
    out.push('- No se ejecutaron pruebas.');
  } else {
    for (const test of cases) {
      out.push(`- [${test.status}] ${test.name}${test.detail ? ` — ${test.detail}` : ''}`);
    }
    out.push('');
    out.push(line('TESTS_TOTAL:', cases.length));
    out.push(line('TESTS_PASS:', cases.filter((t) => t.status === 'PASS').length));
    out.push(line('TESTS_FAIL:', cases.filter((t) => t.status === 'FAIL').length));
  }

  out.push('');
  out.push('## Analisis IA');
  out.push('');
  out.push(line('AI_STATUS:', ai?.AI_STATUS || 'DISABLED'));
  if (ai?.AI_STATUS === 'AVAILABLE') {
    out.push(line('AI_PROVIDER:', ai.AI_PROVIDER));
    out.push(line('AI_MODEL:', ai.AI_MODEL));
    out.push(line('AI_CONFIDENCE:', ai.AI_CONFIDENCE));
    out.push('');
    out.push('AI_DIAGNOSIS:');
    out.push(ai.AI_DIAGNOSIS || '—');
    out.push('');
    out.push('AI_RECOMMENDATION:');
    out.push(ai.AI_RECOMMENDATION || '—');
    out.push('');
    out.push('AI_INPUT_CONTEXT:');
    for (const item of ai.AI_INPUT_CONTEXT || []) out.push(`- ${item}`);
  } else if (ai?.AI_REASON) {
    out.push(`Motivo: ${ai.AI_REASON}`);
  }
  out.push('');
  out.push('La IA no modifica el resultado determinista. La evidencia de Docker y las pruebas mandan.');

  out.push('');
  out.push('## Recomendaciones');
  out.push('');
  const recommendations = diagnostics?.recommendations || [];
  if (!recommendations.length) out.push('- Sin recomendaciones automaticas.');
  for (const item of recommendations) out.push(`- ${item}`);

  out.push('');
  return `${out.join('\n')}\n`;
}

export function writeReport(dir, payload) {
  writeJson(dir, 'result.json', payload.result);
  writeJson(dir, 'metadata.json', payload.metadata);
  writeJson(dir, 'diagnostics.json', payload.diagnostics);
  writeJson(dir, 'test-results.json', payload.tests);
  if (payload.ai && payload.ai.AI_STATUS !== 'DISABLED') {
    writeJson(dir, 'ai-analysis.json', payload.ai);
  }
  const text = renderReportText(payload);
  fs.writeFileSync(path.join(dir, 'report.txt'), text);
  return { dir, text };
}
