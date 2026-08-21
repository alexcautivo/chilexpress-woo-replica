/**
 * Recorre el laboratorio en Chrome headed y emite fallos en vivo
 * (consola JS, peticiones HTTP, PHP debug.log).
 */
import { chromium } from 'playwright';
import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const ROOT = path.resolve(__dirname, '..');
const BASE = process.env.CXP_BASE || 'http://127.0.0.1:8080';
const DEBUG_LOG = path.join(ROOT, 'wordpress', 'wp-content', 'debug.log');
const OUT = path.join(ROOT, 'logs', 'chrome-live-audit.json');

const findings = [];
let phpOffset = 0;

function stamp() {
  return new Date().toISOString();
}

function emit(kind, payload) {
  const row = { t: stamp(), kind, ...payload };
  findings.push(row);
  const label = kind.toUpperCase().padEnd(10);
  const extra = payload.message || payload.url || payload.text || JSON.stringify(payload);
  console.log(`[${row.t}] ${label} ${extra}`);
}

function ignoreConsole(text, url) {
  const t = String(text || '');
  const u = String(url || '');
  if (/Download the React DevTools/i.test(t)) return true;
  if (/JQMIGRATE/i.test(t)) return true;
  if (/Failed to load resource:.*favicon/i.test(t)) return true;
  if (/chrome-extension:/i.test(u)) return true;
  return false;
}

function readPhpDelta() {
  if (!fs.existsSync(DEBUG_LOG)) return [];
  const buf = fs.readFileSync(DEBUG_LOG);
  if (buf.length <= phpOffset) return [];
  const chunk = buf.subarray(phpOffset).toString('utf8');
  phpOffset = buf.length;
  return chunk.split(/\r?\n/).filter(Boolean);
}

function interestingPhp(line) {
  return /PHP (Fatal|Parse|Warning|Notice|Deprecated)|Uncaught|Database error|critical error|Allowed memory/i.test(
    line
  );
}

function flushPhp(pageHint) {
  for (const line of readPhpDelta()) {
    if (interestingPhp(line)) {
      emit('php', { page: pageHint, message: line });
    } else if (/\[CXP HTTP\]|\[Chilexpress\].*(error|fail|401|403|500)/i.test(line)) {
      emit('php-api', { page: pageHint, message: line });
    }
  }
}

async function goto(page, url, hint) {
  emit('nav', { message: `→ ${url}` });
  const res = await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 45000 });
  const status = res ? res.status() : 0;
  if (status >= 400) emit('http', { url, status, message: `HTTP ${status} ${url}` });
  await page.waitForTimeout(1200);
  flushPhp(hint || url);
  const body = await page.locator('body').innerText().catch(() => '');
  if (/There has been a critical error/i.test(body)) {
    emit('fatal-ui', { url, message: 'WordPress critical error en la página' });
  }
  return res;
}

