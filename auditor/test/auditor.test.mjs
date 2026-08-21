/**
 * Pruebas deterministas del propio auditor. No requieren Docker.
 *   node test/auditor.test.mjs
 */
import assert from 'node:assert/strict';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import zlib from 'node:zlib';

import { detectPlatform } from '../src/core/detect.mjs';
import { documentationConflicts, inspectArtifact } from '../src/core/inspect.mjs';
import { redact } from '../src/core/redact.mjs';
import {
  COMPATIBILITY,
  EXIT_CODE,
  STATUS,
  evaluatePass,
  exitCodeFor,
  finalizeResult,
  PASS_CHECKLIST,
} from '../src/core/result.mjs';
import { detectVersion } from '../src/core/version.mjs';
import { readZip } from '../src/core/zip.mjs';
import { evaluate as evaluateSr } from '../src/regression/sr-108688.mjs';
import { describeArtifact } from '../src/core/artifact.mjs';
import { summarize } from '../src/core/matrix.mjs';

let passed = 0;
let failed = 0;

function test(name, fn) {
  try {
    fn();
    passed++;
    console.log(`OK  ${name}`);
  } catch (error) {
    failed++;
    console.error(`ERR ${name}\n    ${error.message}`);
  }
}

// ---------------------------------------------------------------------------
// Constructor de ZIP mínimo (stored) para no depender de binarios externos.
// ---------------------------------------------------------------------------
function buildZip(files) {
  const chunks = [];
  const central = [];
  let offset = 0;

  for (const [name, content] of Object.entries(files)) {
    const nameBuf = Buffer.from(name, 'utf8');
    const data = Buffer.from(content, 'utf8');
    const crc = zlib.crc32 ? zlib.crc32(data) : crc32(data);

    const local = Buffer.alloc(30);
    local.writeUInt32LE(0x04034b50, 0);
    local.writeUInt16LE(20, 4);
    local.writeUInt16LE(0, 6);
    local.writeUInt16LE(0, 8);
    local.writeUInt32LE(crc, 14);
    local.writeUInt32LE(data.length, 18);
    local.writeUInt32LE(data.length, 22);
    local.writeUInt16LE(nameBuf.length, 26);
    chunks.push(local, nameBuf, data);

    const entry = Buffer.alloc(46);
    entry.writeUInt32LE(0x02014b50, 0);
    entry.writeUInt16LE(20, 4);
    entry.writeUInt16LE(20, 6);
    entry.writeUInt32LE(crc, 16);
    entry.writeUInt32LE(data.length, 20);
    entry.writeUInt32LE(data.length, 24);
    entry.writeUInt16LE(nameBuf.length, 28);
    entry.writeUInt32LE(offset, 42);
    central.push(entry, nameBuf);

    offset += local.length + nameBuf.length + data.length;
  }

  const centralBuf = Buffer.concat(central);
  const eocd = Buffer.alloc(22);
  eocd.writeUInt32LE(0x06054b50, 0);
  eocd.writeUInt16LE(Object.keys(files).length, 8);
  eocd.writeUInt16LE(Object.keys(files).length, 10);
  eocd.writeUInt32LE(centralBuf.length, 12);
  eocd.writeUInt32LE(offset, 16);
  return Buffer.concat([...chunks, centralBuf, eocd]);
}

function crc32(buf) {
  let c = ~0;
  for (const byte of buf) {
    c ^= byte;
    for (let i = 0; i < 8; i++) c = (c >>> 1) ^ (0xedb88320 & -(c & 1));
  }
  return ~c >>> 0;
}

function withTempZip(files, fn) {
  const dir = fs.mkdtempSync(path.join(os.tmpdir(), 'cxp-auditor-'));
  const file = path.join(dir, 'artifact-1.4.0.zip');
  fs.writeFileSync(file, buildZip(files));
  try {
    return fn(file, dir);
  } finally {
    fs.rmSync(dir, { recursive: true, force: true });
  }
}

const WOO_PLUGIN = `<?php
/**
 * Plugin Name: Chilexpress Oficial
 * Version: 1.4.0
 * Requires PHP: 7.3
 * WC tested up to: 10.6.2
 */
define( 'CXP_VERSION', '1.4.0' );
add_action( 'plugins_loaded', 'cxp_boot' );
add_filter( 'woocommerce_shipping_methods', 'cxp_register' );
class Cxp_Shipping extends WC_Shipping_Method {}
`;

