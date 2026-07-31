# Cambios · v0.4.0-RC1.5.0

## Motor editorial de Recetas v2

- Sustituye la concatenación de frases por un modelo estructurado universal.
- Categoría = territorio editorial.
- Tag principal = lente disciplinar.
- Tags secundarios = contexto acotado, no suma indiscriminada de lecturas.
- Investigación = selección de ejes con evidencia.
- Plan aplicado = tesis concreta antes de redactar.

## Plan editorial aplicado

Nueva etapa interna dentro de **Generar artículo base**, sin botón adicional:

- tesis;
- lente;
- identidad;
- ejes seleccionados;
- traducciones perceptivas y de uso;
- descartes;
- riesgos;
- estrategia de enlaces;
- receta aplicada.

Incluye fallback estructurado si falla la llamada específica de planificación.

## Cobertura

- Diseño de producto.
- Interior & Arquitectura.
- Moda.
- Movilidad y transporte.
- Diseño digital y 3D.
- Concursos y convocatorias.
- Calendario de eventos.
- Matriz heredada completa para tags no definidos específicamente.

## Revisiones

- Revisión editorial con diagnóstico previo y capacidad de reorganizar.
- Revisión SEO con cambios localizados y conservación de la tesis.
- Identidad de autor o marca en categorías editoriales principales.
- Traducción de decisiones de diseño en percepción, uso y significado.

## Postprocesamiento

- Eliminadas las funciones que escribían párrafos de rescate para enlaces.
- Solo puede enlazar texto existente y aplicar transformaciones técnicas.
- Auditoría de texto visible y cantidad de párrafos antes/después.
- Guardia final bloquea prosa alterada por la capa técnica.

## Enlaces

- Planificados antes de redactar.
- Reglas específicas acumuladas para Eventos y Concursos.
- HTML `<a>` crudo bloqueado en la salida Markdown.
- Evita cierres documentales y URLs escapadas.

## Interfaz y reporte

- Ayudas compactas debajo de campos.
- Plan aplicado visible en el workflow.
- Reporte con receta base, plan, diagnóstico y postprocesamiento real.

## Regresiones protegidas

Radar y Eventos solo reciben los nuevos campos de receta base. No se altera su lógica operativa, la identidad del Evento, País/ACF ni la escritura estructural.
