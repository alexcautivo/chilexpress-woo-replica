/**
 * Orquestación del auditor: ARTEFACTO → INSPECCIÓN → PLAN → ENTORNO →
 * PRUEBAS → REGRESIONES → RESULTADO → IA → REPORTE (§57).
 */
import { analyzeWithAi } from '../ai/index.mjs';
import { assertUnchanged, describeArtifact } from './artifact.mjs';
import { publicAiConfig } from './config.mjs';
import { detectPlatform } from './detect.mjs';
import { documentationConflicts, inspectArtifact } from './inspect.mjs';
import { loadPlan } from './plans.mjs';
import { getAdapter } from './registry.mjs';
import { createReportDir, writeLog, writeReport } from './report.mjs';
import { COMPATIBILITY, STATUS, SUPPORT_STATUS, createResult, finalizeResult, newAuditId } from './result.mjs';
import { detectVersion } from './version.mjs';
import { readZip } from './zip.mjs';

/** Fase común a todos los modos: hash, inspección, plataforma, versión, plan. */
export function inspectPhase(zipPath, options = {}) {
  const artifact = describeArtifact(zipPath);
  const zip = readZip(artifact.path);
  const inspection = inspectArtifact(zip, artifact);
  const detection = detectPlatform(zip, options.platform || '');
  const version = detectVersion(zip, artifact.name);
  const conflicts = documentationConflicts(inspection, version);
  const plan = detection.platform !== 'UNKNOWN' ? loadPlan(detection.platform) : { found: false, documents: [], requirements: [], missing: [] };
  return { artifact, zip, inspection, detection, version, conflicts, plan };
}

function baseResult(phase, mode, auditId) {
  return createResult({
    AUDIT_ID: auditId,
    MODE: mode,
    PLATFORM: phase.detection.platform,
    PLUGIN_VERSION: phase.version.conflict ? 'UNKNOWN' : phase.version.version,
    ARTIFACT: phase.artifact.name,
    ARTIFACT_ORIGIN: phase.artifact.origin,
    SHA256: phase.artifact.sha256,
  });
}

function blockingReason(phase) {
  if (phase.inspection.PLUGIN_INSPECTION === 'FAIL') {
    return `PLUGIN_INSPECTION=FAIL: ${phase.inspection.problems.join('; ')}`;
  }
  if (phase.detection.platform === 'UNKNOWN') {
    return 'PLATFORM=UNKNOWN. Use --platform=<woocommerce|prestashop|magento|shopify> para forzar la seleccion.';
  }
  if (phase.version.conflict) {
    return `VERSION_CONFLICT=YES. Fuentes discrepantes: ${phase.version.distinct.join(', ')}.`;
  }
  return '';
}

export async function runInspect(zipPath, options = {}) {
  const auditId = options.auditId || newAuditId();
  const phase = inspectPhase(zipPath, options);
  const result = finalizeResult({
    ...baseResult(phase, 'INSPECT', auditId),
    STATUS: phase.inspection.PLUGIN_INSPECTION === 'PASS' ? STATUS.PASS : STATUS.FAIL,
    COMPATIBILITY: COMPATIBILITY.NOT_EVALUATED,
    SUPPORT_STATUS: SUPPORT_STATUS.UNKNOWN,
    REASON:
      phase.inspection.PLUGIN_INSPECTION === 'PASS'
        ? 'Inspeccion estatica completada. No se ejecutaron pruebas en Docker.'
        : phase.inspection.problems.join('; '),
  });

  const dir = createReportDir(auditId);
  const payload = {
    result,
    metadata: buildMetadata(phase, result, {}),
    diagnostics: {
      PLUGIN_INSPECTION: phase.inspection.PLUGIN_INSPECTION,
      PLATFORM_DETECTION: phase.detection,
      VERSION_DETECTION: phase.version,
      VERSION_CONFLICT: phase.version.conflict ? 'YES' : 'NO',
      ...phase.conflicts,
      findings: buildInspectFindings(phase),
      recommendations: buildInspectRecommendations(phase),
      inspection: phase.inspection,
      plan: phase.plan,
    },
    tests: { cases: [] },
    ai: { AI_STATUS: 'DISABLED', AI_REASON: 'El modo INSPECT no consulta IA.' },
  };
  writeReport(dir, payload);
  return { result, dir, phase, payload };
}

