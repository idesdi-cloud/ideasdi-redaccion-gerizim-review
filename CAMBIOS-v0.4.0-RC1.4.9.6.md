# Cambios · ideasDi Redacción Gerizim v0.4.0-RC1.4.9.6

## Simplificación del formulario

- Se eliminan de Actualizaciones recurrentes:
  - Temporada editorial.
  - Año editorial de temporada.
  - Etiqueta oficial de temporada.
  - Destacar en home.
- El nombre de la edición se controla únicamente mediante Título y Slug propuestos.
- Las fechas no modifican automáticamente el año o temporada del título.
- Destacar en home conserva siempre su valor actual.

## Operación obligatoria y confirmación única

- Actualizar publicación vigente y Crear nueva edición desde anterior aparecen desmarcados al abrir un expediente nuevo.
- Actualizar publicación vigente es la única opción habilitada y es obligatoria.
- Al seleccionarla aparece una advertencia de sobrescritura.
- Se elimina el checkbox repetido “Confirmo que el evento correcto es el ID…”.
- La selección explícita del ID y de la operación reemplaza esa confirmación adicional.

## Corrección del puente editorial

- La huella inmutable del evento usa únicamente ID y post type.
- Después de aplicar título, slug o campos estructurados, el expediente renueva su huella sobre el mismo ID.
- Preparar redacción en Flujo editorial ya no debe bloquearse porque WordPress haya completado datos internos de un borrador importado.
- Se mantienen las comprobaciones de mismo ID, permisos y tipo de contenido.

## País y ACF

- Se conserva el registro ampliable de países incorporado en RC1.4.9.5.
- Las opciones nuevas se integran mediante filtros ACF y se verifican por clave interna y etiqueta visible.

## Alcance protegido

- No se crean eventos nuevos.
- Crear nueva edición permanece desactivado.
- Concursos y convocatorias permanecen en modo análisis.
- Radar, receta compacta, prompts, investigación, validadores y flujo Gutenberg no se modifican.
