/**
 * Detección de plataforma (§7). Ante duda: UNKNOWN. No se adivina.
 */
import { findEntries, findEntry, readText } from './zip.mjs';

const MIN_SCORE = 3;
const MIN_MARGIN = 2;

function scoreWooCommerce(zip) {
  const signals = [];
  const phpFiles = findEntries(zip, (name) => name.endsWith('.php'));
  for (const entry of phpFiles.slice(0, 40)) {
    const text = readText(zip, entry, 8000);
    if (/^\s*\*\s*Plugin Name\s*:/im.test(text)) {
      signals.push({ weight: 4, detail: `Cabecera "Plugin Name" en ${entry.name}` });
      break;
    }
  }
  if (findEntry(zip, (name) => name.endsWith('readme.txt'))) {
    signals.push({ weight: 1, detail: 'readme.txt estilo wordpress.org' });
  }
  if (findEntry(zip, (name) => name.endsWith('uninstall.php'))) {
    signals.push({ weight: 1, detail: 'uninstall.php de WordPress' });
  }
  const joined = phpFiles.slice(0, 25).map((entry) => readText(zip, entry, 6000)).join('\n');
  if (/\badd_action\s*\(|\badd_filter\s*\(/.test(joined)) {
    signals.push({ weight: 2, detail: 'Uso de add_action/add_filter' });
  }
  if (/WC_Shipping_Method|WooCommerce|wc_get_/.test(joined)) {
    signals.push({ weight: 3, detail: 'Referencias a la API de WooCommerce' });
  }
  return signals;
}

function scorePrestaShop(zip) {
  const signals = [];
  if (findEntry(zip, (name) => name.endsWith('config.xml'))) {
    signals.push({ weight: 2, detail: 'config.xml de módulo PrestaShop' });
  }
  if (findEntry(zip, (name) => /\/controllers\/(front|admin)\//.test(name))) {
    signals.push({ weight: 2, detail: 'Controladores front/admin' });
  }
  if (findEntry(zip, (name) => /\/views\/templates\//.test(name))) {
    signals.push({ weight: 1, detail: 'views/templates' });
  }
  const phpFiles = findEntries(zip, (name) => name.endsWith('.php')).slice(0, 25);
  const joined = phpFiles.map((entry) => readText(zip, entry, 6000)).join('\n');
  if (/class\s+\w+\s+extends\s+(Module|CarrierModule)\b/.test(joined)) {
    signals.push({ weight: 4, detail: 'Clase que extiende Module/CarrierModule' });
  }
  if (/_PS_VERSION_|Configuration::get\(/.test(joined)) {
    signals.push({ weight: 3, detail: 'Constantes/API de PrestaShop' });
  }
  return signals;
}

function scoreMagento(zip) {
  const signals = [];
  if (findEntry(zip, (name) => name.endsWith('registration.php'))) {
    signals.push({ weight: 3, detail: 'registration.php' });
  }
  if (findEntry(zip, (name) => name.endsWith('etc/module.xml'))) {
    signals.push({ weight: 4, detail: 'etc/module.xml' });
  }
  if (findEntry(zip, (name) => name.endsWith('etc/di.xml'))) {
    signals.push({ weight: 2, detail: 'etc/di.xml' });
  }
  const composer = findEntry(zip, (name) => name.endsWith('composer.json'));
  if (composer && /magento2-module/.test(readText(zip, composer, 20000))) {
    signals.push({ weight: 4, detail: 'composer.json type magento2-module' });
  }
  return signals;
}

function scoreShopify(zip) {
  const signals = [];
  if (findEntry(zip, (name) => name.endsWith('shopify.app.toml'))) {
    signals.push({ weight: 5, detail: 'shopify.app.toml' });
  }
  if (findEntry(zip, (name) => /extensions\/.+\/shopify\.extension\.toml$/.test(name))) {
    signals.push({ weight: 4, detail: 'Extensión Shopify' });
  }
  const pkg = findEntry(zip, (name) => name.endsWith('package.json'));
  if (pkg) {
    const text = readText(zip, pkg, 20000);
    if (/@shopify\//.test(text)) signals.push({ weight: 4, detail: 'Dependencias @shopify/*' });
    else signals.push({ weight: 1, detail: 'package.json (proyecto Node)' });
  }
  if (findEntry(zip, (name) => name.endsWith('.liquid'))) {
    signals.push({ weight: 2, detail: 'Plantillas .liquid' });
  }
  return signals;
}

const DETECTORS = {
  woocommerce: scoreWooCommerce,
  prestashop: scorePrestaShop,
  magento: scoreMagento,
  shopify: scoreShopify,
};

export const SUPPORTED_PLATFORMS = Object.keys(DETECTORS);

export function detectPlatform(zip, forced = '') {
  const scores = {};
  const evidence = {};
  for (const [platform, detector] of Object.entries(DETECTORS)) {
    const signals = detector(zip);
    scores[platform] = signals.reduce((total, signal) => total + signal.weight, 0);
    evidence[platform] = signals.map((signal) => signal.detail);
  }

  if (forced) {
    const platform = forced.toLowerCase();
    if (!DETECTORS[platform]) {
      throw new Error(`Plataforma forzada no soportada: ${forced}`);
    }
    return { platform, scores, evidence, forced: true, confident: true };
  }

  const ranked = Object.entries(scores).sort((a, b) => b[1] - a[1]);
  const [best, bestScore] = ranked[0];
  const secondScore = ranked[1] ? ranked[1][1] : 0;
  const confident = bestScore >= MIN_SCORE && bestScore - secondScore >= MIN_MARGIN;
  return {
    platform: confident ? best : 'UNKNOWN',
    scores,
    evidence,
    forced: false,
    confident,
  };
}
