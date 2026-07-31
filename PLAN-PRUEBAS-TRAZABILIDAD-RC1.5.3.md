# Plan de pruebas · Trazabilidad Radar · Gerizim RC1.5.3

## Importación

- JSON inválido no genera evento.
- Validación sin persistencia no genera evento.
- Workflow no persistido no genera evento.
- Importación Radar persistida genera una sola intención.
- Repetición no duplica.
- Timestamp UTC válido.
- Brief histórico 16 excluido.
- Workflow manual no participa.
- Workflow recurrente no participa.
- `radar_source` incorrecto no participa.
- Falta de marca estructurada no permite eventos posteriores.

## Creación

- Error de `wp_insert_post()` no genera evento.
- Post `pending` genera `wordpress_post_created`.
- Nunca se genera `wordpress_draft_created`.
- ID procede de WordPress.
- Brief y workflow proceden de metadatos estructurados.
- Fecha inválida deja el evento bloqueado.
- Repetición no duplica.
- Post 36565 excluido.
- Creación depende de importación y no se entrega antes.

## Publicación

- `pending → publish` genera evento.
- `publish → publish` no genera evento.
- Edición de publicado no genera evento.
- Post normal no genera evento.
- Publicación sin creación trazada queda bloqueada.
- Publicación espera la entrega de creación.
- Fecha de publicación difiere de creación y se conserva.
- Workflow histórico excluido.

## Dependencias

- Padre `retry` mantiene hijo esperando.
- Padre `failed` bloquea hijo con `dependency_not_sent`.
- Padre `blocked` bloquea hijo sin consumir intentos.
- Padre inexistente produce `missing_dependency`.
- Padre recuperado a `sent` reactiva hijo.
- Orden conservado después de reiniciar el worker.

## Transporte

- HTTP 201 + `traceability_event_recorded` → `sent`.
- HTTP 200 + `traceability_event_already_recorded` → `sent`.
- HTTP 400/401/403/409 → `failed`.
- timeout, red, 429 y 5xx → `retry` hasta ocho intentos.
- El octavo fallo temporal → `failed`.
- Una fila `sent` no vuelve a enviarse.
- Token ausente de logs, tabla, workflow y meta.
- Payload y clave no cambian en reintentos.

## Reintentos

- Secuencia: inmediato, 1m, 5m, 15m, 1h, 3h, 6h, 12h.
- Espera por dependencia no incrementa `attempts`.
- Bloqueo por corte no incrementa `attempts`.
- Recuperación de `sending` no altera payload ni clave.

## Fecha de corte

- Corte ausente → `blocked` con `invalid_live_capture_cutoff`.
- Corte inválido → `blocked`.
- No se usa hora actual como fallback.
- Corregir corte reactiva eventos posteriores.
- Eventos anteriores al corte no se reactivan.

## Concurrencia

- Dos workers no adquieren la misma fila.
- Actualización final exige `lock_token` correcto.
- `sending` abandonado por 15 minutos vuelve a `retry`.
- Índice único impide duplicados.

## Reconciliación

- Outbox `sent` corrige reflejo `queued`.
- Outbox `failed` corrige reflejo `retry`.
- Meta incorrecto no altera outbox.
- Dependencia enviada desbloquea hijo.
- Reconciliar no ejecuta HTTP.

## Migración y regresión

- Tabla creada con todos los campos e índices.
- Migración ejecutable en activación y arranque.
- Plugin opera normalmente con captura y entrega desactivadas.
- No se altera flujo editorial, estado `pending`, prompts ni actualizaciones recurrentes.
- No se realizan conexiones reales durante pruebas estáticas/mocks.
