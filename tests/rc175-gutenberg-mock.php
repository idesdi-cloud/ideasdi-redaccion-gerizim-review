<?php

define('ABSPATH', __DIR__);

$GLOBALS['idg_mode'] = 'capture';
$GLOBALS['idg_captured_blocks'] = [];
$GLOBALS['idg_parse_cases'] = [];
$GLOBALS['idg_roundtrip'] = '';

function serialize_blocks($blocks) {
    if (($GLOBALS['idg_mode'] ?? '') === 'capture') {
        $GLOBALS['idg_captured_blocks'] = $blocks;
        return '__SERIALIZED__';
    }

    return (string) ($GLOBALS['idg_roundtrip'] ?? '');
}

function parse_blocks($content) {
    return $GLOBALS['idg_parse_cases'][$content] ?? [];
}

require_once dirname(__DIR__) . '/includes/class-post-creator.php';
require_once dirname(__DIR__) . '/includes/class-final-guard.php';

function rc175_check($condition, $label) {
    if (!$condition) {
        fwrite(STDERR, "FAIL {$label}\n");
        exit(1);
    }

    echo "OK {$label}\n";
}

$method = new ReflectionMethod(
    'IDG_Post_Creator',
    'html_to_gutenberg_blocks'
);

$html = implode("\n", [
    '<p>Introducción.</p>',
    '<p class="featured-snippet-box">Caja editorial.</p>',
    '<h2>Subtítulo</h2>',
    '<h3>Sección</h3>',
    '<ul>',
    '<li>Elemento uno</li>',
    '<li>Elemento dos</li>',
    '</ul>',
]);

$result = $method->invoke(null, $html);

rc175_check(
    $result === '__SERIALIZED__',
    'converter usa serialize_blocks'
);

$blocks = $GLOBALS['idg_captured_blocks'];

rc175_check(
    array_column($blocks, 'blockName') === [
        'core/paragraph',
        'core/paragraph',
        'core/heading',
        'core/heading',
        'core/list',
    ],
    'tipos top-level correctos'
);

rc175_check(
    ($blocks[1]['attrs']['className'] ?? '')
        === 'featured-snippet-box',
    'featured snippet conserva className'
);

rc175_check(
    ($blocks[3]['attrs']['level'] ?? null) === 3,
    'H3 declara level 3'
);

$list = $blocks[4];

rc175_check(
    array_column(
        $list['innerBlocks'],
        'blockName'
    ) === [
        'core/list-item',
        'core/list-item',
    ],
    'lista contiene core/list-item'
);

rc175_check(
    str_contains(
        (string) $list['innerHTML'],
        '<ul class="wp-block-list">'
    ),
    'lista usa wp-block-list'
);

$method->invoke(
    null,
    '<div>HTML desconocido</div>'
);

rc175_check(
    ($GLOBALS['idg_captured_blocks'][0]['blockName'] ?? '')
        === 'core/html',
    'HTML desconocido queda marcado como core/html'
);


/* FINAL GUARD */

$GLOBALS['idg_mode'] = 'guard';

$valid = [
    [
        'blockName' => 'core/paragraph',
        'attrs' => [],
        'innerBlocks' => [],
        'innerHTML' => '<p>Texto</p>',
        'innerContent' => ['<p>Texto</p>'],
    ],
    [
        'blockName' => 'core/heading',
        'attrs' => [],
        'innerBlocks' => [],
        'innerHTML' => '<h2>Subtítulo</h2>',
        'innerContent' => ['<h2>Subtítulo</h2>'],
    ],
    [
        'blockName' => 'core/list',
        'attrs' => [],
        'innerBlocks' => [
            [
                'blockName' => 'core/list-item',
                'attrs' => [],
                'innerBlocks' => [],
                'innerHTML' => '<li>Uno</li>',
                'innerContent' => ['<li>Uno</li>'],
            ],
        ],
        'innerHTML' => '<ul class="wp-block-list"></ul>',
        'innerContent' => [
            '<ul class="wp-block-list">',
            null,
            '</ul>',
        ],
    ],
];

$GLOBALS['idg_parse_cases']['VALID'] = $valid;
$GLOBALS['idg_roundtrip'] = 'VALID';

$status = IDG_Final_Guard::validate_gutenberg_blocks(
    'VALID'
);

rc175_check(
    !empty($status['ok']),
    'Final Guard acepta árbol válido'
);

$GLOBALS['idg_parse_cases']['LOOSE'] = [
    [
        'blockName' => null,
        'attrs' => [],
        'innerBlocks' => [],
        'innerHTML' => '<p>Contenido suelto</p>',
        'innerContent' => [
            '<p>Contenido suelto</p>'
        ],
    ],
];

$GLOBALS['idg_roundtrip'] = 'LOOSE';

$status = IDG_Final_Guard::validate_gutenberg_blocks(
    'LOOSE'
);

rc175_check(
    empty($status['ok']),
    'Final Guard rechaza contenido suelto'
);

$GLOBALS['idg_parse_cases']['HTML'] = [
    [
        'blockName' => 'core/html',
        'attrs' => [],
        'innerBlocks' => [],
        'innerHTML' => '<div>Raw</div>',
        'innerContent' => ['<div>Raw</div>'],
    ],
];

$GLOBALS['idg_roundtrip'] = 'HTML';

$status = IDG_Final_Guard::validate_gutenberg_blocks(
    'HTML'
);

rc175_check(
    empty($status['ok']),
    'Final Guard rechaza core/html'
);

$GLOBALS['idg_parse_cases']['H1'] = [
    [
        'blockName' => 'core/heading',
        'attrs' => ['level' => 1],
        'innerBlocks' => [],
        'innerHTML' => '<h1>Título</h1>',
        'innerContent' => ['<h1>Título</h1>'],
    ],
];

$GLOBALS['idg_roundtrip'] = 'H1';

$status = IDG_Final_Guard::validate_gutenberg_blocks(
    'H1'
);

rc175_check(
    empty($status['ok']),
    'Final Guard rechaza heading level 1'
);

echo "RC175_GUTENBERG_MOCK_OK\n";
