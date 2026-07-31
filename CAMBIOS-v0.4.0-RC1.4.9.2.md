# ideasDi Redacción Gerizim v0.4.0-RC1.4.9.2

Base verificada: `ideasdi-redaccion-gerizim-v0.4.0-RC1.4.9.1`.

## Alcance aplicado · Puente editorial para Eventos recurrentes

- Se mantiene el flujo estructural de RC1.4.9.1: analizar cambios y aplicar campos confirmados al evento existente.
- Después de una aplicación estructural correcta aparece **Preparar redacción en Flujo editorial**.
- La acción crea un workflow Gerizim normal, sin duplicar el motor editorial, y transfiere:
  - evento de destino e ID;
  - título y estado;
  - fechas, ciudad, país, ubicación y URL oficial;
  - información manual;
  - extracto técnico de la fuente;
  - contenido de la edición anterior como material de apoyo;
  - hecho base, ángulo de Agenda y receta editorial compacta inicial.
- El workflow queda marcado con `workflow_origin = recurring_update` y vinculado a un único CPT `evento`.
- En workflows recurrentes se oculta el importador Radar para impedir que el encargo sea sustituido accidentalmente.
- El último botón cambia de **Crear borrador en WordPress** a **Aplicar redacción al evento**.
- La acción final reutiliza el postprocesado, validación real, enlaces y conversión Gutenberg existentes.
- No se crea una entrada normal ni una nueva edición.
- Al aplicar la redacción se actualizan únicamente `post_content`, `post_excerpt` y metadatos editoriales Gerizim.
- Se preservan y verifican título, slug, estado, autor, taxonomías, imagen destacada y campos ACF del evento.
- Una firma del evento bloquea la escritura si el destino cambió después de preparar el workflow.
- El reinicio parcial conserva el vínculo protegido con el evento.

## Normalización de País

- El campo País utiliza autocompletado desde valores existentes del CPT `evento` y, cuando está disponible, desde las opciones reales del campo ACF.
- Se normalizan tildes, mayúsculas y alias habituales como `Spain` / `Espana` → `España`.
- Si ACF usa claves internas —por ejemplo `ES`— Gerizim guarda la clave correspondiente y verifica el valor canónico.
- No se asumió que el caso Argentina de la prueba fuera necesariamente un error del plugin; se reforzó la captura para reducir errores manuales y diferencias de formato.

## Fuera de alcance

- Crear una nueva edición desde la anterior.
- Escribir o redactar concursos y convocatorias.
- Publicar automáticamente.
- Cambiar el estado del evento.
- Modificar receta editorial compacta, prompts, Radar, reglas editoriales, validadores o biblioteca de enlaces.
