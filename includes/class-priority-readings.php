<?php
if (!defined('ABSPATH')) {
    exit;
}

final class IDG_Priority_Readings {
    public static function category_presets(): array {
        return [
            'Diseño de producto' => 'Leer productos desde problema de uso, ergonomía, materialidad, sistema, fabricación y experiencia cotidiana, evitando convertir características en catálogo.',
            'Arquitectura e interiores' => 'Leer espacios desde recorrido, luz, atmósfera, materialidad, programa y vida cotidiana, priorizando cómo se habita antes que la imagen final.',
            'Moda' => 'Leer moda desde silueta, construcción, materialidad, gesto corporal, oficio y códigos culturales, sin reducir la pieza o colección a tendencia.',
            'Movilidad y transporte' => 'Leer movilidad desde usuario, seguridad, interfaz, infraestructura, energía, postura y hábitos urbanos o de operación.',
            'Diseño digital y 3D' => 'Leer herramientas y proyectos digitales desde interfaz, flujo de trabajo, lenguaje visual, iteración, accesibilidad, prototipado y uso real.',
            'Concursos y convocatorias' => 'Leer la convocatoria desde utilidad, fechas, requisitos, elegibilidad, criterios del jurado, entregables, ruta práctica y valor para proceso creativo o portafolio.',
            'Eventos' => 'Leer eventos desde datos verificados, ciudad, sede, agenda, formato, temas a mirar, conversación de industria y cultura visual.',
            'Calendario de eventos' => 'Leer eventos desde datos verificados, ciudad, sede, agenda, formato, temas a mirar, conversación de industria y cultura visual.',
        ];
    }

