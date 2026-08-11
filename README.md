# ideasDi Redacción Gerizim v0.4.0-RC1.6.3.2

Plugin interno para el flujo editorial de ideasDi: importación de briefs desde Radar/Directus, investigación, receta editorial, planificación, redacción, revisiones, validación y creación o actualización controlada de contenido WordPress.

## RC1.6.3.2 · Corrección mínima del retorno OpenAI

Candidata correctiva construida desde la semántica funcional de RC1.6.3. Mantiene las llamadas OpenAI síncronas y las transiciones visibles del flujo. No incorpora background Responses, polling, reentrada, estados de planificación ni scheduler para las fases editoriales.

El único cambio productivo, además del número de versión, está en `IDG_OpenAI_Client`: añade correlación segura de solicitudes, mensajes HTTP accionables y recuperación de una Response ya completada cuando el cuerpo recibido es válido y utilizable aunque el estado HTTP sea anómalo. No se crean llamadas adicionales.

RC1.6.3.1 queda expresamente como versión no aprobada y se usa solo como evidencia del intento asíncrono descartado.


## RC1.6.3 · Planificación y redacción desacopladas

Esta versión continúa la migración progresiva separando la preparación documental y del plan editorial de las fases de redacción. La separación se implementa mediante contratos compatibles, adaptadores internos transparentes, dos pipelines y un orquestador delgado.

`IDG_Workflow_Planning_Pipeline` conserva investigación, ficha documental, receta y plan editorial. `IDG_Workflow_Redaction_Pipeline` conserva generación, revisión editorial y revisión SEO. `IDG_Job_Runner` mantiene persistencia y publicación, pero ya no contiene prompts, llamadas al modelo ni construcción del plan.

El workflow sigue siendo el mismo array `legacy-array-v1`; no se añaden envolturas ni migraciones. Prompts, interfaz, ocho llamadas OpenAI, validaciones, Gutenberg, creación `pending`, actualización recurrente y trazabilidad permanecen sin cambios editoriales intencionales.

RC1.6.2 continúa como la fuente única de estados, transiciones, elegibilidad, bloqueos y reintentos. RC1.6.1 continúa seleccionando las siete acciones mediante el centro de estrategias.

## Corrección RC1.6.0.2

Esta versión habilita el recorrido completo de **Concursos o convocatorias** dentro de `Gerizim → Actualizaciones recurrentes`.

Después de seleccionar una entrada existente por su ID exacto, el módulo permite:

1. analizar la fuente nueva y comparar los datos;
2. aplicar sobre la misma entrada el título, slug, fechas y enlace oficial confirmados;
3. preparar un workflow editorial normal de Gerizim;
4. generar, revisar y optimizar la nueva redacción con el flujo existente;
5. aplicar la versión final sobre el mismo artículo seleccionado.

No se crea una publicación ni una edición nueva. La fase estructural conserva contenido, extracto, estado, autor, categoría, etiquetas e imagen destacada. La fase editorial reutiliza los prompts, receta, validaciones, revisión SEO, Gutenberg y guard final existentes; únicamente sustituye el título, contenido y extracto cuando el editor confirma la versión final.

La escritura se limita a entradas `post` que continúan perteneciendo a la categoría interna **Concursos y convocatorias**, ID `34`. Antes de aplicar datos o redacción se verifican el ID seleccionado, el tipo de publicación, la categoría, la firma del estado analizado y los permisos del usuario.

## Objetivo de RC1.6.0

RC1.6.0 inicia la migración progresiva de la arquitectura interna sin alterar el comportamiento editorial. Introduce:

- un contrato explícito para el formato histórico `legacy-array-v1`;
- contratos PHP para adaptadores de entrada y orquestación;
- adaptadores transparentes para formulario administrativo, Radar, Actualizaciones recurrentes y trazabilidad;
- un orquestador delgado que delega creación, persistencia, cola y ejecución en `IDG_Job_Runner`.

El array almacenado en `wp_options` conserva su estructura histórica. No hay migración de workflows existentes ni cambios de esquema. `IDG_Job_Runner` continúa disponible como API compatible.

Se mantienen sin cambios editoriales intencionales los prompts, el cliente OpenAI, el número de llamadas, las validaciones, el guard final, la creación normal de entradas `pending` y la trazabilidad RC1.5.7. RC1.6.0.2 modifica únicamente el enrutamiento de Actualizaciones recurrentes necesario para que los concursos usen la misma arquitectura editorial ya disponible.

## Configuración

