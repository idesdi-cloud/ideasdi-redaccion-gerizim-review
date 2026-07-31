# Protección de regresiones · ideasDi Redacción Gerizim v0.4.0-RC1.4

Base protegida: v0.3.5.
Evolución funcional: v0.4.0-RC1.x.

## No debe perderse

- Botón Descargar reporte completo.
- Reinicio parcial.
- Selector de etiquetas WordPress existentes.
- Campo URL Diseñador / estudio / marca responsable.
- Lectura de URL de fuente de información.
- Investigación web controlada.
- Ficha de encargo editorial.
- Ficha documental temporal.
- Enlace interno desde matriz tag/categoría.
- Enlace externo obligatorio si hay URL responsable.
- Borrador en Gutenberg.
- Metadatos completos.

## Nuevo en RC1.4

- Pantalla Gerizim → Reglas editoriales.
- Reglas editables guardadas en base de datos, no en archivos.
- Historial de reglas.
- Exportar/importar reglas.
- Reporte con versión de reglas activas.
- Conteo real de VO 1–5 en reporte.

## Regla de arquitectura

Los ajustes continuos de redacción deben hacerse desde reglas editoriales editables. El núcleo del plugin solo debe tocarse para bugs técnicos, nuevas funciones o cambios transversales inevitables.


## Protección añadida en RC1.4.9.2

- Un workflow creado desde Actualizaciones recurrentes conserva el evento de destino durante reinicios parciales.
- El importador Radar no puede reemplazar ese workflow.
- El paso final no puede crear una entrada normal: únicamente actualiza el contenido del CPT `evento` vinculado.
- Antes de escribir se verifica la firma del evento y después se comprueban título, slug, estado, autor, taxonomías, imagen destacada y campos estructurados.

## Protección añadida en RC1.4.9.4

- Un workflow recurrente de Evento debe conservar el mismo ID desde la selección hasta la aplicación editorial.
- Los workflows anteriores sin huella inmutable no pueden aplicar contenido.
- Una temporada distinta entre evento, keyword y H1 debe bloquear la escritura.
- País debe escribirse con la clave interna de ACF cuando el campo tenga opciones y verificarse también por su etiqueta visible.
- La actualización de eventos debe usar `wp_update_post()` y nunca crear una entrada o Evento nuevo.

## Protección añadida en RC1.4.9.5

- La confirmación de identidad se conserva solo para el mismo usuario, ID y huella inmutable; cualquier cambio relevante obliga a confirmar nuevamente.
- Países nuevos se incorporan mediante un registro auxiliar y filtros ACF, sin duplicar etiquetas equivalentes ni perder las opciones originales del campo.
- Año calendario, temporada editorial, año editorial y etiqueta oficial son datos independientes; las fechas no pueden reescribir por sí solas el año del título.
- Título y slug propuestos se validan nuevamente en servidor antes de escribir y un slug usado por otro Evento queda bloqueado.
- El estado visual de análisis debe invalidarse al cambiar cualquier dato del expediente.
- El botón para abrir el Evento nunca equivale a aplicar la redacción.
- La aplicación de la Versión 3 debe verificar el hash del contenido Gutenberg y el extracto realmente almacenados antes de marcar el workflow como completado.
- La aplicación editorial debe conservar el mismo ID y no puede crear un Evento nuevo.


## Protección añadida en RC1.4.9.6

- La selección del modo de operación reemplaza cualquier checkbox adicional de confirmación del ID.
- Ninguna operación puede quedar preseleccionada en un expediente nuevo.
- Destacar en home debe conservarse y no puede entrar en la comparación o escritura recurrente.
- El título del evento es la autoridad editorial de temporada; las fechas no deben reescribir su año.
- La huella de identidad no puede depender de datos mutables del borrador importado.
- Después de aplicar cambios estructurales, el puente editorial debe continuar sobre el mismo ID sin exigir un análisis nuevo.

## Protección añadida en RC1.4.9.8

- Un Evento recurrente mantiene `Agenda` como tipo de pieza y `Categoría WordPress: No aplica`.
- La categoría propia del CPT `evento` se usa como lente editorial, pero nunca se convierte en `category` ni `post_tag`.
- Keyword y responsable pueden corregirse antes de generar contenido sin cambiar el ID de destino.
- Después de generar contenido, el brief vuelve a quedar bloqueado y requiere Reinicio parcial para cambios.
- Los prompts de Eventos no pueden tratarlos como concursos ni obligar el bloque “Lo esencial de la convocatoria”.
- La aplicación editorial conserva el bloqueo por ID, post type y huella inmutable.
- Una variación válida entre título almacenado, keyword y H1 genera aviso, no bloqueo, siempre que la validación H1/keyword y la identidad del ID sean correctas.