    public static function tag_matrix(): array {
        return [
            ['category' => 'Diseño de producto', 'category_slug' => 'diseno-de-productos', 'curated_slug' => 'diseno-productos', 'tag' => 'Audio', 'tag_slug' => 'disenos-de-audio', 'status' => 'Index', 'reading' => 'Leer el producto desde sonido, interfaz, materialidad, gesto de uso y presencia en el espacio doméstico.'],
            ['category' => 'Diseño de producto', 'category_slug' => 'diseno-de-productos', 'curated_slug' => 'diseno-productos', 'tag' => 'Iluminación', 'tag_slug' => 'diseno-de-iluminacion', 'status' => 'Index', 'reading' => 'Analizar cómo la luz modifica atmósfera, orientación, ritual cotidiano, percepción del objeto y relación con el espacio.'],
            ['category' => 'Diseño de producto', 'category_slug' => 'diseno-de-productos', 'curated_slug' => 'diseno-productos', 'tag' => 'Mobiliario', 'tag_slug' => 'mobiliario', 'status' => 'Index', 'reading' => 'Leer el objeto desde escala corporal, apoyo, estabilidad, tacto, presencia espacial y uso cotidiano.'],
            ['category' => 'Diseño de producto', 'category_slug' => 'diseno-de-productos', 'curated_slug' => 'diseno-productos', 'tag' => 'Packaging', 'tag_slug' => 'diseno-de-packaging', 'status' => 'Index', 'reading' => 'Analizar el empaque como experiencia de apertura, protección, identidad visual, materialidad y relación con sostenibilidad.'],
            ['category' => 'Diseño de producto', 'category_slug' => 'diseno-de-productos', 'curated_slug' => 'diseno-productos', 'tag' => 'Herramientas', 'tag_slug' => 'diseno-de-herramientas', 'status' => 'Index', 'reading' => 'Leer la herramienta desde función, ergonomía, precisión, seguridad, repetición de uso y relación mano-objeto.'],
            ['category' => 'Diseño de producto', 'category_slug' => 'diseno-de-productos', 'curated_slug' => 'diseno-productos', 'tag' => 'Drones', 'tag_slug' => 'diseno-de-drones', 'status' => 'Index', 'reading' => 'Usar como tag transversal cuando el caso lo amerite; lectura desde autonomía, control, movilidad, cámara, precisión y uso.'],
            ['category' => 'Diseño de producto', 'category_slug' => 'diseno-de-productos', 'curated_slug' => 'diseno-productos', 'tag' => 'Robótica', 'tag_slug' => 'diseno-de-robotica', 'status' => 'Index', 'reading' => 'Analizar desde interacción humano-máquina, movimiento, autonomía, cuerpo, interfaz y uso cotidiano.'],
            ['category' => 'Diseño de producto', 'category_slug' => 'diseno-de-productos', 'curated_slug' => 'diseno-productos', 'tag' => 'Juegos', 'tag_slug' => 'diseno-de-juguetes', 'status' => 'Index', 'reading' => 'Leer desde experiencia lúdica, interacción, reglas, materialidad, aprendizaje y vínculo social.'],
            ['category' => 'Diseño de producto', 'category_slug' => 'diseno-de-productos', 'curated_slug' => 'diseno-productos', 'tag' => 'Salud y bienestar', 'tag_slug' => 'productos-de-diseno-para-la-salud', 'status' => 'Index', 'reading' => 'Usar como lente de cuidado, ergonomía, seguridad o hábitos.'],
            ['category' => 'Diseño de producto', 'category_slug' => 'diseno-de-productos', 'curated_slug' => 'diseno-productos', 'tag' => 'Mascotas', 'tag_slug' => 'mascotas', 'status' => 'Index', 'reading' => 'Leer desde vínculo humano-animal, higiene, ergonomía, rutina doméstica, seguridad y materialidad.'],
            ['category' => 'Diseño de producto', 'category_slug' => 'diseno-de-productos', 'curated_slug' => 'diseno-productos', 'tag' => 'Oficina', 'tag_slug' => 'productos-de-disenos-para-oficina', 'status' => 'Index', 'reading' => 'Analizar desde ergonomía, sistema de uso, materialidad, permanencia visual y experiencia del trabajo.'],
            ['category' => 'Diseño de producto', 'category_slug' => 'diseno-de-productos', 'curated_slug' => 'diseno-productos', 'tag' => 'Impresión 3D', 'tag_slug' => 'impresora-3d', 'status' => 'Index / Transversal', 'reading' => 'Leer desde fabricación, prototipado, personalización, material, iteración y acceso a producción.'],
            ['category' => 'Diseño de producto', 'category_slug' => 'diseno-de-productos', 'curated_slug' => 'diseno-productos', 'tag' => 'Tecnología', 'tag_slug' => 'diseno-de-productos-informaticos', 'status' => 'No Index', 'reading' => 'Usar solo como enriquecedor cuando no exista un tag técnico más preciso; evitar página tag demasiado amplia.'],
            ['category' => 'Diseño de producto', 'category_slug' => 'diseno-de-productos', 'curated_slug' => 'diseno-productos', 'tag' => 'Diseño sostenible', 'tag_slug' => 'ecodiseno', 'status' => 'Index', 'reading' => 'Usar como lectura complementaria cuando haya datos reales de materiales, circularidad, durabilidad o energía.'],
            ['category' => 'Diseño de producto', 'category_slug' => 'diseno-de-productos', 'curated_slug' => 'diseno-productos', 'tag' => 'Cultura del diseño', 'tag_slug' => 'cultura-del-diseno', 'status' => 'No Index', 'reading' => 'Leer temas académicos generales desde cultura visual, teoría, sociedad, historia y fundamentos del diseño, sin forzarlos hacia una categoría de producto, moda, espacio o tecnología.'],
            ['category' => 'Arquitectura e interiores', 'category_slug' => 'diseno-interior', 'curated_slug' => 'diseno-de-interiores', 'tag' => 'Residencial', 'tag_slug' => 'diseno-interior-residencial', 'status' => 'Index', 'reading' => 'Leer el proyecto desde vida cotidiana, recorrido, privacidad, luz, materialidad y formas de habitar.'],
            ['category' => 'Arquitectura e interiores', 'category_slug' => 'diseno-interior', 'curated_slug' => 'diseno-de-interiores', 'tag' => 'Comercial', 'tag_slug' => 'diseno-interior-comercial', 'status' => 'Index', 'reading' => 'Analizar desde experiencia de marca, flujo de usuarios, atmósfera, materialidad y comportamiento en el espacio.'],
            ['category' => 'Arquitectura e interiores', 'category_slug' => 'diseno-interior', 'curated_slug' => 'diseno-de-interiores', 'tag' => 'Oficina', 'tag_slug' => 'productos-de-disenos-para-oficina', 'status' => 'Index', 'reading' => 'Leer desde trabajo, concentración, ergonomía espacial, acústica, flexibilidad y cultura organizacional.'],
            ['category' => 'Arquitectura e interiores', 'category_slug' => 'diseno-interior', 'curated_slug' => 'diseno-de-interiores', 'tag' => 'Espacios públicos', 'tag_slug' => 'espacios-publicos', 'status' => 'Index', 'reading' => 'Analizar desde acceso, permanencia, escala urbana, recorrido, cuidado y experiencia colectiva.'],
            ['category' => 'Arquitectura e interiores', 'category_slug' => 'diseno-interior', 'curated_slug' => 'diseno-de-interiores', 'tag' => 'Institucional', 'tag_slug' => 'diseno-interior-institucional', 'status' => 'Index', 'reading' => 'Usar para clasificar proyectos educativos, culturales, sanitarios o públicos; indexar solo si hay volumen.'],
            ['category' => 'Arquitectura e interiores', 'category_slug' => 'diseno-interior', 'curated_slug' => 'diseno-de-interiores', 'tag' => 'Reforma', 'tag_slug' => 'reforma', 'status' => 'Index', 'reading' => 'Leer la intervención como reorganización de uso, memoria material, circulación y adaptación del espacio existente.'],
            ['category' => 'Arquitectura e interiores', 'category_slug' => 'diseno-interior', 'curated_slug' => 'diseno-de-interiores', 'tag' => 'Espacios pequeños', 'tag_slug' => 'espacios-pequenos', 'status' => 'Index', 'reading' => 'Analizar desde eficiencia, almacenamiento, escala corporal, luz, flexibilidad y percepción de amplitud.'],
            ['category' => 'Arquitectura e interiores', 'category_slug' => 'diseno-interior', 'curated_slug' => 'diseno-de-interiores', 'tag' => 'Iluminación natural', 'tag_slug' => 'iluminacion-natural', 'status' => 'Index', 'reading' => 'Leer desde orientación, aperturas, sombra, confort, atmósfera y relación interior-exterior.'],
            ['category' => 'Arquitectura e interiores', 'category_slug' => 'diseno-interior', 'curated_slug' => 'diseno-de-interiores', 'tag' => 'Materialidad', 'tag_slug' => 'materialidad', 'status' => 'Index', 'reading' => 'Analizar desde tacto, envejecimiento, luz, durabilidad, acústica y presencia sensorial del espacio.'],
            ['category' => 'Arquitectura e interiores', 'category_slug' => 'diseno-interior', 'curated_slug' => 'diseno-de-interiores', 'tag' => 'Diseño sostenible', 'tag_slug' => 'ecodiseno', 'status' => 'Index', 'reading' => 'Usar como lente complementaria cuando la fuente aporte datos de energía, materiales, circularidad o impacto.'],
            ['category' => 'Arquitectura e interiores', 'category_slug' => 'diseno-interior', 'curated_slug' => 'diseno-de-interiores', 'tag' => 'Mobiliario', 'tag_slug' => 'mobiliario', 'status' => 'Index', 'reading' => 'Leer el mobiliario integrado como mediador entre cuerpo, recorrido, uso y atmósfera.'],
            ['category' => 'Moda', 'category_slug' => 'diseno-de-indumentaria', 'curated_slug' => 'diseno-de-moda-e-indumentaria', 'tag' => 'Alta costura', 'tag_slug' => 'alta-costura', 'status' => 'Index', 'reading' => 'Leer desde oficio, construcción, tiempo de taller, gesto corporal, materialidad y teatralidad de la pieza.'],
            ['category' => 'Moda', 'category_slug' => 'diseno-de-indumentaria', 'curated_slug' => 'diseno-de-moda-e-indumentaria', 'tag' => 'Demi couture', 'tag_slug' => 'demi-couture', 'status' => 'Index', 'reading' => 'Usar cuando el caso esté entre alta costura y prêt-à-porter; explicar siempre el término si aparece en texto.'],
            ['category' => 'Moda', 'category_slug' => 'diseno-de-indumentaria', 'curated_slug' => 'diseno-de-moda-e-indumentaria', 'tag' => 'Prêt-à-porter', 'tag_slug' => 'pret-a-porter', 'status' => 'Index', 'reading' => 'Analizar desde silueta, uso, construcción, materialidad, cuerpo en movimiento y código cultural.'],
            ['category' => 'Moda', 'category_slug' => 'diseno-de-indumentaria', 'curated_slug' => 'diseno-de-moda-e-indumentaria', 'tag' => 'Cápsula', 'tag_slug' => 'capsula', 'status' => 'Index', 'reading' => 'Usar para colaboraciones o colecciones acotadas; evitar tono comercial y enfocarlo en lenguaje de marca.'],
            ['category' => 'Moda', 'category_slug' => 'diseno-de-indumentaria', 'curated_slug' => 'diseno-de-moda-e-indumentaria', 'tag' => 'Accesorios', 'tag_slug' => 'accesorios', 'status' => 'Index', 'reading' => 'Leer desde escala, cuerpo, función, gesto, identidad visual y relación con la silueta.'],
            ['category' => 'Moda', 'category_slug' => 'diseno-de-indumentaria', 'curated_slug' => 'diseno-de-moda-e-indumentaria', 'tag' => 'Calzado', 'tag_slug' => 'calzado', 'status' => 'Index', 'reading' => 'Analizar desde pisada, cuerpo, materialidad, rendimiento, cultura visual y uso cotidiano.'],
            ['category' => 'Moda', 'category_slug' => 'diseno-de-indumentaria', 'curated_slug' => 'diseno-de-moda-e-indumentaria', 'tag' => 'Desfiles', 'tag_slug' => 'desfiles', 'status' => 'Index', 'reading' => 'Leer desde puesta en escena, colección, ritmo visual, narrativa de marca, cuerpo y cultura.'],
            ['category' => 'Moda', 'category_slug' => 'diseno-de-indumentaria', 'curated_slug' => 'diseno-de-moda-e-indumentaria', 'tag' => 'Deportivo', 'tag_slug' => 'diseno-deportivo', 'status' => 'Index', 'reading' => 'Usar como lente de rendimiento, cuerpo, movimiento y cultura deportiva; no siempre como página SEO.'],
            ['category' => 'Moda', 'category_slug' => 'diseno-de-indumentaria', 'curated_slug' => 'diseno-de-moda-e-indumentaria', 'tag' => 'Textiles', 'tag_slug' => 'textiles', 'status' => 'Index', 'reading' => 'Analizar desde superficie, caída, textura, tecnología material, tacto y construcción.'],
            ['category' => 'Moda', 'category_slug' => 'diseno-de-indumentaria', 'curated_slug' => 'diseno-de-moda-e-indumentaria', 'tag' => 'Diseño sostenible', 'tag_slug' => 'ecodiseno', 'status' => 'Index', 'reading' => 'Usar solo si hay datos verificables de material, proceso, circularidad, cuidado o durabilidad.'],
            ['category' => 'Movilidad y transporte', 'category_slug' => 'diseno-transporte', 'curated_slug' => 'diseno-de-transporte', 'tag' => 'Automotriz', 'tag_slug' => 'automovil', 'status' => 'Index', 'reading' => 'Leer desde proporción, interfaz, conducción, identidad visual, habitáculo, energía y experiencia de uso.'],
            ['category' => 'Movilidad y transporte', 'category_slug' => 'diseno-transporte', 'curated_slug' => 'diseno-de-transporte', 'tag' => 'Motocicletas', 'tag_slug' => 'motocicletas', 'status' => 'Index', 'reading' => 'Analizar desde postura, maniobra, equilibrio, seguridad, lenguaje mecánico y relación cuerpo-máquina.'],
            ['category' => 'Movilidad y transporte', 'category_slug' => 'diseno-transporte', 'curated_slug' => 'diseno-de-transporte', 'tag' => 'Bicicleta', 'tag_slug' => 'diseno-de-bicicleta', 'status' => 'Index', 'reading' => 'Leer desde geometría, esfuerzo, aerodinámica, materialidad, postura, movilidad urbana o rendimiento.'],
            ['category' => 'Movilidad y transporte', 'category_slug' => 'diseno-transporte', 'curated_slug' => 'diseno-de-transporte', 'tag' => 'Scooter', 'tag_slug' => 'diseno-de-scooter-para-el-transporte-urbano', 'status' => 'Index', 'reading' => 'Analizar desde compactación, ciudad, portabilidad, seguridad, autonomía y uso cotidiano.'],
            ['category' => 'Movilidad y transporte', 'category_slug' => 'diseno-transporte', 'curated_slug' => 'diseno-de-transporte', 'tag' => 'Transporte público', 'tag_slug' => 'transporte-publico', 'status' => 'Index', 'reading' => 'Analizar desde acceso, flujo, señalización, ergonomía, infraestructura y experiencia colectiva.'],
            ['category' => 'Movilidad y transporte', 'category_slug' => 'diseno-transporte', 'curated_slug' => 'diseno-de-transporte', 'tag' => 'Aeronáutico', 'tag_slug' => 'diseno-de-transporte-aeronautico', 'status' => 'Index', 'reading' => 'Usar en proyectos de aviación, cabinas, drones mayores o movilidad aérea; indexar solo con volumen.'],
            ['category' => 'Movilidad y transporte', 'category_slug' => 'diseno-transporte', 'curated_slug' => 'diseno-de-transporte', 'tag' => 'Marítimo', 'tag_slug' => 'diseno-maritimo', 'status' => 'Index', 'reading' => 'Usar en embarcaciones, puertos, interiores náuticos o movilidad acuática; indexar solo con volumen.'],
            ['category' => 'Movilidad y transporte', 'category_slug' => 'diseno-transporte', 'curated_slug' => 'diseno-de-transporte', 'tag' => 'Maquinaria industrial', 'tag_slug' => 'diseno-de-maquinaria-industrial', 'status' => 'Index', 'reading' => 'Leer desde operación, seguridad, interfaz, logística, ergonomía y eficiencia en movimiento.'],
            ['category' => 'Movilidad y transporte', 'category_slug' => 'diseno-transporte', 'curated_slug' => 'diseno-de-transporte', 'tag' => 'Drones', 'tag_slug' => 'diseno-de-drones', 'status' => 'Index / Transversal', 'reading' => 'Definir si será transversal; analizar desde autonomía, cámara, operación, seguridad y territorio.'],
            ['category' => 'Movilidad y transporte', 'category_slug' => 'diseno-transporte', 'curated_slug' => 'diseno-de-transporte', 'tag' => 'Vehículos eléctricos', 'tag_slug' => 'vehiculos-electricos', 'status' => 'Index', 'reading' => 'Analizar desde energía, autonomía, infraestructura, experiencia de carga, interfaz y cambio cultural.'],
            ['category' => 'Movilidad y transporte', 'category_slug' => 'diseno-transporte', 'curated_slug' => 'diseno-de-transporte', 'tag' => 'Salud y bienestar', 'tag_slug' => 'productos-de-diseno-para-la-salud', 'status' => 'Index', 'reading' => 'Usar como lente de seguridad, accesibilidad, ergonomía o cuidado; no como página principal.'],
            ['category' => 'Movilidad y transporte', 'category_slug' => 'diseno-transporte', 'curated_slug' => 'diseno-de-transporte', 'tag' => 'Diseño sostenible', 'tag_slug' => 'ecodiseno', 'status' => 'No Index', 'reading' => 'Usar para energía, materiales o circularidad cuando haya datos verificables.'],
            ['category' => 'Diseño digital y 3D', 'category_slug' => 'modelado-3d', 'curated_slug' => 'arte-3d', 'tag' => 'CAD', 'tag_slug' => 'cad', 'status' => 'Index', 'reading' => 'Leer desde precisión, flujo técnico, iteración, documentación, control geométrico y paso a producción.'],
            ['category' => 'Diseño digital y 3D', 'category_slug' => 'modelado-3d', 'curated_slug' => 'arte-3d', 'tag' => 'Modelado 3D', 'tag_slug' => 'modelado-3d', 'status' => 'Index', 'reading' => 'Analizar desde forma, geometría, proceso, superficie, prototipado, visualización y control espacial.'],
            ['category' => 'Diseño digital y 3D', 'category_slug' => 'modelado-3d', 'curated_slug' => 'arte-3d', 'tag' => 'Render', 'tag_slug' => 'render', 'status' => 'Index', 'reading' => 'Leer desde representación, luz, materialidad simulada, atmósfera, comunicación visual y percepción.'],
            ['category' => 'Diseño digital y 3D', 'category_slug' => 'modelado-3d', 'curated_slug' => 'arte-3d', 'tag' => 'Visualización arquitectónica', 'tag_slug' => 'visualizacion-arquitectonica', 'status' => 'Index', 'reading' => 'Analizar desde espacio, narrativa visual, realismo, atmósfera, cámara y comunicación del proyecto.'],
            ['category' => 'Diseño digital y 3D', 'category_slug' => 'modelado-3d', 'curated_slug' => 'arte-3d', 'tag' => 'Animación', 'tag_slug' => 'animacion', 'status' => 'Index', 'reading' => 'Leer desde secuencia, ritmo visual, movimiento, tiempo, narrativa y relación entre imagen fija y acción.'],
            ['category' => 'Diseño digital y 3D', 'category_slug' => 'modelado-3d', 'curated_slug' => 'arte-3d', 'tag' => 'Impresión 3D', 'tag_slug' => 'impresora-3d', 'status' => 'Index / Transversal', 'reading' => 'Analizar desde fabricación, prototipado, material, personalización e impacto en procesos creativos.'],
            ['category' => 'Diseño digital y 3D', 'category_slug' => 'modelado-3d', 'curated_slug' => 'arte-3d', 'tag' => 'IA generativa', 'tag_slug' => 'ia-generativa', 'status' => 'Index', 'reading' => 'Leer desde proceso creativo, autoría, interfaz, iteración, control, imagen sintética y cultura visual.'],
            ['category' => 'Diseño digital y 3D', 'category_slug' => 'modelado-3d', 'curated_slug' => 'arte-3d', 'tag' => 'AR/VR', 'tag_slug' => 'ar-vr', 'status' => 'Index', 'reading' => 'Analizar desde inmersión, interacción espacial, interfaz, cuerpo, experiencia y simulación.'],
            ['category' => 'Concursos y convocatorias', 'category_slug' => 'concursos-de-diseno', 'curated_slug' => 'concursos-y-convocatorias-diseno', 'tag' => 'Producto', 'tag_slug' => 'producto', 'status' => 'No Index', 'reading' => 'Priorizar categorías, requisitos, criterios de evaluación, entregables y valor para portafolio.'],
            ['category' => 'Concursos y convocatorias', 'category_slug' => 'concursos-de-diseno', 'curated_slug' => 'concursos-y-convocatorias-diseno', 'tag' => 'Arquitectura e interiores', 'tag_slug' => 'arquitectura-e-interiores', 'status' => 'No Index', 'reading' => 'Leer desde brief, escala espacial, jurado, documentación, representación y claridad de propuesta.'],
            ['category' => 'Concursos y convocatorias', 'category_slug' => 'concursos-de-diseno', 'curated_slug' => 'concursos-y-convocatorias-diseno', 'tag' => 'Moda', 'tag_slug' => 'moda', 'status' => 'No Index', 'reading' => 'Priorizar perfil de participante, colección/propuesta, requisitos visuales, portafolio y criterio de selección.'],
            ['category' => 'Concursos y convocatorias', 'category_slug' => 'concursos-de-diseno', 'curated_slug' => 'concursos-y-convocatorias-diseno', 'tag' => 'Movilidad', 'tag_slug' => 'movilidad', 'status' => 'No Index', 'reading' => 'Leer desde problema, usuario, infraestructura, seguridad, sostenibilidad y presentación del concepto.'],
            ['category' => 'Concursos y convocatorias', 'category_slug' => 'concursos-de-diseno', 'curated_slug' => 'concursos-y-convocatorias-diseno', 'tag' => 'Diseño digital', 'tag_slug' => 'diseno-digital', 'status' => 'No Index', 'reading' => 'Priorizar software/proceso, visualización, entrega digital, prototipo, narrativa y criterios técnicos.'],
            ['category' => 'Concursos y convocatorias', 'category_slug' => 'concursos-de-diseno', 'curated_slug' => 'concursos-y-convocatorias-diseno', 'tag' => 'Varias disciplinas', 'tag_slug' => 'varias-disciplinas', 'status' => 'No Index / Operativo', 'reading' => 'Usar cuando la convocatoria reúne varias áreas de diseño; enlazar a la categoría principal y mantener lectura práctica de fechas, requisitos y entregables.'],
            ['category' => 'Eventos', 'category_slug' => 'eventos', 'curated_slug' => 'eventos', 'tag' => 'América', 'tag_slug' => 'america', 'status' => 'No Index', 'reading' => 'Usar como región para filtrar agenda; indexar solo si se crea página curada de eventos por región.'],
            ['category' => 'Eventos', 'category_slug' => 'eventos', 'curated_slug' => 'eventos', 'tag' => 'Asia', 'tag_slug' => 'asia', 'status' => 'No Index', 'reading' => 'Usar como región para filtrar agenda; indexar solo si se crea página curada de eventos por región.'],
            ['category' => 'Eventos', 'category_slug' => 'eventos', 'curated_slug' => 'eventos', 'tag' => 'África', 'tag_slug' => 'africa', 'status' => 'No Index', 'reading' => 'Usar como región para filtrar agenda; indexar solo si se crea página curada de eventos por región.'],
            ['category' => 'Eventos', 'category_slug' => 'eventos', 'curated_slug' => 'eventos', 'tag' => 'Europa', 'tag_slug' => 'europa', 'status' => 'No Index', 'reading' => 'Usar como región para filtrar agenda; indexar solo si se crea página curada de eventos por región.'],
            ['category' => 'Eventos', 'category_slug' => 'eventos', 'curated_slug' => 'eventos', 'tag' => 'Oceanía', 'tag_slug' => 'oceania', 'status' => 'No Index', 'reading' => 'Usar como región para filtrar agenda; indexar solo si se crea página curada de eventos por región.'],
            ['category' => 'Eventos', 'category_slug' => 'eventos', 'curated_slug' => 'eventos', 'tag' => 'Diseño interdisciplinar', 'tag_slug' => 'diseno-interdisciplinar', 'status' => 'Index', 'reading' => 'Leer ferias, semanas y festivales como cruces entre disciplinas, cultura visual, industria y ciudad.'],
            ['category' => 'Eventos', 'category_slug' => 'eventos', 'curated_slug' => 'eventos', 'tag' => 'Arquitectura e interiores', 'tag_slug' => 'arquitectura-e-interiores', 'status' => 'Index', 'reading' => 'Priorizar ciudad, sede, expositores, instalaciones, materiales, debates y cultura espacial.'],
            ['category' => 'Eventos', 'category_slug' => 'eventos', 'curated_slug' => 'eventos', 'tag' => 'Moda', 'tag_slug' => 'moda', 'status' => 'Index', 'reading' => 'Priorizar calendario, colecciones, pasarela, industria, ciudad, diseñadores y cultura del vestir.'],
            ['category' => 'Eventos', 'category_slug' => 'eventos', 'curated_slug' => 'eventos', 'tag' => 'Movilidad y transporte', 'tag_slug' => 'movilidad-y-transporte', 'status' => 'Index', 'reading' => 'Priorizar industria, infraestructura, tecnología, energía, ciudad y experiencia de movilidad.'],
            ['category' => 'Eventos', 'category_slug' => 'eventos', 'curated_slug' => 'eventos', 'tag' => 'Diseño digital y 3D', 'tag_slug' => 'diseno-digital-y-3d', 'status' => 'Index', 'reading' => 'Priorizar tecnologías, software, visualización, interacción, exhibición digital y cultura creativa.'],
            ['category' => 'Eventos', 'category_slug' => 'eventos', 'curated_slug' => 'eventos', 'tag' => 'Semana de diseño', 'tag_slug' => 'semana-de-diseno', 'status' => 'No Index', 'reading' => 'Formato de evento: útil para redacción y filtros, no necesariamente para SEO inicial.'],
        ];
    }