export async function runPlan(zipPath, options = {}) {
  const auditId = options.auditId || newAuditId();
  const phase = inspectPhase(zipPath, options);
  const blocking = blockingReason(phase);
  const platform = phase.detection.platform;
  const versions = options.versions?.length
    ? options.versions
    : platform !== 'UNKNOWN'
      ? (await import('./matrix.mjs')).defaultVersionsFor(platform)
      : [];

  const result = finalizeResult({
    ...baseResult(phase, 'PLAN', auditId),
    STATUS: blocking ? STATUS.UNKNOWN : STATUS.PASS,
    COMPATIBILITY: COMPATIBILITY.NOT_EVALUATED,
    REASON: blocking || `Plan listo: ${versions.length} combinacion(es) a probar. No se modifico codigo ni entorno.`,
  });

  const dir = createReportDir(auditId);
  const payload = {
    result,
    metadata: buildMetadata(phase, result, {}),
    diagnostics: {
      findings: [
        `Plan de plataforma: ${phase.plan.found ? phase.plan.documents.map((d) => d.file).join(', ') : 'no encontrado'}`,
        ...phase.plan.requirements.slice(0, 20),
      ],
      recommendations: versions.map((version) => `Ejecutar: auditor audit <zip> --platform=${platform} --platform-version=${version}`),
      plannedMatrix: versions.map((version) => ({ platform, platformVersion: version })),
      plan: phase.plan,
      inspection: phase.inspection,
    },
    tests: { cases: [] },
    ai: { AI_STATUS: 'DISABLED', AI_REASON: 'El modo PLAN no consulta IA.' },
  };
  writeReport(dir, payload);
  return { result, dir, phase, payload, versions };
}

function buildInspectFindings(phase) {
  const findings = [];
  findings.push(`Estructura: ${phase.inspection.structure.fileCount} archivos, raiz "${phase.inspection.structure.rootFolder || '(sin carpeta raiz)'}"`);
  findings.push(`Deteccion de plataforma: ${phase.detection.platform} (confianza ${phase.detection.confident ? 'alta' : 'baja'})`);
  for (const [platform, signals] of Object.entries(phase.detection.evidence)) {
    if (signals.length) findings.push(`Senales ${platform}: ${signals.join(' | ')}`);
  }
  findings.push(`Version detectada: ${phase.version.version}${phase.version.conflict ? ' (CONFLICTO)' : ''}`);
  if (phase.inspection.requirements.php) findings.push(`PHP requerido: ${phase.inspection.requirements.php}`);
  for (const raw of phase.inspection.requirements.raw.slice(0, 10)) findings.push(raw);
  if (phase.conflicts.conflicts.length) findings.push(...phase.conflicts.conflicts);
  return findings;
}

function buildInspectRecommendations(phase) {
  const recommendations = [];
  if (phase.detection.platform === 'UNKNOWN') {
    recommendations.push('Forzar la plataforma con --platform= si la deteccion no es concluyente.');
  }
  if (phase.version.conflict) {
    recommendations.push('Resolver el conflicto de versiones antes de auditar: el reporte no puede afirmar una version unica.');
  }
  if (phase.artifact.origin !== 'official-zip') {
    recommendations.push('Este artefacto no es un release oficial: los resultados no representan al paquete distribuido.');
  }
  return recommendations;
}

function buildMetadata(phase, result, extra) {
  return {
    AUDIT_ID: result.AUDIT_ID,
    TIMESTAMP: result.TIMESTAMP,
    MODE: result.MODE,
    PLATFORM: result.PLATFORM,
    PLATFORM_VERSION: result.PLATFORM_VERSION,
    PLUGIN_VERSION: result.PLUGIN_VERSION,
    ARTIFACT: phase.artifact.name,
    ARTIFACT_PATH: phase.artifact.path,
    ARTIFACT_ORIGIN: phase.artifact.origin,
    ARTIFACT_SIZE_BYTES: phase.artifact.sizeBytes,
    ARTIFACT_MODIFIED_AT: phase.artifact.modifiedAt,
    SHA256: phase.artifact.sha256,
    STATUS: result.STATUS,
    EXIT_CODE: result.EXIT_CODE,
    NODE_VERSION: process.version,
    ...publicAiConfig(),
    ...extra,
  };
}

