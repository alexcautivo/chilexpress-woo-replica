#!/usr/bin/env node
/**
 * CLI del auditor multiplataforma Chilexpress.
 * Modos: INSPECT, PLAN, AUDIT, REGRESSION, MATRIX, FIX, FULL (§18).
 */
import fs from 'node:fs';
import path from 'node:path';
import readline from 'node:readline/promises';
import { stdin, stdout } from 'node:process';
import { loadAiConfig, publicAiConfig, saveAiConfig, ANALYSIS_LEVELS } from '../src/core/config.mjs';
import { compose, dockerAvailable } from '../src/core/docker.mjs';
import { defaultVersionsFor, parseVersions, renderTable, summarize } from '../src/core/matrix.mjs';
import { AUDITOR_ROOT } from '../src/core/plans.mjs';
import { getAdapter, listAdapters } from '../src/core/registry.mjs';
import { createReportDir, writeJson, REPORTS_DIR } from '../src/core/report.mjs';
import { EXIT_CODE, newAuditId } from '../src/core/result.mjs';
import { runAudit, runInspect, runPlan } from '../src/core/pipeline.mjs';
import { copyForWork, describeArtifact, writeOrigin } from '../src/core/artifact.mjs';

const HELP = `Auditor multiplataforma Chilexpress

Uso:
  auditor inspect <zip> [--platform=<p>]           Analiza el ZIP. No usa Docker.
  auditor plan <zip> [--platform=<p>]              Que deberia probarse. No modifica nada.
  auditor audit <zip> [opciones]                   Pruebas reales en Docker.
  auditor regression <zip> [opciones]              Ejecuta los casos conocidos.
  auditor matrix <zip> [--versions=a,b,c]          Prueba varias versiones.
  auditor fix <zip> --with=<zip-corregido>         Compara original vs corregido (copias).
  auditor full <zip> [opciones]                    INSPECT + PLAN + AUDIT + REGRESSION + MATRIX + REPORT.

  auditor pack <carpeta> [--out=<zip>]             Empaqueta un arbol local como artefacto de prueba.
  auditor lab up|down <plataforma>                 Controla un laboratorio Docker.
  auditor ai configure|status                      Configuracion de IA opcional.
  auditor platforms                                Lista adapters disponibles.

Opciones:
  --platform=<woocommerce|prestashop|magento|shopify>
  --platform-version=<x.y.z>       Version de plataforma a probar
  --versions=<a,b,c>               Matriz de versiones
  --keep-lab                       No destruir el entorno al terminar
  --json                           Salida JSON del resultado
  --quiet                          Menos texto

Exit codes: 0 PASS · 1 FAIL · 2 ERROR entorno · 3 ERROR auditor · 4 SKIP · 5 UNKNOWN
`;

function parseArgs(argv) {
  const positional = [];
  const flags = {};
  for (const arg of argv) {
    if (arg.startsWith('--')) {
      const [key, value] = arg.slice(2).split('=');
      flags[key] = value === undefined ? true : value;
    } else {
      positional.push(arg);
    }
  }
  return { positional, flags };
}

function makeLogger(quiet) {
  return (message) => {
    if (!quiet) console.log(`[auditor] ${message}`);
  };
}

function printResult(result, dir, flags) {
  if (flags.json) {
    console.log(JSON.stringify(result, null, 2));
  } else {
    console.log('');
    console.log(fs.readFileSync(path.join(dir, 'report.txt'), 'utf8'));
    console.log(`Reporte completo: ${dir}`);
  }
}

function optionsFrom(flags, log) {
  return {
    platform: typeof flags.platform === 'string' ? flags.platform : '',
    platformVersion: typeof flags['platform-version'] === 'string' ? flags['platform-version'] : '',
    port: flags.port,
    keepLab: Boolean(flags['keep-lab']),
    wpImage: typeof flags['wp-image'] === 'string' ? flags['wp-image'] : '',
    log,
  };
}

async function cmdInspect(positional, flags) {
  const { result, dir } = await runInspect(requireZip(positional), optionsFrom(flags, makeLogger(flags.quiet)));
  printResult(result, dir, flags);
  return result.EXIT_CODE;
}

