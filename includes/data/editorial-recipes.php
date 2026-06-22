<?php
if (!defined('ABSPATH')) {
    exit;
}

return [
    'categories' => [
        'diseno-de-producto' => [
            'names' => ['Diseño de producto', 'Diseño de Producto'],
            'frame' => 'Leer el producto como sistema de uso, no como catálogo de características.',
            'focus' => 'experiencia de uso cotidiana',
            'concepts' => ['uso', 'sistema', 'materialidad'],
            'risk' => 'convertir especificaciones en catálogo',
        ],
        'arquitectura-e-interiores' => [
            'names' => ['Arquitectura e interiores', 'Arquitectura y diseño interior', 'Interior & Arquitectura'],
            'frame' => 'Leer el espacio desde cómo se habita y se recorre, antes que desde la imagen final.',
            'focus' => 'relación entre espacio, uso y atmósfera',
            'concepts' => ['recorrido', 'luz', 'materialidad'],
            'risk' => 'describir el proyecto como ficha inmobiliaria',
        ],
        'moda' => [
            'names' => ['Moda'],
            'frame' => 'Leer moda desde cuerpo, construcción, materialidad y códigos culturales.',
            'focus' => 'relación entre cuerpo, prenda y contexto cultural',
            'concepts' => ['silueta', 'materialidad', 'gesto corporal'],
            'risk' => 'reducir la colección a tendencia o apariencia',
        ],
        'movilidad-y-transporte' => [
            'names' => ['Movilidad y transporte', 'Movilidad', 'Transporte'],
            'frame' => 'Leer movilidad desde usuario, seguridad, interfaz, energía e infraestructura.',
            'focus' => 'experiencia de movilidad y relación con el contexto urbano',
            'concepts' => ['usuario', 'seguridad', 'interfaz'],
            'risk' => 'convertir el texto en ficha de prestaciones',
        ],
        'diseno-digital-y-3d' => [
            'names' => ['Diseño digital y 3D', 'Diseño digital', 'Diseño Digital'],
            'frame' => 'Leer proyectos digitales desde interfaz, flujo de trabajo, lenguaje visual y uso real.',
            'focus' => 'relación entre interfaz, visualización y experiencia de uso',
            'concepts' => ['interfaz', 'lenguaje visual', 'uso real'],
            'risk' => 'convertir la pieza en anuncio tecnológico',
        ],
        'concursos-y-convocatorias' => [
            'names' => ['Concursos y convocatorias', 'Concursos de diseño'],
            'frame' => 'Leer convocatorias desde utilidad editorial, condiciones de participación y valor para el proceso creativo.',
            'focus' => 'valor para el proceso creativo o portafolio',
            'concepts' => ['fechas', 'requisitos', 'criterios de evaluación'],
            'risk' => 'escribir como aviso promocional o checklist saturado',
        ],
        'eventos' => [
            'names' => ['Eventos', 'Calendario de eventos', 'Agenda de eventos'],
            'frame' => 'Leer eventos desde datos verificados, ciudad, formato y conversación de industria.',
            'focus' => 'valor editorial del evento para la cultura del diseño',
            'concepts' => ['ciudad', 'agenda', 'formato'],
            'risk' => 'convertir el texto en agenda promocional',
        ],
    ],
    'tags' => [
        'audio' => [
            'names' => ['Audio'],
            'filter' => 'experiencia de escucha y relación cuerpo-objeto',
            'concepts' => ['sonido', 'interfaz', 'presencia espacial'],
            'risk' => 'prometer calidad sonora no verificada',
            'anchors' => ['experiencia de audio', 'diseño de audio', 'experiencia de escucha'],
        ],
        'tecnologia' => [
            'names' => ['Tecnología'],
            'filter' => 'interfaz y sistema técnico cuando aportan a la experiencia',
            'concepts' => ['interfaz', 'sistema', 'uso cotidiano'],
            'risk' => 'convertir la pieza en catálogo de funciones',
            'anchors' => ['relación entre tecnología y uso', 'experiencia tecnológica', 'sistema de uso'],
        ],
        'arquitectura-residencial' => [
            'names' => ['Arquitectura residencial', 'Residencial', 'Vivienda'],
            'filter' => 'vida cotidiana y formas de habitar',
            'concepts' => ['recorrido', 'privacidad', 'vida cotidiana'],
            'risk' => 'describir la vivienda como inmueble',
            'anchors' => ['formas de habitar', 'arquitectura residencial', 'vida cotidiana en el espacio'],
        ],
        'paisaje' => [
            'names' => ['Paisaje'],
            'filter' => 'relación entre implantación, entorno y experiencia espacial',
            'concepts' => ['implantación', 'luz', 'entorno'],
            'risk' => 'usar el paisaje como simple telón visual',
            'anchors' => ['relación con el paisaje', 'implantación en el entorno', 'lectura del paisaje'],
        ],
        'materialidad' => [
            'names' => ['Materialidad'],
            'filter' => 'presencia material y experiencia sensorial',
            'concepts' => ['tacto', 'luz', 'durabilidad'],
            'risk' => 'enumerar materiales sin explicar su efecto',
            'anchors' => ['lectura material', 'presencia sensorial', 'materialidad del proyecto'],
        ],
        'iluminacion-natural' => [
            'names' => ['Iluminación natural'],
            'filter' => 'orientación, sombra y atmósfera',
            'concepts' => ['luz natural', 'sombra', 'relación interior-exterior'],
            'risk' => 'tratar la luz como recurso decorativo',
            'anchors' => ['luz natural en el espacio', 'relación entre luz y atmósfera', 'orientación del proyecto'],
        ],
        'varias-disciplinas' => [
            'names' => ['Varias disciplinas'],
            'filter' => 'criterios comunes cuando conviven varias áreas de diseño',
            'concepts' => ['requisitos', 'alcance', 'criterios de evaluación'],
            'risk' => 'forzar la lectura hacia una sola disciplina',
            'anchors' => ['concursos y convocatorias de diseño', 'marco de varias disciplinas', 'convocatorias de diseño'],
        ],
        'pantallas' => [
            'names' => ['Pantallas'],
            'filter' => 'relación entre pantalla, imagen y contexto de uso',
            'concepts' => ['color', 'escala', 'experiencia visual'],
            'risk' => 'reducir la pieza a especificaciones de pantalla',
            'anchors' => ['experiencia visual', 'diseño de pantallas', 'relación entre pantalla y uso'],
        ],
        'experiencia-digital' => [
            'names' => ['Experiencia digital'],
            'filter' => 'interacción visual y experiencia mediada por tecnología',
            'concepts' => ['interfaz', 'visualización', 'uso cotidiano'],
            'risk' => 'usar lenguaje comercial de plataforma',
            'anchors' => ['experiencia digital', 'interacción visual', 'uso digital cotidiano'],
        ],
        'brasil' => [
            'names' => ['Brasil'],
            'filter' => 'contexto territorial solo cuando aporte al caso',
            'concepts' => ['lugar', 'clima', 'cultura material'],
            'risk' => 'folclorizar el contexto geográfico',
            'anchors' => ['contexto territorial', 'relación con el lugar', 'lectura local del proyecto'],
        ],
        'evergreen' => [
            'names' => ['Evergreen'],
            'filter' => 'vigencia editorial más allá de una fecha puntual',
            'concepts' => ['utilidad', 'contexto', 'consulta futura'],
            'risk' => 'escribir como noticia urgente',
            'anchors' => ['guía editorial', 'lectura atemporal', 'contexto de consulta'],
        ],
    ],
];
