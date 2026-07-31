# Resumen de pruebas · v0.4.0-RC1.4.9.2

## Validación sintáctica

- 22 archivos PHP aprobados con `php -l`.
- `assets/admin.js` aprobado con `node --check`.

## Arnés funcional aislado

Se simuló el evento ID 32393 de Madrid con campos ACF y estado `draft`.

Comprobaciones aprobadas:

1. `Spain`, `Espana` y la clave ACF `ES` se interpretan como `España`.
2. `España` se almacena mediante la clave ACF `ES` cuando el campo la define.
3. El encargo generado conserva `workflow_origin = recurring_update`.
4. El workflow queda fijado al evento ID 32393.
5. El tipo de pieza queda en `Agenda`.
6. El hecho base incorpora fechas normalizadas.
7. El material temporal incorpora información manual, lectura técnica y contenido anterior.
8. La salida SEO se convierte en bloques Gutenberg.
9. La redacción reemplaza el contenido del evento existente.
10. Título, slug, estado, autor, taxonomías, imagen destacada y país permanecen intactos.
11. Una firma desactualizada bloquea la sobrescritura.

## Comprobaciones estáticas de arquitectura

- El nuevo puente usa `admin-post.php`, POST, nonce y permisos.
- El importador Radar no se muestra en workflows recurrentes.
- La acción final recurrente llama a `update_existing_event()` y no a `create_pending_post()`.
- El método de actualización recurrente no contiene `wp_insert_post`.
- La ruta normal de creación de borradores continúa disponible para workflows no recurrentes.
- Solo cambiaron siete archivos operativos respecto de RC1.4.9.1:
  - `ideasdi-redaccion-gerizim.php`
  - `includes/class-recurring-updates.php`
  - `includes/class-admin-page.php`
  - `includes/class-job-runner.php`
  - `includes/class-post-creator.php`
  - `assets/admin.js`
  - `assets/admin.css`

## Prueba recomendada en WordPress

1. Analizar un evento en Borrador.
2. Aplicar la actualización estructural.
3. Pulsar **Preparar redacción en Flujo editorial**.
4. Revisar el encargo precargado.
5. Ejecutar Generar artículo base → Revisión editorial → Revisión SEO.
6. Pulsar **Aplicar redacción al evento**.
7. Abrir el evento y confirmar contenido Gutenberg, extracto, estado Borrador y conservación de campos ACF.
