import fs from 'node:fs';
import path from 'node:path';
import process from 'node:process';

const root = path.resolve(import.meta.dirname, '..');
const base = process.env.CXP_BASE || 'http://127.0.0.1:8080';
const failures = [];

function check(condition, message) {
  if (condition) {
    console.log(`OK  ${message}`);
  } else {
    failures.push(message);
    console.error(`ERR ${message}`);
  }
}

function readJson(relative) {
  return JSON.parse(fs.readFileSync(path.join(root, relative), 'utf8'));
}

const schema = readJson('incidents/schema/incident.schema.json');
const template = readJson('incidents/templates/para-el-cliente.json');
const reference = readJson('incidents/tickets/SR-108688.json');
const example = readJson('incidents/tickets/_EJEMPLO-1.1.json');

check(schema.$schema?.includes('2020-12'), 'schema usa JSON Schema 2020-12');
check(template.schema_version === '1.1', 'plantilla usa schema 1.1');
check(reference.schema_version === '1.1', 'SR-108688 migrado a 1.1');
check(example.schema_version === '1.1', 'ejemplo multi-cliente usa 1.1');

for (const [name, ticket] of [
  ['plantilla', template],
  ['SR-108688', reference],
  ['ejemplo', example],
]) {
  check(Array.isArray(ticket.plugins), `${name}: plugins es array`);
  for (const [index, plugin] of (ticket.plugins || []).entries()) {
    check(Boolean(plugin.slug), `${name}: plugin ${index + 1} tiene slug`);
    check(Boolean(plugin.version), `${name}: plugin ${index + 1} tiene versión`);
    check(
      ['wordpress.org', 'zip_local', 'repo'].includes(plugin.fuente),
      `${name}: plugin ${index + 1} tiene fuente autorizada`,
    );
  }
  check(
    Array.isArray(ticket.flujo_reproduccion?.steps),
    `${name}: flujo_reproduccion.steps existe`,
  );
}

const allowed = new Set([
  'request',
  'open_url',
  'post_ajax',
  'activate_plugin',
  'deactivate_plugin',
  'clear_cache',
  'wait',
  'assert_http',
  'assert_text',
  'assert_log',
  'run_internal_scenario',
]);
for (const ticket of [reference, example]) {
  for (const step of ticket.flujo_reproduccion.steps) {
    check(allowed.has(step.op), `${ticket.ticket_id}: acción segura ${step.op}`);
  }
}

try {
  const jar = new Map();
  function absorb(response) {
    const values = typeof response.headers.getSetCookie === 'function'
      ? response.headers.getSetCookie()
      : (response.headers.get('set-cookie') ? [response.headers.get('set-cookie')] : []);
    for (const value of values) {
      const pair = value.split(';')[0];
      const at = pair.indexOf('=');
      if (at > 0) jar.set(pair.slice(0, at), pair.slice(at + 1));
    }
  }
  function cookieHeader() {
    return [...jar.entries()].map(([key, value]) => `${key}=${value}`).join('; ');
  }

  const loginPage = await fetch(`${base}/wp-login.php`, { redirect: 'manual' });
  absorb(loginPage);
  const loginBody = new URLSearchParams({
    log: 'admin',
    pwd: 'admin',
    'wp-submit': 'Log In',
    redirect_to: `${base}/`,
    testcookie: '1',
  });
  const loginResponse = await fetch(`${base}/wp-login.php`, {
    method: 'POST',
    redirect: 'manual',
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded',
      Cookie: cookieHeader(),
    },
    body: loginBody,
  });
  absorb(loginResponse);

  const response = await fetch(`${base}/`, { headers: { Cookie: cookieHeader() } });
  const html = await response.text();
  absorb(response);
  check(response.status === 200, 'WordPress responde HTTP 200');
  check(html.includes('Aplicar pila'), 'la consola muestra Aplicar pila');
  check(html.includes('PDF técnico'), 'la consola muestra PDF técnico');
  check(html.includes('Actualizar WordPress + plugins públicos a latest'), 'la consola muestra actualización global');

  const nonceMatch = html.match(/var runnerNonce = (\"[^\"]+\")/);
  check(Boolean(nonceMatch), 'la consola publica nonce del runner');
  if (nonceMatch) {
    const nonce = JSON.parse(nonceMatch[1]);
    const body = new URLSearchParams({
      action: 'cxp_incident_preview',
      nonce,
      id: 'SR-108688',
    });
    const previewResponse = await fetch(`${base}/wp-admin/admin-ajax.php`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
        ...(cookieHeader() ? { Cookie: cookieHeader() } : {}),
      },
      body,
    });
    const preview = await previewResponse.json();
    check(preview.success === true, 'endpoint Vista previa responde correctamente');
    check(preview.data?.valid === true, 'SR-108688 pasa validación del runner');
    check(Array.isArray(preview.data?.changes), 'Vista previa devuelve diferencias de pila');

    for (const type of ['client', 'technical']) {
      const pdfUrl = new URL(`${base}/wp-admin/admin-ajax.php`);
      pdfUrl.search = new URLSearchParams({
        action: 'cxp_incident_pdf',
        nonce,
        id: 'SR-108688',
        run_id: 'test-render',
        type,
      });
      const pdfResponse = await fetch(pdfUrl, { headers: { Cookie: cookieHeader() } });
      const pdf = Buffer.from(await pdfResponse.arrayBuffer());
      check(pdfResponse.status === 200, `PDF ${type} responde HTTP 200`);
      check(pdf.subarray(0, 4).toString() === '%PDF', `PDF ${type} tiene firma PDF válida`);
      check(pdf.length > 1000, `PDF ${type} contiene un informe no vacío`);
    }
  }
} catch (error) {
  failures.push(`smoke HTTP: ${error.message}`);
  console.error(`ERR smoke HTTP: ${error.stack || error}`);
}

if (failures.length) {
  console.error(`\n${failures.length} comprobaciones fallaron.`);
  process.exit(1);
}
console.log('\nLaboratorio de incidencias: comprobaciones completadas.');