async function cmdPlan(positional, flags) {
  const { result, dir } = await runPlan(requireZip(positional), {
    ...optionsFrom(flags, makeLogger(flags.quiet)),
    versions: flags.versions ? parseVersions(flags.versions, flags.platform) : undefined,
  });
  printResult(result, dir, flags);
  return result.EXIT_CODE;
}

async function cmdAudit(positional, flags, mode = 'AUDIT') {
  const docker = await dockerAvailable();
  if (!docker.ok) {
    console.error('[auditor] Docker no esta disponible. La auditoria real lo requiere.');
    return EXIT_CODE.ENVIRONMENT_ERROR;
  }
  const { result, dir } = await runAudit(requireZip(positional), {
    ...optionsFrom(flags, makeLogger(flags.quiet)),
    mode,
  });
  printResult(result, dir, flags);
  return result.EXIT_CODE;
}

async function cmdRegression(positional, flags) {
  return cmdAudit(positional, flags, 'REGRESSION');
}

async function cmdMatrix(positional, flags) {
  const zip = requireZip(positional);
  const log = makeLogger(flags.quiet);
  const docker = await dockerAvailable();
  if (!docker.ok) {
    console.error('[auditor] Docker no esta disponible. MATRIX lo requiere.');
    return EXIT_CODE.ENVIRONMENT_ERROR;
  }

  const probe = await runInspect(zip, optionsFrom(flags, () => {}));
  const platform = typeof flags.platform === 'string' && flags.platform ? flags.platform : probe.result.PLATFORM;
  if (platform === 'UNKNOWN') {
    console.error('[auditor] PLATFORM=UNKNOWN: no se puede construir la matriz. Use --platform=.');
    return EXIT_CODE.UNKNOWN;
  }

  const versions = flags.versions ? parseVersions(flags.versions, platform) : defaultVersionsFor(platform);
  const matrixId = newAuditId();
  const rows = [];
  for (const version of versions) {
    log(`Matriz: ${platform} ${version} × plugin ${probe.result.PLUGIN_VERSION}`);
    const { result } = await runAudit(zip, {
      ...optionsFrom(flags, log),
      platform,
      platformVersion: version,
      mode: 'MATRIX',
    });
    rows.push(result);
  }

  const summary = summarize(rows);
  const dir = createReportDir(matrixId);
  writeJson(dir, 'result.json', { AUDIT_ID: matrixId, MODE: 'MATRIX', platform, rows, summary });
  fs.writeFileSync(path.join(dir, 'report.txt'), `# MATRIZ DE COMPATIBILIDAD\n\n${renderTable(rows)}\n\nPeor resultado: ${summary.worst}\n`);

  console.log('');
  console.log(renderTable(rows));
  console.log('');
  console.log(`Peor resultado: ${summary.worst}`);
  console.log(`Reporte: ${dir}`);
  return rows.reduce((worst, row) => Math.max(worst, row.EXIT_CODE), 0);
}

