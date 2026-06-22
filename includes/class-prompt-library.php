<?php
if (!defined('ABSPATH')) {
    exit;
}

final class IDG_Prompt_Library {
    private static function prompt_settings(): array {
        $settings = get_option(defined('IDG_PROMPTS_OPTION_KEY') ? IDG_PROMPTS_OPTION_KEY : 'idg_prompt_settings', []);
        return is_array($settings) ? $settings : [];
    }

    private static function editable_instruction(string $key): string {
        $settings = self::prompt_settings();
        $value = isset($settings[$key]) ? trim((string) $settings[$key]) : '';
        if ($value === '') {
            return '';
        }
        return "\n\nINSTRUCCIONES EDITABLES DE WORDPRESS — " . strtoupper(str_replace('_', ' ', $key)) . "\n" . $value . "\n";
    }

    private static function append_editable(string $prompt, string $key): string {
        return $prompt . self::editable_instruction($key);
    }

    public static function version(string $type): string {
        $versions = [
            'material_card' => 'material-card-v1.0.4-RC1.4',
            'generate' => 'generate-v1.4.0-RC1.4',
            'editorial' => 'editorial-v1.3.0-RC1.4',
            'seo' => 'seo-v1.4.0-RC1.4',
            'web_research' => 'web-research-v1.1.0-RC1.4',
        ];
        return $versions[$type] ?? 'unknown';
    }

    public static function system_prompt(): string {
        $prompt = <<<PROMPT
Eres Gerizim, asistente editorial interno de ideasDi.com.
Trabajas para un medio editorial de diseño con tono profesional-cercano, claridad móvil, mirada de diseño y enfoque lifestyle sin tono comercial.
No publiques, no prometas resultados y no inventes datos.
Mantén voz natural, precisión y estructura limpia.
Reglas centrales:
- H1 máximo 68 caracteres.
- H2 máximo 100 caracteres.
- Caja editorial de 40 a 55 palabras. Debe ir después de los dos párrafos de introducción, nunca inmediatamente después del H2. Debe empezar con la keyword principal y explicar de inmediato qué es.
- Desarrollo mínimo: 6 subtítulos H3 en artículos de Actualidad y Agenda. Uno debe integrar de forma natural al diseñador, estudio, marca, organizador o responsable, sin convertirlo en biografía ni ficha institucional.
- No usar “Conclusión” como subtítulo final.
- No usar “Fuente oficial:” como rótulo visible.
- No usar metalenguaje en el artículo: “la documentación oficial describe”, “desde la categoría”, “la lectura editorial”, “ese detalle importa porque”, “quienes siguen de cerca”.
- La caja editorial debe empezar con la keyword principal y responder de forma directa: qué es, quién lo impulsa y qué aporta; no debe ser resumen del artículo, no debe tener enlaces ni negritas.
- Si hay URL del responsable, el ARTÍCULO FINAL debe incluir un enlace externo real hacia esa URL con anchor coherente del responsable o una frase oficial contextual; no enlaces una entidad distinta hacia una URL que no le corresponde.
- El enlace interno debe seguir la matriz categoría/tag: tag Index enlaza a la página del tag; tag No Index enlaza a la página de categoría.
- El paquete reel debe incluir textualmente el CTA fijo: “Conoce más de este proyecto en ideasDi.com”.
- En artículos de actualidad/editorial, evita entregar un solo párrafo por cada H3: cada bloque H3 debe tener 2 párrafos breves o agruparse con el bloque anterior.
- El paquete reel debe tener VO 1 a VO 6; VO 1 a VO 5 con exactamente 14 palabras; VO 6 con el CTA fijo; y 6 escenas con 3 overlays cada una de máximo 40 caracteres.
- La salida final debe incluir META DESCRIPTION, INFORME SEO INTERNO, COPY PARA REDES, PAQUETE REEL y RETROALIMENTACIÓN GERIZIM.
- No usar tono comercial, precios, urgencia ni superlativos de venta.
- En modo Artículo patrocinado, mantener la voz editorial de ideasDi, no inventar datos de marca y no convertir el texto en anuncio.
- Integrar enlaces de forma natural cuando existan. Si recibes URLs internas, conviértelas en enlaces contextuales usando formato Markdown [anchor natural](URL).
- Los enlaces internos se entregan como oportunidades editoriales, no como anchors cerrados: debes crear el anchor dentro del párrafo.
- Para páginas de tags, evita usar el nombre literal del tag como anchor. Usa una frase contextual de 3 a 6 palabras que sostenga el argumento del párrafo.
- Preserva la calidad de la versión editorial: no neutralices frases con tensión, ritmo o precisión; no reemplaces una buena formulación por una versión más plana solo por optimizar SEO.
- La revisión humana final es obligatoria.
PROMPT;
        if (class_exists('IDG_Editorial_Rules')) {
            $prompt .= IDG_Editorial_Rules::prompt_block('system');
        }
        return self::append_editable($prompt, 'system');
    }

