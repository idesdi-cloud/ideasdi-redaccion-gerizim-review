# Especificación de trazabilidad · RC1.5.6

## Arquitectura

```text
Radar editorial / Directus
        ↓ brief exportado
ideasDi Redacción Gerizim en WordPress
        ↓ workflow editorial
entrada WordPress pending
        ↓ revisión humana y publicación
outbox local ordenado e idempotente
        ↓ entrega futura, cuando se habilite
Radar / Directus
```

La tabla outbox es la fuente de verdad. Workflow y post meta son reflejos verificables. Fallos de trazabilidad nunca revierten el flujo editorial.

## Configuración

Constantes o variables de entorno:

- `IDG_TRACEABILITY_CAPTURE_ENABLED`, predeterminado `false`;
- `IDG_TRACEABILITY_DELIVERY_ENABLED`, predeterminado `false`;
- `IDG_RADAR_TRACEABILITY_URL`;
- `IDG_RADAR_TRACEABILITY_TOKEN`;
- `IDG_TRACEABILITY_LIVE_CAPTURE_STARTED_AT`;
- `IDG_TRACEABILITY_CONTRACT_VERSION`, predeterminado `1.1`.

El token solo existe en memoria durante el transporte. No se persiste en opciones, workflows, post meta, outbox, HTML o logs.

## Eventos y orden

Únicos eventos:

1. `gerizim_imported`;
2. `wordpress_post_created`;
3. `wordpress_published`.

Dependencias:

```text
gerizim_imported
        ↓
wordpress_post_created
        ↓
wordpress_published
```

Solo participan workflows Radar. Quedan fuera workflows manuales, posts normales, Actualizaciones recurrentes e identidades históricas excluidas.

## Fechas

- `radar_exported_at`: Radar produjo el brief.
- `radar_imported_at_utc`: Gerizim persistió la importación.
- `observed_at`: Gerizim observó o encoló el evento.

`gerizim_imported.occurred_at` usa exclusivamente `radar_imported_at_utc`.

Una identidad nueva recibe la hora UTC de la importación real, aunque captura esté apagada. El mismo brief nunca recibe una hora nueva. Si solo existe `radar_imported_at`, se convierte desde la zona horaria de WordPress. Si no existe una fecha fiable, se bloquea con `invalid_import_occurred_at`.

Las fechas anteriores al corte `2026-07-04T14:53:06.000Z` no crean outbox ni recaptura.

## Identidad del workflow

### Mismo brief

Conserva:

- `workflow_id`;
- `radar_imported_at_utc`;
- clave de `gerizim_imported`;
- payload ganador;
- fila outbox.

Una contradicción contractual se conserva como conflicto.

### Brief diferente

Crea un workflow nuevo. No hereda fecha, claves, estados, historial ni outbox. Si se inició desde un Reinicio parcial, el workflow anterior se restaura y verifica antes de abrir el nuevo.

## Snapshot de Reinicio parcial

El registro de opción contiene:

- versión;
- `workflow_id`;
- snapshot completo;
- SHA-256 normalizado;
- fecha UTC de almacenamiento.

Secuencia:

1. guardar mediante `add_option()` no autoload;
2. releer y verificar hash;
3. solo entonces ejecutar el Reinicio parcial;
4. al importar otro brief, restaurar snapshot;
5. releer workflow;
6. comparar todos los campos y hash;
7. eliminar snapshot;
8. releer la opción para confirmar la eliminación.

Cualquier fallo conserva la evidencia recuperable.

## Outbox y migración

Tabla: `{$wpdb->prefix}idg_traceability_outbox`.

Campos contractuales y operativos:

- identidad: `id`, `idempotency_key`, `event_type`;
- contenido: `payload_json`;
- estado: `status`, `attempts`, `next_attempt_at`;
- errores: `last_http_status`, `last_error`, `last_transport_error`;
- tiempo: `created_at`, `updated_at`, `sent_at`;
- causalidad: `dependency_key`;
- concurrencia: `lock_token`, `locked_at`;
- reflejos: `reflection_synced_at`, `reflection_last_error`, `reflection_attempts`, `reflection_last_attempt_at`.

