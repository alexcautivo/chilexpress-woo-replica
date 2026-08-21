/**
 * Pruebas funcionales HTTP del laboratorio (tienda, admin, consola, incidencias).
 * No aplica pila ni reinstala plugins (eso es destructivo).
 */
import fs from 'node:fs';
import path from 'node:path';

const root = path.resolve(import.meta.dirname, '..');
const base = process.env.CXP_BASE || 'http://127.0.0.1:8080';
const debugLog = path.join(root, 'wordpress', 'wp-content', 'debug.log');
const failures = [];
const jar = new Map();

function check(ok, message) {
  if (ok) console.log(`OK  ${message}`);
  else {
    failures.push(message);
    console.error(`ERR ${message}`);
  }
}

function absorb(response) {
  const values = typeof response.headers.getSetCookie === 'function'
    ? response.headers.getSetCookie()
    : (response.headers.get('set-cookie') ? [response.headers.get('set-cookie')] : []);
  for (const value of values) {
    const pair = String(value).split(';')[0];
    const at = pair.indexOf('=');
    if (at > 0) jar.set(pair.slice(0, at), pair.slice(at + 1));
  }
}

function cookieHeader() {
  return [...jar.entries()].map(([k, v]) => `${k}=${v}`).join('; ');
}

async function request(url, options = {}) {
  const headers = { ...(options.headers || {}) };
  const cookies = cookieHeader();
  if (cookies) headers.Cookie = cookies;
  const res = await fetch(url, { ...options, headers, redirect: options.redirect || 'manual' });
  absorb(res);
  const buf = Buffer.from(await res.arrayBuffer());
  const text = buf.toString('utf8');
  return { res, buf, text, status: res.status, location: res.headers.get('location') || '' };
}

function isCritical(html) {
  return /There has been a critical error on this website|Ha habido un error cr[ií]tico en este sitio|class="wp-die-message"/i.test(html);
}

const logOffset = fs.existsSync(debugLog) ? fs.statSync(debugLog).size : 0;

