/**
 * Configuración de IA (§29–§31, §36). La API key nunca se imprime, nunca entra
 * en reportes y nunca se versiona.
 */
import fs from 'node:fs';
import path from 'node:path';
import { AUDITOR_ROOT } from './plans.mjs';

const CONFIG_DIR = path.join(AUDITOR_ROOT, '.auditor');
const CONFIG_FILE = path.join(CONFIG_DIR, 'ai.json');

export const ANALYSIS_LEVELS = ['OFF', 'BASIC', 'STANDARD', 'DEEP'];

const DEFAULTS = {
  enabled: false,
  provider: 'anthropic',
  model: 'claude-sonnet-4-5',
  analysisLevel: 'STANDARD',
  maxRequests: 3,
  maxTokens: 1200,
  timeoutMs: 45_000,
  deepConsent: false,
};

function readFileConfig() {
  if (!fs.existsSync(CONFIG_FILE)) return {};
  try {
    return JSON.parse(fs.readFileSync(CONFIG_FILE, 'utf8'));
  } catch {
    return {};
  }
}

function readEnvConfig() {
  const env = {};
  if (process.env.AI_ENABLED) env.enabled = /^(1|true|yes|on)$/i.test(process.env.AI_ENABLED);
  if (process.env.AI_PROVIDER) env.provider = process.env.AI_PROVIDER.toLowerCase();
  if (process.env.AI_MODEL) env.model = process.env.AI_MODEL;
  if (process.env.AI_ANALYSIS_LEVEL) env.analysisLevel = process.env.AI_ANALYSIS_LEVEL.toUpperCase();
  if (process.env.AI_MAX_REQUESTS) env.maxRequests = Number(process.env.AI_MAX_REQUESTS);
  if (process.env.AI_MAX_TOKENS) env.maxTokens = Number(process.env.AI_MAX_TOKENS);
  if (process.env.AI_TIMEOUT) env.timeoutMs = Number(process.env.AI_TIMEOUT);
  if (process.env.AI_DEEP_CONSENT) env.deepConsent = /^(1|true|yes|on)$/i.test(process.env.AI_DEEP_CONSENT);
  return env;
}

function readApiKey(config) {
  return process.env.ANTHROPIC_API_KEY || process.env.AI_API_KEY || config.apiKey || '';
}

export function loadAiConfig() {
  const file = readFileConfig();
  const config = { ...DEFAULTS, ...file, ...readEnvConfig() };
  if (!ANALYSIS_LEVELS.includes(config.analysisLevel)) config.analysisLevel = 'STANDARD';
  const apiKey = readApiKey(file);
  return {
    ...config,
    apiKey,
    hasApiKey: Boolean(apiKey),
    configFile: CONFIG_FILE,
  };
}

export function saveAiConfig(update) {
  fs.mkdirSync(CONFIG_DIR, { recursive: true });
  const current = readFileConfig();
  const next = { ...DEFAULTS, ...current, ...update };
  fs.writeFileSync(CONFIG_FILE, `${JSON.stringify(next, null, 2)}\n`, { mode: 0o600 });
  try {
    fs.chmodSync(CONFIG_FILE, 0o600);
  } catch {
    /* Windows puede ignorar chmod; el archivo sigue fuera de git */
  }
  return CONFIG_FILE;
}

/** Vista segura para `auditor ai status` y para metadata (§47). */
export function publicAiConfig(config = loadAiConfig()) {
  return {
    AI_ENABLED: config.enabled && config.hasApiKey ? 'YES' : 'NO',
    AI_PROVIDER: config.provider.toUpperCase(),
    AI_MODEL: config.model,
    AI_ANALYSIS_LEVEL: config.analysisLevel,
    AI_MAX_REQUESTS: config.maxRequests,
    AI_MAX_TOKENS: config.maxTokens,
    AI_TIMEOUT_MS: config.timeoutMs,
    AI_KEY_PRESENT: config.hasApiKey ? 'YES' : 'NO',
    AI_DEEP_CONSENT: config.deepConsent ? 'YES' : 'NO',
  };
}