async function cmdFix(positional, flags) {
  const original = requireZip(positional);
  if (typeof flags.with !== 'string') {
    console.error('[auditor] fix requiere --with=<zip-corregido>.');
    return EXIT_CODE.AUDITOR_ERROR;
  }
  const auditId = newAuditId();
  const dir = createReportDir(auditId);

  // Nunca se modifica ninguno de los dos ZIP: se trabaja sobre copias (§18, §48).
  const originalArtifact = describeArtifact(original);
  const fixedArtifact = describeArtifact(flags.with);
  copyForWork(originalArtifact, path.join(dir, 'artifacts'));
  copyForWork(fixedArtifact, path.join(dir, 'artifacts'));

  const a = await runInspect(original, { ...optionsFrom(flags, () => {}), auditId: `${auditId}-A` });
  const b = await runInspect(flags.with, { ...optionsFrom(flags, () => {}), auditId: `${auditId}-B` });

  const filesA = new Set(a.phase.zip.entries.map((entry) => entry.name));
  const filesB = new Set(b.phase.zip.entries.map((entry) => entry.name));
  const comparison = {
    AUDIT_ID: auditId,
    MODE: 'FIX',
    original: { name: originalArtifact.name, sha256: originalArtifact.sha256, version: a.result.PLUGIN_VERSION },
    corrected: { name: fixedArtifact.name, sha256: fixedArtifact.sha256, version: b.result.PLUGIN_VERSION },
    identical: originalArtifact.sha256 === fixedArtifact.sha256,
    onlyInOriginal: [...filesA].filter((name) => !filesB.has(name)).slice(0, 200),
    onlyInCorrected: [...filesB].filter((name) => !filesA.has(name)).slice(0, 200),
    classesAdded: b.phase.inspection.symbols.classes.filter((c) => !a.phase.inspection.symbols.classes.includes(c)),
    hooksAdded: b.phase.inspection.integration.hooks.filter((h) => !a.phase.inspection.integration.hooks.includes(h)),
    hooksRemoved: a.phase.inspection.integration.hooks.filter((h) => !b.phase.inspection.integration.hooks.includes(h)),
    note: 'La aceptacion final depende de AUDIT, REGRESSION y MATRIX, no de este diff.',
  };
  writeJson(dir, 'result.json', comparison);
  fs.writeFileSync(
    path.join(dir, 'report.txt'),
    `# COMPARACION ORIGINAL / CORREGIDO\n\n${JSON.stringify(comparison, null, 2)}\n`,
  );
  console.log(JSON.stringify(comparison, null, 2));
  console.log(`\nReporte: ${dir}`);
  return comparison.identical ? EXIT_CODE.UNKNOWN : EXIT_CODE.PASS;
}

async function cmdFull(positional, flags) {
  const zip = requireZip(positional);
  const log = makeLogger(flags.quiet);
  log('FULL: INSPECT');
  const inspect = await runInspect(zip, optionsFrom(flags, log));
  if (inspect.result.EXIT_CODE !== EXIT_CODE.PASS) {
    printResult(inspect.result, inspect.dir, flags);
    return inspect.result.EXIT_CODE;
  }
  log('FULL: PLAN');
  await runPlan(zip, optionsFrom(flags, log));
  log('FULL: AUDIT + REGRESSION + MATRIX');
  return cmdMatrix(positional, flags);
}

async function cmdPack(positional, flags) {
  const source = positional[0];
  if (!source || !fs.existsSync(source)) {
    console.error('[auditor] pack requiere una carpeta existente.');
    return EXIT_CODE.AUDITOR_ERROR;
  }
  const folder = path.resolve(source);
  const name = typeof flags.out === 'string' ? flags.out : `${path.basename(folder)}-local.zip`;
  const target = path.isAbsolute(name) ? name : path.join(AUDITOR_ROOT, 'artifacts', 'incoming', name);
  fs.mkdirSync(path.dirname(target), { recursive: true });

  const { packFolder } = await import('../src/core/pack.mjs');
  let packed;
  try {
    packed = packFolder(folder, target);
  } catch (error) {
    console.error(`[auditor] No se pudo empaquetar: ${error.message}`);
    return EXIT_CODE.AUDITOR_ERROR;
  }
  // Se marca el origen: nunca debe confundirse con un release oficial (§4).
  writeOrigin(target, 'locally-packed');
  const artifact = describeArtifact(target);
  console.log(`ARTIFACT=${artifact.name}`);
  console.log(`FILES=${packed.fileCount}`);
  console.log(`ROOT=${packed.rootName}`);
  console.log(`SHA256=${artifact.sha256}`);
  console.log(`ORIGIN=${artifact.origin}`);
  console.log(`PATH=${artifact.path}`);
  return EXIT_CODE.PASS;
}

async function cmdLab(positional, flags) {
  const [action, platform] = positional;
  if (!['up', 'down'].includes(action) || !platform) {
    console.error('[auditor] Uso: auditor lab up|down <plataforma>');
    return EXIT_CODE.AUDITOR_ERROR;
  }
  const adapter = getAdapter(platform);
  const project = `cxp-auditor-${platform}`;
  const args = action === 'up' ? ['up', '-d'] : ['down', '-v', '--remove-orphans'];
  const result = await compose(project, adapter.meta.composeFile, args, {
    timeoutMs: 900_000,
    onData: (chunk) => {
      if (!flags.quiet) process.stdout.write(chunk);
    },
  });
  return result.failed ? EXIT_CODE.ENVIRONMENT_ERROR : EXIT_CODE.PASS;
}

