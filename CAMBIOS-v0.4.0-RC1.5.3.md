# Cambios · ideasDi Redacción Gerizim v0.4.0-RC1.5.3

## Alcance

RC1.5.3 añade trazabilidad futura y opt-in entre Gerizim en WordPress y Radar editorial/Directus. Conserva el motor editorial de RC1.5.2, el estado `pending`, la revisión humana y las Actualizaciones recurrentes.

Captura y entrega quedan desactivadas por defecto. No se ejecutan backfills ni se reinterpretan registros históricos.

## Arquitectura añadida

- `includes/class-traceability.php`: configuración, elegibilidad, construcción y captura de eventos, fechas, exclusiones, hooks y reconciliación de reflejos.
- `includes/class-traceability-outbox.php`: tabla persistente, idempotencia, dependencias, locks, reintentos, recuperación y procesamiento.
- `includes/class-traceability-client.php`: transporte HTTP hacia Radar y clasificación del contrato 1.1.
- `includes/class-traceability-admin.php`: diagnóstico y acciones administrativas protegidas.

## Eventos

- `gerizim_imported` después de persistir y volver a verificar el workflow importado.
- `wordpress_post_created` después de crear la entrada `pending`, guardar metadatos, persistir el workflow y verificar identidades.
- `wordpress_published` solo en transición real hacia `publish` mediante `transition_post_status`.

No se usa `wordpress_draft_created`.

## Elegibilidad

Solo participan workflows importados desde Radar que tengan:

- `radar_source = radar-editorial-ideasdi`;
- `radar_brief_id` válido;
- workflow `idg_` válido;
- marca estructurada de importación;
- contrato 1.1;
- origen distinto de Actualizaciones recurrentes.

Quedan fuera workflows manuales, Eventos recurrentes y posts ajenos a Gerizim.

## Orden causal

- `wordpress_post_created` depende de `gerizim_imported`.
- `wordpress_published` depende de `wordpress_post_created`.
- La dependencia se persiste en el outbox y se conserva tras reinicios.
- Padres `failed` o `blocked` bloquean al hijo con `dependency_not_sent` sin consumir intentos.
- El reconciliador reactiva hijos cuando el padre alcanza `sent` y el corte sigue siendo válido.

## Outbox

Tabla `{$wpdb->prefix}idg_traceability_outbox` con:

- clave idempotente única;
- payload mínimo;
- estados `queued`, `sending`, `retry`, `failed`, `sent`, `blocked`;
- intentos y próximo intento;
- HTTP y error resumido;
- dependencia;
- `lock_token` y `locked_at`;
- timestamps de creación, actualización y envío.

La tabla es la fuente de verdad. Workflow y post meta son reflejos reconciliables.

## Reintentos

Máximo de ocho intentos HTTP:

1. inmediato;
2. 1 minuto;
3. 5 minutos;
4. 15 minutos;
5. 1 hora;
6. 3 horas;
7. 6 horas;
8. 12 horas.

Timeout, red, 429 y 5xx se reintentan. HTTP 400, 401, 403 y 409 son terminales. `traceability_event_already_recorded` con HTTP 200 se considera éxito.

## Concurrencia

- Reclamo atómico mediante `lock_token`.
- Actualizaciones finales exigen el mismo lock.
- Filas `sending` abandonadas durante 15 minutos vuelven a `retry`.
- La recuperación no altera payload ni clave idempotente.

## Migración

- Constante `IDG_TRACEABILITY_DB_VERSION = 1.0.0`.
- Opción `idg_traceability_db_version`.
- `dbDelta()` durante activación y arranque cuando hay versión pendiente.
- Migraciones aditivas.

## Configuración

Constantes o variables de entorno:

- `IDG_TRACEABILITY_CAPTURE_ENABLED`;
- `IDG_TRACEABILITY_DELIVERY_ENABLED`;
- `IDG_RADAR_TRACEABILITY_URL`;
- `IDG_RADAR_TRACEABILITY_TOKEN`;
- `IDG_TRACEABILITY_LIVE_CAPTURE_STARTED_AT`;
- `IDG_TRACEABILITY_CONTRACT_VERSION`.

Valores predeterminados: captura off, entrega off, contrato 1.1.

## Seguridad

- El token no se guarda en opciones, meta, workflows, outbox, HTML ni logs.
- El outbox no guarda artículo, investigación, prompts ni documentos completos.
- Logger reforzado para eliminar claves sensibles y redactar el token configurado.
- Panel sin URL ni token visibles; solo indicadores sí/no.

## Panel administrativo

Nueva sección `Trazabilidad Radar` dentro de Ajustes:

- contrato;
- flags de captura y entrega;
- URL/token configurados sí/no;
- fecha de corte;
- diagnóstico de tabla, scheduler y compatibilidad;
- conteos por estado;
- última entrega y último error resumido;
- acciones POST con permiso y nonce para procesar cola y reintentar temporales.

El diagnóstico no genera eventos ni conexiones.

## Exclusiones históricas

No se capturan:

- brief 16;
- post 36565;
- workflow `idg_8bcd8df4-38ad-4e3b-ade1-b5a51c583455`;
- eventos anteriores al corte configurado.

## Correcciones menores

- El estado de WordPress continúa siendo `pending`.
- Textos visibles principales usan “Entrada creada en Pendiente de revisión”.
- Se verificó que `_idg_sponsor_client` aparece una sola vez; no se eliminó ninguna asignación válida.
- Se conservaron nombres internos históricos como `run_draft`, `draft_created` y `draft_post_id`.


## Validación contractual del payload

- Solo se aceptan los tres eventos del contrato 1.1.
- El payload se limita a identidad, fechas, estado, dependencia y evidencia mínima.
- Campos editoriales inesperados se rechazan antes de persistir.
- Capturas concurrentes recuperan la fila ganadora sin alterar su payload.
- La misma clave con identidad contractual distinta produce `idempotency_payload_conflict` y nunca sobrescribe la fila existente.

## Fuente de verdad

- El outbox es la autoridad única de estado.
- Workflow y post meta se actualizan únicamente desde una fila persistida.
- El reconciliador repara diferencias de forma unidireccional.
