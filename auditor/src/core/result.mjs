/**
 * Modelo de resultado común (§24), estados (§25) y exit codes (§26).
 * Ningún adapter puede inventar campos fuera de este contrato.
 */

export const STATUS = {
  PASS: 'PASS',
  FAIL: 'FAIL',
  ERROR: 'ERROR',
  SKIP: 'SKIP',
  UNKNOWN: 'UNKNOWN',
};

export const SUPPORT_STATUS = {
  SUPPORTED: 'SUPPORTED',
  UNSUPPORTED: 'UNSUPPORTED',
  OUT_OF_SUPPORTED_RANGE: 'OUT_OF_SUPPORTED_RANGE',
  EXPERIMENTAL: 'EXPERIMENTAL',
  UNKNOWN: 'UNKNOWN',
};

export const COMPATIBILITY = {
  COMPATIBLE: 'COMPATIBLE',
  INCOMPATIBLE: 'INCOMPATIBLE',
  PARTIAL: 'PARTIAL',
  NOT_EVALUATED: 'NOT_EVALUATED',
  UNKNOWN: 'UNKNOWN',
};

export const EXIT_CODE = {
  PASS: 0,
  FAIL: 1,
  ENVIRONMENT_ERROR: 2,
  AUDITOR_ERROR: 3,
  SKIP: 4,
  UNKNOWN: 5,
};

/**
 * Un fatal crítico jamás puede terminar en 0 (§26). `ERROR` se separa en
 * error de entorno (2) y error del propio auditor (3) mediante `errorKind`.
 */
export function exitCodeFor(status, errorKind = 'environment') {
  switch (status) {
    case STATUS.PASS:
      return EXIT_CODE.PASS;
    case STATUS.FAIL:
      return EXIT_CODE.FAIL;
    case STATUS.ERROR:
      return errorKind === 'auditor' ? EXIT_CODE.AUDITOR_ERROR : EXIT_CODE.ENVIRONMENT_ERROR;
    case STATUS.SKIP:
      return EXIT_CODE.SKIP;
    default:
      return EXIT_CODE.UNKNOWN;
  }
}

export function newAuditId(date = new Date()) {
  const iso = date.toISOString();
  const stamp = iso.slice(0, 19).replace(/[-:T]/g, '');
  const rand = Math.random().toString(36).slice(2, 6).toUpperCase();
  return `AUDIT-${stamp}-${rand}`;
}

export function createResult(overrides = {}) {
  return {
    AUDIT_ID: overrides.AUDIT_ID || newAuditId(),
    TIMESTAMP: new Date().toISOString(),
    MODE: 'AUDIT',
    PLATFORM: 'UNKNOWN',
    PLATFORM_VERSION: 'UNKNOWN',
    PLUGIN_VERSION: 'UNKNOWN',
    ARTIFACT: '',
    ARTIFACT_ORIGIN: 'unknown',
    SHA256: '',
    STATUS: STATUS.UNKNOWN,
    COMPATIBILITY: COMPATIBILITY.NOT_EVALUATED,
    SUPPORT_STATUS: SUPPORT_STATUS.UNKNOWN,
    FATAL_DETECTED: false,
    ERROR_CLASS: '',
    ERROR_FILE: '',
    ERROR_LINE: null,
    EXIT_CODE: EXIT_CODE.UNKNOWN,
    REASON: '',
    ...overrides,
  };
}

/**
 * Sella el resultado: recalcula el exit code y aplica las invariantes del
 * prompt (un fatal nunca puede quedar como PASS, §25 y §55).
 */
export function finalizeResult(result, errorKind = 'environment') {
  const sealed = { ...result };
  if (sealed.FATAL_DETECTED && sealed.STATUS === STATUS.PASS) {
    sealed.STATUS = STATUS.FAIL;
    sealed.REASON = sealed.REASON || 'Se detecto un fatal: PASS no es admisible.';
  }
  sealed.EXIT_CODE = exitCodeFor(sealed.STATUS, errorKind);
  return sealed;
}

/**
 * Criterio real de PASS (§55). Recibe el checklist observado y devuelve el
 * estado y el motivo. No hay atajos: falta uno, no hay PASS.
 */
export const PASS_CHECKLIST = [
  'artifact_valid',
  'hash_recorded',
  'platform_identified',
  'version_identified',
  'requirements_met',
  'docker_reproducible',
  'platform_started',
  'plugin_installed',
  'plugin_initialized',
  'critical_paths_executed',
  'functional_tests_completed',
  'no_fatals',
  'no_uncaught_exceptions',
  'regressions_passed',
  'expected_result_confirmed',
];

export function evaluatePass(checklist) {
  const missing = PASS_CHECKLIST.filter((key) => checklist[key] !== true);
  if (!missing.length) {
    return { status: STATUS.PASS, reason: 'Todos los criterios de la seccion 55 se cumplieron.', missing };
  }
  return {
    status: STATUS.FAIL,
    reason: `No se cumplieron criterios obligatorios: ${missing.join(', ')}.`,
    missing,
  };
}
