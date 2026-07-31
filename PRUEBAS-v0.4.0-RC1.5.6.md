# Pruebas · ideasDi Redacción Gerizim v0.4.0-RC1.5.6

## Resultado consolidado

Los valores finales se registran después de construir y volver a extraer el ZIP:

- Archivos PHP aprobados con `php -l`: `50`.
- JavaScript aprobado con `node --check`: sí.
- Scripts PHP ejecutados: `19`.
- Aserciones `OK`: `289`.
- Resultados `PASS`: `19`.
- Fallos: `0`.
- Matriz de aceptación: `53/53`.

## Cobertura dinámica

- procedencia con captura encendida y apagada;
- mismo brief y brief diferente;
- fecha histórica ausente, inválida, convertible y anterior al corte;
- snapshot almacenado, hash, restauración parcial, fallo de eliminación y barrera previa a escritura;
- recapturas concurrentes, cursor aislado, compatibilidad, conflicto y limpieza fallida;
- doble fallo outbox/recaptura y reconstrucción de `wordpress_published` desde post meta;
- migración completa, `dbDelta()` fallido, tabla parcial, columna ausente, índice ausente, tipo incompatible, escritura fallida y versión no persistida;
- orden causal, anti-starvation, dependencias, locks, recuperación y ocho intentos;
- reconciliación de varios lotes, cursor a filas posteriores, terminales, `sending` con lock y carrera `sending → sent`;
- fallos reales simulados de `update_option()` y `update_post_meta()`;
- respuestas `result`, fallback `code`, clave incompatible, errores permanentes y temporales;
- HTTPS, localhost y redacción de secretos;
- carga del plugin, defaults apagados y estado `pending`.

## Regresión editorial

Los componentes editoriales no incluidos en el alcance de trazabilidad se compararon contra la compilación corregida recibida. Prompts, OpenAI, investigación web, biblioteca disciplinar, recetas, reglas, validador, enlaces, metaboxes, material temporal, actualizaciones recurrentes y creación del contenido editorial permanecen sin cambios.

## Integridad del paquete

- ZIP: `ideasdi-redaccion-gerizim-v0.4.0-RC1.5.6.zip`.
- Tamaño y SHA-256: se publican junto al artefacto final para evitar una referencia circular dentro del propio ZIP.
- `unzip -t`: aprobado.
- Contenido extraído frente al directorio probado: idéntico.
- Checksum distinto de RC1.5.5 histórico: sí.
- Checksum distinto de la compilación RC1.5.5 corregida recibida: sí.

## Entorno y limitaciones

Se usaron PHP CLI, Node y mocks locales. No se ejecutaron WordPress completo, MySQL real, `dbDelta()` real, Action Scheduler real, WP-Cron real ni un endpoint Radar/Directus. No hubo conexiones HTTP reales, creación de posts, modificación de workflows productivos ni backfill.
