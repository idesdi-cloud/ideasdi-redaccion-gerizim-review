# Cambios · ideasDi Redacción Gerizim v0.4.0-RC1.5.4

## Alcance

RC1.5.4 corrige y endurece la infraestructura de trazabilidad de RC1.5.3 sin modificar el motor editorial, Recetas v2, investigación, prompts, biblioteca disciplinar, Gutenberg, Actualizaciones recurrentes ni el estado WordPress `pending`.

## Contrato HTTP

- `result` es el campo oficial del receptor.
- HTTP 201 + `traceability_event_recorded` registra un evento nuevo.
- HTTP 200 + `traceability_event_already_recorded` confirma idempotencia.
- `code` se admite temporalmente solo como compatibilidad.
- Una respuesta exitosa exige `idempotency_key` idéntica a la enviada.
- Una clave ausente o diferente produce `response_idempotency_key_mismatch` y nunca marca `sent`.

## Migración segura

- Esquema DB actualizado a `1.1.0`.
- Se añadieron `last_transport_error` y `reflection_synced_at`.
- `idg_traceability_db_version` solo se actualiza después de verificar tabla, columnas, índice único y ausencia de errores SQL.
- El procesamiento queda bloqueado con esquema incompleto.

## Recaptura durable

- Nueva clase `includes/class-traceability-recapture.php`.
- Si el outbox no puede insertar, se conserva la intención mínima en una opción persistente no autoload.
- Se mantienen `occurred_at`, `observed_at`, brief, workflow, post, tipo, dependencia y payload contractual.
- El reconciliador reconstruye filas ausentes por lotes.
- La publicación guarda `_idg_published_at_utc` antes del insert para recuperar el instante real.

## Idempotencia

- `observed_at` se ignora al comparar un duplicado.
- `occurred_at` no se ignora.
- La misma clave con fecha efectiva diferente produce `idempotency_payload_conflict`.

## Seguridad de URL

- HTTPS obligatorio.
- HTTP permitido en localhost.
- HTTP remoto solo con `IDG_TRACEABILITY_ALLOW_INSECURE_HTTP=true` en entorno local/development.
- URLs inválidas se rechazan antes de ejecutar `wp_remote_post()`.

## Outbox y reconciliación

- `set_blocked()` no modifica filas `sending` ni locks activos.
- Reconciliación limitada por lotes.
- `reflection_synced_at` evita recorrer todas las filas `sent` en cada ejecución.
- Si un reflejo no puede sincronizarse, la fila permanece pendiente de reconciliación.
- Recuperación de locks abandonados después de 15 minutos.

## Reintentos

- Ocho intentos totales.
- El octavo fallo temporal termina con `last_error=retry_limit_reached`.
- El último error HTTP/red queda separado en `last_transport_error`.

## Administración

- Vista de filas `failed` y `blocked`.
- Acción individual `Reactivar revisado` mediante POST, nonce y `manage_options`.
- No existe reintento masivo de errores contractuales.

## Reinicio parcial y Radar

- `Reinicio parcial` añade una marca temporal que permite reemplazar la ficha con otro brief Radar.
- El importador elimina esa marca después de importar.
- Sin Reinicio parcial, las fichas prellenadas continúan protegidas.

## Configuración

Nueva constante opcional:

```php
define('IDG_TRACEABILITY_ALLOW_INSECURE_HTTP', false);
```

Debe permanecer en `false` en producción.