async function cmdAi(positional, flags) {
  const [action] = positional;
  if (action === 'status' || !action) {
    console.log(JSON.stringify(publicAiConfig(), null, 2));
    return EXIT_CODE.PASS;
  }
  if (action !== 'configure') {
    console.error('[auditor] Uso: auditor ai configure|status');
    return EXIT_CODE.AUDITOR_ERROR;
  }

  const current = loadAiConfig();
  const rl = readline.createInterface({ input: stdin, output: stdout });
  console.log('Chilexpress Auditor — AI Configuration');
  console.log('La API key no se muestra nunca despues de guardarla.\n');

  const provider = (await rl.question(`Provider [${current.provider}]: `)).trim() || current.provider;
  const model = (await rl.question(`Model [${current.model}]: `)).trim() || current.model;
  const level = (await rl.question(`Analysis level ${ANALYSIS_LEVELS.join('/')} [${current.analysisLevel}]: `))
    .trim()
    .toUpperCase() || current.analysisLevel;
  const enabledRaw = (await rl.question(`Enable AI? (yes/no) [${current.enabled ? 'yes' : 'no'}]: `)).trim().toLowerCase();
  const apiKey = (await rl.question('API Key (enter para conservar la actual): ')).trim();
  const save = (await rl.question('Save configuration? (yes/no) [yes]: ')).trim().toLowerCase();
  rl.close();

  if (save === 'no' || save === 'n') {
    console.log('Sin cambios.');
    return EXIT_CODE.PASS;
  }

  const update = {
    provider: provider.toLowerCase(),
    model,
    analysisLevel: ANALYSIS_LEVELS.includes(level) ? level : current.analysisLevel,
    enabled: enabledRaw ? ['yes', 'y', 'si', 'true', '1'].includes(enabledRaw) : current.enabled,
  };
  if (apiKey) update.apiKey = apiKey;

  const file = saveAiConfig(update);
  console.log(`\nConfiguracion guardada en ${file} (fuera de git).`);
  console.log(JSON.stringify(publicAiConfig(loadAiConfig()), null, 2));
  return EXIT_CODE.PASS;
}

function cmdPlatforms() {
  for (const adapter of listAdapters()) {
    console.log(`${adapter.platform.padEnd(14)}${adapter.displayName.padEnd(14)}${adapter.composeFile}`);
  }
  console.log(`\nReportes: ${REPORTS_DIR}`);
  return EXIT_CODE.PASS;
}

function requireZip(positional) {
  const zip = positional[0];
  if (!zip) {
    throw Object.assign(new Error('Falta la ruta del ZIP.'), { usage: true });
  }
  return zip;
}

async function main() {
  const [, , command, ...rest] = process.argv;
  const { positional, flags } = parseArgs(rest);

  if (!command || flags.help || command === 'help') {
    console.log(HELP);
    return EXIT_CODE.PASS;
  }

  switch (command) {
    case 'inspect':
      return cmdInspect(positional, flags);
    case 'plan':
      return cmdPlan(positional, flags);
    case 'audit':
      return cmdAudit(positional, flags);
    case 'regression':
      return cmdRegression(positional, flags);
    case 'matrix':
      return cmdMatrix(positional, flags);
    case 'fix':
      return cmdFix(positional, flags);
    case 'full':
      return cmdFull(positional, flags);
    case 'pack':
      return cmdPack(positional, flags);
    case 'lab':
      return cmdLab(positional, flags);
    case 'ai':
      return cmdAi(positional, flags);
    case 'platforms':
      return cmdPlatforms();
    default:
      console.error(`[auditor] Comando desconocido: ${command}\n`);
      console.log(HELP);
      return EXIT_CODE.AUDITOR_ERROR;
  }
}

main()
  .then((code) => {
    process.exitCode = typeof code === 'number' ? code : EXIT_CODE.UNKNOWN;
  })
  .catch((error) => {
    if (error.usage) {
      console.error(`[auditor] ${error.message}\n`);
      console.log(HELP);
      process.exitCode = EXIT_CODE.AUDITOR_ERROR;
      return;
    }
    console.error(`[auditor] Error del auditor: ${error.message}`);
    process.exitCode = EXIT_CODE.AUDITOR_ERROR;
  });