    public static function slugify(string $name): string {
        $slug = sanitize_title(remove_accents($name));
        return $slug ?: sanitize_title($name);
    }

    public static function preset_for_category_name(string $name): string {
        $name = trim($name);
        $presets = self::category_presets();
        if (isset($presets[$name])) {
            return (string) $presets[$name];
        }
        $slug = self::slugify($name);
        foreach ($presets as $cat => $reading) {
            if (self::slugify((string) $cat) === $slug) {
                return (string) $reading;
            }
        }
        return self::fallback_category_preset($slug);
    }

    public static function preset_for_tag_name(string $name, string $category_name = ''): string {
        $row = self::matrix_row_for_tag($name, $category_name);
        if (!empty($row['reading'])) {
            return (string) $row['reading'];
        }
        return self::fallback_tag_preset(self::slugify($name));
    }

    public static function tag_status(string $name, string $category_name = ''): string {
        $row = self::matrix_row_for_tag($name, $category_name);
        return (string) ($row['status'] ?? '');
    }

    public static function is_noindex_tag_name(string $name, string $category_name = ''): bool {
        $slug = self::slugify($name);
        if (in_array($slug, ['cultura-del-diseno', 'convocatoria-abierta', 'varias-disciplinas'], true)) {
            return true;
        }
        $status = mb_strtolower(remove_accents(self::tag_status($name, $category_name)));
        return str_contains($status, 'no index') || str_contains($status, 'noindex') || str_contains($status, 'operativo');
    }

