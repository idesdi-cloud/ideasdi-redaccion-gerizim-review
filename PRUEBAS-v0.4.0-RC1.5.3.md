# Pruebas · ideasDi Redacción Gerizim v0.4.0-RC1.5.3

## 1. Entorno y límites

La validación se realizó sobre el ZIP RC1.5.2 adjunto, en un directorio aislado y sin acceso a WordPress productivo, Directus, Radar, bases de datos reales ni endpoints externos.

No se ejecutaron solicitudes HTTP reales. El transporte se comprobó con mocks locales de `wp_remote_post()`.

## 2. Sintaxis y carga

- 41 archivos PHP aprobados con `php -l`, incluidos código y pruebas.
- `assets/admin.js` aprobado con `node --check`.
- Carga del archivo principal aprobada con stubs mínimos de WordPress.
- Carga aprobada de:
  - `IDG_Traceability`;
  - `IDG_Traceability_Outbox`;
  - `IDG_Traceability_Client`;
  - `IDG_Traceability_Admin`.
- Versión comprobada: `0.4.0-RC1.5.3`.
- Versión de esquema comprobada: `1.0.0`.

## 3. Suite local

Se ejecutaron 11 scripts de prueba con:

- 119 aserciones explícitas aprobadas;
- 11 marcadores finales `PASS`;
- 0 fallos.

Scripts:

1. `plugin-load-smoke.php`;
2. `traceability-capture-mock.php`;
3. `traceability-client-mock.php`;
4. `traceability-defaults-mock.php`;
5. `traceability-invalid-cutoff-mock.php`;
6. `traceability-invalid-delivery-config-mock.php`;
7. `traceability-logger-mock.php`;
8. `traceability-outbox-integration-mock.php`;
9. `traceability-retry-pure.php`;
10. `traceability-schema-mock.php`;
11. `traceability-static.php`.

## 4. Captura e idempotencia

Aprobado:

- importación Radar persistida genera `gerizim_imported`;
- validación aislada no contiene punto de captura;
- workflow manual no participa;
- `radar_source` incorrecto no participa;
- actualización recurrente no participa;
- brief histórico 16 excluido;
- workflow histórico excluido;
- post histórico 36565 excluido;
- repetición conserva la fila existente;
- captura concurrente conserva el payload ganador;
- misma clave con identidad incompatible se rechaza como `idempotency_payload_conflict`;
- payload no contiene artículo ni campos editoriales inesperados;
- timestamp de importación UTC;
- la marca estructurada coincide con brief y workflow actuales.

## 5. Creación de entrada

Aprobado:

- el estado WordPress continúa siendo `pending`;
- el evento se denomina `wordpress_post_created`;
- nunca se genera `wordpress_draft_created`;
- post, brief y workflow se verifican mediante workflow persistido y post meta estructurado;
- `draft_post_id` debe coincidir con el post real;
- `wordpress_post_created` depende de `gerizim_imported`;
- repetición no duplica;
- fecha persistida inválida conserva el evento como `blocked` con `invalid_occurred_at`;
- el reflejo en post meta se produce desde una fila real del outbox.

## 6. Publicación

Aprobado:

- `pending → publish` genera `wordpress_published`;
- `publish → publish` no genera evento;
- un post ajeno a Gerizim no participa;
- publicación sin creación trazada queda `blocked` con `missing_dependency`;
- `wordpress_published` depende de `wordpress_post_created`;
- la fecha de publicación es propia, se guarda en `_idg_published_at_utc` y no reutiliza la fecha de creación;
- workflow, brief, contrato y post se verifican antes de capturar.

## 7. Orden y dependencias

La simulación integral del outbox aprobó la secuencia:

```text
gerizim_imported
wordpress_post_created
wordpress_published
```

También aprobó:

- padre pendiente mantiene al hijo sin envío;
- padre `failed` o `blocked` bloquea al hijo con `dependency_not_sent`;
- el bloqueo por dependencia no consume intentos;
- dependencia ausente produce `missing_dependency`;
- padre recuperado a `sent` reactiva al hijo como `queued`;
- una fila `sent` no vuelve a enviarse;
- el orden se reconstruye desde la tabla, no desde memoria del proceso.

