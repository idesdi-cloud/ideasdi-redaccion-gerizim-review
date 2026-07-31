# Cambios v0.4.0-RC1.4.9.4

Base: v0.4.0-RC1.4.9.3.

## Objetivo

Corregir dos riesgos detectados en pruebas reales de Actualizaciones recurrentes:

1. selección de un evento duplicado o de otra temporada dentro de lotes importados;
2. escritura de País que podía quedar guardada como metadato crudo, pero vacía en el control ACF visible.

## Identidad inmutable del evento

- Los resultados de búsqueda muestran ID, estado, fecha de creación, fecha de modificación, slug, fechas y lugar disponibles.
- El botón de selección identifica expresamente el ID elegido.
- Antes de analizar se exige confirmar el ID y la temporada.
- El formulario incluye un token firmado asociado al usuario, ID y huella inmutable del evento.
- El análisis, la aplicación estructural, la preparación del workflow y la aplicación editorial conservan el mismo ID seleccionado.
- Se comprueba que `wp_update_post()` devuelva exactamente ese ID.
- Los reportes muestran ID seleccionado, ID solicitado, ID realmente actualizado y coincidencia de extremo a extremo.
- Los workflows antiguos sin huella inmutable quedan bloqueados para la aplicación final y deben regenerarse.

## Protección contra cruces editoriales

- Keyword, entidad y URLs de origen quedan bloqueadas en workflows recurrentes.
- Antes de escribir el contenido se compara la entidad base del evento, la keyword y el H1 generado.
- Se bloquea una redacción de Primavera-Verano sobre un evento Otoño-Invierno, y viceversa.
- La aplicación editorial sigue utilizando `wp_update_post()`; no se habilita creación de eventos.

## País y ACF

- Se localiza el campo ACF real por nombre, etiqueta o clave interna, incluso cuando un post importado no tiene aún la referencia `_pais`.
- El país canónico se convierte a la clave interna definida en las opciones ACF, por ejemplo `España` → `ES` cuando corresponda.
- La escritura se realiza mediante `update_field()` con la clave interna del campo.
- La comprobación exige coincidencia del valor crudo y del valor formateado visible que devuelve ACF.
- Si ACF dispone de opciones y el país no coincide con ninguna, la operación se bloquea en vez de guardar un valor inválido.

## Fuera de alcance

- No se eliminan automáticamente duplicados ya existentes.
- No se crean nuevas ediciones.
- No se habilita escritura de concursos o convocatorias.
- No se modifican receta editorial compacta, Radar, prompts generales, reglas editoriales, validadores ni arquitectura Gutenberg.
