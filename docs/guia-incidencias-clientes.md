# Guía fácil — recibir y reproducir incidencias de clientes

Esta guía explica el flujo completo: qué enviar al cliente, qué debe rellenar, cómo importarlo en la consola y qué informe entregar al finalizar.

Autor del laboratorio: **Alexander Alejandro Cautivo Ramos** · [Aeolabs.io](https://aeolabs.io)

---

## 1. Qué enviarle al cliente

En la Consola réplica:

1. Abre **Incidencias**.
2. Pulsa **Copiar JSON para el cliente**.
3. Pega ese JSON debajo de este texto en el correo:

> Hola. Para poder reproducir la falla en un laboratorio necesitamos las versiones exactas y los pasos que generan el problema.
>
> Por favor complete el JSON adjunto o pegado abajo sin borrar sus claves.
>
> Necesitamos:
>
> - URL del sitio y pantalla donde falla.
> - Texto exacto del error.
> - Fecha aproximada en que comenzó.
> - Versión de PHP y WordPress.
> - Tema, tema padre y versiones.
> - **Todos los plugins**, incluso los desactivados, con versión exacta y estado activo/inactivo.
> - Pasos concretos para reproducir la falla.
> - Correo de error crítico o extracto de `debug.log`, si existe.
>
> Puede encontrar las versiones en **WooCommerce → Estado** y **Herramientas → Salud del sitio → Información**.
>
> No incluya contraseñas, claves API, datos de tarjetas ni una base de datos de producción. Si no conoce un valor escriba `no_se`.
>
> Devuelva el JSON completo en este mismo correo.

Después del texto, pega el contenido de:

`incidents/templates/para-el-cliente.json`

El cliente no tiene que programar el flujo técnico. Debe completar principalmente `origen`, `sintoma`, `pila`, `plugins`, `entorno`, `evidencia` y `pedido`.

---

## 2. Cómo rellenar las versiones

### WordPress y PHP

```json
"pila": {
  "php": "8.2.29",
  "wordpress": "6.8.2",
  "woocommerce": "9.8.5"
}
```

Usa siempre la versión completa. Es mejor `8.2.29` que `8.2`.

### Cada plugin

Un plugin público:

```json
{
  "nombre": "WooCommerce",
  "slug": "woocommerce",
  "version": "9.8.5",
  "activo": true,
  "fuente": "wordpress.org",
  "archivo_zip": "",
  "checksum": "",
  "notas": ""
}
```

Un plugin premium o propio:

```json
{
  "nombre": "Plugin privado del cliente",
  "slug": "plugin-privado",
  "version": "2.4.1",
  "activo": true,
  "fuente": "zip_local",
  "archivo_zip": "plugin-privado-2.4.1.zip",
  "checksum": "",
  "notas": "Aeolabs revisará el ZIP antes de instalarlo"
}
```

Reglas:

- `slug` es la carpeta en `wp-content/plugins/`, no necesariamente el nombre visible.
- `activo` debe reflejar el momento exacto de la falla.
- `wordpress.org` descarga la versión pública exacta.
- `zip_local` exige que Aeolabs coloque un ZIP autorizado en `drop-plugins/`.
- `repo` se usa para una copia controlada por el laboratorio, como Chilexpress Oficial 1.4.0.
- No se descargan plugins comerciales desde fuentes no autorizadas.

---

## 3. Importar la respuesta y crear la incidencia

1. Copia el JSON que devolvió el cliente.
2. Consola → **Incidencias**.
3. Pégalo en el cuadro.
4. Pulsa **Crear ticket con este JSON**.

El ticket queda en:

`incidents/tickets/{ticket_id}.json`

Este paso **no cambia WordPress**. Solo valida y guarda la incidencia.

Si el cliente no envió un `ticket_id`, la consola crea uno como `CXP-20260821-181500`.

---

## 4. Elegir cómo probar la incidencia

La consola permite dos formas.

### A. Aplicar exactamente lo que informó el cliente

Es la opción recomendada para comparar el error.

1. Pulsa **Vista previa**.
2. Revisa `actual → solicitado`.
3. Sube los ZIP privados que falten a `drop-plugins/`.
4. Pulsa **Aplicar pila** y confirma.

La consola:

1. Crea un snapshot de WordPress, plugins, temas, configuración y SQLite.
2. Instala la versión exacta de WordPress.
3. Instala cada plugin en la versión del JSON.
4. Activa o desactiva cada plugin según lo reportado.
5. Instala o activa el tema disponible.
6. Limpia cachés y verifica front, admin y `admin-ajax.php`.
7. Comprueba que las versiones reales coincidan con las solicitadas.

Si PHP es distinto, prepara el runtime y muestra **Reinicio requerido**. Reinicia `start.sh` o reconstruye Docker y pulsa **Aplicar pila** otra vez para continuar el mismo run.

### B. Armar otra combinación manual

En **Laboratorio / Versiones** puedes:

- Elegir cualquier versión de WordPress.
- Elegir o escribir una versión para cada plugin.
- Instalar otro plugin público por slug y versión.
- Subir un ZIP privado autorizado.
- Activar o desactivar plugins.
- Usar **Recargar WordPress completo** para reinstalar core y toda la tabla.
- Usar **Actualizar a latest** solo para investigar una actualización general.

`latest` no sirve para reproducir fielmente una incidencia antigua: para eso usa versiones exactas.

---

## 5. Crear y ejecutar el flujo

1. Pulsa **Crear flujo** para generar un flujo seguro desde la URL y el error informado.
2. Revisa el JSON si el caso necesita pasos adicionales.
3. Pulsa **Ejecutar flujo**.

El laboratorio solo admite acciones declarativas registradas:

- abrir una ruta del mismo laboratorio;
- hacer una petición HTTP o AJAX;
- activar/desactivar un plugin;
- limpiar caché;
- esperar;
- comprobar status, texto o `debug.log`;
- ejecutar un escenario interno revisado por Aeolabs.

El JSON del cliente **no puede ejecutar PHP, shell ni URLs externas**.

Durante la prueba se capturan:

- status y cuerpo HTTP reducido;
- errores PHP y nuevas líneas de `debug.log`;
- errores JavaScript, promesas, XHR, fetch y recursos fallidos de Chrome;
- versiones reales;
- pasos y assertions aprobados/fallidos.

---

## 6. Cómo leer el resultado

La consola compara una firma del error reportado con la evidencia real:

- clase o tipo de error;
- mensaje;
- archivo y línea;
- URL y status HTTP;
- marcadores del stack trace;
- versiones y plugins activos.

Resultados:

| Resultado | Significado |
|---|---|
| `coincide` | Se reprodujo el mismo fallo con evidencia suficiente |
| `coincide_parcialmente` | Hay marcadores iguales, pero también diferencias |
| `no_coincide` | El laboratorio produjo otro error |
| `no_reproducible` | La pila y pasos no generaron el fallo informado |

Las causas probables salen de reglas versionadas: dependencia ausente, actualización incompleta, carga prematura, incompatibilidad de PHP, plugin/tema faltante, timeout o fallo externo.

---

## 7. Qué entregar

### PDF para el cliente

Botón **PDF cliente**:

- explica qué reportó;
- qué pudo reproducirse;
- si coincide o no;
- causa probable en lenguaje simple;
- impacto;
- recomendaciones y próximos pasos;
- no incluye secretos ni stack traces largos.

### PDF técnico para desarrolladores

Botón **PDF técnico**:

- ticket y run;
- pila solicitada y real;
- inventario completo de plugins;
- pasos y assertions;
- HTTP, JavaScript, PHP y `debug.log`;
- firma y diff del error;
- reglas que justifican el diagnóstico;
- limitaciones y estado del rollback.

Este PDF es el adecuado para el equipo de desarrollo de **Chilexpress Oficial** porque muestra el hook, archivo, línea, stack y combinación exacta de versiones sin modificar el plugin oficial.

---

## 8. Terminar la prueba

Pulsa **Restaurar snapshot** para volver al estado anterior.

Los artefactos del run quedan en:

`incidents/runs/{ticket_id}/{run_id}/`

Nunca pruebes directamente en producción. El laboratorio reproduce el entorno con datos de prueba y no necesita contraseñas ni claves reales del cliente.
