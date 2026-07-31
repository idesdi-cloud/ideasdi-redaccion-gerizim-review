# Cambios v0.4.0-RC1.4.9.3

Corrección acotada del puente Actualizaciones recurrentes → Flujo editorial para eventos.

## Implementado

- Los workflows vinculados al CPT `evento` dejan de tratar “Eventos” como una categoría estándar de WordPress.
- Nuevo perfil editorial virtual `Calendario de eventos` con tipo de pieza fijo `Agenda`.
- En estos workflows se fuerzan en servidor `category_id = 0` y `tag_ids = []`.
- La interfaz reemplaza el selector de Categoría WordPress por campos de solo lectura: Tipo de contenido, Perfil editorial, Tipo de pieza y Categoría WordPress: No aplica.
- Se leen dinámicamente las taxonomías propias registradas para el CPT `evento` y sus términos asignados.
- Ficha de encargo, receta compacta, prompts y reportes distinguen perfil editorial de categoría WordPress.
- El enlace interno para eventos usa únicamente el archivo real del CPT o una taxonomía real disponible, sin inventar categorías ni URLs.
- El reinicio parcial conserva el perfil editorial y el contexto de taxonomías del evento.

## Protecciones

- No se crean categorías ni etiquetas.
- No se asignan categorías estándar al CPT evento.
- No se modifica el comportamiento de artículos normales, Radar, concursos o convocatorias.
- El motor existente de investigación, redacción, revisión, SEO, validación y Gutenberg se reutiliza sin duplicación.