    public static function category_curated_slug(string $category_name): string {
        $cat_slugs = self::category_slug_candidates($category_name);
        foreach (self::tag_matrix() as $row) {
            if (in_array(self::slugify((string) ($row['category'] ?? '')), $cat_slugs, true) || in_array((string) ($row['category_slug'] ?? ''), $cat_slugs, true)) {
                return (string) ($row['curated_slug'] ?? '');
            }
        }
        return '';
    }

    public static function category_curated_url(string $category_name): string {
        $slug = self::category_curated_slug($category_name);
        if ($slug !== '') {
            return home_url('/' . trim($slug, '/') . '/');
        }
        return '';
    }

    public static function matrix_row_for_public(string $tag_name, string $category_name = ''): array {
        return self::matrix_row_for_tag($tag_name, $category_name);
    }

    private static function matrix_row_for_tag(string $tag_name, string $category_name = ''): array {
        $tag_slug = self::normalize_tag_slug(self::slugify($tag_name));
        $category_slugs = self::category_slug_candidates($category_name);
        $fallback = [];
        foreach (self::tag_matrix() as $row) {
            $row_tag_slug = self::normalize_tag_slug((string) ($row['tag_slug'] ?? ''));
            $row_tag_name_slug = self::normalize_tag_slug(self::slugify((string) ($row['tag'] ?? '')));
            if ($row_tag_slug !== $tag_slug && $row_tag_name_slug !== $tag_slug) {
                continue;
            }
            if (empty($fallback)) {
                $fallback = $row;
            }
            if (!empty($category_slugs) && (in_array(self::slugify((string) ($row['category'] ?? '')), $category_slugs, true) || in_array((string) ($row['category_slug'] ?? ''), $category_slugs, true))) {
                return $row;
            }
        }
        return $fallback;
    }

