/**
 * Fachada de IA (§27, §32–§43).
 *
 * Reglas duras:
 * - Sin API key el auditor sigue funcionando.
 * - Un fallo de IA nunca se convierte en un fallo de plataforma.
 * - La IA jamás transforma FAIL/ERROR en PASS.
 */
import { loadAiConfig, publicAiConfig } from '../core/config.mjs';
import { redact } from '../core/redact.mjs';
import { AnthropicProvider } from './anthropic.mjs';
import { NullProvider } from './provider.mjs';

const SYSTEM_PROMPT = [
  'Eres un analista de compatibilidad de plugins de e-commerce.',
  'Recibes evidencia ya redactada de una auditoria determinista.',
  'No puedes cambiar el veredicto: solo explicar, correlacionar y recomendar.',
  'Si la evidencia es insuficiente, dilo explicitamente.',
  'Responde SOLO con JSON valido con las claves: diagnosis, recommendation, confidence (0 a 1), hypotheses (array de strings).',
].join(' ');

function providerFor(config) {
  if (config.provider === 'anthropic') return new AnthropicProvider(config);
  return new NullProvider(config);
}

function shouldRun(config, deterministic) {
  if (!config.enabled) return { run: false, reason: 'IA deshabilitada en configuracion.' };
  if (!config.hasApiKey) return { run: false, reason: 'No hay API key configurada.' };
  if (config.analysisLevel === 'OFF') return { run: false, reason: 'AI_ANALYSIS_LEVEL=OFF.' };
  if (config.analysisLevel === 'DEEP' && !config.deepConsent) {
    return { run: false, reason: 'DEEP requiere consentimiento explicito (AI_DEEP_CONSENT).' };
  }
  if (config.analysisLevel === 'BASIC') {
    const interesting =
      deterministic.FATAL_DETECTED ||
      deterministic.STATUS === 'FAIL' ||
      deterministic.STATUS === 'ERROR' ||
      deterministic.SUPPORT_STATUS === 'OUT_OF_SUPPORTED_RANGE';
    if (!interesting) return { run: false, reason: 'BASIC solo analiza fatales, incompatibilidades o conflictos.' };
  }
  return { run: true, reason: '' };
}

/**
 * Construye el contexto mínimo necesario (§34): nunca el proyecto entero.
 */
function buildContext(payload, level) {
  const { result, diagnostics, tests, inspection } = payload;
  const context = {
    deterministic: {
      status: result.STATUS,
      platform: result.PLATFORM,
      platformVersion: result.PLATFORM_VERSION,
      pluginVersion: result.PLUGIN_VERSION,
      supportStatus: result.SUPPORT_STATUS,
      fatalDetected: result.FATAL_DETECTED,
      errorClass: result.ERROR_CLASS,
      errorFile: result.ERROR_FILE,
      errorLine: result.ERROR_LINE,
      reason: result.REASON,
    },
    findings: (diagnostics?.findings || []).slice(0, 25),
    failedTests: (tests?.cases || []).filter((test) => test.status !== 'PASS').slice(0, 20),
    stackTrace: (diagnostics?.stackTrace || '').slice(0, 4000),
    requirements: inspection?.requirements || null,
  };
  const labels = ['resultado determinista', 'hallazgos', 'pruebas fallidas', 'stack trace', 'requisitos declarados'];

  if (level === 'STANDARD' || level === 'DEEP') {
    context.hooks = (inspection?.integration?.hooks || []).slice(0, 40);
    context.logExcerpt = (diagnostics?.logExcerpt || '').slice(0, 4000);
    labels.push('hooks del plugin', 'extracto de log');
  }
  if (level === 'DEEP') {
    context.classes = (inspection?.symbols?.classes || []).slice(0, 60);
    context.includes = (inspection?.integration?.includes || []).slice(0, 40);
    context.documentation = (inspection?.documentation || []).slice(0, 3);
    labels.push('clases declaradas', 'includes', 'documentacion del artefacto');
  }
  return { context, labels };
}

function parseModelJson(text) {
  if (!text) return null;
  const fenced = text.match(/```(?:json)?\s*([\s\S]*?)```/i);
  const candidate = (fenced ? fenced[1] : text).trim();
  try {
    return JSON.parse(candidate);
  } catch {
    const start = candidate.indexOf('{');
    const end = candidate.lastIndexOf('}');
    if (start >= 0 && end > start) {
      try {
        return JSON.parse(candidate.slice(start, end + 1));
      } catch {
        return null;
      }
    }
    return null;
  }
}

export async function analyzeWithAi(payload, configOverride) {
  const config = configOverride || loadAiConfig();
  const publicConfig = publicAiConfig(config);
  const gate = shouldRun(config, payload.result);

  if (!gate.run) {
    return {
      AI_STATUS: 'DISABLED',
      AI_REASON: gate.reason,
      AI_PROVIDER: publicConfig.AI_PROVIDER,
      AI_MODEL: publicConfig.AI_MODEL,
      AI_ANALYSIS_LEVEL: publicConfig.AI_ANALYSIS_LEVEL,
    };
  }

  const { context, labels } = buildContext(payload, config.analysisLevel);
  const { text: safeContext, redactions } = redact(JSON.stringify(context, null, 2));
  const prompt = [
    `Nivel de analisis: ${config.analysisLevel}.`,
    'Evidencia (ya redactada):',
    safeContext,
    '',
    'Devuelve solo el JSON pedido.',
  ].join('\n');

  const provider = providerFor(config);
  const response = await provider.analyze(prompt, {
    system: SYSTEM_PROMPT,
    maxTokens: config.maxTokens,
    timeoutMs: config.timeoutMs,
  });

  if (!response.ok) {
    return {
      AI_STATUS: 'UNAVAILABLE',
      AI_REASON: response.reason,
      AI_PROVIDER: publicConfig.AI_PROVIDER,
      AI_MODEL: publicConfig.AI_MODEL,
      AI_ANALYSIS_LEVEL: publicConfig.AI_ANALYSIS_LEVEL,
      AI_INPUT_CONTEXT: labels,
      AI_REDACTIONS: redactions,
    };
  }

  const parsed = parseModelJson(response.text) || {};
  return {
    AI_STATUS: 'AVAILABLE',
    AI_PROVIDER: publicConfig.AI_PROVIDER,
    AI_MODEL: response.model || publicConfig.AI_MODEL,
    AI_ANALYSIS_LEVEL: publicConfig.AI_ANALYSIS_LEVEL,
    AI_DIAGNOSIS: String(parsed.diagnosis || response.text || '').slice(0, 4000),
    AI_RECOMMENDATION: String(parsed.recommendation || '').slice(0, 2000),
    AI_HYPOTHESES: Array.isArray(parsed.hypotheses) ? parsed.hypotheses.slice(0, 10) : [],
    AI_CONFIDENCE: typeof parsed.confidence === 'number' ? Math.max(0, Math.min(1, parsed.confidence)) : null,
    AI_INPUT_CONTEXT: labels,
    AI_REDACTIONS: redactions,
    AI_USAGE: response.usage || null,
    AI_DISCLAIMER: 'Analisis informativo. No altera el resultado determinista.',
  };
}