## 8. Reintentos y transporte

Contrato aprobado con mocks:

- HTTP 201 + `traceability_event_recorded` → `sent`;
- HTTP 200 + `traceability_event_already_recorded` → `sent`;
- HTTP 400, 401, 403 y 409 → `failed`;
- timeout, error de red, HTTP 429 y HTTP 500–599 → `retry`;
- respuesta inesperada no se acepta como éxito.

Secuencia aprobada:

- intento 1: inmediato;
- intento 2: +60 segundos;
- intento 3: +300 segundos;
- intento 4: +900 segundos;
- intento 5: +3600 segundos;
- intento 6: +10800 segundos;
- intento 7: +21600 segundos;
- intento 8: +43200 segundos;
- fallo temporal en el intento 8 → `failed`.

La espera por dependencia, el bloqueo y la recuperación de un lock abandonado no incrementan `attempts`.

## 9. Concurrencia

Aprobado con simulación del almacenamiento:

- un primer worker adquiere el lock mediante actualización condicional;
- un segundo worker no adquiere la misma fila;
- una actualización final con `lock_token` incorrecto es rechazada;
- el propietario del lock puede finalizar la fila;
- una fila `sending` abandonada por más de 15 minutos vuelve a `retry`;
- la recuperación conserva `attempts`, payload e idempotency key;
- el lock se limpia al completar, reintentar, fallar, bloquear o recuperar.

## 10. Fecha de corte

Aprobado:

- captura activa sin corte válido conserva el evento como `blocked`;
- razón exacta: `invalid_live_capture_cutoff`;
- entrega queda impedida mientras captura esté activa y el corte sea inválido;
- no se usa la hora actual como corte silencioso;
- el corte efectivo nunca puede ser anterior a `2026-07-04T14:53:06.000Z`;
- eventos anteriores al corte válido no se insertan;
- el reconciliador puede liberar eventos futuros después de corregir la configuración.

## 11. Migración y esquema

Aprobado con mock de `dbDelta()`:

- tabla `wp_idg_traceability_outbox`;
- campo e índice único `idempotency_key`;
- `dependency_key`;
- `lock_token`;
- `locked_at`;
- índices operativos;
- opción `idg_traceability_db_version` actualizada a `1.0.0`;
- migración registrada para activación y comprobación durante el arranque.

## 12. Seguridad

Aprobado:

- captura y entrega desactivadas por defecto;
- contrato predeterminado 1.1;
- token leído únicamente desde constante o entorno;
- token no aparece como columna, opción, post meta o campo del workflow;
- logger elimina claves sensibles anidadas;
- el valor sensible configurado se redacta de mensajes y contexto;
- panel muestra solo URL/token configurados: sí/no;
- panel no muestra payloads;
- diagnóstico no ejecuta HTTP;
- payload restringido a campos del contrato y evidencia permitida.

## 13. Regresión protegida

Comparación frente a RC1.5.2:

- 11 archivos existentes modificados de manera focalizada;
- 20 archivos añadidos, incluidos clases, documentación y pruebas;
- 0 archivos eliminados.

Se comprobaron idénticos byte a byte 13 componentes protegidos:

- cliente OpenAI;
- investigación web;
- biblioteca disciplinar;
- plan editorial;
- builder de recetas;
- prompts;
- Actualizaciones recurrentes;
- material temporal;
- enlaces internos;
- validador heredado;
- metaboxes;
- sanitización;
- estimación de uso.

## 14. Validaciones pendientes en entorno real

No se validó realmente:

- activación en una instalación WordPress completa;
- `dbDelta()` contra MySQL/MariaDB real;
- Action Scheduler real;
- WP-Cron real;
- transición humana real de una entrada a `publish`;
- receptor Directus del contrato 1.1;
- autenticación real;
- entrega real y respuesta idempotente del endpoint.

Estas pruebas deben realizarse de forma controlada con captura y entrega inicialmente desactivadas.