## Protección RC1.4.9.8 · presentación narrativa de Eventos

- No reintroducir “Datos clave del evento”, “Información del evento”, “Ficha del evento” o títulos equivalentes.
- No convertir fechas, sede, organizador, formato o acceso en listas dentro de Eventos.
- Mantener la Categoría editorial del evento como lente temática, no como categoría estándar de WordPress.
- Mantener el flujo y la aplicación al mismo ID sin cambios.

## Protección añadida en RC1.5.0 · Motor editorial de Recetas v2

- La receta debe funcionar para todas las categorías y tags, no para combinaciones aisladas.
- La categoría define territorio y el tag principal define lente; no se concatenan instrucciones completas.
- La investigación debe producir un plan aplicado antes del artículo base.
- El plan debe registrar tesis, lente, identidad, ejes seleccionados, traducciones perceptivas, ejes descartados, riesgos y enlaces.
- En Diseño de producto, Interior & Arquitectura, Moda, Movilidad y Diseño digital y 3D se evalúa identidad de autor o marca cuando haya evidencia.
- Agenda y Concursos no fuerzan identidad creativa del organizador.
- Ninguna especificación debe quedar aislada de una consecuencia perceptiva, de uso, cultural o de identidad.
- La Revisión editorial debe conservar diagnóstico separado del artículo público.
- La Revisión SEO no puede reescribir la tesis ni añadir párrafos de rescate.
- `IDG_Post_Creator` no puede inventar prosa, cierres o transiciones para cumplir enlaces.
- El postprocesamiento debe mantener el texto visible y registrar cero párrafos añadidos, salvo el aviso patrocinado explícitamente solicitado.
- Los enlaces HTML crudos dentro de ARTÍCULO FINAL deben bloquearse.
- El reporte debe distinguir receta base, receta aplicada, diagnóstico, Versión 3 y contenido enviado a Gutenberg.
- Radar, Eventos recurrentes, ACF, ID inmutable, Gutenberg y publicación Pendiente de revisión permanecen protegidos.


## Protección añadida en RC1.5.1 · pulido sin cambio de arquitectura

- No modificar la arquitectura de Recetas v2 ni volver a concatenar categoría y tag.
- Mantener entre 6 y 7 H3, organizados alrededor de 3 o 4 ejes centrales.
- No convertir automáticamente cada hallazgo del plan en un H3.
- No reintroducir metalenguaje público como “densidad editorial” o “marco documental”.
- En Concursos, las únicas listas públicas permitidas son categorías, fechas y premios confirmados; máximo dos y siempre acompañadas por prosa.
- No desarrollar requisitos, entregables, formatos ni criterios de evaluación; remitir a la web oficial.
- No forzar un H3 para el organizador de Concursos o Agenda.
- Mantener una sola aparición de cada URL interna principal.
- El postprocesamiento puede eliminar un enlace repetido conservando sus palabras, pero no escribir prosa nueva.
- Mantener el límite de 6 MB y bloquear la generación antes de OpenAI cuando el archivo no sea utilizable.
- Permitir reemplazar o descartar el archivo sin perder el brief.

## Protección añadida en RC1.5.2 · biblioteca disciplinar y actualización segura

- La biblioteca disciplinar es contexto abierto y no exhaustivo; nunca puede convertirse en una lista cerrada de palabras permitidas.
- La evidencia documental prevalece sobre la biblioteca y el modelo puede añadir conceptos más precisos encontrados durante la investigación.
- Todas las categorías y combinaciones categoría–tag actuales deben tener rol o familia definidos.
- Una entidad, un tag operativo o un destino SEO no puede actuar automáticamente como lente disciplinar.
- El orden de los tags no puede decidir por sí solo la lente.
- La receta visible debe ser compacta y no repetir íntegramente el ángulo editorial.
- El contexto semántico se integra en la llamada de planificación existente; no debe añadir una llamada nueva a OpenAI.
- La Revisión editorial debe comprobar naturalidad y pertinencia disciplinar, no exigir la presencia literal de palabras de la biblioteca.
- Agenda debe diferenciar feria, semana de la moda, exposición, festival, bienal, conferencia, ceremonia, mercado y semana de diseño.
- No reintroducir en Eventos expresiones genéricas como `fecha cerrada`, `la agenda se entiende` o `ritmo de la agenda`.
- `capa` puede usarse técnicamente, pero no como muletilla metafórica repetida.
- Todos los Eventos deben cerrar con un enlace contextual cuyo anchor sea `página oficial del evento`.
- El anchor interno de Eventos debe sonar natural para la tipología y no mencionar ideasDi por obligación.
- La meta description debe medir entre 106 y 150 caracteres; 120–145 es el rango recomendado.
- Una URL corporativa no puede validar automáticamente un anchor personal o una entidad distinta.
- La validación final de enlaces debe considerar el contenido postprocesado que se enviará a Gutenberg.
- Los años anómalos o incompatibles deben bloquearse antes de modificar un Evento.
- El H1 aprobado puede actualizar `post_title` únicamente sobre el mismo Evento protegido y debe conservar ID, slug, estado, autor, taxonomías, imagen y campos estructurados.
- El título almacenado debe verificarse después de la escritura.
- El material interno de Actualizaciones recurrentes puede permanecer dentro del workflow, pero no debe presentarse como un archivo físico ni ofrecer una acción de descarte engañosa.
- No reconstruir en esta versión el paquete reel ni el sistema automático de negritas.

