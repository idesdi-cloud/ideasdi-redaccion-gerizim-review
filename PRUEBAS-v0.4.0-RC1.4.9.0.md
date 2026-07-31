# Resumen de pruebas · v0.4.0-RC1.4.9.0

## Validación sintáctica

- `php -l`: aprobado en los 22 archivos PHP del plugin.
- `node --check`: aprobado en `assets/admin.js`.

## Pruebas funcionales aisladas

Se ejecutó un arnés PHP con funciones WordPress simuladas para comprobar la lógica nueva sin instalar el plugin en un sitio productivo.

Resultados aprobados:

1. `Milan` se normaliza como `Milán`.
2. `Milano` se normaliza como `Milán`.
3. `Milan`, `Milano` y `Milán` se deduplican en una sola ciudad.
4. Ciudades equivalentes por acentos y mayúsculas se deduplican.
5. Una fecha ISO válida es aceptada.
6. Una fecha imposible es rechazada.
7. Un evento con la misma fecha de inicio y fin es aceptado.
8. Una fecha de fin anterior a la fecha de inicio es rechazada.
9. Una fuente HTTP 403 se captura como estado recuperable.
10. El mensaje 403 confirma que el análisis continúa con información manual.
11. La búsqueda exacta por slug devuelve la coincidencia.
12. La consulta exacta usa `WP_Query` con el argumento `name` antes de la búsqueda difusa.
13. El reporte completo conserva el código HTTP 403.
14. El reporte conserva la información manual después del 403.
15. El reporte confirma que ninguna publicación fue actualizada.

## Comprobación de regresiones

La comparación contra el ZIP base RC1.4.8 detectó cambios únicamente en:

- `ideasdi-redaccion-gerizim.php`: versión y registro de dos handlers `admin_post`.
- `includes/class-recurring-updates.php`: implementación del alcance nuevo.
- `assets/admin.js`: mínimo dinámico y validación de fechas en navegador.
- `assets/admin.css`: presentación del resultado y tabla comparativa.
- Documentación de esta versión.

Los archivos del núcleo editorial protegido permanecen idénticos a RC1.4.8.

## Prueba pendiente en WordPress

La validación final en el sitio debe confirmar visualmente el autocompletado con los datos reales del CPT `evento`, la respuesta de una URL real con HTTP 403 y la descarga del Markdown desde el navegador. El ZIP no realiza cambios en publicaciones durante esas pruebas.
