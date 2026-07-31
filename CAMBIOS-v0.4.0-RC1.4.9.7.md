# Cambios · ideasDi Redacción Gerizim v0.4.0-RC1.4.9.7

## Objetivo

Mejorar la calidad de la redacción de Eventos recurrentes sin añadir pasos ni convertir las clasificaciones propias del CPT `evento` en categorías estándar de WordPress.

## Cambios funcionales

### Brief editable antes de redactar

- Keyword principal deja de estar bloqueada al preparar un workflow recurrente.
- Diseñador / estudio / marca responsable deja de estar bloqueado antes de generar contenido.
- Ambos campos siguen precargados para ahorrar trabajo.
- El ID de destino, el tipo `evento` y su huella permanecen inmutables.
- Después de generar contenido, el brief vuelve a bloquearse según el comportamiento general de Gerizim.

### Categoría editorial propia del Evento

- Se detecta desde las taxonomías registradas para `evento` y, como respaldo, desde campos ACF/meta habituales.
- Se admiten estas categorías de Agenda:
  - Arquitectura e interiores
  - Diseño digital y 3D
  - Diseño interdisciplinar
  - Moda
  - Movilidad y transporte
  - Semana de diseño
- La categoría detectada se muestra en el Brief y puede corregirse antes de generar contenido.
- `Agenda` continúa siendo el formato de la pieza.
- `Categoría WordPress` continúa como `No aplica`.
- No se asignan categorías ni etiquetas estándar al Evento.

### Receta y prompts

- La categoría propia del Evento se incorpora a la ficha de encargo, receta compacta, investigación, redacción, revisión editorial, SEO y reporte.
- Moda usa como foco calendario, colecciones, pasarela, industria, ciudad y cultura del vestir.
- Las demás categorías reciben una lente temática equivalente y específica.
- Se corrige la regla que trataba cualquier pieza Agenda como convocatoria.
- Los Eventos usan un bloque “Datos clave del evento” o equivalente natural, no “Lo esencial de la convocatoria”.

### Validación del H1

- Se mantiene el bloqueo si cambia el ID, el post type o la huella inmutable.
- La diferencia literal entre el título antiguo del Evento, la keyword editable y el H1 final deja de bloquear por sí sola.
- Esas diferencias quedan registradas como avisos.
- La validación final general sigue exigiendo coherencia entre H1 y keyword del workflow.

## Alcance protegido

No se modifica Radar, reglas editoriales globales, investigación web, validadores generales, Gutenberg, matriz de enlaces ni creación de entradas normales.
