# Cambios v0.4.0-RC1.3

Corrección de recuperación de flujo y validación del paquete reel.

## Ajustes principales

- Se recupera el botón **Reinicio parcial** en el panel de estado.
- El reinicio parcial conserva brief, categoría, tags, responsable, URLs, material temporal, investigación, ficha de encargo, artículo base y revisión editorial.
- El reinicio parcial limpia revisión SEO, validación final, errores y datos de borrador para poder retomar desde **Revisión SEO** sin cargar todo de nuevo.
- La validación del paquete reel mantiene como error bloqueante solo la ausencia del paquete o del CTA fijo.
- Los conteos de 14 palabras en VO 1–5 y los 18 overlays pasan a avisos recuperables; quedan en el reporte/metabox, pero no bloquean la creación del borrador.
- Se mejora la detección de overlays con formatos `Overlay:`, `Overlay 1:`, `Subtítulo:` y `Texto en pantalla:`.

## Motivo

La RC1.1 podía bloquear la creación del borrador por detalles formales del reel sin ofrecer una ruta de recuperación. Esta versión prioriza que el artículo pueda llegar a borrador cuando las reglas editoriales principales están correctas, dejando las correcciones finas del reel como avisos editables.
