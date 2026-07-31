<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Motor editorial de recetas v2.
 *
 * Las categorías definen el territorio y las preguntas disponibles.
 * Los tags definidos aquí afinan lentes frecuentes. Los tags no listados
 * continúan cubiertos mediante la matriz completa de IDG_Priority_Readings.
 */
return [
    'categories' => [
        'diseno-de-producto' => [
            'names' => ['Diseño de producto', 'Diseño de Producto'],
            'territory' => 'diseño de producto',
            'discipline' => 'diseño de producto',
            'axes' => ['problema de uso', 'forma y proporción', 'ergonomía', 'materiales y acabados', 'interacción', 'fabricación', 'durabilidad', 'relación con el entorno cotidiano'],
            'experience' => ['tacto', 'agarre', 'peso percibido', 'claridad de uso', 'ritmo cotidiano', 'sensación de precisión, ligereza o robustez'],
            'questions' => [
                '¿Qué problema de uso resuelve y qué decisiones lo hacen visible?',
                '¿Cómo se relacionan forma, material y fabricación?',
                '¿Qué cambia para el cuerpo, la mano o la rutina?',
                '¿Qué rasgos expresan la identidad del diseñador o la marca?',
            ],
            'identity_required' => true,
            'identity_prompt' => 'explicar cómo forma, materiales, interacción o proceso expresan la identidad del diseñador, estudio o marca',
            'risks' => ['convertir características en catálogo', 'enumerar materiales sin explicar su efecto', 'repetir claims de marca'],
        ],
        'arquitectura-e-interiores' => [
            'names' => ['Arquitectura e interiores', 'Arquitectura y diseño interior', 'Interior & Arquitectura'],
            'territory' => 'arquitectura e interiores',
            'discipline' => 'diseño espacial',
            'axes' => ['organización espacial', 'circulación', 'luz', 'escala', 'materialidad', 'estructura', 'relación interior-exterior', 'clima', 'programa'],
            'experience' => ['ritmo del recorrido', 'orientación', 'intimidad', 'apertura y compresión', 'temperatura visual', 'acústica', 'sensación de refugio o exposición'],
            'questions' => [
                '¿Cómo se habita y se recorre el espacio?',
                '¿Qué hacen la luz, la escala y los materiales en la experiencia?',
                '¿Cómo responde el proyecto al clima, el programa y el contexto?',
                '¿Qué rasgos reconocibles pertenecen al lenguaje del estudio o arquitecto?',
            ],
            'identity_required' => true,
            'identity_prompt' => 'mostrar cómo el lenguaje del estudio o arquitecto aparece en la organización, la luz, la materialidad y la relación con el contexto',
            'risks' => ['describir el proyecto como ficha inmobiliaria', 'enumerar ambientes y superficies', 'usar la atmósfera como adjetivo sin evidencia'],
        ],
        'moda' => [
            'names' => ['Moda'],
            'territory' => 'moda',
            'discipline' => 'diseño de moda',
            'axes' => ['silueta', 'construcción', 'textiles', 'color', 'movimiento', 'relación con el cuerpo', 'técnicas artesanales o industriales', 'contexto cultural'],
            'experience' => ['caída', 'rigidez o fluidez', 'peso', 'transparencia', 'movimiento', 'protección', 'exposición del cuerpo', 'ritmo visual'],
            'questions' => [
                '¿Cómo se construye la silueta y qué relación propone con el cuerpo?',
                '¿Qué papel cumplen textiles, color y técnica?',
                '¿Qué se percibe en movimiento y en uso?',
                '¿Cómo se reconoce o transforma la identidad del diseñador o la casa?',
            ],
            'identity_required' => true,
            'identity_prompt' => 'explicar cómo silueta, construcción, material, color y puesta en escena expresan o transforman la identidad del diseñador o la casa',
            'risks' => ['enumerar prendas y colores como catálogo', 'reducir la colección a tendencia', 'repetir lenguaje de campaña'],
        ],
        'movilidad-y-transporte' => [
            'names' => ['Movilidad y transporte', 'Movilidad', 'Transporte'],
            'territory' => 'movilidad y transporte',
            'discipline' => 'diseño de movilidad',
            'axes' => ['diseño exterior', 'proporciones y arquitectura del vehículo', 'diseño interior', 'ergonomía', 'interfaz', 'materiales', 'seguridad cuando exista evidencia', 'experiencia de conducción o desplazamiento', 'relación con infraestructura y contexto'],
            'experience' => ['presencia visual', 'sensación de velocidad o estabilidad', 'campo de visión', 'resguardo', 'facilidad de acceso', 'claridad de controles', 'relación cuerpo-asiento-entorno', 'percepción de ligereza, solidez o precisión'],
            'questions' => [
                '¿Qué decisiones de diseño exterior e interior organizan el vehículo o sistema?',
                '¿Cómo afectan la ergonomía, la visibilidad y el uso?',
                '¿Qué relación existe entre materiales, construcción y experiencia de desplazamiento?',
                '¿Cómo se expresa o evoluciona la identidad de la marca o del diseñador?',
            ],
            'identity_required' => true,
            'identity_prompt' => 'analizar cómo proporciones, superficies, habitáculo, materiales y experiencia expresan o transforman la identidad de marca o autor',
            'risks' => ['convertir el texto en ficha de prestaciones', 'forzar energía, interfaz o seguridad sin evidencia', 'narrar el proyecto desde claims corporativos'],
        ],
        'diseno-digital-y-3d' => [
            'names' => ['Diseño digital y 3D', 'Diseño digital', 'Diseño Digital'],
            'territory' => 'diseño digital y 3D',
            'discipline' => 'diseño digital',
            'axes' => ['interfaz', 'jerarquía', 'navegación', 'movimiento', 'representación', 'accesibilidad', 'interacción', 'tecnología', 'flujo de trabajo', 'lenguaje visual'],
            'experience' => ['claridad', 'fluidez', 'inmersión', 'orientación', 'confianza', 'fricción', 'ritmo', 'sensación de control'],
            'questions' => [
                '¿Cómo se organiza la interacción y la jerarquía visual?',
                '¿Qué fricciones reduce o introduce el sistema?',
                '¿Cómo cambia la experiencia mediante movimiento, representación o tecnología?',
                '¿Qué rasgos expresan la identidad del estudio, plataforma o marca?',
            ],
            'identity_required' => true,
            'identity_prompt' => 'explicar cómo interfaz, movimiento, lenguaje visual y comportamiento del sistema construyen la identidad del estudio, plataforma o marca',
            'risks' => ['enumerar funciones o herramientas', 'convertir la pieza en anuncio tecnológico', 'confundir novedad técnica con calidad de experiencia'],
        ],
        'concursos-y-convocatorias' => [
            'names' => ['Concursos y convocatorias', 'Concursos de diseño'],
            'territory' => 'concursos y convocatorias',
            'discipline' => 'convocatoria de diseño',
            'axes' => ['propósito', 'reto creativo', 'perfil participante', 'categorías', 'fechas principales', 'premios confirmados', 'alcance disciplinar', 'oportunidad para la práctica'],
            'experience' => ['claridad para decidir participar', 'inspiración para reconocer un proyecto pertinente', 'lectura ágil del calendario', 'comprensión del alcance de la convocatoria'],
            'questions' => [
                '¿Qué propone la convocatoria y para qué perfiles puede ser relevante?',
                '¿Qué categorías, fechas o premios confirmados conviene ordenar para una lectura rápida?',
                '¿Qué hace atractiva la oportunidad sin prometer resultados ni convertirla en anuncio?',
            ],
            'identity_required' => false,
            'identity_prompt' => 'mencionar al organizador solo cuando explique la orientación, continuidad o contexto de la convocatoria',
            'risks' => ['escribir como anuncio promocional', 'convertir fechas o instituciones en reflexiones abstractas', 'llenar el artículo de requisitos, entregables o criterios técnicos', 'convertir el texto en checklist sin contexto'],
        ],
        'eventos' => [
            'names' => ['Eventos', 'Calendario de eventos', 'Agenda de eventos'],
            'territory' => 'calendario de eventos',
            'discipline' => 'agenda editorial de diseño',
            'axes' => ['relevancia', 'disciplina', 'programación', 'ciudad', 'sede', 'formato', 'actores', 'temas', 'experiencia de asistencia'],
            'experience' => ['ritmo de la agenda', 'recorrido urbano', 'acceso', 'circulación entre actividades', 'relación entre industria y ciudad'],
            'questions' => [
                '¿Qué ocurre, cuándo, dónde y por qué merece atención?',
                '¿Qué temas, actores y formatos organizan la experiencia?',
                '¿Cómo se relaciona el evento con la ciudad y la disciplina?',
            ],
            'identity_required' => false,
            'identity_prompt' => 'usar la identidad de la institución organizadora solo cuando aporte contexto al evento',
            'risks' => ['convertir el texto en ficha de agenda', 'inventar programación', 'usar tono promocional'],
        ],
    ],
    'event_lenses' => [
        'Arquitectura e interiores' => [
            'discipline' => 'arquitectura e interiores',
            'axes' => ['ciudad', 'sede', 'instalaciones', 'materiales', 'debates', 'cultura espacial'],
            'experience' => ['recorrido', 'escala urbana', 'atmósfera', 'encuentro profesional'],
        ],
        'Diseño digital y 3D' => [
            'discipline' => 'diseño digital y 3D',
            'axes' => ['tecnologías', 'software', 'visualización', 'interacción', 'exhibición digital', 'cultura creativa'],
            'experience' => ['inmersión', 'participación', 'claridad', 'ritmo de la programación'],
        ],
        'Diseño interdisciplinar' => [
            'discipline' => 'diseño interdisciplinar',
            'axes' => ['cruce de disciplinas', 'industria', 'cultura visual', 'ciudad', 'colaboración'],
            'experience' => ['descubrimiento', 'circulación', 'encuentro entre comunidades'],
        ],
        'Moda' => [
            'discipline' => 'moda',
            'axes' => ['calendario', 'colecciones', 'pasarela', 'industria', 'ciudad', 'diseñadores', 'cultura del vestir'],
            'experience' => ['ritmo de la agenda', 'movimiento', 'puesta en escena', 'recorrido urbano'],
        ],
        'Movilidad y transporte' => [
            'discipline' => 'movilidad y transporte',
            'axes' => ['industria', 'infraestructura', 'tecnología', 'energía', 'ciudad', 'experiencia de movilidad'],
            'experience' => ['desplazamiento', 'acceso', 'escala urbana', 'relación entre exhibición y uso'],
        ],
        'Semana de diseño' => [
            'discipline' => 'semana de diseño',
            'axes' => ['ciudad', 'estudios', 'marcas', 'instalaciones', 'exposiciones', 'conversaciones', 'circuitos'],
            'experience' => ['recorrido', 'descubrimiento', 'ritmo urbano', 'conexión entre sedes'],
        ],
    ],
    'tags' => [
        'diseno-automotriz' => [
            'names' => ['Diseño automotriz', 'Automotriz', 'Automóvil'],
            'discipline' => 'diseño automotriz',
            'axes' => ['diseño exterior', 'proporciones y carrocería', 'arquitectura del vehículo', 'habitáculo', 'ergonomía', 'materiales y procesos', 'experiencia de conducción', 'identidad de marca'],
            'experience' => ['presencia y tensión visual', 'resguardo y visibilidad', 'relación cuerpo-asiento-controles', 'sensación de precisión, ligereza o solidez'],
            'questions' => ['¿Qué decisión formal organiza el vehículo?', '¿Cómo se transforma el habitáculo y la experiencia de uso?', '¿Qué rasgos pertenecen al lenguaje de la marca o del diseñador?'],
            'risks' => ['ficha de prestaciones', 'repetir claims de la marca'],
            'anchors' => ['diseño del automóvil contemporáneo', 'relación entre carrocería y uso', 'evolución del diseño automotriz'],
        ],
        'audio' => [
            'names' => ['Audio'],
            'discipline' => 'diseño de audio',
            'axes' => ['sonido', 'interfaz', 'materialidad', 'gesto de uso', 'presencia espacial'],
            'experience' => ['escucha', 'control', 'inmersión', 'convivencia doméstica'],
            'risks' => ['prometer calidad sonora no verificada'],
            'anchors' => ['experiencia de audio', 'diseño de audio', 'experiencia de escucha'],
        ],
        'iluminacion' => [
            'names' => ['Iluminación', 'Iluminación natural'],
            'discipline' => 'diseño de iluminación',
            'axes' => ['fuente de luz', 'distribución', 'sombra', 'temperatura', 'control', 'relación con espacio y objeto'],
            'experience' => ['atmósfera', 'orientación', 'ritual cotidiano', 'confort visual'],
            'risks' => ['tratar la luz como decoración'],
            'anchors' => ['relación entre luz y uso', 'diseño de iluminación', 'atmósfera y orientación'],
        ],
        'mobiliario' => [
            'names' => ['Mobiliario'],
            'discipline' => 'diseño de mobiliario',
            'axes' => ['escala corporal', 'apoyo', 'estabilidad', 'tacto', 'presencia espacial', 'uso cotidiano'],
            'experience' => ['postura', 'comodidad', 'movimiento', 'relación con el espacio'],
            'risks' => ['enumerar dimensiones y materiales'],
            'anchors' => ['relación entre mobiliario y cuerpo', 'diseño de mobiliario', 'escala corporal y uso'],
        ],
        'materialidad' => [
            'names' => ['Materialidad', 'Materiales'],
            'discipline' => 'lectura material',
            'axes' => ['tacto', 'luz', 'envejecimiento', 'durabilidad', 'acústica', 'proceso'],
            'experience' => ['temperatura táctil', 'peso percibido', 'presencia sensorial', 'cambio con el uso'],
            'risks' => ['enumerar materiales sin explicar su efecto'],
            'anchors' => ['lectura material', 'presencia sensorial', 'materialidad del proyecto'],
        ],
        'experiencia-digital' => [
            'names' => ['Experiencia digital'],
            'discipline' => 'experiencia digital',
            'axes' => ['interacción', 'jerarquía', 'visualización', 'flujo', 'accesibilidad', 'respuesta'],
            'experience' => ['claridad', 'fluidez', 'confianza', 'control', 'reducción de fricción'],
            'risks' => ['usar lenguaje comercial de plataforma'],
            'anchors' => ['experiencia digital', 'interacción visual', 'uso digital cotidiano'],
        ],
        'tecnologia' => [
            'names' => ['Tecnología'],
            'discipline' => 'tecnología aplicada al diseño',
            'axes' => ['sistema técnico', 'interfaz', 'proceso', 'integración', 'uso cotidiano'],
            'experience' => ['claridad', 'control', 'continuidad', 'fricción'],
            'risks' => ['convertir la pieza en catálogo de funciones'],
            'anchors' => ['relación entre tecnología y uso', 'experiencia tecnológica', 'sistema de uso'],
        ],
        'diseno-sostenible' => [
            'names' => ['Diseño sostenible', 'Sostenibilidad'],
            'discipline' => 'diseño sostenible',
            'axes' => ['materiales', 'durabilidad', 'reparabilidad', 'circularidad', 'energía', 'proceso'],
            'experience' => ['permanencia', 'mantenimiento', 'cambio con el tiempo', 'uso responsable'],
            'risks' => ['afirmar sostenibilidad sin evidencia'],
            'anchors' => ['decisiones de diseño sostenible', 'durabilidad y circularidad', 'relación entre material y permanencia'],
        ],
        'cultura-del-diseno' => [
            'names' => ['Cultura del diseño'],
            'discipline' => 'cultura del diseño',
            'axes' => ['historia', 'teoría', 'sociedad', 'cultura visual', 'educación', 'práctica profesional'],
            'experience' => ['lectura crítica', 'contexto', 'memoria disciplinar'],
            'risks' => ['forzar el tema hacia una tipología de producto'],
            'role' => 'contextual',
        ],
    ],
];
