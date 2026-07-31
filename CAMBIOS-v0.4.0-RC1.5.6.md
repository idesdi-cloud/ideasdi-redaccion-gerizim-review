# Cambios · ideasDi Redacción Gerizim v0.4.0-RC1.5.6

RC1.5.6 parte de la compilación corregida de RC1.5.5 y congela el contrato Radar/Directus `1.1`. No modifica el motor editorial, no cambia la creación de entradas `pending` y no habilita captura ni entrega.

## Defectos corregidos

### Procedencia temporal

- La hora actual se usa únicamente al persistir una identidad Radar realmente nueva.
- El mismo brief conserva `radar_imported_at_utc` o convierte su `radar_imported_at` histórico desde la zona horaria de WordPress.
- Un mismo brief histórico sin fecha verificable queda bloqueado con `invalid_import_occurred_at`.
- `gerizim_imported.occurred_at` nunca procede de `radar_exported_at` ni de la hora de reconstrucción.

### Reinicio parcial

- El snapshot es ahora una barrera anterior a cualquier escritura del formulario.
- `add_option()` debe completarse y el contenido se relee antes de limpiar el workflow.
- El registro incluye SHA-256 y copia completa del workflow protegido.
- La restauración compara workflow, campos y hash.
- El snapshot no se elimina hasta comprobar la restauración y verificar también su eliminación.
- Un fallo de almacenamiento, restauración o limpieza queda recuperable.

### Recaptura

- Las intenciones usan exclusivamente `idg_traceability_recapture_event_`.
- El cursor `idg_traceability_recapture_cursor` queda fuera de conteos, listados y diagnóstico.
- Cada clave crea una opción independiente con `add_option()` y `autoload=no`.
- La limpieza de una intención se verifica por relectura.
- Las contradicciones se conservan como conflicto y aparecen en el panel.
- Si fallan outbox y opción de recaptura, se registra `recapture_persistence_failed` y se conserva una marca alternativa con hash.
- Las marcas de publicación en post meta se reconstruyen por lotes y se eliminan solo después de persistir de nuevo en outbox o recaptura.

### Migración

- La versión `idg_traceability_db_version` se relee después de escribirla.
- Si no quedó persistida, el esquema permanece inválido con `schema_version_persistence_failed`.
- Una tabla parcial, columna ausente, tipo incompatible, índice ausente o fallo de escritura no habilita la cola.

### Reflejos y concurrencia

- La reconciliación excluye `sending` con lock activo.
- Conserva la versión observada de `status`, `updated_at`, `lock_token` y `locked_at`.
- Después de escribir el reflejo, relee el outbox y solo marca `reflection_synced_at` si esos cuatro valores siguen iguales.
- La actualización final exige ausencia de lock.
- Una transición concurrente `sending → sent` queda pendiente para el ciclo siguiente.
- Fallos de `update_option()` o `update_post_meta()` no producen un falso estado sincronizado.

### Panel

- Añade conteo de conflictos de recaptura.
- Añade conteo y tabla de marcas alternativas recuperables.
- Mantiene acciones individuales protegidas por capacidad, nonce y POST.

## Archivos de código modificados

- `ideasdi-redaccion-gerizim.php`
- `includes/class-admin-page.php`
- `includes/class-traceability.php`
- `includes/class-traceability-outbox.php`
- `includes/class-traceability-recapture.php`
- `includes/class-traceability-admin.php`

## Pruebas modificadas

- `tests/plugin-load-smoke.php`
- `tests/radar-partial-reset-snapshot-mock.php`
- `tests/traceability-capture-mock.php`
- `tests/traceability-outbox-integration-mock.php`
- `tests/traceability-recapture-mock.php`
- `tests/traceability-schema-mock.php`
- `tests/traceability-static.php`
- `tests/wp-admin/includes/upgrade.php`

## Archivos documentales añadidos

- `REGRESION-EDITORIAL-RC1.5.6.sha256`
- `CAMBIOS-v0.4.0-RC1.5.6.md`
- `ESPECIFICACION-TRAZABILIDAD-RC1.5.6.md`
- `PLAN-PRUEBAS-TRAZABILIDAD-RC1.5.6.md`
- `PRUEBAS-v0.4.0-RC1.5.6.md`

## Pruebas añadidas

- `tests/rc156-acceptance.php`
- `tests/traceability-failure-marker-recovery-mock.php`
- `tests/traceability-reflection-failure-mock.php`

## Elementos deliberadamente no modificados

- prompts;
- cliente OpenAI;
- investigación web;
- biblioteca disciplinar;
- Recetas v2;
- reglas editoriales;
- validador;
- metaboxes;
- generación Gutenberg;
- Actualizaciones recurrentes;
- estado WordPress `pending`.

## Estado operativo

- Captura predeterminada: `false`.
- Entrega predeterminada: `false`.
- Contrato: `1.1`.
- Esquema: `1.2.0`.
- Conexiones reales realizadas durante la construcción: ninguna.
- Backfills: ninguno.
