/**
 * Detección de versión (§8). El nombre del ZIP no basta: se cruzan varias
 * fuentes y, si discrepan, se marca VERSION_CONFLICT.
 */
import { findEntries, findEntry, readText } from './zip.mjs';

const SEMVER = /\b(\d+\.\d+(?:\.\d+)?(?:[-+][0-9A-Za-z.-]+)?)\b/;

function push(list, source, value) {
  const clean = String(value || '').trim();
  if (clean) list.push({ source, version: clean });
}

function fromFilename(name) {
  const match = name.match(/(\d+\.\d+(?:\.\d+)?)/);
  return match ? match[1] : '';
}

function fromPhpHeaders(zip) {
  const found = [];
  for (const entry of findEntries(zip, (name) => name.endsWith('.php')).slice(0, 40)) {
    const text = readText(zip, entry, 8000);
    const header = text.match(/^\s*\*\s*Version\s*:\s*(.+)$/im);
    if (header) push(found, `header:${entry.name}`, header[1]);
    const constant = text.match(/define\s*\(\s*['"][A-Z0-9_]*VERSION['"]\s*,\s*['"]([^'"]+)['"]/i);
    if (constant) push(found, `const:${entry.name}`, constant[1]);
  }
  return found;
}

function fromComposer(zip) {
  const found = [];
  const entry = findEntry(zip, (name) => name.endsWith('composer.json'));
  if (!entry) return found;
  try {
    const data = JSON.parse(readText(zip, entry, 50_000));
    push(found, 'composer.json', data.version);
  } catch {
    /* composer.json inválido: se reporta en la inspección, no aquí */
  }
  return found;
}

function fromPackageJson(zip) {
  const found = [];
  const entry = findEntry(zip, (name) => name.endsWith('package.json'));
  if (!entry) return found;
  try {
    const data = JSON.parse(readText(zip, entry, 50_000));
    push(found, 'package.json', data.version);
  } catch {
    /* ignorado a propósito */
  }
  return found;
}

function fromModuleXml(zip) {
  const found = [];
  const entry = findEntry(zip, (name) => name.endsWith('etc/module.xml'));
  if (!entry) return found;
  const match = readText(zip, entry, 20_000).match(/setup_version\s*=\s*"([^"]+)"/);
  if (match) push(found, 'module.xml', match[1]);
  return found;
}

function fromConfigXml(zip) {
  const found = [];
  const entry = findEntry(zip, (name) => name.endsWith('config.xml'));
  if (!entry) return found;
  const match = readText(zip, entry, 20_000).match(/<version>\s*<!\[CDATA\[([^\]]+)\]\]>\s*<\/version>|<version>([^<]+)<\/version>/);
  if (match) push(found, 'config.xml', match[1] || match[2]);
  return found;
}

function fromReadme(zip) {
  const found = [];
  const entry = findEntry(zip, (name) => name.endsWith('readme.txt'));
  if (!entry) return found;
  const text = readText(zip, entry, 20_000);
  const stable = text.match(/^\s*Stable tag\s*:\s*(.+)$/im);
  if (stable) push(found, 'readme.txt:stable-tag', stable[1]);
  return found;
}

function normalize(version) {
  const match = String(version).match(SEMVER);
  if (!match) return '';
  const parts = match[1].split(/[-+]/)[0].split('.');
  while (parts.length < 3) parts.push('0');
  return parts.slice(0, 3).join('.');
}

export function detectVersion(zip, artifactName) {
  const sources = [];
  push(sources, 'filename', fromFilename(artifactName));
  sources.push(...fromPhpHeaders(zip));
  sources.push(...fromComposer(zip));
  sources.push(...fromPackageJson(zip));
  sources.push(...fromModuleXml(zip));
  sources.push(...fromConfigXml(zip));
  sources.push(...fromReadme(zip));

  const normalized = sources
    .map((item) => ({ ...item, normalized: normalize(item.version) }))
    .filter((item) => item.normalized);

  const distinct = [...new Set(normalized.map((item) => item.normalized))];
  if (!distinct.length) {
    return { version: 'UNKNOWN', conflict: false, sources, distinct };
  }

  // El nombre del archivo solo desempata y el readme es documentación: la
  // versión la deciden las fuentes de código y metadata (§8). El desacuerdo del
  // readme se reporta aparte como DOCUMENTATION_CONFLICT (§9).
  const codeSources = normalized.filter(
    (item) => item.source !== 'filename' && !item.source.startsWith('readme.txt'),
  );
  const codeDistinct = [...new Set(codeSources.map((item) => item.normalized))];

  if (codeDistinct.length > 1) {
    return { version: 'UNKNOWN', conflict: true, sources, distinct: codeDistinct };
  }
  if (codeDistinct.length === 1) {
    const version = codeDistinct[0];
    const filename = normalized.find((item) => item.source === 'filename');
    const readme = normalized.find((item) => item.source.startsWith('readme.txt'));
    const conflict = Boolean(filename && filename.normalized !== version);
    return {
      version,
      conflict,
      sources,
      distinct,
      filenameVersion: filename?.normalized || '',
      documentationVersion: readme?.normalized || '',
    };
  }
  return { version: distinct[0], conflict: false, sources, distinct, onlyFilename: true };
}
