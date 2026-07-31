# Pruebas · v0.4.0-RC1.4.9.3

Base: `ideasdi-redaccion-gerizim-v0.4.0-RC1.4.9.2`.

## Sintaxis

- 22 archivos PHP validados con `php -l`.
- `assets/admin.js` validado con `node --check`.

## Pruebas funcionales aisladas

Se simuló un CPT `evento` con las taxonomías propias:

- Categoría del evento: Moda.
- Región: Europa.

Resultado: 25 comprobaciones aprobadas.

- El contexto excluye `category` y `post_tag`.
- El workflow guarda `category_id = 0`, `tag_ids = []` y `piece_type = Agenda`.
- Se registra el perfil virtual `event_calendar / Calendario de eventos`.
- La ficha de encargo muestra Categoría WordPress: No aplica.
- La receta compacta utiliza el marco editorial de Calendario de eventos.
- Los prompts documental y de redacción reciben el tipo Evento, el perfil y las taxonomías propias.
- El enlace interno usa `get_post_type_archive_link('evento')` y no una categoría ficticia.
- Un workflow anterior RC1.4.9.2, sin los nuevos campos, se reconoce por `workflow_origin = recurring_update` y `recurring_target_post_type = evento`.

## Comprobaciones estáticas

14 comprobaciones aprobadas:

- Versión de cabecera y constante actualizadas.
- Protección de servidor para categoría, etiquetas y tipo de pieza.
- Interfaz diferenciada para eventos.
- Lectura dinámica de taxonomías del CPT.
- Excepción específica en prompts y enlaces internos.
- Agenda fija protegida también en JavaScript.

## Regresión

Frente a RC1.4.9.2:

- 35 archivos permanecen idénticos.
- 11 archivos fueron modificados de forma directa para el perfil, interfaz y puente editorial.
- Permanecen idénticos: Radar, reglas editoriales, OpenAI client, investigación web, validador, guard final, creador de entradas y datos de recetas.

## Pendiente de prueba en WordPress

- Confirmar visualmente los nombres reales de las taxonomías registradas por el desarrollo del CPT evento.
- Confirmar la URL real devuelta por `get_post_type_archive_link('evento')`.
- Continuar el workflow ya creado en RC1.4.9.2 y verificar que no solicita Categoría WordPress.