Estados: `queued`, `sending`, `retry`, `failed`, `sent`, `blocked`.

La migración se ejecuta en activación y arranque cuando la versión guardada es inferior. Verifica error SQL, tabla, columnas, tipos, nulabilidad, índices y una prueba temporal de inserción/lectura/borrado. La versión solo se acepta después de persistirla y releerla.

## Recaptura

Cada intención usa una opción individual:

```text
idg_traceability_recapture_event_{sha256(idempotency_key)}
```

Se crea atómicamente con `add_option()` y `autoload=no`. `observed_at` puede variar sin crear conflicto; `occurred_at` y el resto del contrato permanecen inmutables.

Una intención se elimina solo cuando:

- existe una fila compatible en outbox; o
- acaba de insertarse una fila compatible;
- y la eliminación de la opción se verifica por relectura.

Las contradicciones se conservan como `conflict`.

Si falla también la opción de recaptura, se guarda una marca alternativa con payload y SHA-256. Para `wordpress_published`, queda en `_idg_traceability_recapture_failure` y el reconciliador la recorre con cursor estable.

## Locks y cola

El reclamo `queued/retry → sending` es atómico. Solo el mismo `lock_token` puede finalizar. Un lock abandonado se recupera después de 15 minutos sin consumir intentos.

La selección de entrega filtra en SQL las dependencias antes de aplicar el límite. Hijos no enviables no pueden ocultar una raíz posterior lista.

Una dependencia:

- en `queued`, `sending` o `retry`: hace esperar;
- en `failed` o `blocked`: bloquea al hijo;
- ausente: `missing_dependency`;
- en `sent`: libera al hijo.

## Reintentos

Ocho intentos HTTP totales:

1. inmediato;
2. +1 minuto;
3. +5 minutos;
4. +15 minutos;
5. +1 hora;
6. +3 horas;
7. +6 horas;
8. +12 horas.

Se reintentan red, timeout, 429 y 5xx. No se reintentan 400, 401, 403, 409 contractual ni respuestas incompatibles. El octavo fallo temporal termina en `failed` con `retry_limit_reached` y conserva el último error de transporte por separado.

## Reflejos y reconciliación

La reconciliación es paginada por cursor y consulta solo trabajo pendiente. No recorre indefinidamente filas terminales ya sincronizadas.

Para cada fila:

1. excluir `sending` con lock activo;
2. recordar `status`, `updated_at`, `lock_token` y `locked_at`;
3. escribir workflow o post meta;
4. releer el reflejo;
5. releer el outbox;
6. comprobar que la versión no cambió;
7. marcar `reflection_synced_at` con una actualización condicional y sin lock.

Fallo de persistencia o carrera deja la fila pendiente.

## Transporte

`wp_remote_post()` envía JSON por HTTPS con `X-Radar-Traceability-Token`. HTTP solo se permite para localhost o desarrollo explícito. Una respuesta exitosa debe usar `result` —o temporalmente `code`— y devolver exactamente la misma `idempotency_key`.

## Exclusiones

Nunca capturar:

- `brief_id = 16`;
- `post_id = 36565`;
- `workflow_id = idg_8bcd8df4-38ad-4e3b-ade1-b5a51c583455`;
- eventos anteriores al corte live;
- Actualizaciones recurrentes;
- workflows no Radar.

## Staging

Instalar inicialmente con captura y entrega apagadas. Verificar versión, esquema, panel y flujo editorial `pending`. No activar entrega hasta disponer de receptor Directus compatible con contrato 1.1.

## Rollback

1. desactivar captura y entrega;
2. reemplazar el plugin por el ZIP anterior validado;
3. no borrar la tabla outbox, recapturas, snapshots ni marcas recuperables;
4. revisar diagnóstico antes de cualquier reactivación;
5. no reinterpretar filas históricas.

## Limitaciones

La validación de esta entrega usa PHP aislado, mocks y fixtures. No equivale a una ejecución en WordPress completo, MySQL real, `dbDelta()` real, Action Scheduler, WP-Cron o Directus.