try {
  const loginPage = await request(`${base}/wp-login.php`);
  check(loginPage.status < 500, `login GET HTTP ${loginPage.status}`);

  await request(`${base}/wp-login.php`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: new URLSearchParams({
      log: 'admin',
      pwd: 'admin',
      'wp-submit': 'Log In',
      redirect_to: `${base}/wp-admin/`,
      testcookie: '1',
    }),
  });

  const pages = [
    ['home', `${base}/`, ['cxp-dbg', 'Aplicar pila', 'Consola']],
    ['shop', `${base}/shop/`, ['add_to_cart', 'product']],
    ['checkout', `${base}/checkout/`, ['woocommerce', 'cxp-fill-addr', 'Cotizar']],
    ['cart', `${base}/cart/`, ['woocommerce', 'cart']],
    ['admin', `${base}/wp-admin/`, ['dashboard', 'wp-admin', 'cxp-dbg']],
    ['orders', `${base}/wp-admin/admin.php?page=wc-orders`, ['wc-orders', 'hpos', 'Pedidos', 'woocommerce']],
    ['ajax', `${base}/wp-admin/admin-ajax.php`, []],
  ];

  const htmlByPage = {};
  for (const [name, url, needles] of pages) {
    const page = await request(url, { redirect: 'follow' });
    htmlByPage[name] = page.text;
    check(page.status < 500, `${name}: HTTP ${page.status}`);
    check(!isCritical(page.text), `${name}: sin error crítico de WordPress`);
    for (const needle of needles) {
      check(page.text.toLowerCase().includes(needle.toLowerCase()), `${name}: contiene "${needle}"`);
    }
  }

  const home = htmlByPage.home || '';
  check(home.includes('cxp-ticket-apply'), 'consola: botón Aplicar pila en el DOM');
  check(home.includes('cxp-ticket-run'), 'consola: botón Ejecutar flujo en el DOM');
  check(home.includes('cxp-ticket-pdf-client'), 'consola: botón PDF cliente en el DOM');
  check(home.includes('cxp-stack-reload'), 'consola: recargar WordPress completo');
  check(home.includes('cxp-stack-latest'), 'consola: actualizar a latest');

  const nonceMatch = home.match(/var runnerNonce = ("[^"]+")/);
  const ticketNonceMatch = home.match(/var nonce = ("[^"]+");\s*var runnerNonce/);
  check(Boolean(nonceMatch), 'nonce del runner presente');
  check(Boolean(ticketNonceMatch), 'nonce de tickets presente');

  async function ajax(action, extra = {}, nonce) {
    const body = new URLSearchParams({ action, nonce, ...extra });
    return request(`${base}/wp-admin/admin-ajax.php`, {
      method: 'POST',
      redirect: 'follow',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body,
    });
  }

  if (nonceMatch && ticketNonceMatch) {
    const runnerNonce = JSON.parse(nonceMatch[1]);
    const ticketNonce = JSON.parse(ticketNonceMatch[1]);

    const preview = await ajax('cxp_incident_preview', { id: 'SR-108688' }, runnerNonce);
    let previewJson = {};
    try { previewJson = JSON.parse(preview.text); } catch { previewJson = {}; }
    check(previewJson.success === true, 'AJAX vista previa SR-108688');
    check(previewJson.data?.valid === true, 'SR-108688 es un ticket válido');
    check(Array.isArray(previewJson.data?.changes), 'vista previa lista cambios de pila');
    check(Array.isArray(previewJson.data?.flow) && previewJson.data.flow.length > 0, 'vista previa incluye el flujo declarativo');

    const examplePreview = await ajax('cxp_incident_preview', { id: 'CXP-EJEMPLO-11' }, runnerNonce);
    let exampleJson = {};
    try { exampleJson = JSON.parse(examplePreview.text); } catch { exampleJson = {}; }
    check(exampleJson.success === true, 'AJAX vista previa del ejemplo 1.1');

    const getTicket = await ajax('cxp_ticket_get', { id: 'SR-108688' }, ticketNonce);
    let ticketJson = {};
    try { ticketJson = JSON.parse(getTicket.text); } catch { ticketJson = {}; }
    check(ticketJson.success === true && String(ticketJson.data?.text || '').includes('ProductTaxStatus'), 'AJAX leer ticket SR-108688');

    const missing = await ajax('cxp_incident_preview', { id: 'NO-EXISTE' }, runnerNonce);
    let missingJson = {};
    try { missingJson = JSON.parse(missing.text); } catch { missingJson = {}; }
    check(missingJson.success === false, 'ticket inexistente se rechaza');

    for (const type of ['client', 'technical']) {
      const pdfUrl = new URL(`${base}/wp-admin/admin-ajax.php`);
      pdfUrl.search = new URLSearchParams({
        action: 'cxp_incident_pdf',
        nonce: runnerNonce,
        id: 'SR-108688',
        run_id: 'functional-test',
        type,
      });
      const pdf = await request(pdfUrl, { redirect: 'follow' });
      check(pdf.status === 200, `PDF ${type} HTTP 200`);
      check(pdf.buf.subarray(0, 4).toString() === '%PDF', `PDF ${type} firma válida`);
      check(pdf.buf.length > 800, `PDF ${type} no vacío (${pdf.buf.length} bytes)`);
    }

    const execute = await ajax('cxp_incident_execute', { id: 'SR-108688', run_id: 'functional-test' }, runnerNonce);
    let executeJson = {};
    try { executeJson = JSON.parse(execute.text); } catch { executeJson = {}; }
    check(executeJson.success === false, 'ejecutar flujo sin pila aplicada se bloquea (no rompe el sitio)');
  }

  const ajaxGet = await request(`${base}/wp-admin/admin-ajax.php`, { redirect: 'follow' });
  check(ajaxGet.status < 500, `admin-ajax GET HTTP ${ajaxGet.status}`);

  if (fs.existsSync(debugLog)) {
    const delta = fs.readFileSync(debugLog).subarray(logOffset).toString('utf8');
    const fatals = delta.split(/\r?\n/).filter((line) => /PHP Fatal|PHP Parse|Uncaught/i.test(line));
    check(fatals.length === 0, fatals.length ? `sin fatales PHP durante la prueba (${fatals[0]})` : 'sin fatales PHP durante la prueba');
  }
} catch (error) {
  check(false, `excepción: ${error.message}`);
}

if (failures.length) {
  console.error(`\n${failures.length} pruebas funcionales fallaron.`);
  process.exit(1);
}
console.log('\nPruebas funcionales: todo OK.');
