/**
 * Registro de adapters. El Core coordina; los adapters implementan (§57).
 */
import * as magento from '../adapters/magento.mjs';
import * as prestashop from '../adapters/prestashop.mjs';
import * as shopify from '../adapters/shopify.mjs';
import * as woocommerce from '../adapters/woocommerce.mjs';

const ADAPTERS = { woocommerce, prestashop, magento, shopify };

export function getAdapter(platform) {
  const adapter = ADAPTERS[platform];
  if (!adapter) {
    throw new Error(`No hay adapter para la plataforma "${platform}".`);
  }
  return adapter;
}

export function listAdapters() {
  return Object.entries(ADAPTERS).map(([platform, adapter]) => ({
    platform,
    displayName: adapter.meta.displayName,
    composeFile: adapter.meta.composeFile,
  }));
}

export function hasAdapter(platform) {
  return Boolean(ADAPTERS[platform]);
}