    private static function normalize_tag_slug(string $slug): string {
        $slug = trim($slug);
        $aliases = [
            'diseno-automotriz' => 'automovil',
            'automotriz' => 'automovil',
            'automovil' => 'automovil',
        ];
        return $aliases[$slug] ?? $slug;
    }

    private static function category_slug_candidates(string $category_name): array {
        $slug = self::slugify($category_name);
        if ($slug === '') {
            return [];
        }
        $candidates = [$slug];
        $aliases = [
            'movilidad' => ['movilidad-y-transporte', 'diseno-transporte'],
            'transporte' => ['movilidad-y-transporte', 'diseno-transporte'],
            'diseno-transporte' => ['movilidad-y-transporte', 'diseno-transporte'],
            'movilidad-y-transporte' => ['movilidad-y-transporte', 'diseno-transporte'],
        ];
        foreach (($aliases[$slug] ?? []) as $alias) {
            $candidates[] = $alias;
        }
        return array_values(array_unique(array_filter($candidates)));
    }

    private static function fallback_category_preset(string $slug): string {
        if (strpos($slug, 'producto') !== false) return 'Leer productos desde problema de uso, ergonomía, materialidad, sistema, fabricación y experiencia cotidiana.';
        if (strpos($slug, 'arquitect') !== false || strpos($slug, 'interior') !== false) return 'Leer espacios desde recorrido, luz, atmósfera, materialidad, programa y vida cotidiana.';
        if (strpos($slug, 'moda') !== false) return 'Leer moda desde silueta, construcción, materialidad, gesto corporal, oficio y códigos culturales.';
        if (strpos($slug, 'movilidad') !== false || strpos($slug, 'transporte') !== false) return 'Leer movilidad desde usuario, seguridad, interfaz, infraestructura, energía y hábitos urbanos.';
        if (strpos($slug, 'digital') !== false || strpos($slug, '3d') !== false || strpos($slug, 'cad') !== false) return 'Leer herramientas digitales desde interfaz, flujo de trabajo, lenguaje visual, iteración y uso real.';
        if (strpos($slug, 'concurso') !== false || strpos($slug, 'convocatoria') !== false) return 'Leer la convocatoria desde utilidad, fechas, requisitos, criterios del jurado, entregables y ruta práctica.';
        if (strpos($slug, 'evento') !== false || strpos($slug, 'agenda') !== false || strpos($slug, 'calendario') !== false) return 'Leer eventos desde datos verificados, agenda, formato, temas a mirar y conversación de industria.';
        return '';
    }