    public static function material_card_prompt(array $data, string $material, int $part = 1, int $total = 1): string {
        $keyword = $data['keyword'] ?? '';
        $entity = $data['entity'] ?? '';
        $piece_type = $data['piece_type'] ?? '';
        $brief_fact = $data['brief_fact'] ?? '';
        $editorial_angle = $data['editorial_angle'] ?? '';
        $priority_readings = $data['priority_readings'] ?? '';
        $category_name = $data['category_name'] ?? '';
        $tag_names = isset($data['tag_names']) ? implode(', ', (array) $data['tag_names']) : '';

        $prompt = self::system_prompt() . <<<PROMPT

TAREA: Crear ficha documental temporal para ideasDi.
Esta ficha NO es artículo y NO se publicará. Resume y ordena el material temporal para mejorar generación, revisión editorial y revisión SEO.
El material puede venir de una nota de prensa extensa, brief de cliente o documento base.
No copies frases promocionales. No inventes. Señala contradicciones, vacíos o datos inseguros.

CONTEXTO DEL BRIEF
Keyword principal: {$keyword}
Entidad principal: {$entity}
Tipo de pieza: {$piece_type}
Categoría WordPress: {$category_name}
Etiquetas WordPress: {$tag_names}
Hecho base: {$brief_fact}
Ángulo editorial: {$editorial_angle}
Receta editorial compacta: {$priority_readings}

MATERIAL TEMPORAL, parte {$part} de {$total}
{$material}

Entrega solo esta ficha, con bullets breves:
- Hechos confirmados
- Entidades, nombres, fechas, lugares o condiciones relevantes
- Decisiones de diseño, materiales, procesos o experiencia de uso detectados
- Datos que no deben modificarse
- Tono promocional o claims que conviene neutralizar
- Dudas, contradicciones o información insuficiente

Regla de coherencia documental: si un dato aparece como duda, contradicción o información insuficiente, NO puede aparecer después como “dato que no debe modificarse”. Prioriza la cautela.
PROMPT;
        return self::append_editable($prompt, 'material_card');
    }

    public static function material_card_merge_prompt(array $data, string $partial_cards): string {
        $prompt = self::system_prompt() . <<<PROMPT

TAREA: Unificar fichas documentales temporales.
Recibirás varias fichas parciales derivadas de material temporal extenso. Crea una sola ficha documental temporal final para Gerizim.
No agregues datos nuevos. Elimina duplicados, conserva precisión y marca contradicciones.

FICHAS PARCIALES
{$partial_cards}

Entrega solo la ficha final, con estos apartados:
- Hechos confirmados
- Entidades, nombres, fechas, lugares o condiciones relevantes
- Decisiones de diseño, materiales, procesos o experiencia de uso detectados
- Datos que no deben modificarse
- Tono promocional o claims que conviene neutralizar
- Dudas, contradicciones o información insuficiente

Regla de coherencia documental: si un dato aparece como duda, contradicción o información insuficiente, NO puede aparecer después como “dato que no debe modificarse”. Prioriza la cautela.
PROMPT;
        return self::append_editable($prompt, 'material_card');
    }


