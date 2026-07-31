# Cambios · ideasDi Redacción Gerizim v0.4.0-RC1.6.0.2

## Objetivo

Habilitar de forma controlada la actualización completa de **Concursos o convocatorias** desde `Gerizim → Actualizaciones recurrentes`, reutilizando la arquitectura editorial existente y escribiendo siempre sobre la entrada seleccionada.

## Flujo habilitado

1. Selección de una entrada `post` perteneciente a la categoría interna ID `34`.
2. Análisis de fuente y comparación de título, slug, fechas y enlace oficial.
3. Aplicación estructural confirmada sobre el mismo ID.
4. Preparación de un workflow de origen `recurring_update` mediante el adaptador y orquestador actuales.
5. Generación, revisión editorial, optimización SEO y validación final con el flujo existente.
6. Sustitución confirmada del título, contenido y extracto del mismo artículo.

## Protecciones

- No se crea una nueva entrada ni una nueva edición.
- El ID seleccionado debe coincidir en análisis, aplicación estructural, preparación y escritura final.
- El destino debe seguir siendo `post` y pertenecer a la categoría de concursos ID `34`.
- Se verifican huella de identidad, firma del estado analizado y permisos del usuario.
- La fase estructural preserva contenido, extracto, estado, autor, taxonomías e imagen destacada.
- La fase editorial preserva slug, estado, autor, categoría, etiquetas, imagen destacada y campos estructurados.
- La publicación solo se actualiza después de la confirmación humana y de las validaciones existentes.

## Campos estructurales admitidos

- `fecha_inicio_convocatoria`;
- `fecha_cierre_convocatoria`;
- `fecha_premiacion_convocatoria`;
- `enlace_oficial_convocatoria`;
- título y slug confirmados.

Las fechas se guardan en el formato compatible con los campos ACF existentes.

## Alcance editorial preservado

- No se modifican los archivos de prompts.
- No se modifica el cliente OpenAI.
- Se conservan ocho puntos de llamada a OpenAI.
- Se reutilizan receta compacta, planificación, revisión editorial, SEO, Gutenberg, validador y guard final.
- No se modifica el formato histórico de workflows.
- Contratos, adaptadores, orquestación y trazabilidad permanecen compatibles.
