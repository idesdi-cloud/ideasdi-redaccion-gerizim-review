# Plan de pruebas · trazabilidad RC1.5.5

## Importación e identidad

- Captura apagada conserva `radar_imported_at_utc` y no crea outbox.
- `radar_exported_at` nunca se usa como `occurred_at`.
- Activación posterior reconstruye con la fecha original.
- Fecha anterior al corte no crea evento.
- Fecha ausente o inválida conserva recaptura bloqueada.
- Mismo brief conserva workflow, fecha, clave y evento.
- Mismo brief histórico sin fecha UTC ni fecha local convertible se bloquea con `invalid_import_occurred_at`.
- Cambios editoriales del mismo brief no generan conflicto.
- `occurred_at` contradictorio genera `idempotency_payload_conflict`.
- Brief diferente crea workflow nuevo y no hereda trazabilidad.
- Reinicio parcial seguido de otro brief conserva el workflow anterior.
- Fallo de almacenamiento del snapshot impide ejecutar el Reinicio parcial.
- Fallo de restauración conserva el snapshot; una restauración completa verifica hash y campos antes de borrarlo.
- Actualizaciones recurrentes no crean ningún evento.

## Outbox, migración y concurrencia

- Migración completa guarda DB version 1.2.0.
- Tabla parcial, índice faltante, tipo incompatible o fallo de escritura no guardan versión.
- Prueba técnica escritura/lectura/borrado no deja residuo.
- Dos intenciones distintas no se sobrescriben.
- Un cursor aislado no cuenta como recaptura pendiente ni aparece entre problemas.
- Misma clave compatible no cambia payload ni `occurred_at`.
- Misma clave incompatible queda en conflicto.
- Publicación perdida se reconstruye.
- Si también falla la persistencia de recaptura, se registra error operativo y queda un marcador recuperable con el payload de `wordpress_published`.
- Fila `sending` con lock activo no puede ser bloqueada.
- Fila `sending` con lock activo no entra en la sincronización de reflejos.
- Si una fila cambia de `sending` a `sent` durante la sincronización, no se marca `reflection_synced_at` con la versión anterior.
- Lock abandonado se recupera después de 15 minutos.
- Consulta de entrega evita starvation por hijos no enviables.
- Reconciliación procesa lotes y conserva cursores.

## Transporte

- HTTP 201 con `result` y clave coincidente marca `sent`.
- HTTP 200 `already_recorded` marca `sent`.
- `code` funciona solo como compatibilidad.
- Clave ausente o diferente no marca `sent`.
- 400/401/403/409 terminan en `failed`.
- timeout/red/429/5xx producen `retry`.
- Ocho fallos temporales producen `retry_limit_reached`.
- HTTP productivo se rechaza antes de petición.
- Token no aparece en logs, tabla ni errores.

## Regresión editorial

- Estado WordPress continúa en `pending`.
- El plugin carga con captura y entrega apagadas.
- No se modifican OpenAI, investigación, Recetas v2, biblioteca disciplinar ni Actualizaciones recurrentes.
- No se ejecuta ninguna conexión real en pruebas.
