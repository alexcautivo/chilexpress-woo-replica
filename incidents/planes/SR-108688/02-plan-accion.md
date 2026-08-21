# SR-108688 — plan de acción realista

**Para qué sirve:** pasos que el dueño de la tienda o el hosting pueden hacer **esta semana**, sin programar y sin editar Chilexpress a mano (un parche local se pierde en el próximo update del plugin).

**Cómo soluciona el problema:** saca a Chilexpress de la ventana en la que Woo copia archivos. Cuando Woo ya está completo, el fatal de `ProductTaxStatus` **no ocurre**. El sitio vuelve. Las próximas actualizaciones de Woo no lo tiran si se respeta el orden.

Detalle de la causa: [`01-por-que-fallo.md`](01-por-que-fallo.md).

---

## Ahora (el sitio abre)

Hacerlo en horario de poco tráfico.

1. **Backup** (archivos + base de datos).
2. Escritorio → Plugins → desactivar **Chilexpress Oficial**.
3. Actualizar **solo WooCommerce**. Esperar al 100%. No recargar a mitad.
4. Comprobar que existe:  
   `wp-content/plugins/woocommerce/src/Enums/ProductTaxStatus.php`
5. Activar Chilexpress Oficial.
6. Pedido de prueba: checkout **clásico**, región RM, comuna con nombre Chilexpress (ej. `LA REINA`), un envío PREX/CHEX.
7. Si usan OT: generar una OT de prueba (mejor staging).

## Si ya dice “error crítico” (no entra al admin)

1. Backup.
2. FTP / administrador de archivos → `wp-content/plugins/`.
3. Renombrar `chilexpress-oficial` → `chilexpress-oficial.off` (eso apaga el plugin).
4. Abrir `https://celularesenventa.cl/wp-admin/` (con barra final).
5. Completar o reinstalar WooCommerce **11.0.1** hasta el final. Confirmar el archivo del enum.
6. Renombrar otra vez a `chilexpress-oficial` y activarlo.
7. Misma prueba de checkout que arriba.

No borrar WooCommerce “para probar”. No editar `abstract-wc-shipping-method.php`. No bajar Woo a 8.x: deja la tienda insegura y no arregla el orden de carga.

## Próxima vez que actualicen Woo

1. Apagar Chilexpress.
2. Actualizar Woo y esperar.
3. Encender Chilexpress.
4. Pedido de prueba.

No actualizar Woo y Chilexpress al mismo tiempo. No dejar Chilexpress activo mientras el actualizador copia archivos.

## Qué no es este plan

- No es un parche dentro de `chilexpress-oficial` en producción.
- No cambia Woodmart, PHP ni las keys de Chilexpress.
- No sustituye el arreglo de fondo: eso lo tiene que publicar Chilexpress ([`03-mejoras-plugin-chilexpress.md`](03-mejoras-plugin-chilexpress.md)).

## Cómo saber que quedó bien

- Tienda, carrito y checkout abren.
- Se elige región, comuna y un envío Chilexpress.
- No vuelve el correo de WordPress con `ProductTaxStatus not found`.
- `admin-ajax.php` sin Chilexpress a medias no muestra error crítico (puede devolver `0` sin acción: es normal).

## Quién hace qué

| Quién | Qué |
|---|---|
| Tienda / hosting | Backup, desactivar/activar plugin, completar Woo, pedido de prueba |
| Aeolabs | Réplica, evidencia, este plan, JSON de ticket |
| Chilexpress | Publicar 1.4.x (o superior) que no cargue abstractas de Woo en `plugins_loaded` |

Preparado por Alexander Alejandro Cautivo Ramos · Aeolabs.io
