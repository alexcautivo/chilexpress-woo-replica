# AUDITOR MULTIPLATAFORMA CHILEXPRESS

## Arquitectura, Docker local, ZIPs, planes, IA y futuro despliegue en Dokploy

Actúa como **arquitecto senior de software, QA automation engineer, especialista en e-commerce, Docker, PHP, APIs y sistemas de auditoría**, con experiencia en:

* WordPress;
* WooCommerce;
* Magento;
* PrestaShop;
* Shopify;
* PHP;
* Composer;
* Node.js;
* Docker;
* Docker Compose;
* APIs;
* webhooks;
* CI/CD;
* análisis estático;
* pruebas de integración;
* pruebas de compatibilidad;
* análisis asistido por IA.

Tu objetivo es construir/evolucionar un **laboratorio de auditoría multiplataforma Chilexpress**.

El sistema debe permitir recibir un ZIP oficial de un plugin/módulo/app Chilexpress, identificar su plataforma, inspeccionarlo, crear un entorno Docker reproducible, instalarlo, probarlo, detectar incompatibilidades, ejecutar regresiones y generar un informe.

Además, el sistema podrá utilizar modelos de IA, especialmente modelos de Anthropic Claude, para enriquecer determinados análisis.

La IA será opcional.

El auditor determinista debe funcionar aunque no exista API key.

---

# 1. PLATAFORMAS

El sistema debe soportar:

* Magento
* PrestaShop
* WooCommerce
* Shopify

Fuentes/referencias de código actuales:

Magento:
https://chilexpresscode.visualstudio.com/Magento

PrestaShop:
https://chilexpresscode.visualstudio.com/Prestashop

WooCommerce:
https://chilexpresscode.visualstudio.com/Woocommerce

Shopify:
https://chilexpresscode.visualstudio.com/00-com-Plugin%20Shopify

No asumir que las cuatro plataformas utilizan la misma arquitectura.

Cada plataforma debe tener su propio adapter y su propio plan.

---

# 2. PLANES EXISTENTES

Existen archivos/documentos:

* Plan Magento
* Plan PrestaShop
* Plan WooCommerce
* Plan Shopify

Antes de modificar código:

1. localizar los cuatro planes;
2. leerlos completamente;
3. identificar requisitos;
4. identificar versiones soportadas;
5. identificar pruebas;
6. identificar casos conocidos;
7. identificar criterios PASS/FAIL;
8. identificar dependencias;
9. identificar flujo de instalación;
10. identificar cualquier contradicción con el código actual.

No reemplazar los planes automáticamente.

Los planes son especificaciones de plataforma y deben integrarse con el Core.

---

# 3. OBJETIVO

El sistema debe responder:

> ¿Este artefacto concreto de Chilexpress es compatible con esta versión concreta de esta plataforma?

La respuesta debe estar basada en:

* inspección del artefacto;
* requisitos;
* análisis estático;
* entorno reproducible;
* pruebas reales;
* pruebas de regresión;
* evidencia;
* logs;
* y opcionalmente análisis de IA.

---

# 4. EL ZIP ES EL ARTEFACTO REAL

El usuario descargará manualmente los plugins desde la fuente oficial.

Por ejemplo:

WooCommerce:

woocommerce-plugin-1.4.0-RELEASE.zip

PrestaShop:

prestashop-plugin-1.4.0-RELEASE.zip

Magento:

magento-plugin-1.4.0-RELEASE.zip

Shopify utilizará el mecanismo de distribución/configuración correspondiente.

El usuario colocará el ZIP en una carpeta del auditor.

El auditor debe utilizar ese ZIP como **artefacto exacto a auditar**.

No sustituirlo automáticamente por código de Git.

No descargar silenciosamente otra versión.

No modificar el ZIP original.

---

# 5. HASH DEL ARTEFACTO

Antes de analizar el ZIP:

1. calcular SHA-256;
2. registrar nombre;
3. registrar tamaño;
4. registrar fecha;
5. registrar hash.

Ejemplo:

ARTIFACT=woocommerce-plugin-1.4.0-RELEASE.zip

SHA256=<hash>

El hash debe aparecer en el reporte.

El ZIP original debe conservarse.

---

# 6. INSPECCIÓN DEL ZIP

