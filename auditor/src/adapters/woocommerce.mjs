/**
 * Adapter WooCommerce (§19). Único adapter con auditoría Docker real.
 *
 * Instala WordPress y WooCommerce en versiones fijas, instala el ZIP tal cual
 * llegó (montado solo lectura) y ejecuta rutas críticas capturando fatales.
 */
import fs from 'node:fs';
import path from 'node:path';
import {
  compose,
  containerHealthy,
  freePort,
  httpOk,
  imageDigest,
  run,
  waitFor,
} from '../core/docker.mjs';
import { AUDITOR_ROOT } from '../core/plans.mjs';
import { COMPATIBILITY, STATUS, SUPPORT_STATUS, evaluatePass } from '../core/result.mjs';
import { evaluate as evaluateSr108688 } from '../regression/sr-108688.mjs';

const PROJECT = 'cxp-auditor-woocommerce';
const COMPOSE_FILE = path.join(AUDITOR_ROOT, 'labs', 'woocommerce', 'docker-compose.yml');
const SITE_TITLE = 'Chilexpress Auditor Lab';

export const meta = {
  platform: 'woocommerce',
  displayName: 'WooCommerce',
  composeFile: COMPOSE_FILE,
  defaults: {
    wpImage: 'wordpress:6.8-php8.3-apache',
    wpCliImage: 'wordpress:cli-php8.3',
    dbImage: 'mariadb:11.4',
    wooVersion: '9.8.5',
    port: 8181,
  },
};

/**
 * WooCommerce declara un WordPress mínimo por serie mayor: instalar Woo 11
 * sobre WordPress 6.8 falla en el propio instalador. Se elige la imagen fija
 * correspondiente en vez de usar "latest" (§15).
 */
const WP_IMAGE_BY_WOO_MAJOR = {
  9: 'wordpress:6.8-php8.3-apache',
  10: 'wordpress:6.8-php8.3-apache',
  11: 'wordpress:6.9-php8.3-apache',
};

export function wordpressImageFor(wooVersion, override = '') {
  if (override) return override;
  const major = Number(String(wooVersion).split('.')[0]);
  return WP_IMAGE_BY_WOO_MAJOR[major] || meta.defaults.wpImage;
}

/**
 * Rango soportado declarado por el artefacto (§17). Sin dato explícito no se
 * inventa: UNKNOWN.
 */
export function supportStatus(inspection, platformVersion) {
  const tested = (inspection.requirements.raw || []).find((line) => /WC tested up to/i.test(line));
  const requires = inspection.requirements.woocommerce;
  const target = String(platformVersion || '').split('.').map(Number);
  if (!tested && !requires) {
    return { status: SUPPORT_STATUS.UNKNOWN, detail: 'El artefacto no declara compatibilidad con WooCommerce.' };
  }
  if (tested) {
    const max = tested.split(':')[1].trim().split('.').map(Number);
    const targetMajor = target[0] || 0;
    const maxMajor = max[0] || 0;
    const targetMinor = target[1] || 0;
    const maxMinor = max[1] || 0;
    if (targetMajor > maxMajor || (targetMajor === maxMajor && targetMinor > maxMinor)) {
      return {
        status: SUPPORT_STATUS.OUT_OF_SUPPORTED_RANGE,
        detail: `${tested.trim()} y se prueba WooCommerce ${platformVersion}.`,
      };
    }
    return { status: SUPPORT_STATUS.SUPPORTED, detail: `${tested.trim()} cubre WooCommerce ${platformVersion}.` };
  }
  return { status: SUPPORT_STATUS.UNKNOWN, detail: `Solo hay minimo declarado (${requires}).` };
}

function wp(args, options = {}) {
  return compose(PROJECT, COMPOSE_FILE, ['exec', '-T', 'wpcli', 'wp', '--allow-root', '--path=/var/www/html', ...args], {
    timeoutMs: options.timeoutMs || 180_000,
    env: options.env,
  });
}

async function listPlugins(runner) {
  const listing = await runner(['plugin', 'list', '--format=json', '--fields=name,status,version,file']);
  try {
    const parsed = JSON.parse(listing.stdout || '[]');
    return Array.isArray(parsed) ? parsed : [];
  } catch {
    return [];
  }
}

function record(tests, name, status, detail = '') {
  tests.push({ name, status, detail: String(detail).slice(0, 800) });
  return status === 'PASS';
}

