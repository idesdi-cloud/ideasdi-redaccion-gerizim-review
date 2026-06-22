# ideasDi Redacción Gerizim v0.4.0-RC1.4.8

## Alcance: Actualizaciones recurrentes · Fase 1

- Nueva opción independiente en el menú Gerizim: **Actualizaciones recurrentes**.
- No modifica ni comparte estado con **Flujo editorial**.
- Permite elegir entre **Evento** y **Concurso o convocatoria**.
- Búsqueda de publicaciones existentes por título, slug o ID.
- Eventos: consulta el CPT `evento`.
- Concursos: consulta entradas normales de WordPress dentro de la categoría existente ID 34.
- Carga de campos actuales:
  - Eventos: `fecha_inicio`, `fecha_fin`, `ciudad`, `pais`, `ubicacion`, `enlace_oficial`, `destacado_home`, `resumen_editorial`.
  - Concursos: `fecha_inicio_convocatoria`, `fecha_cierre_convocatoria`, `fecha_premiacion_convocatoria`, `enlace_oficial_convocatoria`.
- Lectura compatible con ACF y respaldo mediante metadatos de WordPress.
- Selección de modo:
  - Actualizar publicación vigente.
  - Crear nueva edición desde anterior.
- Preparación visual de año, URL/fuente nueva, información nueva y campos de la nueva edición.

## Fuera del alcance de esta fase

- No analiza fuentes.
- No extrae fechas.
- No genera contenido.
- No llama OpenAI.
- No modifica publicaciones.
- No crea borradores ni nuevas ediciones.
- No cambia el flujo editorial actual.