Antes de Docker:

* estructura;
* archivos;
* namespaces;
* clases;
* interfaces;
* traits;
* Composer;
* autoload;
* package.json;
* manifests;
* metadata;
* versión;
* dependencias;
* PHP requerido;
* plataforma requerida;
* hooks;
* endpoints;
* APIs;
* configuración;
* código de inicialización;
* require/include;
* referencias externas.

Generar:

PLUGIN_INSPECTION=PASS/FAIL

---

# 7. DETECCIÓN DE PLATAFORMA

Detectar automáticamente:

* Magento;
* PrestaShop;
* WooCommerce;
* Shopify.

Utilizar:

* nombre;
* estructura;
* archivos;
* namespaces;
* manifests;
* composer.json;
* metadata;
* documentación.

Si existe incertidumbre:

PLATFORM=UNKNOWN

No adivinar.

Permitir:

--platform=woocommerce

para selección manual.

---

# 8. DETECCIÓN DE VERSIÓN

No confiar únicamente en el nombre del ZIP.

Comparar:

* nombre;
* metadata;
* headers;
* composer.json;
* constantes;
* manifests;
* código.

Si existen versiones contradictorias:

VERSION_CONFLICT=YES

y detener la clasificación automática hasta resolverlo o marcar:

UNKNOWN.

---

# 9. DOCUMENTACIÓN

Si existe documentación del plugin:

* leerla;
* extraer requisitos;
* instalación;
* configuración;
* dependencias;
* restricciones;
* APIs;
* permisos;
* credenciales;
* funciones necesarias.

Comparar documentación con:

* código;
* plan;
* metadata;
* entorno.

Si existe contradicción:

DOCUMENTATION_CONFLICT=YES

---

# 10. DOCKER LOCAL

En esta fase todo debe ejecutarse localmente mediante Docker.

NO realizar ningún deploy en Dokploy.

Cada plataforma debe estar aislada.

Arquitectura conceptual:

Docker
│
├── auditor-core
│
├── WooCommerce Lab
│   ├── WordPress
│   ├── WooCommerce
│   ├── Chilexpress ZIP
│   └── MySQL
│
├── PrestaShop Lab
│   ├── PrestaShop
│   ├── Chilexpress module
│   └── MySQL
│
├── Magento Lab
│   ├── Magento
│   ├── Chilexpress module
│   └── MySQL
│
└── Shopify Lab
└── Shopify integration test environment

La implementación puede variar si existe una razón técnica mejor.

---

# 11. AISLAMIENTO

No compartir entre plataformas:

* filesystem;
* base de datos;
* Composer;
* PHP;
* Node;
* extensiones;
* configuración;
* cache;
* variables de entorno;
* dependencias.

Una plataforma nunca debe contaminar otra.

---

# 12. EJECUCIÓN BAJO DEMANDA

Por defecto, al recibir un ZIP:

1. detectar plataforma;
2. levantar solamente el laboratorio correspondiente;
3. ejecutar pruebas;
4. generar reporte;
5. destruir el entorno temporal cuando corresponda.

También debe existir:

FULL

para ejecutar todos los laboratorios.

---

# 13. BASES DE DATOS

Cuando corresponda:

Magento → magento-db

PrestaShop → prestashop-db

WooCommerce → woocommerce-db

No compartir bases de datos.

Las bases de datos deben poder recrearse desde cero.

---

# 14. HEALTHCHECKS

Orden:

DB READY

↓

PLATFORM READY

↓

PLUGIN INSTALLED

↓

PLUGIN INITIALIZED

↓

AUDITOR READY

↓

TESTS

No utilizar `sleep 30` como mecanismo principal.

---

# 15. VERSIONES REPRODUCIBLES

No utilizar `latest` para componentes críticos.

Registrar:

* plataforma;
* versión;
* PHP;
* Node;
* MySQL;
* Composer;
* imagen Docker;
* digest;
* plugin;
* SHA-256.

---

# 16. MATRIZ DE COMPATIBILIDAD

Permitir:

Platform Version × Plugin Version

Ejemplo:

WooCommerce 10.x × Chilexpress 1.4.0

WooCommerce 10.6.2 × Chilexpress 1.4.0

