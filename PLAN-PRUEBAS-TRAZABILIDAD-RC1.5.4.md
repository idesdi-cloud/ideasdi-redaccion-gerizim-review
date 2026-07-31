# Plan de pruebas de trazabilidad · RC1.5.4

## Contrato HTTP

- HTTP 201 con `result` y clave coincidente: `sent`.
- HTTP 200 con `result=already_recorded` y clave coincidente: `sent`.
- Compatibilidad temporal con `code`.
- Clave ausente o diferente: no éxito.
- HTTP productivo no seguro rechazado antes del transporte.
- Localhost HTTP permitido.

## Esquema

- Instalación completa actualiza versión.
- Tabla parcial no actualiza versión.
- Falta de columna bloquea procesamiento.
- Falta de índice único bloquea procesamiento.
- Error de base de datos bloquea procesamiento.

## Recaptura

- Fallo de inserción conserva intención.
- Reconciliación posterior reconstruye outbox.
- Fecha y payload permanecen iguales.
- Publicación perdida se recupera desde `_idg_published_at_utc` e intención persistente.

## Idempotencia

- Diferencia exclusiva en `observed_at` se acepta como misma intención.
- Diferencia en `occurred_at` produce conflicto.

## Administración

- Solo `failed` o `blocked` pueden reactivarse individualmente.
- Acción requiere permisos y nonce.
- Reactivación no afecta otras filas.
- No hay reintento masivo de errores contractuales.

## Concurrencia y reconciliación

- `set_blocked()` no modifica `sending` ni filas con lock.
- Dos workers no reclaman la misma fila.
- Reconciliación es paginada y limitada.
- Filas `sent` sincronizadas no se recorren de nuevo.
- `sending` abandonada se recupera tras 15 minutos.

## Reintentos

- Secuencia exacta de ocho intentos.
- Octavo fallo temporal termina con `retry_limit_reached`.
- Último error de transporte se conserva aparte.

## Regresión Radar

- Reinicio parcial → importar otro brief funciona.
- Sin reinicio parcial, ficha prellenada continúa protegida.
- La nueva importación elimina la marca temporal de reemplazo.

## Regresión general

- Estado WordPress permanece `pending`.
- Captura y entrega siguen desactivadas por defecto.
- No hay conexiones reales en pruebas.
- Componentes editoriales protegidos permanecen sin cambios.
