# Especificación técnica · Trazabilidad Radar · Gerizim RC1.5.3

## 1. Alcance

RC1.5.3 añade una capa aislada de trazabilidad entre el plugin Gerizim en WordPress y Radar editorial/Directus. No modifica la generación editorial ni cambia el estado de creación de las entradas: WordPress continúa creando entradas `pending` para revisión humana.

Captura y entrega están desactivadas por defecto. El outbox local es la única fuente de verdad; workflow y post meta son reflejos reconciliables.

## 2. Elegibilidad

Solo participan workflows que cumplan simultáneamente:

- `radar_source = radar-editorial-ideasdi`;
- `radar_brief_id` numérico mayor que cero;
- `workflow_id` válido con prefijo `idg_`;
- marca estructurada de importación persistida (`radar_imported_at_utc` y clave idempotente);
- contrato compatible `1.1`;
- no ser actualización recurrente;
- no coincidir con exclusiones históricas.

No se instrumentan workflows manuales, actualizaciones recurrentes, Eventos existentes ni posts ajenos a Gerizim.

## 3. Eventos y orden obligatorio

1. `gerizim_imported`
2. `wordpress_post_created`, dependiente de `gerizim_imported`
3. `wordpress_published`, dependiente de `wordpress_post_created`

Las dependencias se persisten mediante `dependency_key` y se respetan tras reinicios.

## 4. Contrato HTTP 1.1

Respuesta JSON exacta:

```json
{"code":"traceability_event_recorded"}
```

para HTTP 201, y:

```json
{"code":"traceability_event_already_recorded"}
```

para HTTP 200.

Ambas respuestas son éxito y dejan la fila local en `sent`.

- HTTP 400: fallo terminal de validación.
- HTTP 401/403: fallo terminal de autenticación/autorización.
- HTTP 409: conflicto real, nunca duplicado idempotente.
- HTTP 429 y 500–599: error temporal.
- timeout/error de red: error temporal.

## 5. Reintentos

Máximo de ocho intentos HTTP totales:

1. inmediato;
2. +1 minuto;
3. +5 minutos;
4. +15 minutos;
5. +1 hora;
6. +3 horas;
7. +6 horas;
8. +12 horas.

Tras un fallo temporal en el octavo intento, el evento pasa a `failed`. Esperar dependencias, estar bloqueado o recuperar una fila abandonada no consume intentos. El payload, `observed_at` y la clave idempotente no cambian durante reintentos.

## 6. Dependencias

- Padre `queued`, `sending` o `retry`: el hijo espera sin consumir intentos.
- Padre `failed` o `blocked`: el hijo pasa a `blocked` con `dependency_not_sent`.
- Padre inexistente: el hijo pasa a `blocked` con `missing_dependency`.
- Si el padre llega a `sent`, el reconciliador reactiva a `queued` únicamente hijos bloqueados por `dependency_not_sent` o `missing_dependency` cuya dependencia ya exista y esté enviada.

## 7. Fecha de corte

La captura habilitada exige `IDG_TRACEABILITY_LIVE_CAPTURE_STARTED_AT` ISO 8601 UTC válido, terminado en `Z` o `+00:00`. El corte efectivo es el mayor entre el valor configurado y el mínimo histórico protegido.

- Corte ausente o inválido: el evento futuro se conserva como `blocked` con `invalid_live_capture_cutoff`; no se envía ni se usa la hora actual.
- Al corregirse el corte, el reconciliador activa eventos cuyo `occurred_at` sea igual o posterior.
- Eventos anteriores al corte no se capturan cuando el corte es válido.
- Las exclusiones históricas tienen precedencia.

## 8. Exclusiones históricas

No se insertan eventos para:

- `brief_id = 16`;
- `post_id = 36565`;
- `workflow_id = idg_8bcd8df4-38ad-4e3b-ade1-b5a51c583455`;
- cualquier evento con fecha anterior a `2026-07-04T14:53:06.000Z`, aunque se configure un corte más antiguo.

No se modifican registros históricos ni se ejecutan backfills.

## 9. Outbox y concurrencia

Tabla: `{$wpdb->prefix}idg_traceability_outbox`.

Campos:

- `id`;
- `idempotency_key`;
- `event_type`;
- `payload_json`;
- `status`;
- `attempts`;
- `next_attempt_at`;
- `last_http_status`;
- `last_error`;
- `created_at`;
- `updated_at`;
- `sent_at`;
- `dependency_key`;
- `lock_token`;
- `locked_at`.

Índices:

- único por `idempotency_key`;
- `status, next_attempt_at`;
- `dependency_key`;
- `locked_at`.

El reclamo es atómico mediante actualización condicional y `lock_token`. Filas `sending` abandonadas durante más de 15 minutos vuelven a `retry` sin alterar payload ni clave.

## 10. Migración

- Constante: `IDG_TRACEABILITY_DB_VERSION`.
- Opción: `idg_traceability_db_version`.
- `dbDelta()` en activación y durante el arranque cuando exista una versión pendiente.
- Migraciones solo aditivas; no eliminan datos.

## 11. Fuente de fechas

- `gerizim_imported`: UTC capturada después de persistir y verificar el workflow importado.
- `wordpress_post_created`: `get_post_datetime($post, 'date', 'gmt')`; fallback a fecha local persistida convertida a UTC. Una fecha inválida deja el evento `blocked`.
- `wordpress_published`: UTC capturada y guardada inmediatamente durante la transición real a `publish`.
- `observed_at`: UTC fijada durante la captura y conservada en reintentos.

## 12. Reflejos y reconciliación

El outbox manda. Workflow y post meta reflejan:

- clave idempotente;
- estado;
- fecha UTC de sincronización.

La reconciliación es unidireccional `outbox → workflow/post meta`, repara diferencias, libera dependencias y no realiza conexiones HTTP.

## 13. Seguridad

- Token solo desde constante o entorno.
- Nunca en opciones, meta, workflows, outbox, HTML o logs.
- No se almacenan artículos, prompts, investigación ni material editorial completo en el outbox.
- Errores resumidos y truncados.
- Acciones administrativas con `manage_options`, nonce y POST.

## 14. Scheduler

Hook independiente de trazabilidad. Action Scheduler cuando esté disponible; WP-Cron como fallback. Los schedulers solo despiertan el worker; el orden depende exclusivamente del outbox.


## 15. Reflejos estructurados

Workflow importado:

- `radar_imported_at_utc`;
- `traceability_gerizim_imported_key`;
- `traceability_gerizim_imported_status`;
- `traceability_gerizim_imported_synced_at_utc`.

Post WordPress:

- `_idg_radar_brief_id`;
- `_idg_radar_hallazgo_id`;
- `_idg_workflow_id`;
- `_idg_traceability_contract_version`;
- `_idg_traceability_post_created_key`;
- `_idg_traceability_post_created_status`;
- `_idg_traceability_post_created_synced_at_utc`;
- `_idg_traceability_published_key`;
- `_idg_traceability_published_status`;
- `_idg_traceability_published_synced_at_utc`;
- `_idg_published_at_utc`.

Los reflejos no pueden sobrescribir el outbox. Solo la reconciliación `outbox → workflow/post meta` puede reparar sus estados.

## 16. Idempotencia local

Una clave existente conserva el payload ya persistido. Repeticiones y capturas concurrentes recuperan la fila ganadora. Si la misma clave representa otra identidad contractual, la inserción se rechaza con `idempotency_payload_conflict`; nunca se sobrescribe el payload original.
