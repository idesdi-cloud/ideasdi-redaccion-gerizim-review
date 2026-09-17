<?php

define('ABSPATH', __DIR__);

final class IDG_Editorial_Rules {
    public static function get(): array {
        return [
            'reel_vo_words' => 14,
            'reel_scenes' => 6,
            'reel_overlays_per_scene' => 3,
            'reel_overlay_max_chars' => 40,
            'reel_cta' =>
                'Conoce más de este proyecto en ideasDi.com',
        ];
    }
}

require_once dirname(__DIR__)
    . '/includes/class-reel-contract.php';

function rc176_check($condition, $label) {
    if (!$condition) {
        fwrite(
            STDERR,
            "FAIL {$label}\n"
        );
        exit(1);
    }

    echo "OK {$label}\n";
}

function rc176_words($prefix, $count = 14) {
    $words = [];

    for ($i = 1; $i <= $count; $i++) {
        $words[] = $prefix . $i;
    }

    return implode(' ', $words);
}

function rc176_valid_reel() {
    $lines = [];

    for ($scene = 1; $scene <= 5; $scene++) {
        $lines[] =
            'VO '
            . $scene
            . ': '
            . rc176_words('palabra');

        for ($overlay = 1; $overlay <= 3; $overlay++) {
            $lines[] =
                'Overlay '
                . $scene
                . '.'
                . $overlay
                . ': Texto '
                . $scene
                . '-'
                . $overlay;
        }
    }

    $lines[] =
        'VO 6: Cierre. Conoce más de este proyecto en ideasDi.com';

    for ($overlay = 1; $overlay <= 3; $overlay++) {
        $lines[] =
            'Overlay 6.'
            . $overlay
            . ': Cierre '
            . $overlay;
    }

    return implode("\n", $lines);
}

$valid = rc176_valid_reel();

$inspection =
    IDG_Reel_Contract::inspect($valid);

rc176_check(
    !empty($inspection['valid']),
    'contrato acepta Reel válido'
);

rc176_check(
    ($inspection['total_overlays'] ?? 0) === 18,
    'detecta 18 overlays'
);

rc176_check(
    !empty($inspection['cta_in_final_vo']),
    'CTA está en VO 6'
);

for ($scene = 1; $scene <= 6; $scene++) {
    rc176_check(
        ($inspection['overlay_counts'][$scene] ?? 0)
            === 3,
        'escena '
            . $scene
            . ' tiene 3 overlays'
    );
}


/* CTA fuera de VO 6 */

$bad_cta = str_replace(
    'VO 6: Cierre. Conoce más de este proyecto en ideasDi.com',
    'VO 6: Cierre sin llamada final',
    $valid
);

$bad_cta = str_replace(
    'VO 1: ' . rc176_words('palabra'),
    'VO 1: '
        . rc176_words('palabra')
        . ' Conoce más de este proyecto en ideasDi.com',
    $bad_cta
);

$inspection =
    IDG_Reel_Contract::inspect($bad_cta);

rc176_check(
    empty($inspection['valid']),
    'CTA fuera de VO 6 no valida'
);

rc176_check(
    str_contains(
        implode(
            ' | ',
            $inspection['issues']
        ),
        'VO 6 debe incluir el CTA'
    ),
    'reporta CTA faltante específicamente en VO 6'
);


/* Distribución incorrecta por escena */

$bad_scenes = str_replace(
    "Overlay 2.3: Texto 2-3\n",
    '',
    $valid
);

$bad_scenes = str_replace(
    'Overlay 3.3: Texto 3-3',
    "Overlay 3.3: Texto 3-3\nOverlay 3.4: Extra",
    $bad_scenes
);

$inspection =
    IDG_Reel_Contract::inspect($bad_scenes);

$issues =
    implode(
        ' | ',
        $inspection['issues']
    );

rc176_check(
    str_contains(
        $issues,
        'Escena 2 debe incluir exactamente 3 overlays'
    ),
    'detecta escena con 2 overlays'
);

rc176_check(
    str_contains(
        $issues,
        'Escena 3 debe incluir exactamente 3 overlays'
    ),
    'detecta escena con 4 overlays'
);



/* Posiciones duplicadas no deben pasar */

$bad_positions = str_replace(
    'Overlay 2.2: Texto 2-2',
    'Overlay 2.1: Texto duplicado',
    $valid
);

$inspection =
    IDG_Reel_Contract::inspect($bad_positions);

rc176_check(
    str_contains(
        implode(
            ' | ',
            $inspection['issues']
        ),
        'Escena 2 debe usar exactamente Overlay 2.1, 2.2, 2.3'
    ),
    'detecta posiciones overlay duplicadas'
);


/* Numeración explícita se preserva */

$explicit = implode(
    "\n",
    [
        'VO 2: Texto original',
        'Overlay 2.3: Mantener posición explícita',
    ]
);

$normalized_explicit =
    IDG_Reel_Contract::normalize($explicit);

rc176_check(
    str_contains(
        $normalized_explicit,
        'Overlay 2.3: Mantener posición explícita'
    ),
    'normalización preserva numeración explícita'
);


/* Overlay > 40 */

$long_overlay = str_replace(
    'Overlay 4.2: Texto 4-2',
    'Overlay 4.2: Este overlay tiene deliberadamente más de cuarenta caracteres completos',
    $valid
);

$inspection =
    IDG_Reel_Contract::inspect($long_overlay);

rc176_check(
    str_contains(
        implode(
            ' | ',
            $inspection['issues']
        ),
        'Overlay 4.2 supera 40 caracteres'
    ),
    'detecta overlay demasiado largo'
);


/* VO incorrecto */

$bad_vo = str_replace(
    'VO 5: ' . rc176_words('palabra'),
    'VO 5: ' . rc176_words('palabra', 13),
    $valid
);

$inspection =
    IDG_Reel_Contract::inspect($bad_vo);

rc176_check(
    str_contains(
        implode(
            ' | ',
            $inspection['issues']
        ),
        'VO 5 debe tener exactamente 14 palabras'
    ),
    'detecta conteo VO incorrecto'
);


/* Normalización estructural segura */

$legacy = implode(
    "\n",
    [
        'Escena 1:',
        'VO — Bloque 1: Texto original sin cambios',
        'Subtítulo 1: Primer texto',
        'Texto en pantalla 2: Segundo texto',
        'Overlay 3: Tercer texto',
    ]
);

$normalized =
    IDG_Reel_Contract::normalize($legacy);

rc176_check(
    str_contains(
        $normalized,
        'VO 1: Texto original sin cambios'
    ),
    'normaliza etiqueta VO sin reescribir contenido'
);

rc176_check(
    str_contains(
        $normalized,
        'Overlay 1.1: Primer texto'
    ),
    'normaliza primer overlay'
);

rc176_check(
    str_contains(
        $normalized,
        'Overlay 1.2: Segundo texto'
    ),
    'normaliza segundo overlay'
);

rc176_check(
    str_contains(
        $normalized,
        'Overlay 1.3: Tercer texto'
    ),
    'normaliza tercer overlay'
);

rc176_check(
    !str_contains(
        $normalized,
        'lectura editorial'
    )
    && !str_contains(
        $normalized,
        'Mirada ideasDi'
    ),
    'normalización no inventa contenido editorial'
);

echo "RC176_REEL_CONTRACT_OK\n";