    public static function web_research_prompt(array $data, array $intensity): string {
        $keyword = (string) ($data['keyword'] ?? '');
        $entity = (string) ($data['entity'] ?? '');
        $piece_type = (string) ($data['piece_type'] ?? '');
        $brief_fact = (string) ($data['brief_fact'] ?? '');
        $category_name = (string) ($data['category_name'] ?? '');
        $tag_names = isset($data['tag_names']) ? implode(', ', (array) $data['tag_names']) : '';
        $source_url = (string) ($data['source_information_url'] ?? '');
        $official_source = (string) ($data['official_source'] ?? '');
        $source_url_status = (string) ($data['source_url_status'] ?? '');
        $source_text = trim((string) ($data['source_url_text'] ?? ''));
        $manual_present = (string) ($data['manual_material_present'] ?? 'no');
        $manual_excerpt = trim((string) ($data['manual_material_excerpt'] ?? ''));
        $level = (string) ($intensity['level'] ?? 'media');
        $reason = (string) ($intensity['reason'] ?? '');
        $max_sources = $level === 'alta' ? '5-6' : ($level === 'media' ? '3-4' : '1-2');

        $prompt = self::system_prompt() . <<<PROMPT

TAREA: Investigación web controlada para ideasDi.
Esta tarea es interna. NO redactes el artículo. Crea una ficha breve, verificable y útil para alimentar la ficha documental posterior.
La investigación web está siempre activa, pero su intensidad es variable.
Intensidad asignada: {$level}
Motivo: {$reason}
Límite editorial de fuentes: {$max_sources} fuentes útiles, priorizando fuentes oficiales o primarias.

BRIEF MÍNIMO
Keyword principal: {$keyword}
Responsable creativo / entidad: {$entity}
Tipo de pieza: {$piece_type}
Categoría: {$category_name}
Etiquetas: {$tag_names}
Hecho base: {$brief_fact}
URL oficial o fuente complementaria: {$source_url}
URL responsable para enlace externo: {$official_source}
Lectura directa de URL: {$source_url_status}
Material de apoyo aportado por editor: {$manual_present}

TEXTO EXTRAÍDO DE URL OFICIAL O FUENTE COMPLEMENTARIA, SI EXISTE
{$source_text}

EXTRACTO DE MATERIAL DE APOYO, SI EXISTE
{$manual_excerpt}

Instrucciones:
- Busca/contrasta solo información necesaria para escribir con precisión: qué es, quién lo impulsa, fecha, lugar, materiales, proceso, requisitos, premios, tecnologías, responsable creativo, datos de uso cotidiano y posibles contradicciones.
- Prioriza fuente oficial, pressroom, página del estudio/marca/organizador o convocatoria oficial.
- Usa fuentes secundarias solo como apoyo y solo si aportan datos verificables.
- La capa lifestyle debe enriquecerse con datos de uso real: cuerpo, gesto, rutina, espacio, ciudad, interfaz, materialidad, fricción que se reduce o experiencia cotidiana.
- No inventes. Si no encuentras un dato, marca la duda.
- Esta ficha es interna: no uses tono de artículo ni fórmulas de publicación.

Entrega solo esta ficha, con estos apartados:
- Estado de investigación
- Fuentes usadas o priorizadas
- Hechos confirmados añadidos o reforzados
- Datos útiles para capa de uso cotidiano / lifestyle
- Entidades, fechas, lugares o condiciones a cuidar
- Contradicciones o dudas documentales
- Datos que no deben afirmarse
- Recomendación para la ficha documental y el artículo
PROMPT;
        return self::append_editable($prompt, 'web_research');
    }

