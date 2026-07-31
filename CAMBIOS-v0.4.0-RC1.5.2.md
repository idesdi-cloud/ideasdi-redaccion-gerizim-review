# Cambios · ideasDi Redacción Gerizim v0.4.0-RC1.5.2

## Alcance

RC1.5.2 mantiene Recetas v2 y aplica exclusivamente los cambios acordados después de las pruebas de RC1.5.1: especialización semántica, naturalidad, Agenda, Radar/Directus, enlaces, metas y seguridad de Actualizaciones recurrentes.

## Biblioteca disciplinar v1

- Nueva clase `IDG_Disciplinary_Library`.
- Datos separados en `includes/data/disciplinary-library.php`.
- Cobertura de las siete categorías y todas las combinaciones categoría–tag vigentes de la matriz interna.
- Familias para producto, espacio, moda, movilidad, digital, concursos y eventos.
- Roles diferenciados para lente, tipología, contexto, proceso, formato, entidad, operativo y SEO.
- Guías abiertas y no exhaustivas: la investigación puede ampliar vocabulario y conceptos.
- Fallback controlado para tags futuros.

## Receta y planificación

- Receta visible compacta.
- El ángulo editorial ya no se repite completo dentro de la receta.
- El plan registra conceptos activados, expansiones semánticas, verbos recomendados y términos condicionados.
- La selección semántica se integra en la llamada de planificación existente.
- Se conservan 3–4 ejes centrales y 6–7 H3.

## Radar/Directus

- Soporte opcional para `lente_sugerida`.
- Compatibilidad preservada con payloads anteriores.
- Validación de la lente: una entidad no puede convertirse en disciplina.

## Naturalidad

- Generación y revisión reciben vocabulario específico de la familia relevante.
- La revisión debe corregir aperturas repetidas y conceptos importados de otras disciplinas.
- Control de muletillas y abstracciones, sin prohibir usos técnicos legítimos.

## Agenda

- Perfiles diferenciados por tipología de evento.
- Receta compacta y vocabulario específico.
- Control de `fecha cerrada`, `la agenda se entiende` y `ritmo de la agenda`.
- Cierre oficial obligatorio con anchor `página oficial del evento`.
- Anchors internos contextuales y no autorreferenciales.

## Actualizaciones recurrentes

- Validación de años malformados o incompatibles.
- Defensa duplicada antes de la escritura.
- El H1 aprobado actualiza el título del mismo Evento.
- Verificación posterior del título almacenado.
- Preservación de ID, slug, estado, autor, taxonomías, imagen y campos estructurados.
- Material interno recurrente presentado de forma transparente, sin nombre de archivo virtual ni opción de descarte.

## Enlaces y SEO

- Coherencia semántica entre anchor y URL externa.
- Una URL corporativa no valida por sí sola un nombre personal.
- Guardia final sobre el contenido postprocesado.
- Meta description obligatoria entre 106 y 150 caracteres; objetivo 120–145.

## Fuera de alcance

- Reconstrucción del generador de reels.
- Rediseño del sistema automático de negritas.
- Cambios estructurales en Directus o Radar.
- Nuevos pasos manuales.
