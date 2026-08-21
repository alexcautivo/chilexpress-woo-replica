# Incidencias — laboratorio adaptable

Esta carpeta convierte la réplica en un **laboratorio de incidencias**, no solo del ticket SR-108688.

Sirve para que Aeolabs reciba el mismo tipo de datos en cada reporte (este cliente u otro), replique con las versiones exactas, y entregue un plan de acción realista.

Autor: **Alexander Alejandro Cautivo Ramos** · desarrollador full stack · [Aeolabs.io](https://aeolabs.io)

---

## Para qué existe

| Pieza | Para qué | Cómo soluciona el problema |
|---|---|---|
| `templates/para-el-cliente.json` | Se lo **copiamos al cliente**. Lo completa y lo pega de vuelta. | Evita correos incompletos (“se cayó el sitio”) sin versiones, URL ni pasos. |
| `schema/incident.schema.json` | Contrato del JSON. | Si falta un campo obligatorio, el laboratorio lo pide antes de armar el ticket. |
| `tickets/*.json` | Un archivo = un ticket. | Pegar el JSON en la consola **crea** el ticket. Queda versionado y se puede reabrir. |
| `planes/SR-108688/` | Por qué falló + qué hacer hoy + qué debería cambiar Chilexpress. | El dueño de la tienda entiende el fallo; el equipo técnico tiene un plan; Chilexpress tiene un backlog concreto. |

Flujo:

1. Consola réplica → **Incidencias** → **Copiar JSON para el cliente**.
2. El cliente lo rellena (WordPress, Woo, PHP, plugin, error, URL, pasos).
3. Pegamos su JSON → **Crear ticket**.
4. El laboratorio alinea versiones y documenta el plan en `planes/{ticket_id}/`.

Chilexpress Oficial **1.4.0 no se parchea** aquí. Los planes de mejora del plugin son propuesta para el fabricante, no un ZIP modificado.

---

## SR-108688 (caso de referencia)

Ya está cargado como ticket y como plan:

- Ticket: [`tickets/SR-108688.json`](tickets/SR-108688.json)
- Por qué falló: [`planes/SR-108688/01-por-que-fallo.md`](planes/SR-108688/01-por-que-fallo.md)
- Qué hacer ahora: [`planes/SR-108688/02-plan-accion.md`](planes/SR-108688/02-plan-accion.md)
- Cómo debería mejorar Chilexpress: [`planes/SR-108688/03-mejoras-plugin-chilexpress.md`](planes/SR-108688/03-mejoras-plugin-chilexpress.md)

Cualquier incidencia nueva usa el **mismo JSON**. No hace falta otro correo de 14 reenvíos.

---

## Dónde vive en Docker

Local: el compose monta `./incidents` en `/var/www/incidents` (escribible: los tickets nuevos quedan en el repo).

Dokploy: la imagen copia esta carpeta. Variable `CXP_INCIDENTS_DIR=/var/www/incidents`.
