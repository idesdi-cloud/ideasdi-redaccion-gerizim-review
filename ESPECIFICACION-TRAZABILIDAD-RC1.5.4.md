# Especificación técnica de trazabilidad · RC1.5.4

## Alcance

RC1.5.4 corrige y endurece la infraestructura de trazabilidad añadida en RC1.5.3. No modifica el motor editorial, prompts, investigación, biblioteca disciplinar, estado `pending` ni publicación humana.

## Contrato HTTP oficial

El receptor Radar responde JSON con `result` como campo canónico:

- HTTP 201 + `result: traceability_event_recorded`.
- HTTP 200 + `result: traceability_event_already_recorded`.
- Durante la transición, el cliente puede leer `code` si `result` no existe.
- Toda respuesta exitosa debe devolver `idempotency_key` exactamente igual a la enviada.
- Una clave diferente o ausente no marca el evento como `sent`.
- HTTP 400, 401, 403 y 409 son terminales.
- HTTP 429, timeout, red y 5xx son temporales.

## Migración y salud de esquema

`IDG_TRACEABILITY_DB_VERSION` y la opción `idg_traceability_db_version` versionan el esquema. `dbDelta()` no autoriza por sí solo actualizar la opción. Antes se verifican:

- tabla existente;
- columnas requeridas;
- índice único de `idempotency_key`;
- ausencia de error de base de datos.

Con esquema incompleto, captura directa al outbox y procesamiento quedan bloqueados. La intención de captura se conserva en el registro persistente de recaptura.

## Recaptura durable

Cuando no puede insertarse una intención en el outbox, se conserva de forma persistente y no autoload:

- tipo de evento;
- `idempotency_key`;
- `occurred_at` y `observed_at` originales;
- brief, workflow y post;
- payload contractual mínimo;
- dependencia, estado inicial y causa.

El reconciliador procesa esta lista por lotes y reconstruye las filas ausentes sin recalcular fechas. La publicación guarda `_idg_published_at_utc` antes del intento de inserción, para poder recuperar el evento con la fecha real de transición.

## Idempotencia local

La comparación ignora solo `observed_at`. `occurred_at` forma parte de la identidad del payload. La misma clave con otra fecha efectiva produce `idempotency_payload_conflict`.

## URL de entrega

- HTTPS obligatorio.
- HTTP permitido para localhost, 127.0.0.1 o ::1.
- HTTP remoto solo con `IDG_TRACEABILITY_ALLOW_INSECURE_HTTP=true` en entorno local/development.
- URL inválida se rechaza antes de `wp_remote_post()`.

## Outbox, locks y reconciliación

- `set_blocked()` solo opera sobre `queued`, `retry` o `blocked`, sin lock activo.
- Los workers reclaman filas de forma atómica mediante `lock_token` y `locked_at`.
- `sending` abandonado más de 15 minutos vuelve a `retry`.
- Reconciliación por lotes.
- Reflejos se procesan solo cuando `reflection_synced_at` es nulo o anterior a `updated_at`.
- No se recorren todas las filas `sent` en cada ejecución.

## Reintentos

Ocho intentos totales: inmediato, +1m, +5m, +15m, +1h, +3h, +6h y +12h. Tras el octavo fallo temporal:

- `status=failed`;
- `last_error=retry_limit_reached`;
- `last_transport_error` conserva el último error HTTP/red.

## Administración

La sección Trazabilidad Radar muestra filas `failed` y `blocked` sin exponer payloads completos. Cada fila puede reactivarse individualmente mediante POST, nonce y `manage_options`. No existe reintento masivo de errores contractuales.

## Reinicio parcial e importación Radar

El reinicio parcial deja el workflow preparado para reemplazar los datos base mediante un nuevo brief Radar. El importador permite esa sustitución solo cuando existe la marca estructurada `radar_reimport_allowed`; después de importar la elimina.