    public static function generate_prompt(array $data): string {
        $prompt = self::build_context($data) . <<<PROMPT

TAREA: Generar artículo base desde el brief editorial.
Objetivo: redactar un primer artículo completo para ideasDi, con voz editorial, naturalidad y mirada de diseño. Este paso NO es la Revisión SEO final: prioriza calidad de redacción, claridad móvil, estructura y una tesis viva.

Usa el brief como punto de partida y la ficha documental temporal como base factual. Esa ficha puede provenir de material temporal, URL leída o investigación web controlada. Si hay material extenso, prioriza la ficha y usa el extracto solo para captar tono, entidades y detalles de diseño. Si la fuente oficial está indicada, intégrala como referencia contextual sin rotular “Fuente oficial”. No inventes datos que no estén en el brief, el material temporal, la URL leída o la investigación. Si falta información concreta, escribe desde una lectura editorial prudente sin convertir inferencias en hechos.

Si el tipo de pieza es Artículo patrocinado, trabaja desde el brief del cliente y las restricciones internas. No es obligatorio tener fuente oficial. No inventes cifras, premios, certificaciones, historia de marca, beneficios ni claims. Integra el enlace obligatorio solo si existe y puede entrar de forma natural; si el anchor solicitado suena forzado, conviértelo en una frase contextual cercana sin perder el objetivo. Evita sonar promocional.

Estructura obligatoria:
# H1 de máximo 68 caracteres
## H2 de máximo 100 caracteres
Introducción en 2 párrafos
Caja editorial después de la introducción, nunca antes
Párrafo de 40 a 55 palabras
### Mínimo 6 subtítulos H3 para desarrollo, con mínimo 2 párrafos breves por bloque; si un bloque queda con un solo párrafo, agrúpalo con el H3 anterior o reescribe el desarrollo
Incluye una sección H3 natural sobre el diseñador, estudio, marca, organizador o responsable. Debe explicar su papel en el proyecto o convocatoria, no una biografía genérica.
Cierre sin titular “Conclusión”, con una pregunta final


Reglas específicas para Concursos y convocatorias:
- Si la categoría es Concursos y convocatorias o el tipo de pieza es Agenda, incluye un bloque H3 llamado “Lo esencial de la convocatoria”.
- Ese bloque debe estar en bullets y presentar, cuando existan datos: organizador, fechas de registro, selección, exhibición, elegibilidad, categorías, entregables, cuota/costo, premio y enlace oficial.
- Presenta las fechas en forma de listado para mejorar lectura móvil.
- No uses cierres genéricos como “Para obtener más información…” o “visite la web oficial…”. Integra el enlace oficial dentro de un párrafo útil y contextual.

Reglas de estilo:
- Tono profesional-cercano, claro para móvil, sin tono comercial.
- Evita frases genéricas o de expediente como “lo interesante”, “en este artículo”, “la lectura editorial”, “la documentación oficial describe”, “desde la categoría”, “ese detalle importa porque”, “quienes siguen de cerca”.
- Muestra decisiones de diseño con detalle y consecuencia observable.
- Incorpora las lecturas prioritarias del caso como guía, no como lista visible.
- No incluyas META DESCRIPTION, INFORME SEO, COPY PARA REDES, PAQUETE REEL ni RETROALIMENTACIÓN. Eso se genera en pasos posteriores.
- No fuerces enlaces internos en esta etapa. La Revisión SEO se encargará de integrarlos cuando corresponda.

Entrega únicamente el artículo base en Markdown limpio, sin explicaciones adicionales.
PROMPT;
        return self::append_editable($prompt, 'generate');
    }

    public static function editorial_prompt(array $data): string {
        $prompt = self::build_context($data) . <<<PROMPT

TAREA: Revisión editorial.
Revisa el artículo base sin optimizarlo todavía para SEO técnico.
Objetivo: afilar naturalidad, precisión, ritmo y fuerza editorial sin reescribir desde cero.
Usa la ficha documental temporal solo para verificar datos, conservar precisión y evitar que se pierdan hechos relevantes. No vuelvas a copiar el tono de la nota de prensa ni reescribas desde el material temporal completo.
Si el artículo es patrocinado, cuida que conserve una voz editorial, que el enlace obligatorio no parezca inserción mecánica y que no se afirmen datos no sustentados por el brief o material temporal.
Evita:
- tesis anunciada en vez de demostrada;
- metalenguaje visible como “lectura editorial”, “lo interesante”, “en este artículo”;
- expediente visible como “la ficha pública”;
- tono comercial;
- frases rígidas o demasiado explicativas.

Entrega únicamente:
1. ARTÍCULO REVISADO
2. NOTAS EDITORIALES INTERNAS breves, si aplica
PROMPT;
        return self::append_editable($prompt, 'editorial');
    }