WooCommerce 11.0.1 × Chilexpress 1.4.0

El resultado de cada combinación debe ser independiente.

---

# 17. SOPORTE VS PRUEBA EXPERIMENTAL

Diferenciar:

SUPPORTED

UNSUPPORTED

OUT_OF_SUPPORTED_RANGE

EXPERIMENTAL

UNKNOWN

Si la documentación indica:

WooCommerce hasta 10.6.2

y se prueba:

WooCommerce 11.0.1

no declarar automáticamente:

"bug"

ni:

"compatible".

Clasificar inicialmente:

OUT_OF_SUPPORTED_RANGE

y permitir:

EXPERIMENTAL COMPATIBILITY PROBE

Esto es especialmente importante para SR-108688.

---

# 18. MODOS DEL AUDITOR

Implementar:

## INSPECT

Analiza ZIP.

No Docker.

---

## PLAN

Analiza qué debería probarse.

No modifica código.

---

## AUDIT

Ejecuta pruebas reales en Docker.

---

## REGRESSION

Ejecuta casos conocidos.

---

## MATRIX

Prueba varias versiones.

---

## FIX

Trabaja solamente sobre una copia.

Nunca modifica el ZIP original.

---

## FULL

Ejecuta todo:

INSPECT

PLAN

AUDIT

REGRESSION

MATRIX

REPORT

---

# 19. WOOCOMMERCE

Para WooCommerce comprobar:

* WordPress;
* WooCommerce;
* PHP;
* MySQL;
* autoload;
* plugins;
* hooks;
* `plugins_loaded`;
* admin;
* `admin-ajax.php`;
* dependencias;
* clases;
* constantes;
* inicialización;
* integración con Chilexpress.

---

# 20. CASO SR-108688

Convertir SR-108688 en prueba de regresión permanente.

Caso:

WooCommerce 11.0.1

*

Chilexpress 1.4.0

Problema:

Class "Automattic\WooCommerce\Enums\ProductTaxStatus" not found

El test debe determinar si el ZIP actual:

* reproduce;
* corrige;
* o modifica el comportamiento.

Nunca ocultar el fatal.

Nunca crear stubs.

Nunca modificar WooCommerce core.

---

# 21. PRESTASHOP

El adapter debe respetar el Plan PrestaShop.

Comprobar cuando corresponda:

* PHP;
* versión PrestaShop;
* módulo;
* hooks;
* Symfony;
* services;
* Composer;
* controllers;
* instalación;
* configuración;
* compatibilidad.

---

# 22. MAGENTO

Respetar el Plan Magento.

Comprobar cuando corresponda:

* PHP;
* Magento;
* Composer;
* module registration;
* `registration.php`;
* `module.xml`;
* DI;
* observers;
* plugins;
* controllers;
* setup;
* cache;
* compilation;
* APIs.

---

# 23. SHOPIFY

No tratar Shopify como una plataforma PHP.

Respetar su arquitectura.

Comprobar cuando corresponda:

* API version;
* OAuth;
* scopes;
* webhooks;
* Shipping;
* endpoints;
* GraphQL/REST;
* configuración;
* respuestas;
* rate limits;
* autenticación.

La función Shipping debe tratarse como requisito cuando así lo indique la documentación.

---

# 24. MODELO DE RESULTADO

Todos los adapters deben devolver un modelo común:

PLATFORM

VERSION

PLUGIN_VERSION

ARTIFACT

SHA256

STATUS

COMPATIBILITY

SUPPORT_STATUS

FATAL_DETECTED

ERROR_CLASS

ERROR_FILE

ERROR_LINE

EXIT_CODE

AUDIT_ID

---

# 25. ESTADOS

Utilizar:

PASS

FAIL

ERROR

SKIP

UNKNOWN

Nunca usar PASS para ocultar un fallo.

---

# 26. EXIT CODES

0 = PASS

1 = FAIL / incompatibilidad

2 = ERROR de entorno/dependencia

3 = ERROR del auditor

4 = SKIP

5 = UNKNOWN

Un fatal crítico jamás puede terminar como exit code 0.

---

# 27. IA — ARQUITECTURA OPCIONAL

El auditor debe soportar un sistema de IA opcional.

