# Cambios · ideasDi Redacción Gerizim v0.4.0-RC1.5.5

RC1.5.5 corrige procedencia temporal, recaptura, migración, paginación e identidad Radar sin modificar el motor editorial.

- Guarda siempre `radar_imported_at_utc` durante la persistencia real, incluso con captura apagada.
- Separa exportación Radar, importación Gerizim y observación de captura.
- Reconstruye importaciones únicamente desde su fecha original.
- Excluye fechas anteriores al corte y bloquea fechas no fiables sin inventarlas.
- El mismo brief conserva workflow, fecha y evento; otro brief crea un workflow nuevo.
- Excluye completamente Actualizaciones recurrentes del contrato 1.1.
- Sustituye la opción-array de recaptura por una opción atómica por clave idempotente.
- Detecta conflictos de recaptura sin eliminar la evidencia.
- Actualiza el esquema DB a 1.2.0 y verifica tipos, índices y escritura real antes de guardar versión.
- Filtra dependencias en SQL antes del límite de entrega para evitar starvation.
- Añade reconciliación paginada por cursores y verificación real de reflejos.
- Conserva contrato HTTP basado en `result`, clave exacta, locks, ocho intentos, HTTPS y reactivación individual.

## Corrección puntual previa a activación

- Una reimportación del mismo brief sin `radar_imported_at_utc` ni `radar_imported_at` convertible se bloquea con `invalid_import_occurred_at`; no se completa con la hora actual.
- `current_time('mysql')` queda reservado a una identidad Radar nueva y `radar_import_persisted_at_utc` solo usa la hora actual durante esa primera persistencia.
- El snapshot del Reinicio parcial se guarda como registro versionado con SHA-256, se relee antes de limpiar el workflow y se conserva hasta verificar todos los campos restaurados.
- Las intenciones usan el prefijo `idg_traceability_recapture_event_`, separado del cursor `idg_traceability_recapture_cursor`.
- La reconciliación omite filas `sending` con lock y solo marca el reflejo si `status` y `updated_at` siguen siendo los mismos después de sincronizar.
- Si no puede persistirse la intención de recaptura, se registra `traceability_recapture_persistence_error` y se conserva un marcador verificable con el payload, especialmente en post meta para `wordpress_published`.
