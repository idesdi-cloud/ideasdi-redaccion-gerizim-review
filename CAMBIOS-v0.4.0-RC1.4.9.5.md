# Cambios · ideasDi Redacción Gerizim v0.4.0-RC1.4.9.5

## Alcance

Parche incremental sobre RC1.4.9.4 para completar el circuito de actualización de Eventos recurrentes sin modificar Radar, receta editorial compacta, prompts generales, reglas editoriales ni la creación convencional de entradas.

## 1. País ampliable e integrado con ACF

- Se añade un registro auxiliar persistente de países.
- Si un país no existe en las opciones del campo ACF, el editor puede registrarlo durante el análisis.
- Las opciones auxiliares se incorporan dinámicamente mediante `acf/load_field` sin reemplazar las opciones originales.
- Se normalizan alias, idioma, mayúsculas y tildes; por ejemplo Denmark/Danmark/Dinamarca → Dinamarca.
- Se guarda una clave interna estable y se verifica el valor crudo y la etiqueta visible devuelta por ACF.

## 2. Confirmación única de identidad

- La confirmación de ID, título y temporada se recuerda por usuario, ID y huella inmutable durante 24 horas.
- No vuelve a solicitarse en cada análisis del mismo expediente.
- Si cambia el Evento, su huella o el usuario, la confirmación se invalida automáticamente.

## 3. Título y slug editables

- Los valores propuestos aparecen como campos editables en la comparación previa.
- El slug se genera desde el título hasta que el editor lo modifica manualmente.
- El servidor vuelve a sanear y validar ambos valores.
- Se bloquea un slug que ya pertenezca a otro Evento.

## 4. Estado visual del análisis

- Después de analizar, el botón cambia a `Analizado · volver a analizar` y conserva su operatividad.
- Cualquier cambio posterior en el formulario elimina el estado completado y obliga a analizar nuevamente.

## 5. Año calendario y temporada editorial

- Se separan temporada editorial, año editorial, etiqueta oficial y año calendario.
- Etiquetas como SS27 pueden corresponder a fechas de agosto de 2026 sin considerarse contradictorias.
- Las fechas no reescriben automáticamente el año editorial del título.
- Se muestran avisos cuando la etiqueta oficial y la temporada seleccionada sí resultan incompatibles.

## 6. Aplicación inequívoca de la Versión 3

- El enlace previo se llama `Abrir evento actual · redacción no aplicada`.
- La acción principal se llama `Aplicar Versión 3 al evento ID …`.
- Después de aplicar, el enlace cambia a `Abrir evento actualizado`.
- La acción verifica que WordPress almacenó exactamente el contenido Gutenberg esperado y el extracto esperado.
- Si la comparación falla, no se declara éxito.

## Fuera de alcance

- Eliminación automática de duplicados heredados.
- Creación de nuevas ediciones.
- Actualización de concursos y convocatorias.
- Cambios en Radar o en el núcleo editorial protegido.