// ---------------------------------------------------------------------------
// Exit codes y estados
// ---------------------------------------------------------------------------
test('exit code por estado', () => {
  assert.equal(exitCodeFor(STATUS.PASS), EXIT_CODE.PASS);
  assert.equal(exitCodeFor(STATUS.FAIL), EXIT_CODE.FAIL);
  assert.equal(exitCodeFor(STATUS.ERROR, 'environment'), EXIT_CODE.ENVIRONMENT_ERROR);
  assert.equal(exitCodeFor(STATUS.ERROR, 'auditor'), EXIT_CODE.AUDITOR_ERROR);
  assert.equal(exitCodeFor(STATUS.SKIP), EXIT_CODE.SKIP);
  assert.equal(exitCodeFor(STATUS.UNKNOWN), EXIT_CODE.UNKNOWN);
});

test('un fatal nunca puede terminar en PASS ni en exit 0', () => {
  const sealed = finalizeResult({ STATUS: STATUS.PASS, FATAL_DETECTED: true, COMPATIBILITY: COMPATIBILITY.COMPATIBLE });
  assert.equal(sealed.STATUS, STATUS.FAIL);
  assert.equal(sealed.EXIT_CODE, EXIT_CODE.FAIL);
});

test('PASS exige el checklist completo de la seccion 55', () => {
  const complete = Object.fromEntries(PASS_CHECKLIST.map((key) => [key, true]));
  assert.equal(evaluatePass(complete).status, STATUS.PASS);
  const incomplete = { ...complete, no_fatals: false };
  const verdict = evaluatePass(incomplete);
  assert.equal(verdict.status, STATUS.FAIL);
  assert.ok(verdict.missing.includes('no_fatals'));
});

// ---------------------------------------------------------------------------
// ZIP, hash e inspección
// ---------------------------------------------------------------------------
test('el hash del artefacto se calcula y el ZIP no se altera', () => {
  withTempZip({ 'plugin/plugin.php': WOO_PLUGIN }, (file) => {
    const before = fs.readFileSync(file);
    const artifact = describeArtifact(file);
    assert.match(artifact.sha256, /^[a-f0-9]{64}$/);
    assert.deepEqual(fs.readFileSync(file), before);
  });
});

test('rutas con backslash se normalizan', () => {
  withTempZip({ 'plugin\\sub\\file.php': '<?php' }, (file) => {
    const zip = readZip(file);
    assert.equal(zip.entries[0].name, 'plugin/sub/file.php');
  });
});

test('detecta zip-slip como ruta peligrosa', () => {
  withTempZip({ '../evil.php': '<?php' }, (file) => {
    const zip = readZip(file);
    const inspection = inspectArtifact(zip, { name: 'x.zip', sha256: 'a', sizeBytes: 1, origin: 'official-zip' });
    assert.equal(inspection.PLUGIN_INSPECTION, 'FAIL');
    assert.ok(inspection.problems.some((p) => /peligrosas/i.test(p)));
  });
});

// ---------------------------------------------------------------------------
// Detección de plataforma
// ---------------------------------------------------------------------------
test('detecta WooCommerce con confianza', () => {
  withTempZip({ 'chilexpress/chilexpress.php': WOO_PLUGIN, 'chilexpress/readme.txt': 'Stable tag: 1.4.0' }, (file) => {
    const detection = detectPlatform(readZip(file));
    assert.equal(detection.platform, 'woocommerce');
    assert.equal(detection.confident, true);
  });
});

test('detecta Magento por module.xml y composer', () => {
  withTempZip({
    'mod/registration.php': '<?php \\Magento\\Framework\\Component\\ComponentRegistrar::register();',
    'mod/etc/module.xml': '<config><module name="Chilexpress_Shipping" setup_version="1.2.0"/></config>',
    'mod/composer.json': JSON.stringify({ name: 'chilexpress/shipping', type: 'magento2-module', version: '1.2.0' }),
  }, (file) => {
    const detection = detectPlatform(readZip(file));
    assert.equal(detection.platform, 'magento');
  });
});

test('detecta Shopify por shopify.app.toml', () => {
  withTempZip({
    'app/shopify.app.toml': 'api_version = "2025-01"\nscopes = "write_shipping,read_orders"',
    'app/package.json': JSON.stringify({ name: 'cxp-app', version: '2.0.0', dependencies: { '@shopify/shopify-app-remix': '^3' } }),
  }, (file) => {
    const detection = detectPlatform(readZip(file));
    assert.equal(detection.platform, 'shopify');
  });
});

test('sin senales suficientes devuelve UNKNOWN y no adivina', () => {
  withTempZip({ 'docs/readme.md': '# solo documentacion' }, (file) => {
    const detection = detectPlatform(readZip(file));
    assert.equal(detection.platform, 'UNKNOWN');
    assert.equal(detection.confident, false);
  });
});