La IA NO debe ser necesaria para ejecutar las pruebas deterministas.

Sin API key:

el auditor debe seguir funcionando.

Con IA:

el auditor puede realizar análisis adicionales.

La IA debe actuar como:

ASSISTANT / ANALYST

y no como:

SOURCE OF TRUTH.

---

# 28. PROVEEDOR DE IA

Implementar inicialmente soporte para:

Anthropic Claude

pero diseñar el sistema mediante una abstracción:

AI_PROVIDER

para poder agregar posteriormente:

* Anthropic;
* OpenAI;
* modelos locales;
* otros proveedores.

No acoplar el Core directamente a Anthropic.

---

# 29. CONFIGURACIÓN DE IA DESDE CONSOLA

Crear una configuración interactiva.

Ejemplo conceptual:

Chilexpress Auditor

AI Configuration

Provider:
[ Anthropic ]

API Key:
[ ******** ]

Model:
[ Claude ... ]

Enable AI:
[ YES ]

Save configuration?:
[ YES ]

La API key debe almacenarse de forma segura.

Nunca:

* imprimirla en logs;
* incluirla en reportes;
* guardarla en Git;
* incluirla en Dockerfile;
* incluirla en el código;
* mostrarla en pantalla después de introducirla.

---

# 30. VARIABLES DE ENTORNO DE IA

Permitir configuración mediante:

AI_ENABLED=true

AI_PROVIDER=anthropic

ANTHROPIC_API_KEY=<secret>

AI_MODEL=<model>

Pero también permitir configuración mediante consola.

La configuración local debe terminar almacenándose de forma segura.

Crear:

.env.example

sin secretos reales.

---

# 31. CONFIGURACIÓN INTERACTIVA

El usuario debe poder ejecutar algo conceptualmente similar a:

auditor ai configure

y seleccionar:

1. proveedor;
2. API key;
3. modelo;
4. habilitar/deshabilitar IA;
5. nivel de análisis;
6. límites;
7. guardar configuración.

También:

auditor ai status

debe mostrar:

AI ENABLED
PROVIDER=ANTHROPIC
MODEL=<configured model>

Nunca mostrar:

ANTHROPIC_API_KEY

---

# 32. IA COMO CAPA DE ANÁLISIS

La IA puede utilizarse para:

* analizar stack traces;
* explicar errores;
* correlacionar logs;
* analizar código;
* analizar diffs;
* analizar documentación;
* detectar posibles incompatibilidades;
* identificar cambios entre versiones;
* generar hipótesis de causa raíz;
* sugerir pruebas;
* priorizar riesgos;
* resumir resultados;
* explicar conflictos entre documentación y código;
* proponer correcciones;
* revisar una corrección;
* generar casos de regresión candidatos.

---

# 33. IA NO PUEDE CAMBIAR EL RESULTADO DETERMINISTA

Regla crítica:

La IA jamás puede convertir:

FAIL

en:

PASS.

Tampoco puede convertir:

ERROR

en:

PASS.

El resultado oficial siempre proviene de las pruebas deterministas.

La IA solamente puede agregar:

AI_ANALYSIS

AI_DIAGNOSIS

AI_RECOMMENDATION

AI_CONFIDENCE

Ejemplo:

STATUS=FAIL

AI_DIAGNOSIS:
"El stack trace sugiere una inicialización temprana de una clase de WooCommerce."

Esto NO cambia:

STATUS=FAIL.

---

# 34. IA Y EVIDENCIA

Cuando la IA haga una afirmación, el reporte debe indicar qué evidencia recibió.

Ejemplo:

AI_INPUT_CONTEXT:

* stack trace;
* archivo;
* línea;
* versión;
* diff;
* documentación;
* logs.

No enviar innecesariamente todo el proyecto a la API.

Utilizar solamente el contexto necesario.

---

# 35. PRIVACIDAD Y SECRETOS

Antes de enviar información a la IA:

detectar y ocultar:

* API keys;
* passwords;
* tokens;
* cookies;
* JWT;
* credenciales;
* secretos;
* datos personales innecesarios;
* datos de clientes.

Aplicar redacción:

[REDACTED]

antes de enviar el contexto al modelo.

---

# 36. CONTROL DE COSTOS

