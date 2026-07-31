# Pruebas · ideasDi Redacción Gerizim v0.4.0-RC1.5.4

## Resultado consolidado

- 14 scripts PHP ejecutados.
- 166 aserciones aprobadas.
- 14 marcadores finales PASS.
- 0 fallos.
- 13 componentes editoriales protegidos idénticos byte a byte frente a RC1.5.3.

## Casos nuevos aprobados

### Contrato

- HTTP 201 con `result`.
- HTTP 200 con `result=traceability_event_already_recorded`.
- compatibilidad temporal con `code`.
- respuesta con clave distinta rechazada.
- respuesta sin clave rechazada.

### Esquema

- migración completa actualiza versión;
- migración parcial no actualiza versión;
- falta de columna bloquea esquema;
- índice único verificado;
- procesamiento bloqueado con esquema incompleto.

### Recaptura

- fallo de inserción conserva intención persistente;
- publicación perdida conserva timestamp real;
- reconciliador reconstruye la fila;
- recaptura no recalcula `occurred_at`.

### Idempotencia

- diferencia solo en `observed_at` aceptada;
- diferencia en `occurred_at` produce conflicto.

### Seguridad y concurrencia

- HTTPS válido;
- HTTP localhost permitido;
- HTTP productivo rechazado;
- `sending` con lock no puede pasar a blocked;
- locks abandonados continúan recuperables.

### Administración

- fila revisada `failed` o `blocked` puede reactivarse individualmente;
- acciones protegidas por permisos, nonce y POST;
- sin reintento masivo de errores contractuales.

### Reconciliación

- reflejos procesados por lotes;
- filas sincronizadas no vuelven a recorrerse indefinidamente;
- orden causal importación → creación → publicación preservado.

### Reinicio parcial

- Reinicio parcial → importar otro brief Radar: aprobado;
- el nuevo brief reemplaza los campos conservados;
- la marca de reemplazo se elimina;
- una ficha prellenada sin reinicio sigue bloqueada.

## Regresión protegida

Permanecen idénticos:

- `class-web-research.php`;
- `class-openai-client.php`;
- `class-disciplinary-library.php`;
- `class-editorial-recipe-builder.php`;
- `class-editorial-plan.php`;
- `class-prompt-library.php`;
- `class-validator.php`;
- `class-final-guard.php`;
- `class-recurring-updates.php`;
- `class-temporary-material.php`;
- `class-internal-links.php`;
- `class-editorial-rules.php`;
- `class-metabox.php`.

## Limitaciones

- No se instaló el plugin en WordPress real.
- No se ejecutó una migración sobre MySQL real.
- No se enviaron eventos a Radar/Directus.
- El transporte, cron, Action Scheduler, dbDelta y WordPress se validaron mediante análisis estático y mocks.