## Protección añadida en RC1.5.3 · trazabilidad Radar

- Captura y entrega desactivadas por defecto.
- Estado WordPress `pending` preservado.
- Solo workflows importados desde Radar son elegibles.
- Actualizaciones recurrentes y posts ajenos quedan excluidos.
- Outbox aislado del motor editorial y fuente única de estado.
- Fallos de Radar no revierten importación, creación ni publicación.
- Orden persistente: importación → creación → publicación.
- Token fuera de opciones, meta, workflows, logs y tabla.
- No se modifican prompts, investigación, biblioteca disciplinar ni generación editorial.

## Protección añadida en RC1.5.4 · endurecimiento de trazabilidad

- `result` es el campo canónico del receptor; `code` es compatibilidad temporal.
- Una respuesta exitosa exige `idempotency_key` coincidente.
- La versión DB solo cambia después de verificar tabla, columnas e índice único.
- Capturas no insertadas quedan en recaptura persistente.
- `occurred_at` forma parte de la identidad idempotente.
- HTTP remoto inseguro se rechaza.
- `set_blocked()` no modifica filas `sending` ni locks activos.
- Reconciliación por lotes mediante `reflection_synced_at`.
- Octavo fallo temporal usa `retry_limit_reached` y conserva el transporte aparte.
- Reactivación de `failed`/`blocked` solo individual y revisada.
- Reinicio parcial permite reemplazar la ficha con otro brief Radar.


## Protección añadida en RC1.5.5 · procedencia e identidad Radar

- `radar_imported_at_utc` se guarda durante toda importación real, aunque captura esté apagada.
- `radar_exported_at`, `radar_imported_at_utc` y `observed_at` no son intercambiables.
- Nunca usar hora actual para reconstruir un `occurred_at` ausente.
- Fechas anteriores al corte no crean outbox ni recaptura.
- Fechas no fiables quedan como intención bloqueada `invalid_import_occurred_at`.
- El mismo brief conserva workflow, fecha, clave y evento.
- Un brief diferente crea workflow nuevo y no modifica el anterior.
- Todas las Actualizaciones recurrentes quedan fuera del contrato 1.1.
- Cada recaptura usa una opción independiente no autoload creada con `add_option()`.
- La migración debe verificar estructura e inserción/lectura/borrado antes de guardar versión DB.
- La consulta de entrega filtra dependencias antes de aplicar límite.
- La reconciliación usa lotes, cursores y verificación por relectura.
- El mismo brief histórico sin fecha original verificable se bloquea; `current_time('mysql')` solo corresponde a una identidad nueva.
- El snapshot del Reinicio parcial debe almacenarse, releerse y verificarse por SHA-256 antes de limpiar el workflow.
- Un fallo de restauración conserva el snapshot; solo se borra después de verificar todos los campos protegidos.
- Las intenciones usan `idg_traceability_recapture_event_`; el cursor nunca cuenta como intención.
- Reflejos no procesan `sending` con lock y solo se marcan si `status` y `updated_at` permanecen iguales.
- Un fallo al guardar la recaptura registra error operativo y conserva un marcador alternativo verificable con payload.

## Protección añadida en RC1.5.6 · durabilidad verificable

- El snapshot del Reinicio parcial es una barrera previa a cualquier escritura del workflow.
- La restauración no termina hasta verificar también la eliminación del snapshot.
- Una recaptura compatible solo se elimina después de comprobar su limpieza.
- Los conflictos de recaptura y las marcas alternativas deben permanecer visibles en diagnóstico.
- Una marca alternativa de `wordpress_published` debe poder reconstruirse con su payload y hash originales.
- La versión del esquema se relee; un fallo de `update_option()` mantiene el esquema inválido.
- La reconciliación compara `status`, `updated_at`, `lock_token` y `locked_at` antes de marcar sincronización.
- Fallos de workflow option o post meta dejan el reflejo pendiente.
- Los cursores deben avanzar por todos los lotes y no recorrer indefinidamente filas terminales ya sincronizadas.
- El contrato permanece en `1.1`, la entrada en `pending` y captura/entrega apagadas por defecto.