export async function runAudit(zipPath, options = {}) {
  const auditId = options.auditId || newAuditId();
  const log = options.log || (() => {});
  const phase = inspectPhase(zipPath, options);
  const dir = createReportDir(auditId);

  const blocking = blockingReason(phase);
  if (blocking) {
    const result = finalizeResult({
      ...baseResult(phase, options.mode || 'AUDIT', auditId),
      STATUS: phase.detection.platform === 'UNKNOWN' || phase.version.conflict ? STATUS.UNKNOWN : STATUS.FAIL,
      REASON: blocking,
    });
    const payload = {
      result,
      metadata: buildMetadata(phase, result, {}),
      diagnostics: { findings: buildInspectFindings(phase), recommendations: buildInspectRecommendations(phase), inspection: phase.inspection },
      tests: { cases: [] },
      ai: { AI_STATUS: 'DISABLED', AI_REASON: 'No se llego a ejecutar la auditoria.' },
    };
    writeReport(dir, payload);
    return { result, dir, payload };
  }

  const adapter = getAdapter(phase.detection.platform);
  log(`Plataforma ${phase.detection.platform}. Artefacto ${phase.artifact.name} (${phase.artifact.sha256.slice(0, 12)}…).`);

  let outcome;
  try {
    outcome = await adapter.audit({
      artifact: phase.artifact,
      zip: phase.zip,
      inspection: phase.inspection,
      plan: phase.plan,
      pluginVersion: phase.version.version,
      options,
      log,
    });
  } catch (error) {
    outcome = {
      status: STATUS.ERROR,
      errorKind: 'auditor',
      reason: `Error interno del auditor: ${error.message}`,
      tests: [],
      findings: [],
      metadata: {},
      logs: {},
    };
  }

  // El ZIP no puede haber cambiado durante la auditoría (§4).
  try {
    assertUnchanged(phase.artifact);
  } catch (error) {
    outcome.status = STATUS.ERROR;
    outcome.errorKind = 'auditor';
    outcome.reason = error.message;
  }

  const result = finalizeResult(
    {
      ...baseResult(phase, options.mode || 'AUDIT', auditId),
      PLATFORM_VERSION: outcome.platformVersion || 'UNKNOWN',
      PLUGIN_VERSION: outcome.pluginVersion || phase.version.version,
      STATUS: outcome.status,
      COMPATIBILITY: outcome.compatibility || COMPATIBILITY.NOT_EVALUATED,
      SUPPORT_STATUS: outcome.supportStatus || SUPPORT_STATUS.UNKNOWN,
      FATAL_DETECTED: Boolean(outcome.fatalDetected),
      ERROR_CLASS: outcome.errorClass || '',
      ERROR_FILE: outcome.errorFile || '',
      ERROR_LINE: outcome.errorLine ?? null,
      REASON: outcome.reason || '',
    },
    outcome.errorKind || 'environment',
  );

  const diagnostics = {
    findings: [
      ...(outcome.findings || []),
      ...buildInspectFindings(phase),
    ],
    recommendations: outcome.recommendations || [],
    regressions: outcome.regressions || [],
    healthchecks: outcome.timings || [],
    checklist: outcome.checklist || {},
    supportDetail: outcome.supportDetail || '',
    stackTrace: outcome.stackTrace || '',
    logExcerpt: outcome.logExcerpt || '',
    inspection: phase.inspection,
    plan: phase.plan,
    ...phase.conflicts,
  };

  const tests = { cases: outcome.tests || [] };

  for (const [name, content] of Object.entries(outcome.logs || {})) {
    writeLog(dir, name, content);
  }

  const ai = await analyzeWithAi({ result, diagnostics, tests, inspection: phase.inspection });

  const payload = {
    result,
    metadata: buildMetadata(phase, result, {
      WORDPRESS_VERSION: outcome.wordpressVersion || '',
      ...(outcome.metadata || {}),
    }),
    diagnostics,
    tests,
    ai,
  };
  writeReport(dir, payload);
  return { result, dir, payload };
}