```php
define('IDG_TRACEABILITY_CAPTURE_ENABLED', false);
define('IDG_TRACEABILITY_DELIVERY_ENABLED', false);
define('IDG_RADAR_TRACEABILITY_URL', '');
define('IDG_RADAR_TRACEABILITY_TOKEN', '');
define('IDG_TRACEABILITY_LIVE_CAPTURE_STARTED_AT', '2026-07-04T14:53:06.000Z');
define('IDG_TRACEABILITY_CONTRACT_VERSION', '1.1');
// Solo para desarrollo local controlado:
define('IDG_TRACEABILITY_ALLOW_INSECURE_HTTP', false);
```

También se aceptan variables de entorno con los mismos nombres. El token nunca se guarda en opciones, metadatos, workflows, outbox, HTML ni logs.

## Contrato HTTP oficial

El receptor debe responder:

- HTTP 201 + `result = traceability_event_recorded`;
- HTTP 200 + `result = traceability_event_already_recorded`.

Durante una transición temporal, Gerizim también puede leer `code` si `result` no existe. Toda respuesta de éxito debe devolver `idempotency_key` idéntica a la enviada.

## Orden causal

```text
gerizim_imported
    ↓
wordpress_post_created
    ↓
wordpress_published
```

La tabla outbox conserva dependencias y locks después de reinicios.

## Seguridad del esquema

La versión de base de datos solo se actualiza después de verificar:

- tabla disponible;
- columnas requeridas;
- índice único de `idempotency_key`;
- ausencia de errores SQL.

Con esquema incompleto, no se procesa la cola.

## Recaptura durable

Si una captura no logra insertarse en el outbox, la intención mínima queda en un registro persistente para reconstrucción posterior. Se conservan las fechas originales, especialmente `_idg_published_at_utc` para publicaciones.

Las intenciones usan el prefijo `idg_traceability_recapture_event_`, independiente del cursor. Si también falla ese almacenamiento, se registra un error operativo y queda un marcador alternativo verificable con payload y SHA-256. Las marcas de publicación se recuperan por lotes desde post meta.

## Reintentos

Ocho intentos totales: inmediato, +1 minuto, +5 minutos, +15 minutos, +1 hora, +3 horas, +6 horas y +12 horas. El octavo fallo temporal termina con:

- `status = failed`;
- `last_error = retry_limit_reached`;
- último error HTTP/red en `last_transport_error`.

## URL de entrega

- HTTPS obligatorio.
- HTTP permitido en localhost.
- HTTP remoto solo con configuración explícita de desarrollo.

## Panel administrativo

Gerizim → Ajustes → Trazabilidad Radar muestra diagnóstico, conteos, recapturas pendientes, filas fallidas o bloqueadas y el resultado real de `Procesar cola ahora`. No se muestran errores SQL completos, tokens, payloads ni cabeceras HTTP.

## Reinicio parcial e importación Radar

El mismo brief puede continuar en su workflow conservando fecha y clave. Si perdió todas las fechas originales, la reimportación se bloquea con `invalid_import_occurred_at`. Un brief diferente crea una nueva redacción; si hubo Reinicio parcial, el workflow anterior se restaura desde un snapshot con SHA-256 antes de abrir el nuevo.

## Documentación incluida

- `CAMBIOS-v0.4.0-RC1.6.3.md`;
- `PRUEBAS-v0.4.0-RC1.6.3.md`;
- `CAMBIOS-v0.4.0-RC1.6.2.md`;
- `PRUEBAS-v0.4.0-RC1.6.2.md`;
- `CAMBIOS-v0.4.0-RC1.6.0.2.md`;
- `PRUEBAS-v0.4.0-RC1.6.0.2.md`;
- `CAMBIOS-v0.4.0-RC1.6.0.1.md`;
- `PRUEBAS-v0.4.0-RC1.6.0.1.md`;
- `CAMBIOS-v0.4.0-RC1.6.0.md`;
- `PRUEBAS-v0.4.0-RC1.6.0.md`;
- `CONTRATO-RADAR-DIRECTUS-1.1.md`;
- `REGRESION-EDITORIAL-RC1.6.3.sha256`;
- `REGRESION-EDITORIAL-RC1.6.2.sha256`;
- `REGRESION-EDITORIAL-RC1.6.1.sha256`;
- `REGRESION-EDITORIAL-RC1.6.0.2.sha256`;
- `REGRESION-EDITORIAL-RC1.5.6.sha256`.

## Instalación controlada

1. Respaldar el plugin actualmente instalado y la base de datos.
2. Reemplazarlo por el ZIP RC1.6.3.
3. Confirmar versión **0.4.0-RC1.6.3**.
4. No modificar las constantes existentes de captura, entrega, URL, token o corte.
5. Confirmar que los workflows existentes abren y conservan su contenido.
6. Probar primero con un concurso cuyo ID, categoría, fechas y fuente oficial puedan verificarse.
7. Aplicar la actualización estructural y comprobar que permanece el mismo ID de WordPress.
8. Preparar la redacción, recorrer las etapas normales y revisar la publicación antes de confirmar la aplicación final.
