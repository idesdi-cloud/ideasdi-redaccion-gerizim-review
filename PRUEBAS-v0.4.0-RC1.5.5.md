# Pruebas · ideasDi Redacción Gerizim v0.4.0-RC1.5.5

## Resultado consolidado

- 47 archivos PHP aprobados con `php -l`.
- `assets/admin.js` aprobado con `node --check`.
- 16 scripts de pruebas ejecutados.
- 195 aserciones `OK`.
- 16 resultados `PASS`.
- 0 fallos.

## Cobertura simulada

- Carga del plugin y versión RC1.5.5.
- Captura y entrega desactivadas por defecto.
- Procedencia Radar persistida aun con captura apagada.
- Conversión fiable de fecha local histórica a UTC.
- Separación entre `radar_exported_at`, `radar_imported_at_utc` y `observed_at`.
- Mismo brief conserva workflow, fecha y clave.
- Mismo brief histórico sin ninguna fecha queda bloqueado con `invalid_import_occurred_at`.
- Brief diferente prepara workflow nuevo y no hereda trazabilidad.
- Snapshot del Reinicio parcial con SHA-256, readback y comparación de campos protegidos.
- Fallo de almacenamiento bloquea el Reinicio parcial.
- Fallo de restauración conserva el snapshot; éxito confirmado lo elimina.
- Exclusión completa de Actualizaciones recurrentes.
- Fecha inválida conserva intención bloqueada.
- Contrato HTTP `result`, fallback temporal `code` y clave de respuesta exacta.
- HTTPS obligatorio fuera de localhost/desarrollo.
- Migración completa, parcial, índice ausente, tipo incompatible y fallo de escritura.
- Prueba escritura/lectura/borrado sin residuo.
- Recaptura atómica por opción independiente con prefijo `idg_traceability_recapture_event_`.
- Cursor aislado excluido de conteos y problemas.
- Recuperación de publicación e importación perdida.
- Conflicto por `occurred_at` distinto.
- Error operativo y marcador recuperable cuando falla también la persistencia de recaptura de `wordpress_published`.
- Orden causal persistente entre tres ejecuciones.
- Consulta anti-starvation.
- Ocho intentos y `retry_limit_reached`.
- Protección y exclusión de fila `sending` con lock activo.
- Carrera `sending → sent` durante sincronización sin falso `reflection_synced_at`.
- Reactivación administrativa individual.
- Reconciliación paginada.
- Redacción de secretos en logs y errores.

## Limitaciones

No se ejecutó WordPress completo, MySQL real, `dbDelta()` real, Action Scheduler real, WP-Cron real ni un receptor Radar/Directus. Todas las conexiones HTTP fueron simuladas. Captura y entrega permanecen desactivadas por defecto.
