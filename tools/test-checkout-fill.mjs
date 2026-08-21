/**
 * Comprueba en un navegador real que «Usar dirección» deja el checkout
 * utilizable: región fijada y comuna acorde a la dirección elegida.
 *
 *   cd tools && node test-checkout-fill.mjs
 *
 * Chilexpress carga región y comuna por AJAX, así que este caso solo se puede
 * verificar con un navegador; las pruebas HTTP no lo detectan.
 */
import { chromium } from 'playwright';

const base = process.env.CXP_BASE || 'http://127.0.0.1:8080';
const browser = await chromium.launch({ channel: 'chrome' });
const page = await browser.newPage();
const failures = [];

function check(ok, label, detail = '') {
  console.log(`${ok ? 'OK ' : 'ERR'} ${label}${detail ? ` — ${detail}` : ''}`);
  if (!ok) failures.push(label);
}

try {
  await page.goto(`${base}/shop/`, { waitUntil: 'domcontentloaded' });
  const addToCart = page.locator('a[href*="add-to-cart="]').first();
  await addToCart.click();
  await page.waitForLoadState('domcontentloaded');

  await page.goto(`${base}/checkout/`, { waitUntil: 'domcontentloaded' });
  await page.waitForSelector('#billing_state', { timeout: 30_000 });

  // Ñuñoa: es el caso que fallaba, porque no era la primera direccion.
  const row = page.locator('.cxp-addr[data-addr="nunoa"]');
  await row.locator('.cxp-fill-addr').click();

  await page.waitForFunction(
    () =>
      document.querySelector('#billing_state')?.value === 'RM' &&
      /NUNOA|ÑUÑOA/i.test(document.querySelector('#billing_city')?.value || ''),
    null,
    { timeout: 30_000 },
  ).catch(() => {});

  const state = await page.locator('#billing_state').inputValue();
  const city = await page.locator('#billing_city').inputValue();
  const street = await page.locator('#billing_address_1').inputValue();
  const name = await page.locator('#billing_first_name').inputValue();

  check(state === 'RM', 'Region queda fijada en RM', `valor="${state}"`);
  check(/NUNOA|ÑUÑOA/i.test(city), 'Comuna corresponde a la direccion elegida', `valor="${city}"`);
  check(/Irarrazaval/i.test(street), 'Calle corresponde a la direccion elegida', `valor="${street}"`);
  check(name === 'Camila', 'Nombre corresponde a la direccion elegida', `valor="${name}"`);

  // Cambiar a otra comuna debe reflejarse, no quedarse con la anterior.
  await page.locator('.cxp-addr[data-addr="lareina"] .cxp-fill-addr').click();
  await page.waitForFunction(
    () => /LA REINA/i.test(document.querySelector('#billing_city')?.value || ''),
    null,
    { timeout: 25_000 },
  ).catch(() => {});
  const city2 = await page.locator('#billing_city').inputValue();
  const state2 = await page.locator('#billing_state').inputValue();
  check(/LA REINA/i.test(city2), 'Cambiar de direccion actualiza la comuna', `valor="${city2}"`);
  check(state2 === 'RM', 'La region se mantiene tras el segundo relleno', `valor="${state2}"`);
} catch (error) {
  check(false, 'excepcion', String(error.message || error));
} finally {
  await browser.close();
}

console.log(failures.length ? `\n${failures.length} fallo(s).` : '\nRelleno de checkout: todo OK.');
process.exitCode = failures.length ? 1 : 0;
