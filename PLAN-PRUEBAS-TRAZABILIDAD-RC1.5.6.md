# Plan de pruebas · trazabilidad RC1.5.6

## Objetivo

Verificar que RC1.5.6 conserva el flujo editorial y corrige procedencia, snapshot, recaptura, migración, cola, locks y reflejos sin conexiones reales.

## Capas

1. **Lint:** todos los PHP con `php -l` y JavaScript con `node --check`.
2. **Carga:** plugin y clases principales con constantes desactivadas.
3. **Mocks funcionales:** procedencia, importación, posts, publicación, cliente HTTP, outbox, migración, recaptura, locks y reflejos.
4. **Matriz 53/53:** `tests/rc156-acceptance.php` confirma que cada criterio solicitado tiene evidencia ejecutable o estructural.
5. **Regresión:** comparación de componentes editoriales protegidos contra la base corregida recibida.
6. **Paquete:** `unzip -t`, extracción limpia y comparación byte a byte con el directorio probado.

## Casos obligatorios

### Fechas, 1–7

Nueva identidad, captura apagada, conservación del mismo brief, ausencia histórica, conversión local, fecha inválida y exclusión anterior al corte.

### Workflows, 8–13

Brief diferente, workflow anterior intacto, mismo workflow/clave, conflicto y exclusión recurrente.

### Reinicio parcial, 14–18

Fallo de almacenamiento, hash, fallo de restauración, restauración parcial y eliminación verificada.

### Recaptura, 19–24

Concurrencia, cursor aislado, limpieza compatible, conflicto durable, doble fallo visible y reconstrucción de publicación.

### Migración, 25–30

`dbDelta()` fallido, tabla parcial, columna e índice ausentes, versión no actualizada y cola bloqueada.

### Cola, 31–34

Anti-starvation, raíz posterior, liberación causal y octavo fallo.

### Reconciliación, 35–41

Varios lotes, cursor, terminales, lock activo, carrera y fallos de opciones/meta.

### HTTP, 42–48

`result`, `code`, clave exacta, `already_recorded`, política URL y redacción de token.

### Regresión, 49–53

Defaults apagados, carga editorial, `pending`, fixtures sin red real y componentes protegidos.

## Criterio de salida

- cero fallos de lint;
- todos los scripts `PASS`;
- matriz 53/53;
- cero conexiones reales;
- ZIP válido e idéntico al árbol probado;
- checksum distinto de RC1.5.5.