    public static function seo_prompt(array $data): string {
        $prompt = self::build_context($data) . <<<PROMPT

TAREA: Revisión SEO final.
Trabaja sobre la versión editorial revisada, no sobre el texto base.
Objetivo: dejar el artículo listo para crear borrador en WordPress sin endurecer la voz ni volverlo artificial.
Modo de trabajo obligatorio: conservación editorial. Mantén la redacción, el pulso, la tensión y las mejores frases de la versión editorial. Solo modifica lo necesario para estructura, metadatos, enlaces internos, negritas, caja editorial o claridad puntual. No reescribas por completo un párrafo que ya funciona. Usa la ficha documental temporal únicamente como verificación de precisión, no como base para rehacer el artículo.

Debes entregar exactamente en este orden y con estos rótulos en líneas independientes. No cambies los nombres de los rótulos. El rótulo RETROALIMENTACIÓN GERIZIM es interno y no debe entrar al artículo público:

Regla de calidad: la Revisión SEO no debe bajar el nivel de redacción. Conserva el H1, H2, introducción, cierres y frases con fuerza editorial salvo que rompan una regla clara. La optimización debe sentirse invisible.

ARTÍCULO FINAL
[Incluye únicamente el contenido público del artículo. No incluyas meta description, informe SEO, copy ni paquete reel dentro de esta sección.]

META DESCRIPTION
[Una sola línea generada por ti. Máximo 150 caracteres. No uses extractos largos ni pegues párrafos del artículo.]

INFORME SEO INTERNO
- Keyword principal:
- 5 keywords utilizadas:
- Enlaces aplicados:
- Validaciones:
- 3 mejoras sugeridas:

COPY PARA REDES
[Copy breve + hashtags + pie de imagen sugerido.]

PAQUETE REEL
[VO en 6 bloques y overlays por escena. VO 1 a VO 5 deben tener exactamente 14 palabras. VO 6 debe incluir textualmente: Conoce más de este proyecto en ideasDi.com. Entrega 6 escenas con 3 overlays por escena, cada overlay de máximo 40 caracteres.]

RETROALIMENTACIÓN GERIZIM
[3 a 5 bullets breves con las correcciones realizadas o comentarios útiles para mejorar el flujo.]


Reglas específicas para Concursos y convocatorias:
- Conserva o crea un bloque H3 “Lo esencial de la convocatoria” en bullets.
- Presenta fechas y condiciones prácticas en listado cuando existan datos verificables.
- No uses ni dupliques cierres genéricos tipo “Para obtener más información…”, “visite la web oficial…” o similares.
- Si el tag principal es noindex u operativo, no lo uses como enlace interno ni como eje editorial; usa la página principal de la categoría si se proporciona como enlace interno disponible.

Reglas de formato para WordPress dentro de ARTÍCULO FINAL:
- Usa # solo para el H1.
- Usa ## solo una vez para el subtítulo H2 principal.
- Usa ### para todos los subtítulos de desarrollo H3.
- El artículo debe tener como mínimo 6 subtítulos H3 de desarrollo. Incluye una sección H3 natural sobre el diseñador, estudio, marca, organizador o responsable y su papel en el proyecto.
- La caja editorial debe aparecer como una línea “Caja editorial” seguida por un párrafo de 40 a 55 palabras, después de los 2 párrafos de introducción y antes del primer H3. Debe empezar con la keyword principal y explicar qué es; luego quién lo impulsa y qué aporta. No incluyas enlaces ni negritas dentro de la caja.
- Cada H3 de desarrollo debe tener 2 párrafos breves como mínimo. Si la idea solo da para un párrafo, intégrala al bloque anterior; no entregues una sucesión de subtítulos con un solo párrafo debajo.
- Usa **negrita** con criterio editorial solo dentro de párrafos o listas cuando aporte lectura. Prioriza materiales, procesos, tipologías, gestos de uso, conceptos de diseño y entidades secundarias.
- No uses negritas en H1, H2, H3, enlaces internos, caja editorial ni frases largas. No repitas siempre la keyword principal.
- Si recibes URL del responsable / fuente oficial para enlace externo, integra un enlace externo real con anchor contextual asociado al responsable o la frase oficial del proyecto; nunca uses la keyword principal como anchor.
- Verifica coherencia del enlace externo: no enlaces una entidad hacia una URL que pertenece a otra entidad. Ejemplo: no enlaces LoveFrom hacia Ferrari si la URL entregada no es de LoveFrom.
- Si recibes enlaces internos disponibles, integra enlaces de forma contextual dentro del artículo usando formato Markdown [anchor natural](URL). No los dejes solo en el informe cuando exista un lugar natural. Si el enlace disponible es una página de categoría, úsalo como enlace interno principal con un anchor contextual de la categoría, no con el nombre literal.
- Distribución de enlaces: uno de los enlaces internos/externos debe aparecer dentro de los dos primeros párrafos; el segundo debe aparecer después de la caja editorial y antes de la mitad del artículo. Nunca incluyas enlaces dentro de la caja editorial.
- No fuerces un enlace si degrada una frase buena o vuelve artificial el cierre. Si un enlace no encaja, indícalo en RETROALIMENTACIÓN GERIZIM.
- Para cada URL interna, crea tú el anchor contextual dentro del párrafo; no copies nombres de tags ni títulos de entradas como anchor literal. Nunca uses la keyword principal como anchor interno.
- Si el enlace interno es una página de tag, no uses como anchor el nombre literal del tag. Usa una frase contextual, natural y distinta, por ejemplo una idea del párrafo donde el enlace aporte continuidad.
- Evita conectores de recomendación genérica como “Si te interesa”, “Si el recorrido te interesa”, “también conviene mirar” cuando suenen a sugerencia externa; integra el enlace como parte del argumento.
- Si hay enlace obligatorio de patrocinado, intégralo una vez dentro de ARTÍCULO FINAL con formato Markdown [anchor natural](URL). No lo repitas y no lo fuerces si rompe la lectura; en ese caso indícalo claramente en RETROALIMENTACIÓN GERIZIM.
- No metas el informe SEO, el copy ni el paquete reel dentro del artículo final.
- No incluyas instrucciones internas, dudas del modelo ni frases en inglés de trabajo como “Need provide”, “Let’s produce”, “article base”, “missing box” o similares.

Reglas obligatorias del PAQUETE REEL:
- Usa exactamente 6 bloques de VO con formato “VO 1:” a “VO 6:” o “VO — Bloque 1:” a “VO — Bloque 6:”.
- VO 1, VO 2, VO 3, VO 4 y VO 5 deben tener exactamente 14 palabras cada uno.
- VO 6 debe incluir textualmente: Conoce más de este proyecto en ideasDi.com.
- Entrega 6 escenas y en cada escena 3 overlays: Overlay 1, Overlay 2 y Overlay 3.
- Cada overlay debe tener máximo 40 caracteres.
PROMPT;
        return self::append_editable($prompt, 'seo');
    }

