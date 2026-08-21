/**
 * SR-108688 como regresión permanente (§20).
 *
 * Caso: WooCommerce 11.0.1 × Chilexpress 1.4.0
 *   Class "Automattic\WooCommerce\Enums\ProductTaxStatus" not found
 *
 * Determina si el artefacto REPRODUCE, CORRIGE o CAMBIA el comportamiento.
 * Nunca oculta el fatal, nunca crea stubs, nunca toca WooCommerce core.
 */

export const ID = 'SR-108688';
export const TITLE = 'ProductTaxStatus not found al cargar Chilexpress con WooCommerce 11';
const MARKER = 'Automattic\\WooCommerce\\Enums\\ProductTaxStatus';

export const OUTCOME = {
  REPRODUCED: 'REPRODUCED',
  FIXED: 'FIXED',
  CHANGED: 'CHANGED',
  NOT_APPLICABLE: 'NOT_APPLICABLE',
  INCONCLUSIVE: 'INCONCLUSIVE',
};

function mentionsMarker(text) {
  return typeof text === 'string' && text.includes(MARKER);
}

/**
 * @param {object} evidence
 * @param {string} evidence.logExcerpt   Contenido de debug.log del run
 * @param {string} evidence.activationOutput Salida de la activación del plugin
 * @param {Array}  evidence.httpProbes   [{ url, status, bodyExcerpt }]
 * @param {string} evidence.wooVersion
 * @param {boolean} evidence.pluginActive
 */
export function evaluate(evidence) {
  const {
    logExcerpt = '',
    activationOutput = '',
    httpProbes = [],
    wooVersion = '',
    pluginActive = false,
  } = evidence;

  const bodies = httpProbes.map((probe) => probe.bodyExcerpt || '').join('\n');
  const haystack = [logExcerpt, activationOutput, bodies].join('\n');
  const markerFound = mentionsMarker(haystack);
  const fatalFound = /PHP Fatal error|Uncaught Error|Fatal error:/i.test(haystack);
  const serverError = httpProbes.some((probe) => probe.status >= 500);

  const wooMajor = Number(String(wooVersion).split('.')[0] || 0);
  const applicable = wooMajor >= 11;

  if (markerFound) {
    return {
      id: ID,
      title: TITLE,
      outcome: OUTCOME.REPRODUCED,
      status: 'FAIL',
      applicable,
      detail: `Se observo la clase ausente ${MARKER} con WooCommerce ${wooVersion || 'desconocida'}.`,
      evidence: { markerFound, fatalFound, serverError },
    };
  }

  if (fatalFound || serverError) {
    return {
      id: ID,
      title: TITLE,
      outcome: OUTCOME.CHANGED,
      status: 'FAIL',
      applicable,
      detail: 'Hay un fatal o respuesta 5xx, pero no es el marcador exacto de SR-108688. El comportamiento cambio.',
      evidence: { markerFound, fatalFound, serverError },
    };
  }

  if (!applicable) {
    return {
      id: ID,
      title: TITLE,
      outcome: OUTCOME.NOT_APPLICABLE,
      status: 'SKIP',
      applicable,
      detail: `WooCommerce ${wooVersion || 'desconocida'} es anterior a la serie 11: el enum no aplica.`,
      evidence: { markerFound, fatalFound, serverError },
    };
  }

  if (!pluginActive) {
    return {
      id: ID,
      title: TITLE,
      outcome: OUTCOME.INCONCLUSIVE,
      status: 'FAIL',
      applicable,
      detail: 'El plugin no quedo activo: no se puede afirmar que el fallo este corregido.',
      evidence: { markerFound, fatalFound, serverError },
    };
  }

  return {
    id: ID,
    title: TITLE,
    outcome: OUTCOME.FIXED,
    status: 'PASS',
    applicable,
    detail: `Con WooCommerce ${wooVersion} y el plugin activo no aparece ${MARKER} ni ningun fatal.`,
    evidence: { markerFound, fatalFound, serverError },
  };
}

export const REGISTRY = [{ id: ID, title: TITLE, platform: 'woocommerce', evaluate }];