    private static function fallback_tag_preset(string $slug): string {
        if (strpos($slug, 'ilumin') !== false) return 'Analizar cómo la luz modifica atmósfera, orientación, ritual cotidiano, percepción del objeto y relación con el espacio.';
        if (strpos($slug, 'mobiliario') !== false) return 'Leer desde escala corporal, apoyo, estabilidad, tacto, presencia espacial y uso cotidiano.';
        if (strpos($slug, 'material') !== false) return 'Analizar desde tacto, envejecimiento, luz, durabilidad, acústica y presencia sensorial.';
        if (strpos($slug, 'desfile') !== false) return 'Leer desde puesta en escena, colección, ritmo visual, narrativa de marca, cuerpo y cultura.';
        if (strpos($slug, 'animacion') !== false) return 'Leer desde secuencia, ritmo visual, movimiento, tiempo, narrativa y relación entre imagen fija y acción.';
        if (strpos($slug, 'sostenible') !== false) return 'Usar como lectura complementaria cuando haya datos reales de materiales, circularidad, durabilidad o energía.';
        return '';
    }

    public static function build_suggestion(int $category_id, array $tag_ids): string {
        $workflow = [
            'category_id' => $category_id,
            'tag_ids' => array_values(array_filter(array_map('intval', $tag_ids))),
        ];
        if (class_exists('IDG_Editorial_Recipe_Builder')) {
            $recipe = IDG_Editorial_Recipe_Builder::recipe_text($workflow);
            if ($recipe !== '') {
                return $recipe;
            }
        }

        $parts = [];
        $category_name = '';
        if ($category_id) {
            $term = get_term($category_id, 'category');
            if ($term && !is_wp_error($term)) {
                $category_name = (string) $term->name;
                $preset = self::preset_for_category_name($category_name);
                if ($preset !== '') $parts[] = $preset;
            }
        }
        foreach (array_slice($tag_ids, 0, 2) as $tag_id) {
            $tag = get_term((int) $tag_id, 'post_tag');
            if ($tag && !is_wp_error($tag)) {
                $preset = self::preset_for_tag_name((string) $tag->name, $category_name);
                if ($preset !== '') $parts[] = $preset;
            }
        }
        return self::dedupe_parts($parts);
    }