export async function audit(context) {
  const { artifact, inspection, options, log } = context;
  const tests = [];
  const findings = [];
  const timings = [];
  const checklist = {
    artifact_valid: inspection.PLUGIN_INSPECTION === 'PASS',
    hash_recorded: Boolean(artifact.sha256),
    platform_identified: true,
    version_identified: context.pluginVersion !== 'UNKNOWN',
    requirements_met: false,
    docker_reproducible: false,
    platform_started: false,
    plugin_installed: false,
    plugin_initialized: false,
    critical_paths_executed: false,
    functional_tests_completed: false,
    no_fatals: false,
    no_uncaught_exceptions: false,
    regressions_passed: false,
    expected_result_confirmed: false,
  };

  const wooVersion = options.platformVersion || meta.defaults.wooVersion;
  const wpImage = wordpressImageFor(wooVersion, options.wpImage);
  const port = await freePort(Number(options.port || meta.defaults.port));
  const env = {
    AUDITOR_WP_IMAGE: wpImage,
    AUDITOR_WPCLI_IMAGE: options.wpCliImage || meta.defaults.wpCliImage,
    AUDITOR_DB_IMAGE: options.dbImage || meta.defaults.dbImage,
    AUDITOR_WP_PORT: String(port),
    AUDITOR_ARTIFACT_DIR: artifact.directory,
  };
  const baseUrl = `http://127.0.0.1:${port}`;
  const metadata = {
    DOCKER_IMAGE: wpImage,
    DOCKER_DIGEST: '',
    DATABASE_VERSION: env.AUDITOR_DB_IMAGE,
    RUNTIME: 'docker-compose',
    PHP_VERSION: '',
    NODE_VERSION: process.version,
    SITE_URL: baseUrl,
  };

  const cleanup = async () => {
    log('Destruyendo el laboratorio WooCommerce…');
    await compose(PROJECT, COMPOSE_FILE, ['down', '-v', '--remove-orphans'], { env, timeoutMs: 240_000 });
  };

  try {
    log('Levantando laboratorio WooCommerce aislado…');
    await compose(PROJECT, COMPOSE_FILE, ['down', '-v', '--remove-orphans'], { env, timeoutMs: 240_000 });
    const up = await compose(PROJECT, COMPOSE_FILE, ['up', '-d'], { env, timeoutMs: 900_000 });
    if (up.failed) {
      return {
        status: STATUS.ERROR,
        errorKind: 'environment',
        reason: `No se pudo levantar el laboratorio: ${up.stderr.trim().slice(0, 500)}`,
        tests,
        findings,
        metadata,
        checklist,
        logs: { compose: `${up.stdout}\n${up.stderr}` },
      };
    }
    checklist.docker_reproducible = true;

    const dbReady = await waitFor('DB READY', () => containerHealthy(PROJECT, 'db'), { timeoutMs: 240_000 });
    timings.push(dbReady);
    if (!dbReady.ok) throw Object.assign(new Error('La base de datos nunca quedo healthy.'), { kind: 'environment' });

    const platformReady = await waitFor('PLATFORM READY', () => httpOk(`${baseUrl}/`), { timeoutMs: 240_000 });
    timings.push(platformReady);
    if (!platformReady.ok) throw Object.assign(new Error('WordPress no respondio por HTTP.'), { kind: 'environment' });
    checklist.platform_started = true;

    metadata.DOCKER_DIGEST = await imageDigest(wpImage);

    const install = await wp([
      'core',
      'install',
      `--url=${baseUrl}`,
      `--title=${SITE_TITLE}`,
      '--admin_user=auditor',
      '--admin_password=auditor-lab',
      '--admin_email=auditor@example.invalid',
      '--skip-email',
    ]);
    record(tests, 'WordPress instalado', install.failed ? 'FAIL' : 'PASS', install.failed ? install.stderr : install.stdout);
    if (install.failed) throw Object.assign(new Error('No se pudo instalar WordPress.'), { kind: 'environment' });

    const wpVersion = (await wp(['core', 'version'])).stdout.trim();
    const phpVersion = (await wp(['eval', 'echo PHP_VERSION;'])).stdout.trim();
    metadata.PHP_VERSION = phpVersion;

    const woo = await wp(['plugin', 'install', 'woocommerce', `--version=${wooVersion}`, '--activate'], {
      timeoutMs: 300_000,
    });
    record(tests, `WooCommerce ${wooVersion} instalado`, woo.failed ? 'FAIL' : 'PASS', woo.failed ? woo.stderr : 'ok');
    if (woo.failed) throw Object.assign(new Error(`No se pudo instalar WooCommerce ${wooVersion}.`), { kind: 'environment' });

    const realWoo = (await wp(['plugin', 'get', 'woocommerce', '--field=version'])).stdout.trim();
    record(
      tests,
      'Version de WooCommerce verificada',
      realWoo === wooVersion ? 'PASS' : 'FAIL',
      `solicitada ${wooVersion} / real ${realWoo}`,
    );

    const support = supportStatus(inspection, realWoo || wooVersion);
    checklist.requirements_met = support.status !== SUPPORT_STATUS.UNSUPPORTED;
    findings.push(`SUPPORT_STATUS: ${support.status} — ${support.detail}`);

    // El artefacto se instala tal cual: el ZIP se monta solo lectura en /artifact.
    log('Instalando el artefacto desde el ZIP original…');
    const before = await listPlugins(wp);
    const pluginInstall = await wp(['plugin', 'install', `/artifact/${artifact.name}`, '--force'], {
      timeoutMs: 300_000,
    });
    const installedOk = !pluginInstall.failed;
    checklist.plugin_installed = installedOk;
    record(
      tests,
      'Artefacto instalado desde el ZIP',
      installedOk ? 'PASS' : 'FAIL',
      installedOk ? pluginInstall.stdout.trim().slice(0, 300) : pluginInstall.stderr.trim(),
    );

    // El slug se deduce por diferencia entre el inventario previo y el posterior,
    // no por heuristica sobre el nombre del ZIP.
    let slug = '';
    let pluginFile = '';
    if (installedOk) {
      const after = await listPlugins(wp);
      const known = new Set(before.map((plugin) => plugin.name));
      const appeared = after.filter((plugin) => !known.has(plugin.name));
      let entry = appeared[0];
      if (!entry) {
        const baseline = new Set(['woocommerce', 'akismet', 'hello', 'hello-dolly']);
        entry = after.find((plugin) => !baseline.has(plugin.name));
      }
      slug = entry?.name || '';
      pluginFile = entry?.file || '';
      record(
        tests,
        'Slug del plugin identificado',
        slug ? 'PASS' : 'FAIL',
        slug || `inventario posterior: ${after.map((p) => p.name).join(', ')}`,
      );
    }

    let activation = { failed: true, stdout: '', stderr: 'no se intento activar' };
    if (slug) {
      activation = await wp(['plugin', 'activate', slug], { timeoutMs: 180_000 });
      // Un ZIP con carpeta anidada produce un slug que wp-cli no resuelve por
      // nombre. Se reintenta con la ruta real usando la API de WordPress.
      if (activation.failed && pluginFile) {
        const fallback = await wp(
          [
            'eval',
            `require_once ABSPATH . 'wp-admin/includes/plugin.php'; $r = activate_plugin('${pluginFile.replace(/'/g, "\\'")}'); echo is_wp_error($r) ? 'ERROR:' . $r->get_error_message() : 'OK';`,
          ],
          { timeoutMs: 180_000 },
        );
        const ok = !fallback.failed && fallback.stdout.includes('OK');
        activation = {
          failed: !ok,
          stdout: fallback.stdout,
          stderr: ok ? '' : `${activation.stderr}\n${fallback.stdout}${fallback.stderr}`,
        };
      }
      checklist.plugin_initialized = !activation.failed;
      record(
        tests,
        `Plugin ${slug} activado`,
        activation.failed ? 'FAIL' : 'PASS',
        activation.failed ? activation.stderr.trim() : 'activo',
      );
    }

    const pluginVersion = slug
      ? (await wp(['plugin', 'get', slug, '--field=version'])).stdout.trim() || context.pluginVersion
      : context.pluginVersion;
    const activeList = await listPlugins(wp);
    const pluginActive = Boolean(
      slug && activeList.find((plugin) => plugin.name === slug && plugin.status === 'active'),
    );

    log('Ejecutando rutas criticas…');
    const probes = [];
    for (const route of ['/', '/wp-admin/admin-ajax.php', '/?wc-ajax=get_refreshed_fragments']) {
      const response = await fetch(`${baseUrl}${route}`, { redirect: 'manual' }).catch((error) => ({
        status: 0,
        text: async () => String(error.message || error),
      }));
      const body = (await response.text()).slice(0, 4000);
      probes.push({ url: route, status: response.status, bodyExcerpt: body });
      record(
        tests,
        `Ruta critica ${route}`,
        response.status && response.status < 500 ? 'PASS' : 'FAIL',
        `HTTP ${response.status}`,
      );
    }
    checklist.critical_paths_executed = true;
    checklist.functional_tests_completed = true;

    const logRead = await compose(
      PROJECT,
      COMPOSE_FILE,
      ['exec', '-T', 'wordpress', 'sh', '-lc', 'cat /var/www/html/wp-content/debug.log 2>/dev/null || true'],
      { env, timeoutMs: 60_000 },
    );
    const logExcerpt = logRead.stdout.slice(-20_000);

    const activationOutput = `${activation.stdout}\n${activation.stderr}`;
    const haystack = [logExcerpt, activationOutput, probes.map((p) => p.bodyExcerpt).join('\n')].join('\n');
    const fatalMatch = haystack.match(/PHP Fatal error:\s*(.+)/i) || haystack.match(/Uncaught Error:\s*(.+)/i);
    // Archivo y linea solo tienen sentido si pertenecen al fatal: buscarlos en
    // todo el log capturaria avisos de deprecacion sin relacion.
    const fatalContext = fatalMatch ? haystack.slice(haystack.indexOf(fatalMatch[0]), haystack.indexOf(fatalMatch[0]) + 2000) : '';
    const classMatch = fatalContext.match(/Class ["']([^"']+)["'] not found/i);
    const fileMatch = fatalContext.match(/ in (\/[^\s:]+\.php)(?::| on line )(\d+)/i);

    const fatalDetected = Boolean(fatalMatch) || probes.some((probe) => probe.status >= 500);
    checklist.no_fatals = !fatalDetected;
    checklist.no_uncaught_exceptions = !/Uncaught (Error|Exception)/i.test(haystack);
    record(tests, 'Sin fatales PHP', fatalDetected ? 'FAIL' : 'PASS', fatalMatch ? fatalMatch[1].slice(0, 300) : 'ninguno');

    const regression = evaluateSr108688({
      logExcerpt,
      activationOutput,
      httpProbes: probes,
      wooVersion: realWoo || wooVersion,
      pluginActive,
    });
    record(tests, `Regresion ${regression.id}`, regression.status, `${regression.outcome} — ${regression.detail}`);
    checklist.regressions_passed = regression.status !== 'FAIL';
    findings.push(`Regresion ${regression.id}: ${regression.outcome}. ${regression.detail}`);

    checklist.expected_result_confirmed = checklist.no_fatals && checklist.regressions_passed && pluginActive;

    const verdict = evaluatePass(checklist);
    const compatibility = fatalDetected
      ? COMPATIBILITY.INCOMPATIBLE
      : verdict.status === STATUS.PASS
        ? COMPATIBILITY.COMPATIBLE
        : COMPATIBILITY.PARTIAL;

    const recommendations = [];
    if (regression.outcome === 'REPRODUCED') {
      recommendations.push('Desactivar el plugin antes de actualizar WooCommerce y reactivarlo al terminar.');
      recommendations.push('El fabricante debe inicializar en woocommerce_loaded en lugar de plugins_loaded.');
    }
    if (support.status === SUPPORT_STATUS.OUT_OF_SUPPORTED_RANGE) {
      recommendations.push('La combinacion esta fuera del rango declarado por el artefacto: tratar como prueba experimental.');
    }

    return {
      status: verdict.status,
      reason: verdict.reason,
      compatibility,
      supportStatus: support.status,
      supportDetail: support.detail,
      platformVersion: realWoo || wooVersion,
      wordpressVersion: wpVersion,
      pluginVersion: pluginVersion || context.pluginVersion,
      fatalDetected,
      errorClass: classMatch ? classMatch[1] : '',
      errorFile: fileMatch ? fileMatch[1] : '',
      errorLine: fileMatch ? Number(fileMatch[2]) : null,
      tests,
      findings,
      recommendations,
      regressions: [regression],
      timings,
      metadata,
      checklist,
      logs: {
        'debug.log': logExcerpt,
        'activation.txt': activationOutput,
        'probes.json': JSON.stringify(probes, null, 2),
      },
      stackTrace: fatalMatch ? haystack.slice(Math.max(0, haystack.indexOf(fatalMatch[0]) - 200), 4000) : '',
      logExcerpt,
    };
  } catch (error) {
    return {
      status: STATUS.ERROR,
      errorKind: error.kind || 'environment',
      reason: String(error.message || error),
      tests,
      findings,
      metadata,
      checklist,
      logs: {},
    };
  } finally {
    if (!options.keepLab) {
      await cleanup();
    } else {
      log(`Laboratorio conservado en ${baseUrl} (--keep-lab).`);
    }
  }
}

export function labReadme() {
  return fs.existsSync(COMPOSE_FILE) ? COMPOSE_FILE : '';
}