La IA debe poder limitarse.

Configuraciones:

AI_ENABLED

AI_MAX_REQUESTS

AI_MAX_TOKENS

AI_TIMEOUT

AI_ANALYSIS_LEVEL

Niveles:

OFF

BASIC

STANDARD

DEEP

Por defecto:

STANDARD.

---

# 37. IA EN MODO BASIC

Utilizar IA solamente cuando existe:

* fatal;
* exception;
* incompatibilidad;
* conflicto de versiones.

Objetivo:

explicar el problema.

---

# 38. IA EN MODO STANDARD

Además:

* analizar stack trace;
* revisar archivos relacionados;
* correlacionar logs;
* comparar requisitos;
* proponer causa raíz;
* sugerir pruebas.

---

# 39. IA EN MODO DEEP

Además:

* analizar múltiples archivos;
* comparar versiones;
* revisar diffs;
* revisar documentación;
* identificar patrones de incompatibilidad;
* proponer corrección;
* proponer pruebas de regresión.

La ejecución DEEP debe requerir consentimiento/configuración explícita si implica un consumo significativo de API.

---

# 40. IA PARA GENERAR PRUEBAS

La IA puede sugerir:

TEST CASE

INPUT

EXPECTED RESULT

pero esos tests deben pasar por una validación determinista antes de convertirse en tests oficiales.

No permitir que la IA introduzca automáticamente tests peligrosos o destructivos.

---

# 41. IA PARA ANALIZAR CORRECCIONES

Si el usuario corrige un plugin:

ZIP ORIGINAL

vs.

ZIP CORREGIDO

la IA puede revisar:

* diff;
* arquitectura;
* posibles regresiones;
* compatibilidad;
* errores nuevos.

Pero la aceptación final depende de:

AUDIT

REGRESSION

MATRIX.

---

# 42. REPORTES CON IA

El reporte puede tener:

## Resultado determinista

PASS/FAIL/ERROR

## Diagnóstico técnico

Datos objetivos.

## Análisis IA

Interpretación del modelo.

## Recomendaciones

Sugerencias.

Debe quedar claro qué parte proviene del sistema determinista y cuál de IA.

Ejemplo:

DETERMINISTIC_RESULT=FAIL

AI_ANALYSIS=AVAILABLE

AI_CONFIDENCE=0.87

AI_DIAGNOSIS:
"Probable incompatibilidad por evaluación temprana de una clase WooCommerce."

---

# 43. FALLBACK SI LA IA FALLA

Si:

* API key inválida;
* timeout;
* rate limit;
* API caída;
* modelo no disponible;
* error de red;

el auditor debe continuar.

Ejemplo:

AI_STATUS=UNAVAILABLE

Pero:

AUDIT_STATUS=FAIL

si las pruebas deterministas fallaron.

Nunca convertir un problema de IA en un problema de plataforma.

---

# 44. DOCKER Y API DE IA

Por defecto, el acceso a la IA debe ocurrir desde el componente auditor.

Los contenedores de plataforma no necesitan conocer la API key.

Arquitectura:

auditor-core
│
├── deterministic engine
│
├── AI adapter
│       └── Anthropic
│
└── report engine

Los contenedores de:

WooCommerce

Magento

PrestaShop

Shopify

no deben recibir la API key de Anthropic salvo que exista una razón técnica explícita.

---

# 45. SEGURIDAD DEL ZIP

El ZIP recibido debe considerarse externo/no confiable.

Nunca ejecutar directamente sobre el host.

Utilizar Docker.

Limitar:

* filesystem;
* permisos;
* red;
* privilegios;
* acceso a secretos.

El plugin debe probarse dentro del laboratorio.

---

# 46. REPORTES

Cada auditoría debe crear:

reports/AUDIT-ID/

result.json

report.txt

diagnostics.json

test-results.json

metadata.json

ai-analysis.json

logs/

artifacts/

`ai-analysis.json` solamente si IA está habilitada.

---

# 47. METADATA

Registrar:

AUDIT_ID

TIMESTAMP

PLATFORM

PLATFORM_VERSION

PLUGIN_VERSION

ARTIFACT

SHA256

RUNTIME

PHP_VERSION

NODE_VERSION

DATABASE_VERSION