    private static function dedupe_parts(array $parts): string {
        $tokens = [];
        foreach ($parts as $part) {
            foreach (preg_split('/;\s*/', $part) as $token) {
                $token = trim($token, " .	

 ");
                if ($token === '') continue;
                $key = mb_strtolower(remove_accents($token));
                $tokens[$key] = $token;
            }
        }
        return implode('; ', array_values($tokens)) . (empty($tokens) ? '' : '.');
    }

    public static function admin_data($categories, $tags): array {
        if (class_exists('IDG_Editorial_Recipe_Builder')) {
            return [
                'categories' => IDG_Editorial_Recipe_Builder::admin_category_presets($categories),
                'tags' => IDG_Editorial_Recipe_Builder::admin_tag_presets($tags),
            ];
        }
        $category_data = [];
        if (!is_wp_error($categories)) {
            foreach ($categories as $cat) {
                $preset = self::preset_for_category_name((string) $cat->name);
                if ($preset !== '') $category_data[(string) $cat->term_id] = $preset;
            }
        }
        $tag_data = [];
        if (!is_wp_error($tags)) {
            foreach ($tags as $tag) {
                $preset = self::preset_for_tag_name((string) $tag->name);
                if ($preset !== '') $tag_data[(string) $tag->term_id] = $preset;
            }
        }
        return ['categories' => $category_data, 'tags' => $tag_data];
    }
}