async function main() {
  phpOffset = fs.existsSync(DEBUG_LOG) ? fs.statSync(DEBUG_LOG).size : 0;
  fs.mkdirSync(path.dirname(OUT), { recursive: true });
  emit('info', { message: `Chrome headed contra ${BASE} (php log offset ${phpOffset})` });

  const browser = await chromium.launch({
    channel: 'chrome',
    headless: false,
    args: ['--start-maximized'],
  });
  const context = await browser.newContext({ viewport: null });
  const page = await context.newPage();

  page.on('console', (msg) => {
    const type = msg.type();
    const text = msg.text();
    const loc = msg.location();
    if (!['error', 'warning'].includes(type)) return;
    if (ignoreConsole(text, loc.url)) return;
    emit('console', { level: type, message: text, url: loc.url, line: loc.lineNumber });
  });
  page.on('pageerror', (err) => {
    emit('pageerror', { message: err.message, stack: String(err.stack || '').split('\n')[0] });
  });
  page.on('requestfailed', (req) => {
    const u = req.url();
    if (/favicon|chrome-extension/i.test(u)) return;
    emit('netfail', {
      url: u,
      method: req.method(),
      message: `${req.method()} FAIL ${u} — ${req.failure()?.errorText || ''}`,
    });
  });
  page.on('response', (res) => {
    const status = res.status();
    const u = res.url();
    if (status < 400) return;
    if (/favicon/i.test(u)) return;
    emit('http', { url: u, status, message: `HTTP ${status} ${u}` });
  });

  try {
    await goto(page, `${BASE}/`, 'home');
    await goto(page, `${BASE}/shop/`, 'shop');

    const addBtn = page.locator('a.add_to_cart_button, .wd-add-btn a.add_to_cart_button').first();
    if (await addBtn.count()) {
      emit('info', { message: 'Clic en Agregar al carrito (primer producto)' });
      await addBtn.click({ timeout: 10000 });
      await page.waitForTimeout(2500);
      flushPhp('add-to-cart');
    } else {
      emit('ui', { message: 'No hay botón add_to_cart_button en /shop/' });
    }

    await goto(page, `${BASE}/checkout/`, 'checkout');

    const classic = await page.locator('form.woocommerce-checkout, form.checkout').count();
    if (!classic) {
      emit('ui', { message: 'Checkout clásico no encontrado (¿Woo Blocks?)' });
    }

    const fillBtn = page.locator('.cxp-fill-addr').first();
    if (await fillBtn.count()) {
      emit('info', { message: 'Clic en Usar dirección (primera tarjeta RM)' });
      await fillBtn.click();
      await page.waitForTimeout(3500);
      flushPhp('fill-address');
      const city = await page.locator('#billing_city').inputValue().catch(() => '');
      const state = await page.locator('#billing_state').inputValue().catch(() => '');
      emit('info', { message: `Formulario: state=${state || '∅'} city=${city || '∅'}` });
      if (!city) emit('ui', { message: 'billing_city vacío después de Usar dirección' });
    } else {
      emit('ui', { message: 'No está el botón Usar dirección (.cxp-fill-addr)' });
    }

    const probeBtn = page.locator('.cxp-probe-addr').first();
    if (await probeBtn.count()) {
      emit('info', { message: 'Clic en Cotizar envío' });
      await probeBtn.click();
      await page.waitForSelector('.cxp-probe-out', { timeout: 20000 }).catch(() => null);
      await page.waitForTimeout(2000);
      const probeText = (await page.locator('.cxp-probe-out').first().innerText().catch(() => '')).trim();
      emit(probeText ? 'info' : 'ui', {
        message: probeText ? `Cotizador: ${probeText}` : 'Cotizar envío no mostró .cxp-probe-out',
      });
      flushPhp('probe-rate');
    }

    const ship = page.locator('#shipping_method, ul.woocommerce-shipping-methods, .woocommerce-shipping-totals');
    const shipText = (await ship.first().innerText().catch(() => '')).trim();
    emit('info', { message: `Métodos de envío: ${shipText ? shipText.replace(/\s+/g, ' ').slice(0, 240) : 'no visibles'}` });

    await goto(page, `${BASE}/cart/`, 'cart');
    await goto(page, `${BASE}/wp-admin/`, 'wp-admin');
    await goto(page, `${BASE}/wp-admin/admin.php?page=wc-orders`, 'wc-orders');
    await goto(
      page,
      `${BASE}/wp-admin/admin.php?page=chilexpress_woo_oficial_menu`,
      'chilexpress-admin'
    );

    emit('info', { message: 'Pausa 4s en Chrome para ver la última pantalla' });
    await page.waitForTimeout(4000);
  } catch (err) {
    emit('crash', { message: err.message, stack: String(err.stack || '').split('\n').slice(0, 4).join(' | ') });
    flushPhp('crash');
  } finally {
    flushPhp('end');
    const summary = {
      started: findings[0]?.t,
      ended: stamp(),
      counts: findings.reduce((acc, f) => {
        acc[f.kind] = (acc[f.kind] || 0) + 1;
        return acc;
      }, {}),
      findings,
    };
    fs.writeFileSync(OUT, JSON.stringify(summary, null, 2), 'utf8');
    console.log('\n=== RESUMEN ===');
    console.log(JSON.stringify(summary.counts, null, 2));
    console.log(`Informe: ${OUT}`);
    await browser.close();
  }
}

main().catch((err) => {
  console.error(err);
  process.exit(1);
});
