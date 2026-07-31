# Especificación de trazabilidad · RC1.5.5

## Alcance

RC1.5.5 endurece la trazabilidad contrato 1.1 sin modificar el motor editorial. Captura y entrega permanecen desactivadas por defecto.

## Eventos y orden

1. `gerizim_imported`.
2. `wordpress_post_created` con estado `pending`.
3. `wordpress_published` durante una transición real a `publish`.

`wordpress_post_created` depende de `gerizim_imported`; `wordpress_published` depende de `wordpress_post_created`. Las dependencias se conservan en el outbox después de reinicios.

## Elegibilidad

Solo participan workflows con:

- `radar_source = radar-editorial-ideasdi`;
- `radar_brief_id` válido;
- `workflow_id` válido;
- marca estructurada de importación persistida.

Se excluyen todos los workflows manuales, posts ajenos y todas las Actualizaciones recurrentes antes de crear outbox o recaptura.

## Fechas

- `radar_exported_at`: exportación en Radar; solo evidencia.
- `radar_imported_at_utc`: persistencia real en Gerizim; se guarda siempre, aunque captura esté apagada.
- `observed_at`: captura o puesta en cola.

`gerizim_imported.occurred_at` procede exclusivamente de `radar_imported_at_utc` o, para datos históricos compatibles, de una conversión fiable de `radar_imported_at` local.

No se usa la hora actual como sustituto de una fecha original ausente. Una fecha anterior al corte live excluye el evento sin outbox ni recaptura. Una fecha ausente o no convertible conserva una intención bloqueada con `invalid_import_occurred_at`.

Para el mismo brief, la ausencia simultánea de `radar_imported_at_utc` y una `radar_imported_at` convertible bloquea la actualización antes de modificar el workflow. `current_time('mysql')` solo se asigna al importar una identidad Radar nueva. La marca de persistencia actual en UTC solo se crea durante esa primera importación; las identidades existentes conservan su fecha original.

## Identidad Radar

- El mismo brief conserva `workflow_id`, `radar_imported_at_utc`, clave idempotente y evento del outbox.
- Los cambios editoriales del mismo brief no crean otra importación.
- Una contradicción en identidad o payload inmutable produce `idempotency_payload_conflict`.
- Un `radar_brief_id` diferente crea un workflow nuevo; el anterior conserva fechas, historial, claves y estados.
- Reinicio parcial se usa para continuar el mismo brief. Si después se importa otro brief, el snapshot restaura el workflow anterior y el nuevo brief se abre en otro workflow.
- El snapshot es un registro no autoload versionado, contiene SHA-256 y se relee antes de ejecutar el Reinicio parcial. La restauración compara el hash y todos los campos protegidos; el snapshot solo se borra después de confirmar la restauración completa.

## Outbox

La tabla `{$wpdb->prefix}idg_traceability_outbox` es la fuente de verdad. Workflow y post meta solo reflejan clave, estado y fecha de sincronización.

La migración DB `1.2.0` verifica antes de guardar versión:

- tabla;
- columnas, tipos y nulabilidad mínima;
- índice único exclusivo de `idempotency_key`;
- índices operativos;
- error inmediato de `dbDelta()`;
- prueba escritura, lectura y borrado sin residuo.

La cola usa locks atómicos, recupera `sending` abandonados después de 15 minutos y filtra dependencias antes de aplicar el límite para evitar starvation.

## Recaptura durable

Cada intención usa una opción no autoload independiente:

`idg_traceability_recapture_event_{sha256(idempotency_key)}`

`add_option()` evita que dos solicitudes sobrescriban la misma clave. Las intenciones diferentes no comparten un array. `observed_at` no participa en la comparación; `occurred_at` sí.

El cursor `idg_traceability_recapture_cursor` no comparte el prefijo de eventos y no participa en `count()`, `option_rows()` ni `problem_rows()`. Si falla tanto el outbox como la opción de recaptura, Gerizim registra un error operativo explícito y guarda un marcador alternativo verificable con el payload en el workflow o post meta.

## Reintentos

Máximo 8 intentos: inmediato, +1 min, +5 min, +15 min, +1 h, +3 h, +6 h y +12 h. El octavo fallo temporal termina en `failed`, `last_error=retry_limit_reached` y conserva `last_transport_error`.

## Contrato HTTP

Campo oficial: `result`. Compatibilidad temporal: `code`.

- HTTP 201 + `result=traceability_event_recorded`.
- HTTP 200 + `result=traceability_event_already_recorded`.

Toda respuesta exitosa debe devolver `idempotency_key` exactamente igual a la enviada. HTTP 400/401/403/409 son terminales; timeout, red, 429 y 5xx son temporales.

## Administración y reconciliación

La reconciliación usa cursores por trabajo, lotes y ciclos. Solo revisa dependencias, corte, reflejos pendientes y recapturas. Excluye filas `sending` con lock activo. Después de escribir un reflejo, relee workflow o post meta y confirma que `status` y `updated_at` no cambiaron antes de marcar `reflection_synced_at`; si cambiaron, la fila queda pendiente para el ciclo siguiente.

Las filas `failed` o `blocked` solo pueden reactivarse individualmente, después de revisión, mediante POST, nonce y `manage_options`. No existe reintento masivo de errores contractuales.