DOCKER_IMAGE

DOCKER_DIGEST

STATUS

EXIT_CODE

AI_ENABLED

AI_PROVIDER

AI_MODEL

No registrar la API key.

---

# 48. COMPARACIÓN ORIGINAL/CORREGIDO

Permitir:

audit original.zip

audit corrected.zip

y generar comparación:

* SHA256;
* archivos;
* diff;
* dependencias;
* resultados;
* regresiones;
* cambios de comportamiento.

La IA puede ayudar a interpretar el diff.

---

# 49. PLAN FUTURO DE DOKPLOY

Crear:

PLAN-DEPLOY-DOKPLOY.md

No ejecutar ningún deploy ahora.

No conectarse a Dokploy.

No modificar producción.

El documento debe explicar:

LOCAL

↓

STAGING

↓

DOKPLOY

↓

PRODUCCIÓN

---

# 50. DOKPLOY Y SECRETOS DE IA

El futuro plan debe documentar cómo configurar:

ANTHROPIC_API_KEY

como secreto en Dokploy.

Nunca:

* subir `.env` real;
* poner la API key en Git;
* poner la API key en Dockerfile;
* ponerla en imágenes;
* imprimirla en logs.

---

# 51. ROLLBACK

Registrar:

VERSION_ANTERIOR

VERSION_NUEVA

COMMIT

IMAGE_TAG

IMAGE_DIGEST

PLUGIN_SHA256

CONFIGURATION_VERSION

El rollback debe poder restaurar el artefacto anterior.

---

# 52. OBSERVABILIDAD

Guardar:

* logs;
* stack traces;
* pruebas;
* tiempos;
* versiones;
* hashes;
* exit codes;
* diagnóstico;
* AI analysis cuando exista.

Asociar todo a:

AUDIT_ID.

---

# 53. RESULTADO DE USUARIO

La experiencia futura debe ser sencilla.

Ejemplo:

1. Descargo:

woocommerce-plugin-1.4.0-RELEASE.zip

2. Lo coloco en:

artifacts/incoming/

3. Ejecuto:

auditor audit ./artifacts/incoming/woocommerce-plugin-1.4.0-RELEASE.zip

4. El sistema:

* detecta WooCommerce;
* detecta 1.4.0;
* calcula SHA256;
* inspecciona el ZIP;
* lee requisitos;
* selecciona Plan WooCommerce;
* determina versiones a probar;
* levanta Docker;
* instala WordPress;
* instala WooCommerce;
* instala Chilexpress;
* ejecuta tests;
* ejecuta regresiones;
* analiza errores;
* opcionalmente consulta Claude;
* genera reporte.

5. Resultado:

PASS

o:

FAIL

o:

ERROR

o:

UNKNOWN.

---

# 54. EJEMPLO DE RESULTADO

# AUDITOR CHILEXPRESS

AUDIT_ID:
AUDIT-2026-08-21-001

PLUGIN:
woocommerce-plugin-1.4.0-RELEASE.zip

SHA256:
xxxxxxxx

PLATFORM:
WooCommerce

PLUGIN_VERSION:
1.4.0

PLATFORM_VERSION:
11.0.1

SUPPORT_STATUS:
OUT_OF_SUPPORTED_RANGE

DETERMINISTIC_RESULT:
FAIL

FATAL_DETECTED:
YES

ERROR:
Automattic\WooCommerce\Enums\ProductTaxStatus

FILE:
abstract-wc-shipping-method.php

LINE:
84

EXIT_CODE:
1

AI_ANALYSIS:
AVAILABLE

AI_DIAGNOSIS:
Probable incompatibilidad asociada a la disponibilidad/evaluación temprana de una clase WooCommerce.

AI_RECOMMENDATION:
Revisar orden de inicialización y evaluación temprana en la integración.

CONCLUSIÓN:
La combinación no supera la prueba.

Importante:

El diagnóstico de IA no sustituye la evidencia del stack trace.

---

# 55. CRITERIO REAL DE PASS

PASS solamente cuando:

* ZIP válido;
* hash registrado;
* plataforma identificada;
* versión identificada;
* requisitos satisfechos;
* Docker reproducible;
* plataforma iniciada;
* plugin instalado;
* plugin inicializado;
* rutas críticas ejecutadas;
* pruebas funcionales completadas;
* no existen fatales;
* no existen excepciones no controladas;
* regresiones aprobadas;
* resultado esperado confirmado.

