# Instrucciones detalladas para el cliente — SR-108688

Usen estas instrucciones en **celularesenventa.cl** (producción) o en un staging clonado. Versiones objetivo:

- WordPress 7.0.3
- WooCommerce 11.0.1
- PHP 8.4.19
- Chilexpress Oficial 1.4.0
- Woodmart Child 1.0.0

## A. Sitio caído ahora (“error crítico”)

1. Backup completo (archivos + base de datos) antes de tocar nada.
2. FTP / SFTP / File Manager → `wp-content/plugins/`.
3. Renombrar `chilexpress-oficial` → `chilexpress-oficial.off`.
4. Abrir `https://celularesenventa.cl/wp-admin/` (con la barra final).
5. Plugins → WooCommerce debe figurar **11.0.1** y activo.
6. Por FTP, confirmar que existe:  
   `wp-content/plugins/woocommerce/src/Enums/ProductTaxStatus.php`
7. Si Woo quedó a medias: Plugins → añadir nuevo → WooCommerce → instalar 11.0.1 (o restaurar desde backup + actualizar otra vez **con Chilexpress aún desactivado**).
8. Renombrar `chilexpress-oficial.off` → `chilexpress-oficial`.
9. Activar Chilexpress Oficial.
10. WooCommerce → Ajustes → Envío: zona Chile con método Chilexpress.
11. Pedido de prueba a una comuna real (nombre Chilexpress, p. ej. `LA REINA`, no un slug).
12. Si usan OT: Pedidos → editar pedido → Generar OT (o el flujo que ya usaban).

## B. Próxima actualización de WooCommerce (prevenir la caída)

1. Horario de bajo tráfico.
2. Modo mantenimiento si lo tienen.
3. **Desactivar Chilexpress Oficial.**
4. Actualizar WooCommerce (y solo Woo en ese paso).
5. Esperar a que termine; no recargar a mitad.
6. Verificar `ProductTaxStatus.php` como en A.6.
7. Activar Chilexpress Oficial.
8. Pedido de prueba.

No actualicen Woo y Chilexpress al mismo tiempo. Chilexpress 1.4.0 no debe estar activo mientras Woo copia archivos.

## C. Checkout y cotizador (si “no llama a las APIs”)

Chilexpress Oficial 1.4.0 en el front **clásico** pide región/comuna así:

- Campos `#billing_state` y `#billing_city`
- AJAX `admin-ajax.php?action=obtener_comunas_desde_region`
- El cotizador real es PHP hacia `qaservices.wschilexpress.com` o producción; **no** aparece como fetch en el Network del navegador hacia Chilexpress.

Si el checkout es el de **bloques** de WooCommerce, ese JS no corre. Usar la página de checkout clásica (`[woocommerce_checkout]`) o un checkout que siga exponiendo esos campos.

La comuna debe coincidir con el catálogo Chilexpress (`LA REINA`, no `la-reina`). El número de calle, solo dígitos, o la API de OT puede rechazar.

## D. Qué no hacer

- No borrar WooCommerce para “probar”.
- No cambiar PHP ni el tema para este fatal: no es la causa.
- No editar a mano `abstract-wc-shipping-method.php`.
- No dejar Chilexpress activo a mitad de un update de Woo.

## E. Cómo verificar que ya no está el escenario del correo

Tras un update controlado (Chilexpress apagado → Woo completo → Chilexpress on):

1. `/wp-admin/` carga.
2. `/wp-admin/admin-ajax.php` no muestra error crítico (puede devolver `0`; eso es normal sin acción).
3. Un producto con peso/dimensiones en **kg/cm** cotiza PREX/CHEX.
4. No reaparece el correo `Class "Automattic\WooCommerce\Enums\ProductTaxStatus" not found`.

## F. Contacto de réplica interna (equipo técnico, no producción)

Sitio local: {{SITE_URL}}  
Admin: {{SITE_URL}}wp-admin/ · usuario `admin` / contraseña `admin`  
Consola inferior: botones para generar este documento, el diagnóstico y restaurar la pila 7.0.3 / 11.0.1 / 1.4.0.
