/**
 * Inspección del ZIP antes de Docker (§6) y lectura de documentación (§9).
 */
import { findEntries, findEntry, readText, rootFolder, unsafePaths } from './zip.mjs';

const CODE_LIMIT = 60;

function collectPhp(zip) {
  return findEntries(zip, (name) => name.endsWith('.php'));
}

function scanSymbols(text) {
  const namespaces = [...text.matchAll(/^\s*namespace\s+([^;{]+)[;{]/gm)].map((m) => m[1].trim());
  const classes = [...text.matchAll(/^\s*(?:final\s+|abstract\s+)?class\s+(\w+)/gm)].map((m) => m[1]);
  const interfaces = [...text.matchAll(/^\s*interface\s+(\w+)/gm)].map((m) => m[1]);
  const traits = [...text.matchAll(/^\s*trait\s+(\w+)/gm)].map((m) => m[1]);
  return { namespaces, classes, interfaces, traits };
}

function scanIntegration(text) {
  const hooks = [
    ...text.matchAll(/\badd_(?:action|filter)\s*\(\s*['"]([^'"]+)['"]/g),
    ...text.matchAll(/\$this->registerHook\s*\(\s*['"]([^'"]+)['"]/g),
  ].map((m) => m[1]);
  const ajax = [...text.matchAll(/wp_ajax(?:_nopriv)?_([a-z0-9_]+)/gi)].map((m) => m[1]);
  const urls = [...text.matchAll(/https?:\/\/[^\s'"<>)]+/g)].map((m) => m[0]);
  const includes = [...text.matchAll(/\b(?:require|include)(?:_once)?\s*[( ]\s*([^;]+);/g)].map((m) =>
    m[1].trim().slice(0, 160),
  );
  return { hooks, ajax, urls, includes };
}

function unique(list, limit = 200) {
  return [...new Set(list.filter(Boolean))].slice(0, limit);
}

function readJson(zip, predicate) {
  const entry = findEntry(zip, predicate);
  if (!entry) return null;
  try {
    return { name: entry.name, data: JSON.parse(readText(zip, entry, 200_000)) };
  } catch (error) {
    return { name: entry.name, data: null, error: String(error.message || error) };
  }
}

function extractRequirements(zip) {
  const requirements = {
    php: '',
    wordpress: '',
    woocommerce: '',
    prestashop: '',
    magento: '',
    node: '',
    shopifyApiVersion: '',
    raw: [],
  };

  for (const entry of collectPhp(zip).slice(0, CODE_LIMIT)) {
    const text = readText(zip, entry, 8000);
    const php = text.match(/^\s*\*\s*Requires PHP\s*:\s*(.+)$/im);
    const wp = text.match(/^\s*\*\s*Requires at least\s*:\s*(.+)$/im);
    const wc = text.match(/^\s*\*\s*WC requires at least\s*:\s*(.+)$/im);
    const wcTested = text.match(/^\s*\*\s*WC tested up to\s*:\s*(.+)$/im);
    if (php && !requirements.php) requirements.php = php[1].trim();
    if (wp && !requirements.wordpress) requirements.wordpress = wp[1].trim();
    if (wc && !requirements.woocommerce) requirements.woocommerce = wc[1].trim();
    if (wcTested) requirements.raw.push(`WC tested up to: ${wcTested[1].trim()}`);
  }

  const readme = findEntry(zip, (name) => name.endsWith('readme.txt'));
  if (readme) {
    const text = readText(zip, readme, 40_000);
    for (const key of ['Requires at least', 'Tested up to', 'Requires PHP', 'WC requires at least', 'WC tested up to']) {
      const match = text.match(new RegExp(`^\\s*${key}\\s*:\\s*(.+)$`, 'im'));
      if (match) requirements.raw.push(`${key}: ${match[1].trim()}`);
    }
  }

  const composer = readJson(zip, (name) => name.endsWith('composer.json'));
  if (composer?.data?.require) {
    const req = composer.data.require;
    if (req.php) requirements.php = requirements.php || req.php;
    for (const [pkg, constraint] of Object.entries(req)) {
      requirements.raw.push(`composer require ${pkg}: ${constraint}`);
      if (/magento\/(framework|product-community-edition)/.test(pkg)) requirements.magento = constraint;
      if (/prestashop/.test(pkg)) requirements.prestashop = constraint;
    }
  }

  const pkg = readJson(zip, (name) => name.endsWith('package.json'));
  if (pkg?.data?.engines?.node) requirements.node = pkg.data.engines.node;

  const toml = findEntry(zip, (name) => name.endsWith('shopify.app.toml'));
  if (toml) {
    const match = readText(zip, toml, 20_000).match(/api_version\s*=\s*"([^"]+)"/);
    if (match) requirements.shopifyApiVersion = match[1];
  }

  requirements.raw = unique(requirements.raw, 60);
  return requirements;
}

function extractDocumentation(zip) {
  const docs = findEntries(zip, (name) => /\.(md|txt|rst)$/.test(name) && !name.includes('/vendor/')).slice(0, 10);
  return docs.map((entry) => ({
    file: entry.name,
    excerpt: readText(zip, entry, 4000).replace(/\r/g, '').slice(0, 2000),
  }));
}

export function inspectArtifact(zip, artifact) {
  const php = collectPhp(zip);
  const symbols = { namespaces: [], classes: [], interfaces: [], traits: [] };
  const integration = { hooks: [], ajax: [], urls: [], includes: [] };

  for (const entry of php.slice(0, CODE_LIMIT)) {
    const text = readText(zip, entry, 120_000);
    const found = scanSymbols(text);
    symbols.namespaces.push(...found.namespaces);
    symbols.classes.push(...found.classes);
    symbols.interfaces.push(...found.interfaces);
    symbols.traits.push(...found.traits);
    const ints = scanIntegration(text);
    integration.hooks.push(...ints.hooks);
    integration.ajax.push(...ints.ajax);
    integration.urls.push(...ints.urls);
    integration.includes.push(...ints.includes);
  }

  const unsafe = unsafePaths(zip);
  const composer = readJson(zip, (name) => name.endsWith('composer.json'));
  const packageJson = readJson(zip, (name) => name.endsWith('package.json'));

  const problems = [];
  if (!zip.entries.length) problems.push('El ZIP no contiene entradas.');
  if (unsafe.length) problems.push(`Rutas peligrosas en el ZIP: ${unsafe.slice(0, 5).join(', ')}`);
  if (composer && composer.data === null) problems.push(`composer.json ilegible: ${composer.error}`);
  if (packageJson && packageJson.data === null) problems.push(`package.json ilegible: ${packageJson.error}`);

  return {
    PLUGIN_INSPECTION: problems.length ? 'FAIL' : 'PASS',
    problems,
    artifact: {
      name: artifact.name,
      sha256: artifact.sha256,
      sizeBytes: artifact.sizeBytes,
      origin: artifact.origin,
    },
    structure: {
      rootFolder: rootFolder(zip),
      entryCount: zip.entries.length,
      fileCount: zip.entries.filter((entry) => !entry.isDirectory).length,
      phpFiles: php.length,
      topLevel: unique(zip.entries.map((entry) => entry.name.split('/')[0]), 40),
      largest: [...zip.entries]
        .filter((entry) => !entry.isDirectory)
        .sort((a, b) => b.uncompressedSize - a.uncompressedSize)
        .slice(0, 10)
        .map((entry) => ({ name: entry.name, bytes: entry.uncompressedSize })),
    },
    symbols: {
      namespaces: unique(symbols.namespaces),
      classes: unique(symbols.classes),
      interfaces: unique(symbols.interfaces),
      traits: unique(symbols.traits),
    },
    integration: {
      hooks: unique(integration.hooks),
      ajaxActions: unique(integration.ajax),
      externalUrls: unique(integration.urls, 60),
      includes: unique(integration.includes, 60),
    },
    manifests: {
      composer: composer?.data ?? null,
      packageJson: packageJson?.data ?? null,
      hasModuleXml: Boolean(findEntry(zip, (name) => name.endsWith('etc/module.xml'))),
      hasRegistrationPhp: Boolean(findEntry(zip, (name) => name.endsWith('registration.php'))),
      hasConfigXml: Boolean(findEntry(zip, (name) => name.endsWith('config.xml'))),
      hasShopifyToml: Boolean(findEntry(zip, (name) => name.endsWith('shopify.app.toml'))),
    },
    requirements: extractRequirements(zip),
    documentation: extractDocumentation(zip),
  };
}

/**
 * Contradicciones documentación vs código/metadata (§9).
 */
export function documentationConflicts(inspection, version) {
  const conflicts = [];
  if (version.documentationVersion && version.version !== 'UNKNOWN' && version.documentationVersion !== version.version) {
    conflicts.push(
      `readme.txt declara Stable tag ${version.documentationVersion} y el codigo declara ${version.version}: la documentacion distribuida quedo desactualizada`,
    );
  }
  if (version.filenameVersion && version.version !== 'UNKNOWN' && version.filenameVersion !== version.version) {
    conflicts.push(`El nombre del ZIP indica ${version.filenameVersion} y el codigo declara ${version.version}`);
  }
  if (inspection.requirements.php && inspection.manifests.composer?.require?.php) {
    const header = inspection.requirements.php.replace(/[^\d.]/g, '');
    const composerPhp = String(inspection.manifests.composer.require.php).replace(/[^\d.]/g, '');
    if (header && composerPhp && !composerPhp.startsWith(header.split('.')[0])) {
      conflicts.push(`PHP requerido difiere: cabecera ${inspection.requirements.php} vs composer ${inspection.manifests.composer.require.php}`);
    }
  }
  return { DOCUMENTATION_CONFLICT: conflicts.length ? 'YES' : 'NO', conflicts };
}