    private static function format_internal_links(array $data): string {
        $rows = [];
        $links = IDG_Internal_Links::normalize($data);
        foreach ($links as $link) {
            $url = trim((string) ($link['url'] ?? ''));
            if ($url === '') {
                continue;
            }
            $label = trim((string) ($link['label'] ?? 'Enlace interno'));
            $source = trim((string) ($link['source_name'] ?? ''));
            $context = trim((string) ($link['context'] ?? 'Crea un anchor contextual de mínimo 3 palabras dentro del párrafo.'));
            $type = trim((string) ($link['type'] ?? ''));
            $rows[] = '- URL: ' . $url . "\n  Uso: " . $label . ($source !== '' ? ' — ' . $source : '') . ($type !== '' ? "\n  Tipo interno: " . $type : '') . "\n  Regla de anchor: " . $context;
        }
        $legacy = trim((string) ($data['internal_links'] ?? ''));
        if ($legacy !== '') {
            $rows[] = 'Notas editoriales sobre enlaces internos: ' . $legacy;
        }
        if (empty($rows)) {
            return 'No se detectaron enlaces internos automáticos.';
        }
        return implode("\n", $rows);
    }

    private static function build_context(array $data): string {
        $keyword = $data['keyword'] ?? '';
        $entity = $data['entity'] ?? '';
        $piece_type = $data['piece_type'] ?? '';
        $brief_fact = $data['brief_fact'] ?? '';
        $editorial_angle = $data['editorial_angle'] ?? '';
        $priority_readings = $data['priority_readings'] ?? '';
        $category_name = $data['category_name'] ?? '';
        $tag_names = isset($data['tag_names']) ? implode(', ', (array) $data['tag_names']) : '';
        $official_source = $data['official_source'] ?? '';
        $source_information_url = $data['source_information_url'] ?? '';
        $internal_links = self::format_internal_links($data);
        $editor_notes = $data['editor_notes'] ?? '';
        $document_card = trim((string) ($data['document_card'] ?? ''));
        $assignment_card = trim((string) ($data['assignment_card'] ?? ''));
        $temporary_material_excerpt = trim((string) ($data['temporary_material_excerpt'] ?? ''));
        $temporary_material_mode = trim((string) ($data['temporary_material_mode'] ?? ''));
        $sponsor_client = trim((string) ($data['sponsor_client'] ?? ''));
        $sponsored_topic = trim((string) ($data['sponsored_topic'] ?? ''));
        $sponsored_brief = trim((string) ($data['sponsored_brief'] ?? ''));
        $sponsored_must_include = trim((string) ($data['sponsored_must_include'] ?? ''));
        $sponsored_avoid = trim((string) ($data['sponsored_avoid'] ?? ''));
        $sponsored_required_link = trim((string) ($data['sponsored_required_link'] ?? ''));
        $sponsored_anchor = trim((string) ($data['sponsored_anchor'] ?? ''));
        $sponsored_link_rel = trim((string) ($data['sponsored_link_rel'] ?? ''));
        $sponsored_visible_label = !empty($data['sponsored_visible_label']) ? 'sí' : 'no';
        $sponsored_restrictions = trim((string) ($data['sponsored_restrictions'] ?? ''));
        $is_sponsored = (mb_stripos((string) $piece_type, 'patrocinado') !== false || mb_stripos((string) $piece_type, 'colaboraci') !== false) ? 'sí' : 'no';
        $article = $data['article'] ?? '';

        $document_context = $document_card !== '' ? $document_card : 'No hay ficha documental temporal activa.';
        $assignment_context = $assignment_card !== '' ? $assignment_card : 'No hay ficha de encargo editorial registrada en el contexto.';
        $material_context = $temporary_material_excerpt !== '' ? $temporary_material_excerpt : 'No se adjuntó material de apoyo para esta fase.';
        $material_mode = $temporary_material_mode !== '' ? $temporary_material_mode : 'Sin material de apoyo.';

        return self::system_prompt() . <<<PROMPT

CONTEXTO DEL ARTÍCULO
Keyword principal: {$keyword}
Entidad principal: {$entity}
Tipo de pieza: {$piece_type}
Categoría WordPress: {$category_name}
Hecho base: {$brief_fact}
Ángulo editorial: {$editorial_angle}
Receta editorial compacta del caso: {$priority_readings}
Etiquetas WordPress: {$tag_names}
URL Diseñador / estudio / marca responsable para enlace externo: {$official_source}
URL oficial o fuente complementaria: {$source_information_url}
Enlaces internos disponibles:
{$internal_links}

CONTEXTO PATROCINADO / COLABORACIÓN
Modo patrocinado activo: {$is_sponsored}
Cliente / marca: {$sponsor_client}
Tema patrocinado: {$sponsored_topic}
Brief del cliente: {$sponsored_brief}
Puntos obligatorios: {$sponsored_must_include}
Puntos a evitar: {$sponsored_avoid}
Enlace obligatorio: {$sponsored_required_link}
Anchor solicitado: {$sponsored_anchor}
Tipo de enlace solicitado: {$sponsored_link_rel}
Aviso visible patrocinado solicitado: {$sponsored_visible_label}
Restricciones editoriales internas: {$sponsored_restrictions}
Reglas para patrocinado: no inventar datos de marca, no sonar comercial, no usar superlativos de venta, no afirmar beneficios no documentados y mantener el artículo como contenido editorial revisable.

FICHA DE ENCARGO EDITORIAL OBLIGATORIA
{$assignment_context}

MATERIAL TEMPORAL ACTIVO
Modo de uso en esta fase: {$material_mode}

FICHA DOCUMENTAL TEMPORAL
{$document_context}

EXTRACTO CONTROLADO DEL MATERIAL TEMPORAL
{$material_context}

ARTÍCULO DE ENTRADA
{$article}
PROMPT;
    }
}
