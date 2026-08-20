"""SR-108688 manual rerun — same steps as the console button."""
from __future__ import annotations

import subprocess
import time
from pathlib import Path

ROOT = Path(r"C:\Users\HP\Desktop\wirdscrepss")
WP = ROOT / "wordpress"
PHP = ROOT / "runtime" / "php-8.4.19" / "php.exe"
INI = ROOT / "runtime" / "php-8.4.19" / "php.ini"
ENUM = WP / "wp-content" / "plugins" / "woocommerce" / "src" / "Enums" / "ProductTaxStatus.php"
HID = Path(str(ENUM) + ".cxp-sr108688")
PROBE = WP / "wp-content" / "mu-plugins" / "cxp-sr108688" / "probe.php"
LOG = WP / "wp-content" / "debug.log"
CACHE = WP / "wp-content" / "cache"
CACHE.mkdir(parents=True, exist_ok=True)


def curl(url: str, out: Path | None = None, timeout: int = 20) -> tuple[int, str]:
    cmd = ["curl", "-sS", "-L", "--max-time", str(timeout), "-w", "\nHTTP:%{http_code}", url]
    r = subprocess.run(cmd, capture_output=True, text=True, encoding="utf-8", errors="replace")
    body = r.stdout or ""
    code = 0
    if "\nHTTP:" in body:
        body, _, tail = body.rpartition("\nHTTP:")
        try:
            code = int(tail.strip())
        except ValueError:
            code = 0
    if out:
        out.write_text(body, encoding="utf-8")
    return code, body


def has_class(path: Path) -> bool:
    return path.is_file() and "class ProductTaxStatus" in path.read_text(encoding="utf-8", errors="replace")


def restore() -> None:
    if HID.is_file():
        ENUM.write_bytes(HID.read_bytes())
        HID.unlink()


def step(title: str) -> None:
    print("\n========", title, "========")


try:
    step("1. SITIO SANO (antes)")
    shop, _ = curl("http://127.0.0.1:8080/shop/")
    ajax, ajax_body = curl("http://127.0.0.1:8080/wp-admin/admin-ajax.php", CACHE / "ajax-before.html")
    st, st_body = curl("http://127.0.0.1:8080/__sr108688/status")
    print(f"shop HTTP {shop}")
    print(f"admin-ajax HTTP {ajax} (400 sin action = WordPress sano, no fatal)")
    print("admin-ajax fatal?", "Fatal error" in ajax_body or "ProductTaxStatus" in ajax_body)
    print("enum class presente?", has_class(ENUM))
    print("status:\n", st_body.strip())

    step("2. VENTANA UPDATE (stub: ruta existe, clase no)")
    HID.write_bytes(ENUM.read_bytes())
    ENUM.write_text("<?php\n// SR-108688 stub\n", encoding="utf-8")
    print("stub escrito, class presente?", has_class(ENUM), "backup?", HID.is_file())

    step("3. PROBE CLI admin-ajax.php (correo del cliente)")
    before_len = LOG.stat().st_size if LOG.is_file() else 0
    p = subprocess.run(
        [str(PHP), "-c", str(INI), str(PROBE)],
        capture_output=True,
        text=True,
        encoding="utf-8",
        errors="replace",
        timeout=40,
    )
    (CACHE / "probe-out.txt").write_text((p.stderr or "") + "\n" + (p.stdout or ""), encoding="utf-8")
    print("probe exit", p.returncode, "(255 = fatal PHP, esperado)")
    mixed = ((p.stderr or "") + (p.stdout or ""))[:600]
    print("probe output head:\n", mixed or "(vacío; el stack va a debug.log)")

    step("4. STACK debug.log vs correo producción")
    time.sleep(0.2)
    log = LOG.read_text(encoding="utf-8", errors="replace") if LOG.is_file() else ""
    idx = log.rfind("ProductTaxStatus")
    chunk = log[max(0, log.rfind("[", 0, idx)) : idx + 2200] if idx >= 0 else ""
    print(chunk[:2000])
    needles = [
        'Class "Automattic\\WooCommerce\\Enums\\ProductTaxStatus" not found',
        "abstract-wc-shipping-method.php:84",
        "class-chilexpress-woo-oficial-admin.php(116)",
        "[constant expression]()",
        "class-chilexpress-woo-oficial.php(162)",
        "class-chilexpress-woo-oficial.php(79)",
        "chilexpress-woo-oficial.php(103)",
        "do_action('plugins_loaded')",
        "admin-ajax.php",
    ]
    print("----- coincidencia con el correo -----")
    hits = 0
    haystack = chunk if chunk else log[-4000:]
    for n in needles:
        ok = n in haystack
        hits += int(ok)
        print("HIT " if ok else "MISS", n)
    print(f"marcadores {hits}/{len(needles)}")

    step("5. RESTAURAR")
    restore()
    print("enum class presente?", has_class(ENUM))
    print("backup leftover?", HID.is_file())
    st, st_body = curl("http://127.0.0.1:8080/__sr108688/status")
    print("status:\n", st_body.strip())

    step("6. SITIO SANO (después)")
    shop, shop_html = curl("http://127.0.0.1:8080/shop/")
    admin, _ = curl("http://127.0.0.1:8080/wp-admin/")
    ajax, ajax_body = curl("http://127.0.0.1:8080/wp-admin/admin-ajax.php")
    print(f"shop HTTP {shop}")
    print(f"wp-admin HTTP {admin}")
    print(f"admin-ajax HTTP {ajax}")
    print("shop fatal?", "Fatal error" in shop_html)
    print("consola botón?", "Replicar caída exacta" in shop_html)
    print("admin-ajax fatal?", "Fatal error" in ajax_body or "ProductTaxStatus" in ajax_body)

    step("RESULTADO")
    if hits >= 7 and has_class(ENUM) and shop == 200:
        print("OK: caída replicada frame a frame y sitio restaurado.")
    else:
        print("REVISAR: no todos los marcadores o el sitio no volvió.")
finally:
    restore()