test('se puede forzar la plataforma', () => {
  withTempZip({ 'docs/readme.md': '# nada' }, (file) => {
    const detection = detectPlatform(readZip(file), 'prestashop');
    assert.equal(detection.platform, 'prestashop');
    assert.equal(detection.forced, true);
  });
});

// ---------------------------------------------------------------------------
// Versión
// ---------------------------------------------------------------------------
test('la version sale del codigo, no solo del nombre', () => {
  withTempZip({ 'p/p.php': WOO_PLUGIN }, (file) => {
    const version = detectVersion(readZip(file), 'artifact-9.9.9.zip');
    assert.equal(version.version, '1.4.0');
    assert.equal(version.conflict, true, 'el nombre discrepa del codigo');
  });
});

test('fuentes de codigo contradictorias producen VERSION_CONFLICT', () => {
  withTempZip({
    'p/p.php': WOO_PLUGIN,
    'p/composer.json': JSON.stringify({ name: 'x/y', version: '2.0.0' }),
  }, (file) => {
    const version = detectVersion(readZip(file), 'artifact-1.4.0.zip');
    assert.equal(version.conflict, true);
    assert.equal(version.version, 'UNKNOWN');
  });
});

test('readme desactualizado es conflicto de documentacion, no de version', () => {
  withTempZip({ 'p/p.php': WOO_PLUGIN, 'p/readme.txt': 'Stable tag: 1.3.2\n' }, (file) => {
    const zip = readZip(file);
    const version = detectVersion(zip, 'artifact-1.4.0.zip');
    assert.equal(version.version, '1.4.0');
    assert.equal(version.conflict, false);
    const inspection = inspectArtifact(zip, { name: 'a.zip', sha256: 'a', sizeBytes: 1, origin: 'official-zip' });
    const conflicts = documentationConflicts(inspection, version);
    assert.equal(conflicts.DOCUMENTATION_CONFLICT, 'YES');
  });
});

// ---------------------------------------------------------------------------
// Regresión SR-108688
// ---------------------------------------------------------------------------
test('SR-108688 reproducido cuando aparece el marcador', () => {
  const outcome = evaluateSr({
    logExcerpt: 'PHP Fatal error: Uncaught Error: Class "Automattic\\WooCommerce\\Enums\\ProductTaxStatus" not found',
    wooVersion: '11.0.1',
    pluginActive: true,
  });
  assert.equal(outcome.outcome, 'REPRODUCED');
  assert.equal(outcome.status, 'FAIL');
});

test('SR-108688 corregido cuando el plugin queda activo sin fatal', () => {
  const outcome = evaluateSr({ logExcerpt: '', wooVersion: '11.0.1', pluginActive: true, httpProbes: [{ status: 200 }] });
  assert.equal(outcome.outcome, 'FIXED');
  assert.equal(outcome.status, 'PASS');
});

test('SR-108688 marca CHANGED ante otro fatal distinto', () => {
  const outcome = evaluateSr({ logExcerpt: 'PHP Fatal error: Uncaught Error: Call to undefined function foo()', wooVersion: '11.0.1', pluginActive: true });
  assert.equal(outcome.outcome, 'CHANGED');
  assert.equal(outcome.status, 'FAIL');
});

test('SR-108688 no aplica antes de WooCommerce 11', () => {
  const outcome = evaluateSr({ logExcerpt: '', wooVersion: '9.8.5', pluginActive: true });
  assert.equal(outcome.outcome, 'NOT_APPLICABLE');
  assert.equal(outcome.status, 'SKIP');
});

// ---------------------------------------------------------------------------
// Privacidad
// ---------------------------------------------------------------------------
test('la redaccion oculta secretos antes de la IA', () => {
  const { text, redactions } = redact(
    'ANTHROPIC_API_KEY=sk-ant-abcdef1234567890 y Authorization: Bearer zzz y correo juan@example.com',
  );
  assert.ok(!text.includes('sk-ant-abcdef1234567890'));
  assert.ok(!text.includes('juan@example.com'));
  assert.ok(redactions.length > 0);
});

// ---------------------------------------------------------------------------
// Matriz
// ---------------------------------------------------------------------------
test('la matriz reporta el peor resultado', () => {
  assert.equal(summarize([{ STATUS: 'PASS' }, { STATUS: 'FAIL' }]).worst, 'FAIL');
  assert.equal(summarize([{ STATUS: 'PASS' }, { STATUS: 'PASS' }]).worst, 'PASS');
  assert.equal(summarize([{ STATUS: 'SKIP' }, { STATUS: 'SKIP' }]).worst, 'SKIP');
});

console.log(`\n${passed} OK, ${failed} con error.`);
process.exitCode = failed ? 1 : 0;