La IA no puede otorgar PASS.

---

# 56. CRITERIO DE ACEPTACIÓN DEL LABORATORIO

El sistema estará terminado cuando pueda:

1. recibir ZIP;
2. calcular SHA256;
3. detectar plataforma;
4. detectar versión;
5. inspeccionar ZIP;
6. leer documentación;
7. leer plan;
8. crear Docker;
9. instalar plataforma;
10. instalar ZIP;
11. ejecutar tests;
12. capturar errores;
13. ejecutar regresiones;
14. generar PASS/FAIL/ERROR;
15. generar reporte;
16. conservar ZIP;
17. destruir laboratorio;
18. recrearlo;
19. repetir pruebas;
20. obtener resultados reproducibles;
21. utilizar IA opcionalmente;
22. continuar funcionando sin IA;
23. proteger API keys;
24. generar análisis IA separado del resultado determinista;
25. mantener SR-108688 como regresión.

---

# 57. REGLA FINAL DE ARQUITECTURA

La arquitectura debe separar claramente:

ARTEFACTO

↓

INSPECCIÓN

↓

PLAN

↓

ENTORNO

↓

PRUEBAS DETERMINISTAS

↓

REGRESIONES

↓

RESULTADO

↓

IA OPCIONAL

↓

REPORTE

La IA NO reemplaza las pruebas.

Docker NO corrige los bugs.

El ZIP NO se modifica.

Los planes definen las reglas de cada plataforma.

El Core coordina.

Los adapters implementan la lógica específica.

Los resultados deterministas son la fuente de verdad.

La IA sirve para comprender, correlacionar, explicar y recomendar.

---

# 58. ORDEN DE IMPLEMENTACIÓN

Antes de modificar código:

FASE 1
Leer y comprender los cuatro planes.

FASE 2
Inspeccionar la arquitectura actual.

FASE 3
Identificar código común y código específico.

FASE 4
Diseñar Core + Adapters.

FASE 5
Implementar recepción e inspección de ZIP.

FASE 6
Implementar Docker local.

FASE 7
Implementar WooCommerce.

FASE 8
Convertir SR-108688 en regresión.

FASE 9
Implementar PrestaShop.

FASE 10
Implementar Magento.

FASE 11
Implementar Shopify.

FASE 12
Implementar matriz de compatibilidad.

FASE 13
Implementar sistema de IA opcional.

FASE 14
Implementar reportes.

FASE 15
Implementar PLAN-DEPLOY-DOKPLOY.md.

No saltarse fases sin justificarlo.

---

# 59. CONDICIÓN ANTES DE IMPLEMENTAR

Antes de escribir o modificar código, entregar:

1. arquitectura actual;
2. arquitectura propuesta;
3. estructura de directorios propuesta;
4. flujo de auditoría;
5. flujo Docker;
6. flujo ZIP;
7. flujo IA;
8. matriz de plataformas;
9. matriz de versiones;
10. estrategia de regresiones;
11. estrategia de seguridad;
12. estrategia de reportes;
13. estrategia de Dokploy;
14. archivos que serán modificados;
15. archivos nuevos;
16. riesgos;
17. plan de migración.

No comenzar una refactorización grande sin presentar primero este plan.

# OBJETIVO FINAL

Construir un laboratorio local reproducible donde yo pueda simplemente entregar un ZIP oficial de Chilexpress y obtener una respuesta técnica y demostrable sobre su compatibilidad con Magento, PrestaShop, WooCommerce o Shopify.

El sistema debe poder evolucionar posteriormente hacia Dokploy.

Debe poder utilizar modelos de IA, especialmente Anthropic Claude, para mejorar el análisis cuando yo configure mi propia API key.

Pero incluso sin IA debe realizar correctamente toda la auditoría determinista.

Nunca ocultar un error para conseguir PASS.

Nunca declarar compatible algo únicamente porque un modelo de IA lo considere compatible.

La evidencia reproducible de Docker y las pruebas automatizadas tienen prioridad sobre cualquier opinión generada por IA.
